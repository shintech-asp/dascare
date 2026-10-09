<?php
/**
 * citizen/notifications/delete_notifications.php
 *
 * Deletes a single notification belonging to the signed-in citizen.
 *
 * DASCARE's notifications table has no deleted_at column (see
 * dascare.sql) — the previous version of this file soft-deleted
 * against a column that doesn't exist here, so the UPDATE silently
 * matched zero rows every time. This does a real DELETE instead;
 * notifications aren't kept for audit purposes the way emergency
 * request status changes are; nothing else references a deleted row.
 */

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../reusables/realtime.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$notifId = (int) ($data['id'] ?? 0);

if ($notifId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No notification ID provided']);
    exit;
}

$stmt = $pdo->prepare("
    DELETE FROM notifications
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$notifId, $userId]);
if ($stmt->rowCount() > 0) realtimeNotifyUsers($pdo, [$userId], 'notification.read'); // other tabs/devices update their badge

echo json_encode(['success' => $stmt->rowCount() > 0]);