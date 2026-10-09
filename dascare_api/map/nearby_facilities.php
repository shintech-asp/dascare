<?php
// map/nearby_facilities.php
// Public, read-only reference layer for the emergency request map: active
// hospitals/medical facilities and active rescue organizations with a known
// location. No auth required — a guest filing a request needs this exactly
// as much as a logged-in citizen does.
require '../cors.php';
require_once __DIR__ . '/../db/db.php';

header('Content-Type: application/json');

// ==================================================
// HOSPITALS / MEDICAL FACILITIES
// ==================================================
$hospitals = $pdo->query("
    SELECT id, name, facility_type, address_text, phone, latitude, longitude, capabilities
    FROM medical_facilities
    WHERE status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

// ==================================================
// RESCUE / AMBULANCE ORGANIZATIONS
// Only ones with a plotted location — an org without lat/lng just can't be
// pinned yet, no point sending a marker-less row to the frontend.
// ==================================================
$orgs = $pdo->query("
    SELECT id, name, organization_type, address_line, phone, latitude, longitude
    FROM organizations
    WHERE status = 'active'
      AND deleted_at IS NULL
      AND latitude IS NOT NULL
      AND longitude IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

if ($orgs) {
    $areaStmt = $pdo->prepare("SELECT barangay FROM organization_service_areas WHERE organization_id = ? ORDER BY barangay");
    foreach ($orgs as &$org) {
        $areaStmt->execute([$org['id']]);
        $org['service_areas'] = $areaStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($org);
}

echo json_encode([
    'hospitals'     => $hospitals,
    'organizations' => $orgs,
]);
