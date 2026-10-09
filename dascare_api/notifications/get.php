<?php
/**
 * citizen/notifications/get.php
 *
 * Returns the signed-in citizen's notifications, newest first.
 *
 * Rewritten against DASCARE's actual `notifications` table — the
 * previous version of this file was carried over from a different
 * (e-commerce) system and referenced columns/tables that don't exist
 * here: is_read, deleted_at, url, product_id, order_id, order_item_id,
 * artist_id, ship_photo, order_items. DASCARE's notifications table is
 * a generic polymorphic-reference shape instead:
 *   notification_type  varchar   e.g. 'request_status_changed'
 *   related_type        varchar   e.g. 'emergency_request'
 *   related_id           bigint    id within related_type's own table
 *   read_at              datetime  NULL = unread (no separate is_read flag)
 * There's also no deleted_at column — notifications aren't soft-deleted,
 * see delete_notifications.php.
 */

require_once __DIR__ . '/../session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // Polling for notifications while logged out is a normal, expected
    // case (e.g. the header badge mounts before auth resolves) — not a
    // failure. Keep the status 200 so the frontend just sees an empty
    // list instead of a console error.
    echo json_encode([]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, notification_type, title, message, related_type, related_id, read_at, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Maps a notification's polymorphic target to a frontend route. Extend
// this as more related_types start getting written elsewhere in the
// codebase — unrecognized/legacy rows just get a null url and the
// frontend falls back to marking-as-read without navigating.
function notificationUrl(?string $relatedType, ?int $relatedId): ?string {
    if (!$relatedId) return null;
    return match ($relatedType) {
        'emergency_request' => "/citizen/requests/{$relatedId}",
        default => null,
    };
}

// Icon shown in the notification list — lucide set, matching every other
// icon in the app (the old version of this file used mdi: icons, which
// don't match anything else here).
function notificationIcon(?string $relatedType): string {
    return match ($relatedType) {
        'emergency_request'   => 'lucide:siren',
        'kyc_verification'    => 'lucide:shield-check',
        'dispatch_assignment' => 'lucide:truck',
        'patient_handoff'     => 'lucide:heart-pulse',
        default                => 'lucide:bell',
    };
}

$notifications = array_map(static function (array $row): array {
    return [
        'id'                => (int) $row['id'],
        'notification_type' => $row['notification_type'],
        'title'             => $row['title'],
        'message'           => $row['message'],
        'related_type'      => $row['related_type'],
        'related_id'        => $row['related_id'] !== null ? (int) $row['related_id'] : null,
        // Keep both names for compatibility with older frontend code while
        // the UI standardizes on `read`. Both are derived from read_at.
        'read'              => $row['read_at'] !== null,
        'is_read'           => $row['read_at'] !== null,
        'read_at'           => $row['read_at'],
        'created_at'        => $row['created_at'],
        'url'               => notificationUrl($row['related_type'], $row['related_id'] !== null ? (int) $row['related_id'] : null),
        'icon'              => notificationIcon($row['related_type']),
    ];
}, $rows);

echo json_encode($notifications);
