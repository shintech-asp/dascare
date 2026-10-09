<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/routing.php';
header('Content-Type: application/json');

$ctx = requireOrganizationAccess($pdo, 'dispatch.tracking.read');

try {
    $stmt = $pdo->prepare("SELECT da.id, da.assignment_status, da.emergency_request_id,
            er.reference_number, er.severity, er.address_text, er.barangay, er.latitude incident_latitude, er.longitude incident_longitude,
            a.id ambulance_id, a.unit_code, a.plate_number, a.ambulance_type, a.status ambulance_status,
            a.last_latitude, a.last_longitude, a.last_accuracy_m, a.last_location_at
        FROM dispatch_assignments da
        JOIN emergency_requests er ON er.id = da.emergency_request_id
        JOIN ambulances a ON a.id = da.ambulance_id
        WHERE da.organization_id = ?
          AND da.assignment_status IN ('assigned','acknowledged','responding','on_scene','transporting')
        ORDER BY da.assigned_at DESC");
    $stmt->execute([(int) $ctx['organization_id']]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $assignmentIds = array_map(fn($m) => (int) $m['id'], $missions);
    $assignedToCurrent = [];
    if ($assignmentIds) {
        $ph = implode(',', array_fill(0, count($assignmentIds), '?'));
        $params = array_merge($assignmentIds, [(int) $ctx['organization_member_id']]);
        $crew = $pdo->prepare("SELECT dispatch_assignment_id FROM crew_assignments WHERE dispatch_assignment_id IN ($ph) AND organization_member_id = ?");
        $crew->execute($params);
        foreach ($crew->fetchAll(PDO::FETCH_COLUMN) as $id) $assignedToCurrent[(int) $id] = true;
    }

    $setting = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key='tracking.default_interval_seconds' LIMIT 1")->fetchColumn();
    $intervals = json_decode((string) $setting, true);
    $activeInterval = max(5, (int) ($intervals['active'] ?? 15));
    $staleAfter = max(45, $activeInterval * 3);

    foreach ($missions as &$m) {
        $m['id'] = (int) $m['id'];
        $m['emergency_request_id'] = (int) $m['emergency_request_id'];
        $m['ambulance_id'] = (int) $m['ambulance_id'];
        foreach (['incident_latitude','incident_longitude','last_latitude','last_longitude','last_accuracy_m'] as $key) {
            $m[$key] = $m[$key] !== null ? (float) $m[$key] : null;
        }
        $age = $m['last_location_at'] ? max(0, time() - strtotime($m['last_location_at'])) : null;
        $m['location_age_seconds'] = $age;
        $m['location_stale'] = $age === null || $age > $staleAfter;
        $m['assigned_to_current_user'] = !empty($assignedToCurrent[$m['id']]);
        $m['route'] = routingForAssignment($pdo, $m['id']);
    }
    unset($m);

    $canShare = ($ctx['user_role'] === 'organization_admin') || organizationHasPermission($pdo, $ctx, 'dispatch.tracking.update');
    echo json_encode([
        'success' => true,
        'missions' => $missions,
        'can_share_location' => $canShare,
        'tracking_interval_seconds' => $activeInterval,
        'stale_after_seconds' => $staleAfter,
    ]);
} catch (Throwable $e) {
    error_log('Tracking list failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load live tracking.');
}
