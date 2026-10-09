<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
$reviewerId = requirePlatformExecutive($pdo, 'citizens.kyc_verifications.approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    platformJsonError(405, 'Method not allowed.');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    platformJsonError(422, 'Invalid request body.');
}

$citizenId = (int) ($payload['user_id'] ?? 0);
$action = strtolower(trim((string) ($payload['action'] ?? '')));
$note = trim(strip_tags((string) ($payload['note'] ?? '')));

if ($citizenId <= 0) {
    platformJsonError(422, 'A valid citizen ID is required.');
}
if (!in_array($action, ['approve', 'reject', 'resubmit'], true)) {
    platformJsonError(422, 'Choose approve, reject, or request resubmission.');
}
if (in_array($action, ['reject', 'resubmit'], true) && mb_strlen($note) < 5) {
    platformJsonError(422, 'Please provide a clear reason of at least 5 characters.');
}
if (mb_strlen($note) > 1500) {
    platformJsonError(422, 'Review notes must be 1,500 characters or fewer.');
}

$statusByAction = ['approve' => 2, 'reject' => 3, 'resubmit' => 4];
$newStatus = $statusByAction[$action];

$notificationByAction = [
    'approve' => [
        'type' => 'kyc_approved',
        'title' => 'Identity Verification Approved',
        'message' => 'Your DASCARE identity verification has been approved.',
    ],
    'reject' => [
        'type' => 'kyc_rejected',
        'title' => 'Identity Verification Rejected',
        'message' => 'Your DASCARE identity verification was rejected. Open Verification to review the administrator note.',
    ],
    'resubmit' => [
        'type' => 'kyc_resubmission_requested',
        'title' => 'Verification Requires Resubmission',
        'message' => 'Please review the administrator note and submit corrected verification information.',
    ],
];

try {
    $pdo->beginTransaction();

    $select = $pdo->prepare("\n        SELECT k.*, u.email, u.first_name, u.last_name\n        FROM kyc_verifications k\n        INNER JOIN users u ON u.id = k.user_id AND u.deleted_at IS NULL\n        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'citizen'\n        WHERE k.user_id = ?\n        FOR UPDATE\n    ");
    $select->execute([$citizenId]);
    $current = $select->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        $pdo->rollBack();
        platformJsonError(404, 'Verification submission not found.');
    }

    if ((int) $current['status'] !== 1) {
        $pdo->rollBack();
        platformJsonError(409, 'This submission is no longer pending review. Refresh the queue and try again.');
    }

    if ($action === 'approve') {
        $update = $pdo->prepare("\n            UPDATE kyc_verifications\n            SET status = 2,\n                verification_note = NULLIF(?, ''),\n                verified_at = NOW(),\n                verified_by = ?,\n                updated_at = NOW()\n            WHERE user_id = ?\n        ");
        $update->execute([$note, $reviewerId, $citizenId]);
    } else {
        $update = $pdo->prepare("\n            UPDATE kyc_verifications\n            SET status = ?,\n                verification_note = ?,\n                rejection_count = rejection_count + 1,\n                last_rejected_at = NOW(),\n                verified_at = NULL,\n                verified_by = ?,\n                updated_at = NOW()\n            WHERE user_id = ?\n        ");
        $update->execute([$newStatus, $note, $reviewerId, $citizenId]);
    }

    $notification = $notificationByAction[$action];
    $dedupKey = 'kyc_review_' . $citizenId . '_' . md5((string) $current['submitted_at'] . '|' . $action);
    $notificationStmt = $pdo->prepare("\n        INSERT INTO notifications\n            (user_id, notification_type, title, message, related_type, related_id, dedup_key)\n        VALUES (?, ?, ?, ?, 'kyc_verification', ?, ?)\n        ON DUPLICATE KEY UPDATE\n            title = VALUES(title),\n            message = VALUES(message),\n            read_at = NULL,\n            created_at = CURRENT_TIMESTAMP\n    ");
    $notificationStmt->execute([
        $citizenId,
        $notification['type'],
        $notification['title'],
        $notification['message'],
        $citizenId,
        $dedupKey,
    ]);

    $oldValues = json_encode([
        'status' => (int) $current['status'],
        'verification_note' => $current['verification_note'],
        'verified_at' => $current['verified_at'],
        'verified_by' => $current['verified_by'] !== null ? (int) $current['verified_by'] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newValues = json_encode([
        'status' => $newStatus,
        'decision' => $action,
        'verification_note' => $note !== '' ? $note : null,
        'reviewed_by' => $reviewerId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $auditStmt = $pdo->prepare("\n        INSERT INTO audit_logs\n            (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)\n        VALUES (?, NULL, ?, 'kyc_verification', ?, ?, ?, ?, ?)\n    ");
    $auditStmt->execute([
        $reviewerId,
        'kyc.' . $action,
        $citizenId,
        $oldValues,
        $newValues,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => match ($action) {
            'approve' => 'Citizen verification approved.',
            'reject' => 'Citizen verification rejected.',
            default => 'Resubmission requested from the citizen.',
        },
        'status' => $newStatus,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Platform KYC review failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save this verification decision.');
}
