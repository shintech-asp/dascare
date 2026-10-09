<?php
/**
 * citizen/emergency_requests/detail.php
 *
 * Powers TrackRequest.vue. Returns one emergency_requests row plus:
 *   - its current dispatch (dispatch_assignments -> ambulances -> organizations),
 *     if one has been made yet
 *   - its full status_logs history (emergency_request_status_logs)
 *
 * Scoped to requester_user_id = session user — a citizen can only ever
 * pull up their own requests (404, not 403, so request IDs aren't
 * enumerable by status code).
 */

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../reusables/incident_attention.php';
require_once __DIR__ . '/../reusables/dispatch_dss.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Viewable by the citizen who filed it, or by a guest holding that request's
// key — the DASCARE Android app sends guest SOS keys in X-Guest-Tokens, which
// reusables/mobile_auth.php maps to $_SESSION['guest_request_ids'].
$isCitizen = isset($_SESSION['user_id']) && ($_SESSION['user_level'] ?? '') === 'citizen';
$guestRequestIds = array_values(array_filter(array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? []))));

if (!$isCitizen && !$guestRequestIds) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to view this request.']);
    exit;
}

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$requestId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid request id is required.']);
    exit;
}

$userId = $isCitizen ? (int) $_SESSION['user_id'] : 0;
$guestPlaceholders = $guestRequestIds ? implode(',', array_fill(0, count($guestRequestIds), '?')) : '0';

// Human labels for dispatch_assignments.assignment_status — mirrors the
// STATUS_LABELS map on the frontend, just for the ambulance sub-object.
const ASSIGNMENT_STATUS_LABELS = [
    'recommended' => 'Recommended',
    'assigned' => 'Assigned',
    'acknowledged' => 'Acknowledged',
    'declined' => 'Declined',
    'responding' => 'En route to scene',
    'on_scene' => 'On scene',
    'transporting' => 'Transporting patient',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'reassigned' => 'Reassigned',
];

try {
    syncIncidentAttentionFlags($pdo, $requestId);
    $stmt = $pdo->prepare("
        SELECT
            er.id,
            er.reference_number,
            er.status,
            er.attention_level,
            er.attention_flagged_at,
            er.attention_reason,
            TIMESTAMPDIFF(MINUTE, er.submitted_at, NOW()) AS age_minutes,
            er.severity,
            er.request_mode,
            er.scheduled_for,
            er.description,
            er.address_text,
            er.barangay,
            er.landmark,
            er.latitude,
            er.longitude,
            er.submitted_at,
            er.merged_into_request_id,
            ec.name AS emergency_category_name
        FROM emergency_requests er
        INNER JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        WHERE er.id = ?
          AND (er.requester_user_id = ? OR (er.requester_user_id IS NULL AND er.id IN ($guestPlaceholders)))
        LIMIT 1
    ");
    $stmt->execute([$requestId, $userId, ...$guestRequestIds]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }

    $result = [
        'id' => (int) $request['id'],
        'reference_number' => $request['reference_number'],
        'emergency_category_name' => $request['emergency_category_name'],
        'status' => $request['status'],
        'attention_level' => $request['attention_level'] ?? 'normal',
        'attention_flagged_at' => $request['attention_flagged_at'] ?? null,
        'attention_reason' => $request['attention_reason'] ?? null,
        'age_minutes' => isset($request['age_minutes']) ? (int) $request['age_minutes'] : null,
        'severity' => $request['severity'],
        'request_mode' => $request['request_mode'],
        'scheduled_for' => $request['scheduled_for'],
        'description' => $request['description'],
        'address_text' => $request['address_text'],
        'barangay' => $request['barangay'],
        'landmark' => $request['landmark'],
        'latitude' => $request['latitude'] !== null ? (float) $request['latitude'] : null,
        'longitude' => $request['longitude'] !== null ? (float) $request['longitude'] : null,
        'submitted_at' => $request['submitted_at'],
        'ambulance' => null,
        'status_logs' => [],
        // Set when dedup linked this report to an earlier one — the citizen
        // then follows that incident's status and unit instead of their own.
        'merged_into' => null,
    ];

    // Most recent non-declined dispatch, if any has been made yet. Also
    // pulls the ambulance's last known GPS fix (ambulances.last_latitude/
    // last_longitude) so the map can plot it alongside the incident pin
    // while the mission is active.
    $ambStmt = $pdo->prepare("
        SELECT
            a.unit_code,
            a.last_latitude,
            a.last_longitude,
            a.last_accuracy_m,
            a.last_location_at,
            o.name AS organization_name,
            da.assignment_status
        FROM dispatch_assignments da
        INNER JOIN ambulances a ON a.id = da.ambulance_id
        INNER JOIN organizations o ON o.id = da.organization_id
        WHERE da.emergency_request_id = ? AND da.assignment_status != 'declined'
        ORDER BY da.assigned_at DESC
        LIMIT 1
    ");
    $loadAmbulance = function (int $id) use ($ambStmt): ?array {
        $ambStmt->execute([$id]);
        $assignment = $ambStmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) return null;
        return [
            'unit_code' => $assignment['unit_code'],
            'organization_name' => $assignment['organization_name'],
            'status_label' => ASSIGNMENT_STATUS_LABELS[$assignment['assignment_status']] ?? $assignment['assignment_status'],
            'latitude' => $assignment['last_latitude'] !== null ? (float) $assignment['last_latitude'] : null,
            'longitude' => $assignment['last_longitude'] !== null ? (float) $assignment['last_longitude'] : null,
            'accuracy_m' => $assignment['last_accuracy_m'] !== null ? (float) $assignment['last_accuracy_m'] : null,
            'location_at' => $assignment['last_location_at'],
            'location_age_seconds' => $assignment['last_location_at'] ? max(0, time() - strtotime($assignment['last_location_at'])) : null,
            'location_stale' => !$assignment['last_location_at'] || (time() - strtotime($assignment['last_location_at'])) > 45,
        ];
    };
    $result['ambulance'] = $loadAmbulance($requestId);

    if ($request['merged_into_request_id'] !== null && $request['status'] === 'duplicate') {
        $primaryId = (int) $request['merged_into_request_id'];
        $pStmt = $pdo->prepare("
            SELECT id, reference_number, status, address_text, barangay, landmark, latitude, longitude, submitted_at
            FROM emergency_requests WHERE id = ? LIMIT 1
        ");
        $pStmt->execute([$primaryId]);
        $primary = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($primary) {
            $result['merged_into'] = [
                'id' => (int) $primary['id'],
                'reference_number' => $primary['reference_number'],
                'status' => $primary['status'],
                'address_text' => $primary['address_text'],
                'barangay' => $primary['barangay'],
                'landmark' => $primary['landmark'],
                'latitude' => (float) $primary['latitude'],
                'longitude' => (float) $primary['longitude'],
                'submitted_at' => $primary['submitted_at'],
                'distance_m' => (int) round(dssHaversineKm((float) $request['latitude'], (float) $request['longitude'], (float) $primary['latitude'], (float) $primary['longitude']) * 1000),
            ];
            // The unit responding to the linked incident is the one coming.
            $result['ambulance'] = $loadAmbulance($primaryId);
        }
    }

    $logStmt = $pdo->prepare("
        SELECT new_status, notes, created_at
        FROM emergency_request_status_logs
        WHERE emergency_request_id = ?
        ORDER BY created_at ASC
    ");
    $logStmt->execute([$requestId]);
    $result['status_logs'] = $logStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($result);
} catch (PDOException $e) {
    error_log('citizen/emergency_requests/detail.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load this request. Please try again.']);
}