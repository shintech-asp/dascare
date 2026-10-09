<?php
/**
 * GET realtime/auth.php — a signed Ably token request listing the channels
 * the caller may LISTEN to (never publish). Web: cookie session. App: Bearer
 * token / X-Guest-Tokens via cors.php's mobile bootstrap. See
 * reusables/realtime.php.
 *
 * Response: { success, enabled, tokenRequest|null, channels: {user, org, platform, requests[]}, prefix }
 * tokenRequest is null when there is nothing to listen to yet (e.g. a guest
 * with no requests); the screen just keeps polling.
 */
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/realtime.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!realtimeEnabled($pdo)) {
    echo json_encode(['success' => true, 'enabled' => false]);
    exit;
}

const REALTIME_MAX_REQUEST_CHANNELS = 30;

$userId = (int) ($_SESSION['user_id'] ?? 0);
$guestRequestIds = array_values(array_filter(array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? []))));
$channels = ['user' => null, 'org' => null, 'platform' => null, 'requests' => []];
$listen = [];
$requestIds = [];
$clientId = '';

try {
    $role = null;
    if ($userId > 0) {
        $stmt = $pdo->prepare('
            SELECT ur.role, u.account_status
            FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id
            WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1
        ');
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($account && $account['account_status'] === 'active') $role = $account['role'];
    }

    if ($role !== null) {
        $clientId = 'user:' . $userId;
        $channels['user'] = realtimeChannel('user', $userId);
        $listen[] = $channels['user'];
    }

    if ($role === 'platform_executive_admin') {
        // Oversight of every incident: platform feed + any request channel.
        $channels['platform'] = realtimeChannel('platform');
        $listen[] = $channels['platform'];
        $listen[] = realtimeChannel('request') . ':*';
    } elseif (in_array($role, ['organization_admin', 'organization_operational_user'], true)) {
        // Same membership rules as requireOrganizationAccess().
        $stmt = $pdo->prepare("
            SELECT om.organization_id
            FROM organization_members om
            INNER JOIN organizations o ON o.id = om.organization_id AND o.deleted_at IS NULL
            WHERE om.user_id = ? AND om.deleted_at IS NULL
              AND om.membership_status IN ('active', 'invited') AND o.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $orgId = (int) $stmt->fetchColumn();
        if ($orgId > 0) {
            $channels['org'] = realtimeChannel('org', $orgId);
            $listen[] = $channels['org'];
        }
    } elseif ($role === 'citizen') {
        // Their own recent requests (open ones first).
        $stmt = $pdo->prepare("
            SELECT id, merged_into_request_id
            FROM emergency_requests
            WHERE requester_user_id = ?
              AND (status NOT IN ('completed', 'cancelled', 'rejected', 'false_alarm') OR submitted_at >= NOW() - INTERVAL 1 DAY)
            ORDER BY submitted_at DESC
            LIMIT " . REALTIME_MAX_REQUEST_CHANNELS);
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requestIds[] = (int) $r['id'];
            // A report linked as a duplicate follows the incident it was linked to.
            if ($r['merged_into_request_id']) $requestIds[] = (int) $r['merged_into_request_id'];
        }
    }

    // Guest requests sent from this browser session / this phone.
    if ($guestRequestIds) {
        $guestRequestIds = array_slice($guestRequestIds, -REALTIME_MAX_REQUEST_CHANNELS);
        $ph = implode(',', array_fill(0, count($guestRequestIds), '?'));
        $stmt = $pdo->prepare("SELECT id, merged_into_request_id FROM emergency_requests WHERE id IN ($ph)");
        $stmt->execute($guestRequestIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $requestIds[] = (int) $r['id'];
            if ($r['merged_into_request_id']) $requestIds[] = (int) $r['merged_into_request_id'];
        }
        if ($clientId === '') $clientId = 'guest';
    }

    foreach (array_slice(array_values(array_unique($requestIds)), 0, REALTIME_MAX_REQUEST_CHANNELS * 2) as $id) {
        $channels['requests'][] = $id;
        $listen[] = realtimeChannel('request', $id);
    }
} catch (Throwable $e) {
    error_log('Realtime auth failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Live updates are unavailable right now.']);
    exit;
}

echo json_encode([
    'success' => true,
    'enabled' => true,
    'prefix' => realtimeConfig()['prefix'],
    'channels' => $channels,
    'tokenRequest' => $listen ? realtimeTokenRequest($listen, $clientId) : null,
]);
