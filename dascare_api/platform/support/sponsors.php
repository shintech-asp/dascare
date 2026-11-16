<?php
// Sponsors (Platform → Sponsors). GET list; POST multipart (save, with optional logo) or JSON {action: status|delete}.
// Sponsor policy (enforced here and shown on the public sponsor page):
//  - categories are limited to SPONSOR_CATEGORIES (no politics, gambling, alcohol/tobacco/vape, lending apps, supplements with health claims);
//  - nothing goes live without the admin confirming the sponsor was verified;
//  - sponsors are never shown on emergency screens and receive no user data.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/support.php';
require_once __DIR__ . '/../../reusables/input_validation.php';
header('Content-Type: application/json');

$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
$isMultipart = stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data') === 0;
$in = $isGet ? [] : ($isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []));
$action = (string) ($in['action'] ?? 'save');
$isNew = $action === 'save' && (int) ($in['id'] ?? 0) <= 0;
requirePlatformExecutive($pdo, $isGet ? 'support.sponsors.read' : ($action === 'delete' ? 'support.sponsors.delete' : ($isNew ? 'support.sponsors.create' : 'support.sponsors.update')));
$uid = (int) ($_SESSION['user_id'] ?? 0);

function spAudit(PDO $pdo, int $uid, string $action, int $id, array $new): void
{
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$uid, $action, 'sponsor', $id, json_encode($new), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}
function spDate($v, string $label): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) platformJsonError(422, "$label must be a valid date.");
    return $v;
}

try {
    if ($isGet) {
        $rows = $pdo->query('SELECT id, name, tagline, website_url, category, tier, logo_file IS NOT NULL AS has_logo, status, starts_on, ends_on, sort_order, admin_note, created_at FROM sponsors ORDER BY FIELD(status, \'pending\', \'active\', \'paused\', \'ended\'), FIELD(tier, \'presenting\', \'partner\', \'supporter\'), sort_order, name')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['has_logo'] = (bool) $r['has_logo']; $r['sort_order'] = (int) $r['sort_order']; }
        unset($r);
        echo json_encode(['success' => true, 'sponsors' => $rows, 'categories' => SPONSOR_CATEGORIES]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($in['id'] ?? 0);
        $s = $pdo->prepare('SELECT logo_file FROM sponsors WHERE id = ?');
        $s->execute([$id]);
        if (($file = $s->fetchColumn()) === false) platformJsonError(404, 'Sponsor not found.');
        $pdo->prepare('DELETE FROM sponsors WHERE id = ?')->execute([$id]);
        if ($file) @unlink(dascareUploadsDir('sponsors') . basename((string) $file));
        spAudit($pdo, $uid, 'platform.sponsor_deleted', $id, []);
        echo json_encode(['success' => true, 'message' => 'Sponsor removed.']);
        exit;
    }

    if ($action === 'status') {
        $id = (int) ($in['id'] ?? 0);
        $status = (string) ($in['status'] ?? '');
        if (!in_array($status, ['active', 'paused', 'ended'], true)) platformJsonError(422, 'Choose a status.');
        $s = $pdo->prepare('SELECT tier, logo_file FROM sponsors WHERE id = ?');
        $s->execute([$id]);
        $sp = $s->fetch(PDO::FETCH_ASSOC);
        if (!$sp) platformJsonError(404, 'Sponsor not found.');
        if ($status === 'active') {
            if (empty($in['verified'])) platformJsonError(422, 'Confirm you verified the sponsor (registration, category, content) before showing it.');
            if ($sp['tier'] !== 'supporter' && !$sp['logo_file']) platformJsonError(422, 'Partners and presenting sponsors need a logo.');
        }
        $pdo->prepare('UPDATE sponsors SET status = ? WHERE id = ?')->execute([$status, $id]);
        spAudit($pdo, $uid, 'platform.sponsor_' . $status, $id, ['verified' => !empty($in['verified'])]);
        echo json_encode(['success' => true, 'message' => 'Sponsor ' . ($status === 'active' ? 'is now shown.' : ($status === 'paused' ? 'hidden (paused).' : 'marked ended.'))]);
        exit;
    }

    // ---- save (create / update)
    $id = (int) ($in['id'] ?? 0);
    $name = mb_substr(trim(strip_tags((string) ($in['name'] ?? ''))), 0, 120);
    $tagline = mb_substr(trim(strip_tags((string) ($in['tagline'] ?? ''))), 0, 160);
    $site = trim((string) ($in['website_url'] ?? ''));
    $category = (string) ($in['category'] ?? '');
    $tier = (string) ($in['tier'] ?? 'supporter');
    $note = mb_substr(trim(strip_tags((string) ($in['admin_note'] ?? ''))), 0, 250);
    $sort = max(0, min(1000, (int) ($in['sort_order'] ?? 0)));
    if (mb_strlen($name) < 2 || !inputLooksLikeText($name, 2)) platformJsonError(422, 'Enter the sponsor\'s name.');
    if ($tagline !== '' && !inputLooksLikeText($tagline, 4)) platformJsonError(422, 'The tagline must be readable text.');
    if ($site !== '' && (!preg_match('#^https://[^\s]+\.[^\s]+$#i', $site) || mb_strlen($site) > 255)) platformJsonError(422, 'The website must start with https://');
    if (!isset(SPONSOR_CATEGORIES[$category])) platformJsonError(422, 'Choose an allowed sponsor category.');
    if (!in_array($tier, ['supporter', 'partner', 'presenting'], true)) platformJsonError(422, 'Choose a tier.');
    $from = spDate($in['starts_on'] ?? '', 'Start date');
    $to = spDate($in['ends_on'] ?? '', 'End date');
    if ($from && $to && $to < $from) platformJsonError(422, 'The end date cannot be before the start date.');

    $old = null;
    if ($id > 0) { $o = $pdo->prepare('SELECT * FROM sponsors WHERE id = ?'); $o->execute([$id]); $old = $o->fetch(PDO::FETCH_ASSOC); if (!$old) platformJsonError(404, 'Sponsor not found.'); }

    // Logo: PNG/JPG/WEBP up to 1 MB, at least 64 px.
    $logoFile = $old['logo_file'] ?? null;
    $newLogo = null;
    $f = $_FILES['logo'] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($f['error'] !== UPLOAD_ERR_OK) platformJsonError(422, 'The logo could not be uploaded.');
        if ($f['size'] <= 0 || $f['size'] > 1024 * 1024) platformJsonError(422, 'The logo must be 1 MB or smaller.');
        $mime = mime_content_type($f['tmp_name']);
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime] ?? null;
        $dim = @getimagesize($f['tmp_name']);
        if (!$ext || !$dim) platformJsonError(422, 'The logo must be a PNG, JPG or WEBP image.');
        if ($dim[0] < 64 || $dim[1] < 64) platformJsonError(422, 'The logo must be at least 64 × 64 pixels.');
        $dir = dascareUploadsDir('sponsors');
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Could not create logo storage.');
        $newLogo = 'sponsor_' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], $dir . $newLogo)) throw new RuntimeException('Could not store the logo.');
        $logoFile = $newLogo;
    } elseif ($old && !empty($in['remove_logo'])) {
        $logoFile = null;
    }
    if ($tier !== 'supporter' && !$logoFile && ($old['status'] ?? '') === 'active') platformJsonError(422, 'An active partner or presenting sponsor needs a logo.');

    if ($old) {
        $pdo->prepare('UPDATE sponsors SET name = ?, tagline = NULLIF(?, \'\'), website_url = NULLIF(?, \'\'), category = ?, tier = ?, logo_file = ?, starts_on = ?, ends_on = ?, sort_order = ?, admin_note = NULLIF(?, \'\') WHERE id = ?')
            ->execute([$name, $tagline, $site, $category, $tier, $logoFile, $from, $to, $sort, $note, $id]);
        if (($old['logo_file'] ?? null) && $old['logo_file'] !== $logoFile) @unlink(dascareUploadsDir('sponsors') . basename((string) $old['logo_file']));
    } else {
        $pdo->prepare("INSERT INTO sponsors (name, tagline, website_url, category, tier, logo_file, status, starts_on, ends_on, sort_order, admin_note, created_by) VALUES (?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, 'pending', ?, ?, ?, NULLIF(?, ''), ?)")
            ->execute([$name, $tagline, $site, $category, $tier, $logoFile, $from, $to, $sort, $note, $uid]);
        $id = (int) $pdo->lastInsertId();
    }
    spAudit($pdo, $uid, $old ? 'platform.sponsor_updated' : 'platform.sponsor_created', $id, ['name' => $name, 'category' => $category, 'tier' => $tier]);
    echo json_encode(['success' => true, 'message' => $old ? 'Sponsor saved.' : 'Sponsor added. Review it, then show it when ready.', 'id' => $id]);
} catch (Throwable $e) {
    error_log('Platform sponsors failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save that sponsor.');
}
