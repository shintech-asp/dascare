<?php
/**
 * GET /citizen/dashboard.php
 *
 * Backs CitizenDashboardHome.vue's fetchDashboard(). Returns the logged-in
 * citizen's profile + a snapshot of their emergency_requests: the single
 * active (non-terminal) request if any, their 5 most recent requests, and
 * a total count.
 *
 * Response shape (matches what CitizenDashboardHome.vue already reads):
 * {
 *   "user": { id, name, email, phone, level, roleLabel, kyc_status },
 *   "active_request": { id, reference_number, emergency_category_name, status, submitted_at, ... } | null,
 *   "recent_requests": [ ...same shape as active_request... ],
 *   "total_requests": number
 * }
 */

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../reusables/incident_attention.php';

header('Content-Type: application/json');

// ----------------------------------
// AUTH GUARD
// ----------------------------------
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Not authenticated']);
    exit;
}

if (($_SESSION['user_level'] ?? '') !== 'citizen') {
    http_response_code(403);
    echo json_encode(['message' => 'This endpoint is for citizen accounts only']);
    exit;
}

// ----------------------------------
// KYC GUARD (server-side mirror of the frontend's showVerificationWall)
// The dashboard's request history is only meaningful — and only meant to
// be reachable — once identity is verified. Frontend already skips this
// fetch unless kyc_status === 2, but we don't trust the client alone.
// ----------------------------------
$kycStatus = (int) ($_SESSION['user_kyc_status'] ?? 0);
if ($kycStatus !== 2) {
    http_response_code(403);
    echo json_encode([
        'message'    => 'Account not verified',
        'kyc_status' => $kycStatus,
    ]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Statuses that count as "done" — everything else is treated as active,
// mirroring emergency_requests.status enum + CitizenDashboardHome.vue's
// TERMINAL_STATUSES constant.
$TERMINAL_STATUSES = ['completed', 'cancelled', 'rejected', 'duplicate', 'false_alarm'];

try {
    syncIncidentAttentionFlags($pdo);
    // ----------------------------------
    // TOTAL REQUEST COUNT
    // ----------------------------------
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM emergency_requests WHERE requester_user_id = ?
    ");
    $countStmt->execute([$userId]);
    $totalRequests = (int) $countStmt->fetchColumn();

    // ----------------------------------
    // RECENT REQUESTS (last 5, newest first)
    // ----------------------------------
    $recentStmt = $pdo->prepare("
        SELECT
            er.id,
            er.reference_number,
            er.request_mode,
            er.severity,
            er.status,
            er.attention_level,
            er.attention_flagged_at,
            er.attention_reason,
            TIMESTAMPDIFF(MINUTE, er.submitted_at, NOW()) AS age_minutes,
            er.address_text,
            er.submitted_at,
            ec.name AS emergency_category_name,
            p.reference_number AS merged_into_reference,
            p.status AS linked_status
        FROM emergency_requests er
        INNER JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        LEFT JOIN emergency_requests p ON p.id = er.merged_into_request_id AND er.status = 'duplicate'
        WHERE er.requester_user_id = ?
        ORDER BY er.submitted_at DESC
        LIMIT 5
    ");
    $recentStmt->execute([$userId]);
    $recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recentRequests as &$r) {
        $r['id'] = (int) $r['id'];
    }
    unset($r);

    // ----------------------------------
    // ACTIVE REQUEST
    // Prefer one already in the last-5 batch (covers the common case with
    // zero extra queries); fall back to a dedicated lookup in case the
    // citizen's single active request happens to be older than their 5
    // most recent rows (e.g. a long-running standard/scheduled request
    // with several newer completed ones on top of it).
    // ----------------------------------
    $activeRequest = null;
    // A report linked to an earlier one (dedup) is active for as long as the
    // incident it's linked to is.
    foreach ($recentRequests as $r) {
        if (!in_array($r['linked_status'] ?? $r['status'], $TERMINAL_STATUSES, true)) {
            $activeRequest = $r;
            break;
        }
    }

    if ($activeRequest === null) {
        $placeholders = implode(',', array_fill(0, count($TERMINAL_STATUSES), '?'));
        $activeStmt = $pdo->prepare("
            SELECT
                er.id,
                er.reference_number,
                er.request_mode,
                er.severity,
                er.status,
                er.address_text,
                er.submitted_at,
                ec.name AS emergency_category_name,
                p.reference_number AS merged_into_reference,
                p.status AS linked_status
            FROM emergency_requests er
            INNER JOIN emergency_categories ec ON ec.id = er.emergency_category_id
            LEFT JOIN emergency_requests p ON p.id = er.merged_into_request_id AND er.status = 'duplicate'
            WHERE er.requester_user_id = ?
              AND COALESCE(p.status, er.status) NOT IN ($placeholders)
            ORDER BY er.submitted_at DESC
            LIMIT 1
        ");
        $activeStmt->execute([$userId, ...$TERMINAL_STATUSES]);
        $found = $activeStmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            $found['id'] = (int) $found['id'];
            $activeRequest = $found;
        }
    }

    // ----------------------------------
    // USER (already refreshed into $_SESSION by session.php above)
    // ----------------------------------
    $user = [
        'id'        => $userId,
        'name'      => $_SESSION['user_name'] ?? null,
        'email'     => $_SESSION['user_email'] ?? null,
        'phone'     => $_SESSION['user_phone'] ?? null,
        'level'     => $_SESSION['user_level'] ?? null,
        'roleLabel' => getRoleLabel($_SESSION['user_level'] ?? ''),
        'kyc_status' => $kycStatus,
    ];

    echo json_encode([
        'user'            => $user,
        'active_request'  => $activeRequest,
        'recent_requests' => $recentRequests,
        'total_requests'  => $totalRequests,
    ]);

} catch (PDOException $e) {
    error_log('Citizen dashboard error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Could not load dashboard data']);
}
