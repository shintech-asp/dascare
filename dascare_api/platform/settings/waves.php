<?php
// Dispatch waves (reusables/dispatch_dss.php → dssWaveSettings): on/off, seconds
// between waves, and how many ranked organizations wave 2 adds.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dss.php';
header('Content-Type: application/json');

$isGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
requirePlatformExecutive($pdo, $isGet ? 'system.system_settings.read' : 'system.system_settings.update');
$uid = (int) ($_SESSION['user_id'] ?? 0);
$key = 'dispatch.waves';

if ($isGet) {
    echo json_encode(['success' => true] + dssWaveSettings($pdo) + ['limits' => DSS_WAVE_LIMITS]);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) platformJsonError(422, 'Invalid request body.');
$enabled = filter_var($in['enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($enabled === null) platformJsonError(422, 'Waves must be on or off.');
$new = ['enabled' => $enabled];
foreach (['wave_seconds' => 'Seconds between waves', 'wave2_size' => 'Organizations added in wave 2'] as $f => $label) {
    [$lo, $hi] = DSS_WAVE_LIMITS[$f];
    $v = $in[$f] ?? null;
    if (!is_numeric($v) || (int) $v != $v || $v < $lo || $v > $hi) platformJsonError(422, "$label must be a whole number from $lo to $hi.");
    $new[$f] = (int) $v;
}
try {
    $pdo->beginTransaction();
    $old = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
    $old->execute([$key]);
    $oldVal = $old->fetchColumn();
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by_user_id) VALUES (?, ?, 'Dispatch waves: seconds between waves and how many organizations wave 2 adds.', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_user_id = VALUES(updated_by_user_id)")
        ->execute([$key, json_encode($new), $uid]);
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$uid, 'platform.settings.dispatch_waves', 'system_setting', json_encode(['key' => $key, 'value' => $oldVal]), json_encode(['key' => $key, 'value' => $new]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Dispatch waves updated.'] + $new);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Waves save failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save the dispatch waves.');
}
