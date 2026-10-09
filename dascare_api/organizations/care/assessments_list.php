<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');

$ctx = requireOrganizationAccess($pdo, 'incidents.patient_assessments.read');
$requestId = (int) ($_GET['request_id'] ?? 0);
if ($requestId <= 0) organizationJsonError(422, 'Choose an incident.');

try {
    $check = $pdo->prepare('SELECT 1 FROM dispatch_assignments WHERE emergency_request_id=? AND organization_id=? LIMIT 1');
    $check->execute([$requestId, (int) $ctx['organization_id']]);
    if (!$check->fetchColumn()) organizationJsonError(404, 'Incident not found for this organization.');

    $stmt = $pdo->prepare("SELECT pa.*, CONCAT(u.first_name,' ',u.last_name) recorded_by
        FROM patient_assessments pa
        JOIN users u ON u.id = pa.recorded_by_user_id
        WHERE pa.emergency_request_id=?
        ORDER BY pa.assessed_at DESC, pa.id DESC");
    $stmt->execute([$requestId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['approximate_age'] = $row['approximate_age'] !== null ? (int) $row['approximate_age'] : null;
        $row['vital_signs'] = $row['vital_signs_json'] ? (json_decode($row['vital_signs_json'], true) ?: []) : [];
        unset($row['vital_signs_json']);
    }
    unset($row);
    echo json_encode(['success'=>true,'assessments'=>$rows]);
} catch (Throwable $e) {
    error_log('Assessment list failed: '.$e->getMessage());
    organizationJsonError(500,'Unable to load patient assessments.');
}
