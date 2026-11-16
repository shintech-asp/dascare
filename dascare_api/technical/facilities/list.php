<?php
// Technical → Hospitals: every receiving facility (active and inactive) with how often it has been used.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
header('Content-Type: application/json');
requireTechnicalSuperAdmin($pdo);
try {
    $rows = $pdo->query("SELECT f.id, f.name, f.facility_type, f.address_text, f.phone, f.latitude, f.longitude, f.capabilities, f.status, f.updated_at,
                                (SELECT COUNT(*) FROM patient_handoffs h WHERE h.medical_facility_id = f.id) AS handoff_count
                         FROM medical_facilities f ORDER BY (f.status = 'active') DESC, f.name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['id'] = (int) $r['id']; $r['latitude'] = (float) $r['latitude']; $r['longitude'] = (float) $r['longitude']; $r['handoff_count'] = (int) $r['handoff_count']; }
    unset($r);
    echo json_encode(['success' => true, 'facilities' => $rows, 'bounds' => ['south' => 14.20, 'north' => 14.45, 'west' => 120.80, 'east' => 121.10]]);
} catch (Throwable $e) {
    error_log('Technical facilities list failed: ' . $e->getMessage());
    technicalJsonError(500, 'Unable to load hospitals.');
}
