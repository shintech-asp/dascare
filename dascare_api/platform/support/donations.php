<?php
// Platform → Donations & Sponsors → Donations. GET list + totals; POST {action: record|update}.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/support.php';
require_once __DIR__ . '/../../reusables/input_validation.php';
header('Content-Type: application/json');

$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
$body = $isGet ? [] : (json_decode(file_get_contents('php://input'), true) ?: []);
$action = (string) ($body['action'] ?? '');
requirePlatformExecutive($pdo, $isGet ? 'support.donations.read' : ($action === 'record' ? 'support.donations.create' : 'support.donations.update'));
$uid = (int) ($_SESSION['user_id'] ?? 0);

function supAudit(PDO $pdo, int $uid, string $action, string $type, int $id, array $new): void
{
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$uid, $action, $type, $id, json_encode($new), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

try {
    if ($isGet) {
        // No webhook yet / missed webhook: settle recent pending donations by asking PayMongo directly,
        // and expire ones nobody finished after 2 days.
        try {
            $pdo->exec("UPDATE donations SET status = 'expired' WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
            $pend = $pdo->query("SELECT * FROM donations WHERE status = 'pending' AND paymongo_checkout_id IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY) ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pend as $p) { try { supportRefreshFromPaymongo($pdo, $p); } catch (Throwable $e) { error_log('Pending donation refresh failed: ' . $e->getMessage()); } }
        } catch (Throwable $e) { error_log('Donation reconcile failed: ' . $e->getMessage()); }
        $where = ['1=1']; $args = [];
        $status = (string) ($_GET['status'] ?? '');
        if (in_array($status, ['pending', 'paid', 'failed', 'cancelled', 'expired', 'refunded'], true)) { $where[] = 'd.status = ?'; $args[] = $status; }
        if ((int) ($_GET['campaign_id'] ?? 0) > 0) { $where[] = 'd.campaign_id = ?'; $args[] = (int) $_GET['campaign_id']; }
        $q = trim((string) ($_GET['search'] ?? ''));
        if ($q !== '') { $where[] = '(d.reference LIKE ? OR d.donor_name LIKE ? OR d.donor_email LIKE ?)'; $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($q, 0, 60)) . '%'; array_push($args, $like, $like, $like); }
        $st = $pdo->prepare("SELECT d.id, d.reference, d.amount_centavos, d.status, d.source, d.method, d.donor_name, d.donor_email, d.is_anonymous, d.show_on_wall, d.message, d.admin_note, d.paid_at, d.created_at, c.title AS campaign_title
                             FROM donations d LEFT JOIN support_campaigns c ON c.id = d.campaign_id WHERE " . implode(' AND ', $where) . " ORDER BY d.created_at DESC, d.id DESC LIMIT 200");
        $st->execute($args);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as &$i) { $i['id'] = (int) $i['id']; $i['amount_centavos'] = (int) $i['amount_centavos']; }
        unset($i);
        $stats = $pdo->query("SELECT
              COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_centavos END), 0) AS paid_total,
              COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount_centavos END), 0) AS paid_month,
              COALESCE(SUM(CASE WHEN status = 'paid' AND YEAR(paid_at) = YEAR(CURDATE()) THEN amount_centavos END), 0) AS paid_year,
              SUM(status = 'paid') AS paid_count, SUM(status = 'pending') AS pending_count, SUM(status = 'refunded') AS refunded_count
            FROM donations")->fetch(PDO::FETCH_ASSOC);
        foreach ($stats as $k => $v) $stats[$k] = (int) $v;
        echo json_encode(['success' => true, 'items' => $items, 'stats' => $stats, 'online_ready' => paymongoConfig() !== null]);
        exit;
    }

    if ($action === 'record') {
        $amount = $body['amount'] ?? null;
        if (!is_numeric($amount) || $amount < 1 || $amount > 1000000 || $amount != round((float) $amount, 2)) platformJsonError(422, 'Enter the amount received in pesos (₱1 to ₱1,000,000).');
        $name = mb_substr(trim(strip_tags((string) ($body['donor_name'] ?? ''))), 0, 120);
        if ($name !== '' && !inputIsPersonName($name)) platformJsonError(422, 'Donor name can only contain letters, spaces, apostrophes, periods and hyphens.');
        $method = mb_substr(trim(strip_tags((string) ($body['method'] ?? 'other'))), 0, 40) ?: 'other';
        $note = mb_substr(trim(strip_tags((string) ($body['note'] ?? ''))), 0, 250);
        $paidOn = (string) ($body['paid_on'] ?? date('Y-m-d'));
        $d = DateTime::createFromFormat('Y-m-d', $paidOn);
        if (!$d || $d->format('Y-m-d') !== $paidOn || $paidOn > date('Y-m-d')) platformJsonError(422, 'Enter the date the donation was received (not in the future).');
        $campaignId = (int) ($body['campaign_id'] ?? 0) ?: null;
        if ($campaignId !== null) { $c = $pdo->prepare('SELECT 1 FROM support_campaigns WHERE id = ?'); $c->execute([$campaignId]); if (!$c->fetchColumn()) platformJsonError(422, 'Choose a valid fundraising goal.'); }
        $ref = supportNewReference();
        $pdo->prepare("INSERT INTO donations (reference, campaign_id, amount_centavos, status, source, method, donor_name, is_anonymous, admin_note, paid_at, recorded_by_user_id) VALUES (?, ?, ?, 'paid', 'manual', ?, ?, ?, ?, ?, ?)")
            ->execute([$ref, $campaignId, (int) round((float) $amount * 100), $method, $name !== '' ? $name : null, $name === '' ? 1 : 0, $note !== '' ? $note : null, $paidOn . ' 12:00:00', $uid]);
        $id = (int) $pdo->lastInsertId();
        supAudit($pdo, $uid, 'platform.donation_recorded', 'donation', $id, ['reference' => $ref, 'amount' => $amount, 'method' => $method]);
        echo json_encode(['success' => true, 'message' => 'Donation recorded.', 'reference' => $ref]);
        exit;
    }

    if ($action === 'update') {
        $id = (int) ($body['id'] ?? 0);
        $status = (string) ($body['status'] ?? '');
        $note = mb_substr(trim(strip_tags((string) ($body['note'] ?? ''))), 0, 250);
        if ($id <= 0 || !in_array($status, ['refunded', 'cancelled'], true)) platformJsonError(422, 'Choose a donation and a status.');
        if (mb_strlen($note) < 5) platformJsonError(422, 'Add a short note (at least 5 characters) explaining why.');
        $pdo->beginTransaction();
        $s = $pdo->prepare('SELECT status FROM donations WHERE id = ? FOR UPDATE');
        $s->execute([$id]);
        $cur = $s->fetchColumn();
        $ok = ($status === 'refunded' && $cur === 'paid') || ($status === 'cancelled' && $cur === 'pending');
        if (!$ok) { $pdo->rollBack(); platformJsonError(409, $status === 'refunded' ? 'Only a paid donation can be marked refunded (refund it in PayMongo first).' : 'Only a pending donation can be cancelled.'); }
        $pdo->prepare('UPDATE donations SET status = ?, admin_note = ? WHERE id = ?')->execute([$status, $note, $id]);
        supAudit($pdo, $uid, 'platform.donation_' . $status, 'donation', $id, ['note' => $note]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Donation updated.']);
        exit;
    }
    platformJsonError(422, 'Unknown action.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Platform donations failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to complete that.');
}
