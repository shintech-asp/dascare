<?php
// Save the false-alarm sanction rules (system_settings 'sanctions.false_alarm').
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') platformJsonError(405, 'Method not allowed.');
$actor = requirePlatformExecutive($pdo, 'system.system_settings.update');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
try { $clean = sanctionValidateSettings($in); } catch (InvalidArgumentException $e) { platformJsonError(422, $e->getMessage()); }
try {
    $old = sanctionSettings($pdo);
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by_user_id) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_user_id = VALUES(updated_by_user_id)")
        ->execute([SANCTION_SETTING_KEY, json_encode($clean), 'False-alarm sanctions: strike window, warning / timed suspension / review thresholds, and guest phone-number blocking.', $actor]);
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$actor, 'sanctions.settings_updated', 'system_setting', null, json_encode($old), json_encode($clean), $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'False alarm sanction rules saved. They apply to new strikes.', 'settings' => $clean]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Sanction settings failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save the sanction rules.');
}
