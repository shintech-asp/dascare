<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.maintenance_records.create');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$body = fleetBody();
$ambulanceId = (int) ($body['ambulance_id'] ?? 0);
$type = trim((string) ($body['maintenance_type'] ?? 'preventive'));
$status = trim((string) ($body['status'] ?? 'scheduled'));
$scheduledFor = trim((string) ($body['scheduled_for'] ?? '')) ?: null;
$provider = trim(strip_tags((string) ($body['provider'] ?? '')));
$notes = trim(strip_tags((string) ($body['notes'] ?? '')));
$cost = ($body['cost'] ?? '') !== '' ? (float) $body['cost'] : null;
if (!in_array($type, ['preventive','repair','inspection','other'], true)) organizationJsonError(422, 'Choose a valid maintenance type.');
if (!in_array($status, ['scheduled','in_progress'], true)) organizationJsonError(422, 'New maintenance must be scheduled or in progress.');
if ($scheduledFor !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledFor)) organizationJsonError(422, 'Choose a valid maintenance date.');
if (mb_strlen($provider) > 160 || mb_strlen($notes) > 1000) organizationJsonError(422, 'Maintenance information is too long.');
if ($cost !== null && ($cost < 0 || $cost > 9999999999.99)) organizationJsonError(422, 'Enter a valid maintenance cost.');

try {
    $pdo->beginTransaction();
    $unit = fleetOwnedAmbulance($pdo, (int) $ctx['organization_id'], $ambulanceId, true);
    $startedAt = $status === 'in_progress' ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("\n      INSERT INTO ambulance_maintenance_records\n        (ambulance_id, created_by_user_id, maintenance_type, status, scheduled_for, provider, cost, notes, started_at)\n      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)\n    ");
    $stmt->execute([$ambulanceId,$ctx['user_id'],$type,$status,$scheduledFor,$provider ?: null,$cost,$notes ?: null,$startedAt]);
    $id = (int) $pdo->lastInsertId();

    if ($status === 'in_progress' && !in_array($unit['status'], ['reserved','dispatched','on_scene','transporting','returning'], true) && $unit['status'] !== 'maintenance') {
        $pdo->prepare("UPDATE ambulances SET status='maintenance' WHERE id=? AND organization_id=?")->execute([$ambulanceId,$ctx['organization_id']]);
        fleetStatusLog($pdo, $ambulanceId, $unit['status'], 'maintenance', (int) $ctx['user_id'], 'Maintenance started.');
    }
    fleetAudit($pdo, $ctx, 'fleet.maintenance_created', 'ambulance_maintenance', $id, null, ['ambulance_id'=>$ambulanceId,'type'=>$type,'status'=>$status,'scheduled_for'=>$scheduledFor,'provider'=>$provider ?: null,'cost'=>$cost,'notes'=>$notes ?: null]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Maintenance record created.','id'=>$id]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Maintenance create failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to create the maintenance record.');
}
