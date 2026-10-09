<?php
/**
 * citizen/notifications/mark_read.php
 *
 * Marks a single notification read (read_at = NOW()). No separate
 * is_read column in this schema — read_at IS NULL is "unread".
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
    UPDATE notifications
    SET read_at = NOW()
    WHERE id = ? AND user_id = ? AND read_at IS NULL
");
$stmt->execute([$notifId, $userId]);

// rowCount() is 0 both when the notification doesn't exist/isn't the
// citizen's and when it was already read — tell those apart so a
// double-click (already read) still reports success instead of a
// false "failed" the second time.
if ($stmt->rowCount() > 0) {
    realtimeNotifyUsers($pdo, [$userId], 'notification.read'); // other tabs/devices update their badge
    echo json_encode(['success' => true]);
    exit;
}

$check = $pdo->prepare("SELECT 1 FROM notifications WHERE id = ? AND user_id = ?");
$check->execute([$notifId, $userId]);
echo json_encode(['success' => (bool) $check->fetchColumn()]);
