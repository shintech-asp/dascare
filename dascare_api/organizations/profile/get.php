<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'org_settings.org_profile.read');
$organizationId = $ctx['organization_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, application_reference, name, organization_type, registration_number,
               accreditation_body, email, phone, address_line, latitude, longitude,
               status, application_status, verified_at, created_at, updated_at
        FROM organizations
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$organizationId]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$organization) organizationJsonError(404, 'Organization not found.');

    $areas = $pdo->prepare('SELECT barangay FROM organization_service_areas WHERE organization_id = ? ORDER BY barangay');
    $areas->execute([$organizationId]);

    $docs = $pdo->prepare("
        SELECT id, doc_type, original_name, mime_type, file_size, status, rejection_reason, reviewed_at, created_at
        FROM organization_documents
        WHERE organization_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $docs->execute([$organizationId]);
    $documents = $docs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($documents as &$document) {
        $document['id'] = (int) $document['id'];
        $document['file_size'] = $document['file_size'] !== null ? (int) $document['file_size'] : null;
    }
    unset($document);

    $organization['id'] = (int) $organization['id'];
    $organization['latitude'] = $organization['latitude'] !== null ? (float) $organization['latitude'] : null;
    $organization['longitude'] = $organization['longitude'] !== null ? (float) $organization['longitude'] : null;

    echo json_encode([
        'success' => true,
        'organization' => $organization,
        'service_areas' => $areas->fetchAll(PDO::FETCH_COLUMN),
        'documents' => $documents,
    ]);
} catch (Throwable $e) {
    error_log('Organization profile get failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to load the organization profile.');
}
