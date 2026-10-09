<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulances.create');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$data = fleetValidateUnitPayload(fleetBody());

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("\n        INSERT INTO ambulances\n            (organization_id, unit_code, plate_number, vehicle_make_model, model_year, ambulance_type, capability_notes, capacity, registration_expiry, inspection_expiry, status)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'offline')\n    ");
    $stmt->execute([
        $ctx['organization_id'], $data['unit_code'], $data['plate_number'], $data['vehicle_make_model'], $data['model_year'],
        $data['ambulance_type'], $data['capability_notes'], $data['capacity'], $data['registration_expiry'], $data['inspection_expiry'],
    ]);
    $id = (int) $pdo->lastInsertId();
    fleetStatusLog($pdo, $id, null, 'offline', (int) $ctx['user_id'], 'Ambulance added to organization fleet.');
    fleetAudit($pdo, $ctx, 'fleet.ambulance_created', 'ambulance', $id, null, $data + ['status'=>'offline']);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Ambulance added to the fleet.','id'=>$id]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ((string) $e->getCode() === '23000') organizationJsonError(409, 'That unit code or plate number is already in use.');
    error_log('Fleet create failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to add the ambulance.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Fleet create failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to add the ambulance.');
}
