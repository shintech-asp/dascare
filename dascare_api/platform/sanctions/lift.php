<?php
// Lift a false-alarm suspension (strikes stay on record).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/false_alarm_sanctions.php';
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') platformJsonError(405, 'Method not allowed.');
$actor = requirePlatformExecutive($pdo, 'citizens.citizens.update');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = (int) ($in['user_id'] ?? 0);
$note = mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, 500);
if ($userId <= 0) platformJsonError(422, 'Choose a citizen.');
if (mb_strlen($note) < 5) platformJsonError(422, 'Give a reason (at least 5 characters).');
try {
    $pdo->beginTransaction();
    $u = $pdo->prepare("SELECT id FROM users WHERE id = ? AND account_status = 'suspended' AND suspension_source = 'false_alarm' FOR UPDATE");
    $u->execute([$userId]);
    if (!$u->fetchColumn()) throw new RuntimeException('This account is not suspended for false alarms.');
    sanctionLiftUser($pdo, $userId);
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)')
        ->execute([$actor, 'sanctions.suspension_lifted', 'user', $userId, json_encode(['note' => $note]), $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    assignmentNotifyUser($pdo, $userId, 'account_sanction', 'Suspension lifted', mb_substr('Your DASCARE account is active again. ' . $note, 0, 500), 'user', $userId, 'sanction-lifted:' . $userId . ':' . time());
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Suspension lifted.']);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    platformJsonError(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Lift suspension failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to lift the suspension.');
}
