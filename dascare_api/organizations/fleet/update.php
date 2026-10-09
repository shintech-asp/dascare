<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulances.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$body = fleetBody();
$ambulanceId = (int) ($body['id'] ?? 0);
$data = fleetValidateUnitPayload($body);

try {
    $pdo->beginTransaction();
    $old = fleetOwnedAmbulance($pdo, (int) $ctx['organization_id'], $ambulanceId, true);
    $stmt = $pdo->prepare("\n        UPDATE ambulances SET unit_code=?, plate_number=?, vehicle_make_model=?, model_year=?, ambulance_type=?, capability_notes=?, capacity=?, registration_expiry=?, inspection_expiry=?\n        WHERE id=? AND organization_id=?\n    ");
    $stmt->execute([
        $data['unit_code'],$data['plate_number'],$data['vehicle_make_model'],$data['model_year'],$data['ambulance_type'],$data['capability_notes'],
        $data['capacity'],$data['registration_expiry'],$data['inspection_expiry'],$ambulanceId,$ctx['organization_id']
    ]);
    fleetAudit($pdo, $ctx, 'fleet.ambulance_updated', 'ambulance', $ambulanceId, $old, $data);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Ambulance details updated.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ((string) $e->getCode() === '23000') organizationJsonError(409, 'That unit code or plate number is already in use.');
    error_log('Fleet update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update the ambulance.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Fleet update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update the ambulance.');
}
