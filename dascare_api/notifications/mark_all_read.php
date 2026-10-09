<?php
/**
 * citizen/notifications/mark_all_read.php
 *
 * Marks every unread notification for the signed-in citizen as read.
 * No separate is_read column in this schema — read_at IS NULL is
 * "unread", and this WHERE clause is exactly what
 * idx_notification_unread (user_id, read_at, created_at) was built for.
 */

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../reusables/realtime.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    UPDATE notifications
    SET read_at = NOW()
    WHERE user_id = ? AND read_at IS NULL
");
$stmt->execute([$userId]);
if ($stmt->rowCount() > 0) realtimeNotifyUsers($pdo, [$userId], 'notification.read'); // other tabs/devices update their badge

// Nothing to update (everything was already read) is still a success —
// only an actual query failure should read as false, and PDO would have
// thrown for that. rowCount() only tells us how many changed, not
// whether the request succeeded.
echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
