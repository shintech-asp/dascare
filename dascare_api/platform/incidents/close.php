<?php
// platform/incidents/close.php
// Platform oversight closes an incident before completion: cancelled, false
// alarm or rejected, with a reason. See reusables/request_close.php.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/request_close.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') platformJsonError(405, 'Method not allowed.');
$userId = requirePlatformExecutive($pdo, 'oversight.emergency_requests.update');
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['request_id'] ?? 0);
$status = (string) ($body['status'] ?? '');
$reason = trim((string) ($body['reason'] ?? ''));
if ($requestId <= 0) platformJsonError(422, 'Invalid incident.');
if (!in_array($status, ['cancelled', 'false_alarm', 'rejected'], true)) platformJsonError(422, 'Choose Cancelled, False alarm or Rejected.');
if ($reason === '') platformJsonError(422, 'Please give a reason.');
if (mb_strlen($reason) > 255) platformJsonError(422, 'Please keep the reason under 255 characters.');

try {
    $result = requestClose($pdo, $requestId, $status, $reason, ['kind' => 'platform', 'user_id' => $userId, 'label' => 'a platform administrator']);
    $labels = ['cancelled' => 'Incident cancelled.', 'false_alarm' => 'Marked as a false alarm.', 'rejected' => 'Request rejected.'];
    echo json_encode(['success' => true, 'message' => $labels[$status] . ' Any assigned unit and crew were released.', 'result' => $result]);
} catch (RuntimeException $e) {
    platformJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Platform incident close failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to close this incident.');
}
