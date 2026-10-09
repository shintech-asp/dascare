<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dss.php';
require_once __DIR__ . '/../../reusables/incident_attention.php';
require_once __DIR__ . '/../../reusables/dispatch_dedup.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'oversight.emergency_requests.read');
$requestId = (int) ($_GET['id'] ?? 0);
if ($requestId <= 0) platformJsonError(422, 'Invalid incident.');

try {
    dssSyncExpiredOffers($pdo, $requestId);
    syncIncidentAttentionFlags($pdo, $requestId);
    $request = dssLoadRequest($pdo, $requestId);

    $runStmt = $pdo->prepare("SELECT * FROM dss_recommendation_runs WHERE emergency_request_id = ? ORDER BY id DESC LIMIT 1");
    $runStmt->execute([$requestId]);
    $run = $runStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $recommendations = [];
    $offers = [];
    if ($run) {
        $recStmt = $pdo->prepare("
            SELECT dr.*, o.name AS organization_name, a.unit_code AS recommended_unit_code, a.ambulance_type AS recommended_ambulance_type
            FROM dss_recommendations dr
            INNER JOIN organizations o ON o.id = dr.organization_id
            LEFT JOIN ambulances a ON a.id = dr.recommended_ambulance_id
            WHERE dr.run_id = ? ORDER BY dr.rank_position ASC
        ");
        $recStmt->execute([(int)$run['id']]);
        $recommendations = $recStmt->fetchAll(PDO::FETCH_ASSOC);
        $offerStmt = $pdo->prepare("
            SELECT io.*, o.name AS organization_name
            FROM incident_offers io
            INNER JOIN organizations o ON o.id = io.organization_id
            WHERE io.dss_run_id = ? ORDER BY io.id ASC
        ");
        $offerStmt->execute([(int)$run['id']]);
        $offers = $offerStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Dedup: reports linked to this incident, or the incident this report
    // was linked to (only one of the two can apply).
    $linkedReports = dedupLinkedReports($pdo, $requestId);
    $mergedInto = null;
    if ($request['merged_into_request_id'] !== null && $request['status'] === 'duplicate') {
        $p = $pdo->prepare('SELECT id, reference_number, status, address_text, latitude, longitude, submitted_at FROM emergency_requests WHERE id = ?');
        $p->execute([(int) $request['merged_into_request_id']]);
        if ($primary = $p->fetch(PDO::FETCH_ASSOC)) {
            $primary['id'] = (int) $primary['id'];
            $primary['distance_m'] = (int) round(dssHaversineKm((float) $request['latitude'], (float) $request['longitude'], (float) $primary['latitude'], (float) $primary['longitude']) * 1000);
            $mergedInto = $primary;
        }
    }
    echo json_encode(['success'=>true,'incident'=>$request,'run'=>$run,'recommendations'=>$recommendations,'offers'=>$offers,'linked_reports'=>$linkedReports,'merged_into'=>$mergedInto]);
} catch (Throwable $e) {
    error_log('Platform incident detail failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load incident details.');
}
