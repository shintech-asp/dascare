<?php
/**
 * citizen/emergency_requests/list.php
 *
 * Powers MyRequests.vue. Returns every emergency_requests row filed by
 * the signed-in citizen (requester_user_id = session user), newest first.
 * Guests never see this page (no account to list requests under), so
 * this endpoint requires a logged-in citizen session — same session.php
 * bootstrap (CORS + cookie session + $pdo) as the rest of the API.
 */

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../reusables/incident_attention.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_level'] ?? '') !== 'citizen') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to view your requests.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    syncIncidentAttentionFlags($pdo);
    $stmt = $pdo->prepare("
        SELECT
            er.id,
            er.reference_number,
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
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $requests = array_map(static function (array $r): array {
        return [
            'id' => (int) $r['id'],
            'reference_number' => $r['reference_number'],
            'emergency_category_name' => $r['emergency_category_name'],
            'status' => $r['status'],
            'attention_level' => $r['attention_level'] ?? 'normal',
            'attention_flagged_at' => $r['attention_flagged_at'] ?? null,
            'attention_reason' => $r['attention_reason'] ?? null,
            'age_minutes' => isset($r['age_minutes']) ? (int) $r['age_minutes'] : null,
            'address_text' => $r['address_text'],
            'submitted_at' => $r['submitted_at'],
            // Set when dedup linked this report to an earlier one; the
            // linked incident's status is what the citizen should follow.
            'merged_into_reference' => $r['merged_into_reference'],
            'linked_status' => $r['linked_status'],
        ];
    }, $rows);

    echo json_encode($requests);
} catch (PDOException $e) {
    error_log('citizen/emergency_requests/list.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load your requests. Please try again.']);
}