<?php
/**
 * citizen/notifications/unread_count.php
 *
 * Powers the header notification badge. Unread = read_at IS NULL — the
 * table has no separate is_read flag. idx_notification_unread
 * (user_id, read_at, created_at) covers this query directly.
 */

require_once __DIR__ . '/../session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ? AND read_at IS NULL
");
$stmt->execute([$userId]);

echo json_encode(['count' => (int) $stmt->fetchColumn()]);
