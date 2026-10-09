<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/realtime.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/push.php';

header('Content-Type: application/json');
$reviewerId = requirePlatformExecutive($pdo, 'citizens.citizens.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') platformJsonError(405, 'Method not allowed.');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) platformJsonError(422, 'Invalid request body.');

$userId = (int) ($payload['user_id'] ?? 0);
$action = strtolower(trim((string) ($payload['action'] ?? '')));
$reason = trim(strip_tags((string) ($payload['reason'] ?? '')));
if ($userId <= 0) platformJsonError(422, 'A valid citizen account is required.');
if (!in_array($action, ['suspend', 'reactivate', 'disable'], true)) platformJsonError(422, 'Choose suspend, reactivate, or disable.');
if (in_array($action, ['suspend', 'disable'], true) && mb_strlen($reason) < 5) platformJsonError(422, 'Please provide a clear reason of at least 5 characters.');
if (mb_strlen($reason) > 1000) platformJsonError(422, 'Reason must be 1,000 characters or fewer.');

$newStatus = ['suspend' => 'suspended', 'reactivate' => 'active', 'disable' => 'disabled'][$action];

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("\n        SELECT u.id, u.first_name, u.last_name, u.email, u.account_status, u.email_verified_at\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'citizen'\n        WHERE u.id = ? AND u.deleted_at IS NULL\n        FOR UPDATE\n    ");
    $stmt->execute([$userId]);
    $citizen = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$citizen) {
        $pdo->rollBack();
        platformJsonError(404, 'Citizen account not found.');
    }
    if ($action === 'reactivate' && $citizen['email_verified_at'] === null) {
        $pdo->rollBack();
        platformJsonError(409, 'An unverified account cannot be reactivated yet.');
    }
    if ($citizen['account_status'] === $newStatus) {
        $pdo->rollBack();
        platformJsonError(409, 'This citizen account is already in that status.');
    }

    $pdo->prepare('UPDATE users SET account_status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $userId]);

    $notificationData = match ($action) {
        'suspend' => ['citizen_account_suspended', 'Account Suspended', 'Your DASCARE account has been suspended following an administrative review. You may still request emergency assistance as a guest.'],
        'disable' => ['citizen_account_disabled', 'Account Deactivated', 'Your DASCARE account has been deactivated following an administrative review. You may still request emergency assistance as a guest.'],
        default => ['citizen_account_reactivated', 'Account Reactivated', 'Your DASCARE account has been reactivated. You may sign in again using your existing credentials.'],
    };
    $message = $notificationData[2] . ($reason !== '' ? ' Review note: ' . mb_substr($reason, 0, 240) : '');
    $dedupKey = 'citizen_account_status_' . $userId . '_' . $newStatus . '_' . date('YmdHi');
    $notify = $pdo->prepare("\n        INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id, dedup_key)\n        VALUES (?, ?, ?, ?, 'citizen_account', ?, ?)\n        ON DUPLICATE KEY UPDATE message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP\n    ");
    $notify->execute([$userId, $notificationData[0], $notificationData[1], $message, $userId, $dedupKey]);
    realtimeNotifyUsers($pdo, [$userId]);
    // DASCARE app on the citizen's phone (no-op without Firebase).
    pushQueueUser($pdo, $userId, $notificationData[1], $message, ['type' => $notificationData[0], 'related_type' => 'citizen_account', 'related_id' => $userId], $dedupKey);

    $audit = $pdo->prepare("\n        INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)\n        VALUES (?, NULL, ?, 'citizen_account', ?, ?, ?, ?, ?)\n    ");
    $audit->execute([
        $reviewerId,
        'citizen.account_' . $action,
        $userId,
        json_encode(['account_status' => $citizen['account_status']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode(['account_status' => $newStatus, 'reason' => $reason !== '' ? $reason : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'status' => $newStatus,
        'message' => match ($action) {
            'suspend' => 'Citizen account suspended.',
            'disable' => 'Citizen account deactivated.',
            default => 'Citizen account reactivated.',
        },
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Platform citizen status failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to update this citizen account.');
}
