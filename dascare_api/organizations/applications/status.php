<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$roleStmt = $pdo->prepare('SELECT role FROM user_roles WHERE user_id = ? LIMIT 1');
$roleStmt->execute([$userId]);
$role = $roleStmt->fetchColumn();
if (!in_array($role, ['organization_admin', 'organization_operational_user'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Organization access required.']);
    exit;
}

$stmt = $pdo->prepare("SELECT
        o.id, o.application_reference, o.name, o.organization_type,
        o.registration_number, o.accreditation_body, o.email, o.phone,
        o.address_line, o.latitude, o.longitude, o.status, o.application_status,
        o.verification_note, o.rejection_count, o.last_rejected_at,
        o.verified_at, o.application_submitted_at, o.application_reviewed_at,
        CONCAT(COALESCE(v.first_name,''), ' ', COALESCE(v.last_name,'')) AS reviewer_name
    FROM organization_members om
    INNER JOIN organizations o ON o.id = om.organization_id
    LEFT JOIN users v ON v.id = COALESCE(o.application_reviewed_by, o.verified_by)
    WHERE om.user_id = ? AND om.deleted_at IS NULL AND o.deleted_at IS NULL
    LIMIT 1");
$stmt->execute([$userId]);
$organization = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$organization) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No organization application is linked to this account.']);
    exit;
}

$areaStmt = $pdo->prepare('SELECT barangay FROM organization_service_areas WHERE organization_id = ? ORDER BY barangay');
$areaStmt->execute([$organization['id']]);
$serviceAreas = $areaStmt->fetchAll(PDO::FETCH_COLUMN);

$docStmt = $pdo->prepare("SELECT id, doc_type, original_name, mime_type, file_size, status, rejection_reason, reviewed_at
    FROM organization_documents WHERE organization_id = ? ORDER BY created_at, id");
$docStmt->execute([$organization['id']]);
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

$organization['id'] = (int) $organization['id'];
$organization['rejection_count'] = (int) $organization['rejection_count'];
$organization['latitude'] = $organization['latitude'] !== null ? (float) $organization['latitude'] : null;
$organization['longitude'] = $organization['longitude'] !== null ? (float) $organization['longitude'] : null;
foreach ($documents as &$doc) {
    $doc['id'] = (int) $doc['id'];
    $doc['file_size'] = $doc['file_size'] !== null ? (int) $doc['file_size'] : null;
}
unset($doc);

echo json_encode([
    'success' => true,
    'organization' => $organization,
    'service_areas' => $serviceAreas,
    'documents' => $documents,
]);
