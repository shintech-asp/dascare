<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'organizations.organizations.read');

$organizationId = (int) ($_GET['id'] ?? 0);
if ($organizationId <= 0) {
    platformJsonError(422, 'A valid organization application ID is required.');
}

try {
    $stmt = $pdo->prepare("\n        SELECT\n            o.id, o.application_reference, o.name, o.organization_type,\n            o.registration_number, o.accreditation_body, o.email, o.phone,\n            o.address_line, o.latitude, o.longitude, o.status, o.application_status,\n            o.verification_note, o.rejection_count, o.last_rejected_at,\n            o.verified_at, o.verified_by, o.application_submitted_at,\n            o.application_reviewed_at, o.application_reviewed_by, o.created_at, o.updated_at,\n            admin.id AS admin_user_id, admin.first_name AS admin_first_name, admin.last_name AS admin_last_name,\n            admin.email AS admin_email, admin.phone AS admin_phone, admin.account_status AS admin_account_status,\n            admin.email_verified_at AS admin_email_verified_at,\n            reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name\n        FROM organizations o\n        LEFT JOIN users admin ON admin.id = o.primary_admin_user_id\n        LEFT JOIN users reviewer ON reviewer.id = o.application_reviewed_by\n        WHERE o.id = ?\n          AND o.deleted_at IS NULL\n          AND o.application_reference IS NOT NULL\n        LIMIT 1\n    ");
    $stmt->execute([$organizationId]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$organization) {
        platformJsonError(404, 'Organization application not found.');
    }

    $areaStmt = $pdo->prepare('SELECT id, barangay FROM organization_service_areas WHERE organization_id = ? ORDER BY barangay');
    $areaStmt->execute([$organizationId]);
    $serviceAreas = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

    $docStmt = $pdo->prepare("\n        SELECT id, doc_type, original_name, mime_type, file_size, status, rejection_reason, reviewed_at, created_at\n        FROM organization_documents\n        WHERE organization_id = ?\n        ORDER BY created_at, id\n    ");
    $docStmt->execute([$organizationId]);
    $documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

    $historyStmt = $pdo->prepare("\n        SELECT a.id, a.action, a.old_values, a.new_values, a.created_at,\n               u.first_name, u.last_name\n        FROM audit_logs a\n        LEFT JOIN users u ON u.id = a.user_id\n        WHERE a.entity_type = 'organization'\n          AND a.entity_id = ?\n          AND a.action LIKE 'organization.application_%'\n        ORDER BY a.created_at DESC, a.id DESC\n        LIMIT 20\n    ");
    $historyStmt->execute([$organizationId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    $organization['id'] = (int) $organization['id'];
    $organization['admin_user_id'] = $organization['admin_user_id'] !== null ? (int) $organization['admin_user_id'] : null;
    $organization['rejection_count'] = (int) $organization['rejection_count'];
    $organization['latitude'] = $organization['latitude'] !== null ? (float) $organization['latitude'] : null;
    $organization['longitude'] = $organization['longitude'] !== null ? (float) $organization['longitude'] : null;
    $organization['admin_name'] = trim(($organization['admin_first_name'] ?? '') . ' ' . ($organization['admin_last_name'] ?? '')) ?: null;
    $organization['reviewer_name'] = trim(($organization['reviewer_first_name'] ?? '') . ' ' . ($organization['reviewer_last_name'] ?? '')) ?: null;
    unset($organization['admin_first_name'], $organization['admin_last_name'], $organization['reviewer_first_name'], $organization['reviewer_last_name']);

    foreach ($serviceAreas as &$area) {
        $area['id'] = (int) $area['id'];
    }
    unset($area);

    foreach ($documents as &$document) {
        $document['id'] = (int) $document['id'];
        $document['file_size'] = $document['file_size'] !== null ? (int) $document['file_size'] : null;
        $document['media_url'] = '/platform/organizations/media.php?document_id=' . $document['id'];
    }
    unset($document);

    foreach ($history as &$event) {
        $event['id'] = (int) $event['id'];
        $event['actor_name'] = trim(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? '')) ?: 'System';
        $event['old_values'] = $event['old_values'] ? json_decode($event['old_values'], true) : null;
        $event['new_values'] = $event['new_values'] ? json_decode($event['new_values'], true) : null;
        unset($event['first_name'], $event['last_name']);
    }
    unset($event);

    echo json_encode([
        'success' => true,
        'organization' => $organization,
        'service_areas' => $serviceAreas,
        'documents' => $documents,
        'history' => $history,
    ]);
} catch (Throwable $e) {
    error_log('Platform organization detail failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load this organization application.');
}
