<?php
// Public: organization types, whether each can still be applied for (one LGU
// only) and the documents each must upload — for /apply/organization.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_requirements.php';
header('Content-Type: application/json');
try {
    $lguTaken = organizationLguTaken($pdo);
    $types = [];
    foreach (ORGANIZATION_TYPES as $value => $label) {
        $available = !($value === LGU_ORGANIZATION_TYPE && $lguTaken);
        $types[] = ['value' => $value, 'label' => $label, 'available' => $available, 'unavailable_reason' => $available ? null : 'Dasmariñas already has its LGU account.', 'documents' => ORGANIZATION_DOCUMENT_REQUIREMENTS[$value]];
    }
    echo json_encode(['success' => true, 'types' => $types, 'lgu_taken' => $lguTaken]);
} catch (Throwable $e) {
    error_log('Organization requirements failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load organization requirements.']);
}
