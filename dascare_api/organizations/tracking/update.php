<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/care_helpers.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$ctx = requireOrganizationAccess($pdo, 'dispatch.tracking.update');
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$assignmentId = (int) ($body['assignment_id'] ?? 0);
$lat = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$accuracy = filter_var($body['accuracy_m'] ?? null, FILTER_VALIDATE_FLOAT);
$speed = filter_var($body['speed_kph'] ?? null, FILTER_VALIDATE_FLOAT);
$heading = filter_var($body['heading_degrees'] ?? null, FILTER_VALIDATE_INT);

if ($assignmentId <= 0 || $lat === false || $lng === false) organizationJsonError(422, 'Valid mission coordinates are required.');
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) organizationJsonError(422, 'Invalid coordinates.');
if ($accuracy !== false && ($accuracy < 0 || $accuracy > 5000)) $accuracy = null;
if ($speed !== false && ($speed < 0 || $speed > 250)) $speed = null;
if ($heading !== false && ($heading < 0 || $heading > 359)) $heading = null;

try {
    $pdo->beginTransaction();
    $assignment = careFindAssignment($pdo, $ctx, $assignmentId, true);
    if (!$assignment) throw new RuntimeException('Mission not found.');
    if (!in_array($assignment['assignment_status'], ['assigned','acknowledged','responding','on_scene','transporting'], true)) {
        throw new RuntimeException('Location sharing is only available during an active mission.');
    }
    if (!careUserAssignedToMission($pdo, $ctx, $assignmentId) || ($ctx['user_role'] ?? '') === 'organization_admin') {
        throw new RuntimeException('Only assigned field responders can share this ambulance location.');
    }

    $insert = $pdo->prepare("INSERT INTO ambulance_locations
        (ambulance_id, emergency_request_id, latitude, longitude, accuracy_m, speed_kph, heading_degrees, recorded_by_user_id, recorded_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $insert->execute([
        (int) $assignment['ambulance_id'], (int) $assignment['emergency_request_id'], $lat, $lng,
        $accuracy === false ? null : $accuracy,
        $speed === false ? null : $speed,
        $heading === false ? null : $heading,
        (int) $ctx['user_id'],
    ]);
    $pdo->prepare("UPDATE ambulances SET last_latitude=?, last_longitude=?, last_accuracy_m=?, last_location_at=NOW() WHERE id=?")
        ->execute([$lat, $lng, $accuracy === false ? null : $accuracy, (int) $assignment['ambulance_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'recorded_at' => date('Y-m-d H:i:s')]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    organizationJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Tracking update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update ambulance location.');
}
