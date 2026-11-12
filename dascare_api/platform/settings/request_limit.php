<?php
// How many emergency requests one account / network address may send, and how
// long a person waits once they hit the limit. Read by reusables/rate_limit.php
// (rateLimitRule) on every citizen/create.php call.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/rate_limit.php';
header('Content-Type: application/json');

$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
requirePlatformExecutive($pdo, $isGet ? 'system.system_settings.read' : 'system.system_settings.update');
$key = 'emergency.request_limit';
$uid = (int) ($_SESSION['user_id'] ?? 0);

[$defMax, $defWindow] = RATE_LIMIT_RULES['emergency_request'];
$ranges = EMERGENCY_LIMIT_RANGES;

if ($isGet) {
    [$max, $window] = rateLimitRule($pdo, 'emergency_request');
    echo json_encode(['success' => true, 'max' => $max, 'window_minutes' => intdiv($window, 60), 'ranges' => $ranges, 'defaults' => ['max' => $defMax, 'window_minutes' => intdiv($defWindow, 60)]]);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) platformJsonError(422, 'Invalid request body.');
$max = $in['max'] ?? null;
$win = $in['window_minutes'] ?? null;
foreach (['max' => 'Requests allowed', 'window_minutes' => 'Time window (minutes)'] as $f => $label) {
    $v = $f === 'max' ? $max : $win;
    [$lo, $hi] = $ranges[$f];
    if (!is_numeric($v) || (int) $v != $v || $v < $lo || $v > $hi) platformJsonError(422, "$label must be a whole number from $lo to $hi.");
}
$new = ['max' => (int) $max, 'window_minutes' => (int) $win];
try {
    $pdo->beginTransaction();
    $old = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $old->execute([$key]);
    $oldVal = $old->fetchColumn();
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by_user_id) VALUES (?, ?, 'Emergency requests allowed per account/network address within the time window.', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_user_id = VALUES(updated_by_user_id)")
        ->execute([$key, json_encode($new), $uid]);
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$uid, 'platform.settings.request_limit', 'system_setting', json_encode(['key' => $key, 'value' => $oldVal]), json_encode(['key' => $key, 'value' => $new]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Emergency request limit updated.', 'max' => $new['max'], 'window_minutes' => $new['window_minutes']]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Request limit save failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save the request limit.');
}
