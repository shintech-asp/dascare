<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../../reusables/dispatch_dss.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAnyPermission($pdo, ['dispatch.dispatch_assignments.read','dispatch.dispatch_assignments.approve']);

try {
    dssSyncExpiredOffers($pdo);
    $stmt = $pdo->prepare("
        SELECT
            io.id, io.offer_status, io.offered_at, io.expires_at, io.responded_at, io.response_note,
            io.emergency_request_id,
            er.reference_number, er.severity, er.description, er.address_text, er.barangay, er.landmark,
            er.latitude, er.longitude, er.requester_name, er.requester_phone, er.status AS incident_status, er.submitted_at,
            ec.name AS category_name,
            dr.rank_position, dr.total_score, dr.distance_km, dr.explanation,
            a.id AS recommended_ambulance_id, a.unit_code AS recommended_unit_code, a.ambulance_type AS recommended_ambulance_type
        FROM incident_offers io
        INNER JOIN emergency_requests er ON er.id = io.emergency_request_id
        INNER JOIN emergency_categories ec ON ec.id = er.emergency_category_id
        INNER JOIN dss_recommendations dr ON dr.id = io.recommendation_id
        LEFT JOIN ambulances a ON a.id = dr.recommended_ambulance_id
        WHERE io.organization_id = ?
        ORDER BY
            CASE io.offer_status WHEN 'sent' THEN 0 WHEN 'accepted' THEN 1 WHEN 'declined' THEN 2 WHEN 'timed_out' THEN 3 ELSE 4 END,
            io.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([(int)$ctx['organization_id']]);
    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($offers as &$offer) {
        foreach (['id','emergency_request_id','rank_position','recommended_ambulance_id'] as $key) $offer[$key] = $offer[$key] !== null ? (int)$offer[$key] : null;
        $offer['total_score'] = $offer['total_score'] !== null ? (float)$offer['total_score'] : null;
        $offer['distance_km'] = $offer['distance_km'] !== null ? (float)$offer['distance_km'] : null;
        $offer['latitude'] = (float)$offer['latitude'];
        $offer['longitude'] = (float)$offer['longitude'];
    }
    unset($offer);
    $stats = ['sent'=>0,'accepted'=>0,'declined'=>0,'timed_out'=>0];
    foreach ($offers as $offer) if (isset($stats[$offer['offer_status']])) $stats[$offer['offer_status']]++;
    echo json_encode(['success'=>true,'offers'=>$offers,'stats'=>$stats,'can_respond'=>organizationHasPermission($pdo,$ctx,'dispatch.dispatch_assignments.approve')]);
} catch (Throwable $e) {
    error_log('Incident offer list failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load incident offers.');
}
