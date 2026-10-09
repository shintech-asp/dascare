<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/realtime.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
$reviewerId = requirePlatformExecutive($pdo, 'organizations.organizations.approve');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    platformJsonError(405, 'Method not allowed.');
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    platformJsonError(422, 'Invalid request body.');
}

$organizationId = (int) ($payload['organization_id'] ?? 0);
$action = strtolower(trim((string) ($payload['action'] ?? '')));
$note = trim(strip_tags((string) ($payload['note'] ?? '')));

if ($organizationId <= 0) platformJsonError(422, 'A valid organization application is required.');
if (!in_array($action, ['approve', 'revision', 'reject'], true)) platformJsonError(422, 'Choose approve, request revision, or reject.');
if (in_array($action, ['revision', 'reject'], true) && mb_strlen($note) < 8) platformJsonError(422, 'Please provide a clear review reason of at least 8 characters.');
if (mb_strlen($note) > 2000) platformJsonError(422, 'Review notes must be 2,000 characters or fewer.');

try {
    $pdo->beginTransaction();

    $select = $pdo->prepare("\n        SELECT o.*, admin.id AS admin_user_id, admin.email AS admin_email\n        FROM organizations o\n        LEFT JOIN users admin ON admin.id = o.primary_admin_user_id\n        WHERE o.id = ?\n          AND o.deleted_at IS NULL\n          AND o.application_reference IS NOT NULL\n        FOR UPDATE\n    ");
    $select->execute([$organizationId]);
    $current = $select->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        $pdo->rollBack();
        platformJsonError(404, 'Organization application not found.');
    }

    if (!in_array($current['application_status'], ['pending', 'revision_requested'], true)) {
        $pdo->rollBack();
        platformJsonError(409, 'This application is no longer awaiting a review decision. Refresh the queue and try again.');
    }

    if ($action === 'approve') {
        $update = $pdo->prepare("\n            UPDATE organizations\n            SET application_status = 'approved',\n                status = 'active',\n                verification_note = NULLIF(?, ''),\n                verified_at = NOW(),\n                verified_by = ?,\n                application_reviewed_by = ?,\n                application_reviewed_at = NOW()\n            WHERE id = ?\n        ");
        $update->execute([$note, $reviewerId, $reviewerId, $organizationId]);

        $pdo->prepare("\n            UPDATE organization_documents\n            SET status = 'approved', rejection_reason = NULL, reviewed_by = ?, reviewed_at = NOW()\n            WHERE organization_id = ?\n        ")->execute([$reviewerId, $organizationId]);

        $pdo->prepare("\n            UPDATE organization_members\n            SET joined_at = COALESCE(joined_at, CURRENT_DATE)\n            WHERE organization_id = ? AND deleted_at IS NULL\n        ")->execute([$organizationId]);
    } elseif ($action === 'revision') {
        $update = $pdo->prepare("\n            UPDATE organizations\n            SET application_status = 'revision_requested',\n                status = 'pending',\n                verification_note = ?,\n                application_reviewed_by = ?,\n                application_reviewed_at = NOW()\n            WHERE id = ?\n        ");
        $update->execute([$note, $reviewerId, $organizationId]);

        $pdo->prepare("\n            UPDATE organization_documents\n            SET status = 'pending', reviewed_by = NULL, reviewed_at = NULL\n            WHERE organization_id = ? AND status <> 'rejected'\n        ")->execute([$organizationId]);
    } else {
        $update = $pdo->prepare("\n            UPDATE organizations\n            SET application_status = 'rejected',\n                status = 'pending',\n                verification_note = ?,\n                rejection_count = rejection_count + 1,\n                last_rejected_at = NOW(),\n                verified_at = NULL,\n                verified_by = NULL,\n                application_reviewed_by = ?,\n                application_reviewed_at = NOW()\n            WHERE id = ?\n        ");
        $update->execute([$note, $reviewerId, $organizationId]);

        $pdo->prepare("\n            UPDATE organization_documents\n            SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()\n            WHERE organization_id = ?\n        ")->execute([$note, $reviewerId, $organizationId]);
    }

    $notificationMap = [
        'approve' => [
            'type' => 'organization_application_approved',
            'title' => 'Organization Application Approved',
            'message' => 'Your organization has been approved. The DASCARE organization workspace is now active.',
        ],
        'revision' => [
            'type' => 'organization_application_revision',
            'title' => 'Organization Application Needs Revision',
            'message' => 'A Platform Executive requested changes to your organization application. Open Application Status to review the note.',
        ],
        'reject' => [
            'type' => 'organization_application_rejected',
            'title' => 'Organization Application Rejected',
            'message' => 'Your organization application was rejected. Open Application Status to review the decision note.',
        ],
    ];

    $adminUserId = (int) ($current['admin_user_id'] ?? 0);
    if ($adminUserId > 0) {
        $notification = $notificationMap[$action];
        $dedupKey = 'org_review_' . $organizationId . '_' . $action . '_' . md5((string) $current['updated_at'] . '|' . $note);
        $notify = $pdo->prepare("\n            INSERT INTO notifications\n                (user_id, notification_type, title, message, related_type, related_id, dedup_key)\n            VALUES (?, ?, ?, ?, 'organization_application', ?, ?)\n            ON DUPLICATE KEY UPDATE\n                title = VALUES(title), message = VALUES(message), read_at = NULL, created_at = CURRENT_TIMESTAMP\n        ");
        $notify->execute([$adminUserId, $notification['type'], $notification['title'], $notification['message'], $organizationId, $dedupKey]);
        realtimeNotifyUsers($pdo, [(int) $adminUserId]);
        realtimePlatformBadgesChanged($pdo, 'applications');
    }

    $oldValues = json_encode([
        'status' => $current['status'],
        'application_status' => $current['application_status'],
        'verification_note' => $current['verification_note'],
        'verified_at' => $current['verified_at'],
        'verified_by' => $current['verified_by'] !== null ? (int) $current['verified_by'] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $newValues = json_encode([
        'decision' => $action,
        'status' => $action === 'approve' ? 'active' : 'pending',
        'application_status' => match ($action) {
            'approve' => 'approved',
            'revision' => 'revision_requested',
            default => 'rejected',
        },
        'review_note' => $note !== '' ? $note : null,
        'reviewed_by' => $reviewerId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $audit = $pdo->prepare("\n        INSERT INTO audit_logs\n            (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)\n        VALUES (?, ?, ?, 'organization', ?, ?, ?, ?, ?)\n    ");
    $audit->execute([
        $reviewerId,
        $organizationId,
        'organization.application_' . $action,
        $organizationId,
        $oldValues,
        $newValues,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => match ($action) {
            'approve' => 'Organization approved and operational access activated.',
            'revision' => 'Revision requested from the organization applicant.',
            default => 'Organization application rejected.',
        },
        'application_status' => match ($action) {
            'approve' => 'approved',
            'revision' => 'revision_requested',
            default => 'rejected',
        },
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Platform organization review failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save this organization decision.');
}
