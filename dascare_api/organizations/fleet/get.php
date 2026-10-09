<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/fleet_helpers.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulances.read');
$ambulanceId = (int) ($_GET['id'] ?? 0);

try {
    $unit = fleetOwnedAmbulance($pdo, (int) $ctx['organization_id'], $ambulanceId);
    $unit['id'] = (int) $unit['id'];
    $unit['capacity'] = (int) $unit['capacity'];
    $unit['model_year'] = $unit['model_year'] !== null ? (int) $unit['model_year'] : null;
    $unit['credential_state'] = fleetCredentialState($unit);

    $statusStmt = $pdo->prepare("\n        SELECT l.*, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS changed_by_name\n        FROM ambulance_status_logs l\n        LEFT JOIN users u ON u.id = l.changed_by_user_id\n        WHERE l.ambulance_id = ? ORDER BY l.created_at DESC, l.id DESC LIMIT 30\n    ");
    $statusStmt->execute([$ambulanceId]);

    $readinessStmt = $pdo->prepare("\n        SELECT r.*, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS checked_by_name\n        FROM ambulance_readiness_checks r\n        LEFT JOIN users u ON u.id = r.checked_by_user_id\n        WHERE r.ambulance_id = ? ORDER BY r.checked_at DESC, r.id DESC LIMIT 20\n    ");
    $readinessStmt->execute([$ambulanceId]);

    $maintenanceStmt = $pdo->prepare("\n        SELECT m.*, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS created_by_name\n        FROM ambulance_maintenance_records m\n        LEFT JOIN users u ON u.id = m.created_by_user_id\n        WHERE m.ambulance_id = ? ORDER BY COALESCE(m.scheduled_for, DATE(m.created_at)) DESC, m.id DESC LIMIT 30\n    ");
    $maintenanceStmt->execute([$ambulanceId]);

    echo json_encode([
        'success'=>true,
        'unit'=>$unit,
        'status_history'=>$statusStmt->fetchAll(PDO::FETCH_ASSOC),
        'readiness_checks'=>$readinessStmt->fetchAll(PDO::FETCH_ASSOC),
        'maintenance_records'=>$maintenanceStmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    if ((int) http_response_code() >= 400) throw $e;
    error_log('Fleet detail failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load ambulance details.');
}
