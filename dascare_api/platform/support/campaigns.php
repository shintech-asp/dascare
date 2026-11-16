<?php
// Fundraising goals (per month / per year / custom range) and the support page text.
// GET → campaigns with progress + page settings; POST {action: save_campaign|save_settings}.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/support.php';
require_once __DIR__ . '/../../reusables/input_validation.php';
header('Content-Type: application/json');

$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
$body = $isGet ? [] : (json_decode(file_get_contents('php://input'), true) ?: []);
$action = (string) ($body['action'] ?? '');
$isNew = $action === 'save_campaign' && (int) ($body['id'] ?? 0) <= 0;
requirePlatformExecutive($pdo, $isGet ? 'support.campaigns.read' : ($isNew ? 'support.campaigns.create' : 'support.campaigns.update'));
$uid = (int) ($_SESSION['user_id'] ?? 0);

function campAudit(PDO $pdo, int $uid, string $action, int $id, array $new): void
{
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$uid, $action, 'support_campaign', $id, json_encode($new), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
}

try {
    if ($isGet) {
        $rows = $pdo->query('SELECT * FROM support_campaigns ORDER BY is_active DESC, sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'campaigns' => array_map(fn($c) => supportCampaignProgress($pdo, $c), $rows), 'settings' => supportSettings($pdo), 'methods' => SUPPORT_METHODS, 'limits' => ['min_amount' => SUPPORT_MIN_AMOUNT_LIMIT, 'max_amount' => SUPPORT_MAX_AMOUNT], 'online_ready' => paymongoConfig() !== null]);
        exit;
    }

    if ($action === 'save_campaign') {
        $title = mb_substr(trim(strip_tags((string) ($body['title'] ?? ''))), 0, 120);
        $purpose = mb_substr(trim(strip_tags((string) ($body['purpose'] ?? ''))), 0, 500);
        $period = (string) ($body['period'] ?? '');
        $goal = $body['goal'] ?? null;
        if (mb_strlen($title) < 3 || !inputLooksLikeText($title, 3)) platformJsonError(422, 'Give the goal a name (at least 3 letters).');
        if (mb_strlen($purpose) < 10 || !inputLooksLikeText($purpose, 8)) platformJsonError(422, 'Say what the money is for (at least a short sentence) — donors see this.');
        if (!in_array($period, ['month', 'year', 'custom'], true)) platformJsonError(422, 'Choose per month, per year or a custom period.');
        if (!is_numeric($goal) || $goal < 100 || $goal > 10000000 || $goal != round((float) $goal, 2)) platformJsonError(422, 'The goal must be between ₱100 and ₱10,000,000.');
        $from = $to = null;
        if ($period === 'custom') {
            $from = (string) ($body['starts_on'] ?? ''); $to = (string) ($body['ends_on'] ?? '');
            foreach ([$from, $to] as $d) { $x = DateTime::createFromFormat('Y-m-d', $d); if (!$x || $x->format('Y-m-d') !== $d) platformJsonError(422, 'A custom period needs a start and end date.'); }
            if ($to < $from) platformJsonError(422, 'The end date cannot be before the start date.');
        }
        $active = !empty($body['is_active']) ? 1 : 0;
        $sort = max(0, min(1000, (int) ($body['sort_order'] ?? 0)));
        $id = (int) ($body['id'] ?? 0);
        $centavos = (int) round((float) $goal * 100);
        if ($id > 0) {
            $u = $pdo->prepare('UPDATE support_campaigns SET title = ?, purpose = ?, period = ?, goal_centavos = ?, starts_on = ?, ends_on = ?, is_active = ?, sort_order = ? WHERE id = ?');
            $u->execute([$title, $purpose, $period, $centavos, $from, $to, $active, $sort, $id]);
            if (!$u->rowCount()) { $c = $pdo->prepare('SELECT 1 FROM support_campaigns WHERE id = ?'); $c->execute([$id]); if (!$c->fetchColumn()) platformJsonError(404, 'Goal not found.'); }
        } else {
            $pdo->prepare('INSERT INTO support_campaigns (title, purpose, period, goal_centavos, starts_on, ends_on, is_active, sort_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$title, $purpose, $period, $centavos, $from, $to, $active, $sort, $uid]);
            $id = (int) $pdo->lastInsertId();
        }
        campAudit($pdo, $uid, 'platform.support_goal_saved', $id, ['title' => $title, 'period' => $period, 'goal' => $goal, 'active' => $active]);
        echo json_encode(['success' => true, 'message' => 'Goal saved.', 'id' => $id]);
        exit;
    }

    if ($action === 'save_settings') {
        try { $clean = supportCleanSettings((array) ($body['settings'] ?? [])); }
        catch (RuntimeException $e) { platformJsonError(422, $e->getMessage()); }
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by_user_id) VALUES ('support.page', ?, 'Support page text, amounts and payment methods.', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_user_id = VALUES(updated_by_user_id)")
            ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $uid]);
        campAudit($pdo, $uid, 'platform.support_page_saved', 0, ['enabled' => $clean['enabled'], 'min_amount' => $clean['min_amount'], 'methods' => $clean['methods']]);
        echo json_encode(['success' => true, 'message' => 'Support page saved.', 'settings' => $clean]);
        exit;
    }
    platformJsonError(422, 'Unknown action.');
} catch (Throwable $e) {
    error_log('Platform campaigns failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save that.');
}
