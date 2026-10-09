<?php
require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../db/db.php';
require_once __DIR__ . '/../../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.maintenance_records.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$body = fleetBody();
$id = (int) ($body['id'] ?? 0);
$status = trim((string) ($body['status'] ?? ''));
$notes = trim(strip_tags((string) ($body['notes'] ?? '')));
if (!in_array($status, ['scheduled','in_progress','completed','cancelled'], true)) organizationJsonError(422, 'Choose a valid maintenance status.');
if (mb_strlen($notes) > 1000) organizationJsonError(422, 'Maintenance notes are too long.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("\n      SELECT m.*, a.organization_id, a.status AS ambulance_status\n      FROM ambulance_maintenance_records m\n      INNER JOIN ambulances a ON a.id=m.ambulance_id AND a.deleted_at IS NULL\n      WHERE m.id=? AND a.organization_id=? LIMIT 1 FOR UPDATE\n    ");
    $stmt->execute([$id,$ctx['organization_id']]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) { $pdo->rollBack(); organizationJsonError(404, 'Maintenance record not found.'); }

    $startedAt = $record['started_at']; $completedAt = $record['completed_at'];
    if ($status === 'in_progress' && !$startedAt) $startedAt = date('Y-m-d H:i:s');
    if ($status === 'completed') $completedAt = date('Y-m-d H:i:s');
    if ($status !== 'completed') $completedAt = null;
    $pdo->prepare('UPDATE ambulance_maintenance_records SET status=?, notes=?, started_at=?, completed_at=? WHERE id=?')->execute([$status,$notes ?: $record['notes'],$startedAt,$completedAt,$id]);

    if ($status === 'in_progress' && !in_array($record['ambulance_status'], ['reserved','dispatched','on_scene','transporting','returning'], true) && $record['ambulance_status'] !== 'maintenance') {
        $pdo->prepare("UPDATE ambulances SET status='maintenance' WHERE id=? AND organization_id=?")->execute([$record['ambulance_id'],$ctx['organization_id']]);
        fleetStatusLog($pdo, (int)$record['ambulance_id'], $record['ambulance_status'], 'maintenance', (int)$ctx['user_id'], 'Maintenance started.');
    }
    fleetAudit($pdo, $ctx, 'fleet.maintenance_updated', 'ambulance_maintenance', $id, ['status'=>$record['status'],'notes'=>$record['notes']], ['status'=>$status,'notes'=>$notes ?: $record['notes']]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Maintenance record updated.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Maintenance update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update the maintenance record.');
}
