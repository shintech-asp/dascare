<?php
// The signed-in citizen's false-alarm record (strikes, appeals, suspension,
// rules). A citizen suspended for false alarms can't sign in, so the login
// refusal hands them a 30-minute key (X-Sanction-Token / ?token=) instead.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');

$userId = sanctionRequestUser($pdo);
if (!$userId) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Please log in again.']); exit; }
try {
    echo json_encode(['success' => true] + sanctionUserRecord($pdo, $userId));
} catch (Throwable $e) {
    error_log('Sanction record failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load your false alarm record.']);
}
