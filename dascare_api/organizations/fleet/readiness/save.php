<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulance_readiness.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$body = fleetBody();
$ambulanceId = (int) ($body['ambulance_id'] ?? 0);
$fuel = trim((string) ($body['fuel_level'] ?? 'adequate'));
if (!in_array($fuel, ['low','adequate','full'], true)) organizationJsonError(422, 'Choose a valid fuel level.');
$fields = ['oxygen_ready','medical_supplies_ready','lights_siren_ready','communications_ready','stretcher_ready','cleanliness_ready'];
$checks = [];
foreach ($fields as $field) $checks[$field] = !empty($body[$field]) ? 1 : 0;
$criticalIssue = !empty($body['critical_issue']) ? 1 : 0;
$notes = trim(strip_tags((string) ($body['notes'] ?? '')));
if (mb_strlen($notes) > 500) organizationJsonError(422, 'Readiness notes are too long.');
$allReady = $fuel !== 'low' && !in_array(0, $checks, true);
$overall = $criticalIssue ? 'out_of_service' : ($allReady ? 'ready' : 'needs_attention');

try {
    $pdo->beginTransaction();
    $unit = fleetOwnedAmbulance($pdo, (int) $ctx['organization_id'], $ambulanceId, true);
    $stmt = $pdo->prepare("\n        INSERT INTO ambulance_readiness_checks\n          (ambulance_id, checked_by_user_id, overall_status, fuel_level, oxygen_ready, medical_supplies_ready, lights_siren_ready, communications_ready, stretcher_ready, cleanliness_ready, critical_issue, notes)\n        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    $stmt->execute([
        $ambulanceId,$ctx['user_id'],$overall,$fuel,$checks['oxygen_ready'],$checks['medical_supplies_ready'],$checks['lights_siren_ready'],
        $checks['communications_ready'],$checks['stretcher_ready'],$checks['cleanliness_ready'],$criticalIssue,$notes ?: null,
    ]);
    $checkId = (int) $pdo->lastInsertId();

    if ($overall === 'out_of_service' && !in_array($unit['status'], ['reserved','dispatched','on_scene','transporting','returning'], true) && $unit['status'] !== 'maintenance') {
        $pdo->prepare("UPDATE ambulances SET status='maintenance' WHERE id=? AND organization_id=?")->execute([$ambulanceId,$ctx['organization_id']]);
        fleetStatusLog($pdo, $ambulanceId, $unit['status'], 'maintenance', (int) $ctx['user_id'], 'Critical issue found during readiness check.');
    }

    fleetAudit($pdo, $ctx, 'fleet.readiness_checked', 'ambulance_readiness_check', $checkId, null, [
        'ambulance_id'=>$ambulanceId,'overall_status'=>$overall,'fuel_level'=>$fuel,'checks'=>$checks,'critical_issue'=>(bool)$criticalIssue,'notes'=>$notes ?: null,
    ]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>$overall === 'ready' ? 'Readiness check passed.' : 'Readiness check saved with attention required.','overall_status'=>$overall]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Readiness check failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to save the readiness check.');
}
