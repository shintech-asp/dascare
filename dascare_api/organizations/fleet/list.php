<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'fleet.ambulances.read');

try {
    $stmt = $pdo->prepare("\n        SELECT a.*,\n               rc.overall_status AS readiness_status, rc.checked_at AS readiness_checked_at,\n               COALESCE(mm.open_maintenance, 0) AS open_maintenance,\n               mm.next_maintenance_date\n        FROM ambulances a\n        LEFT JOIN ambulance_readiness_checks rc ON rc.id = (\n            SELECT r2.id FROM ambulance_readiness_checks r2\n            WHERE r2.ambulance_id = a.id ORDER BY r2.checked_at DESC, r2.id DESC LIMIT 1\n        )\n        LEFT JOIN (\n            SELECT ambulance_id,\n                   SUM(status IN ('scheduled','in_progress')) AS open_maintenance,\n                   MIN(CASE WHEN status IN ('scheduled','in_progress') THEN scheduled_for END) AS next_maintenance_date\n            FROM ambulance_maintenance_records\n            GROUP BY ambulance_id\n        ) mm ON mm.ambulance_id = a.id\n        WHERE a.organization_id = ? AND a.deleted_at IS NULL\n        ORDER BY a.unit_code ASC\n    ");
    $stmt->execute([$ctx['organization_id']]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');
    $stats = ['total'=>0,'available'=>0,'maintenance'=>0,'offline'=>0,'ready'=>0,'attention'=>0,'credential_alerts'=>0];
    foreach ($units as &$unit) {
        $unit['id'] = (int) $unit['id'];
        $unit['capacity'] = (int) $unit['capacity'];
        $unit['model_year'] = $unit['model_year'] !== null ? (int) $unit['model_year'] : null;
        $unit['open_maintenance'] = (int) $unit['open_maintenance'];
        $unit['credential_alert'] = (!empty($unit['registration_expiry']) && $unit['registration_expiry'] < $today)
            || (!empty($unit['inspection_expiry']) && $unit['inspection_expiry'] < $today);
        $stats['total']++;
        if (isset($stats[$unit['status']])) $stats[$unit['status']]++;
        if ($unit['readiness_status'] === 'ready') $stats['ready']++;
        if (in_array($unit['readiness_status'], ['needs_attention','out_of_service'], true)) $stats['attention']++;
        if ($unit['credential_alert']) $stats['credential_alerts']++;
    }
    unset($unit);

    echo json_encode(['success'=>true,'units'=>$units,'stats'=>$stats]);
} catch (Throwable $e) {
    error_log('Fleet list failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load fleet information.');
}
