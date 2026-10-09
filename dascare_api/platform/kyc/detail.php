<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'citizens.kyc_verifications.read');

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    platformJsonError(422, 'A valid citizen ID is required.');
}

try {
    $stmt = $pdo->prepare("\n        SELECT\n            k.user_id, k.status, k.id_type, k.phone_number, k.birthdate,\n            k.address, k.barangay, k.city, k.province, k.zip_code,\n            k.latitude, k.longitude, k.submitted_at, k.verification_note,\n            k.rejection_count, k.last_rejected_at, k.verified_at, k.verified_by,\n            k.created_at, k.updated_at,\n            u.first_name, u.last_name, u.email, u.phone AS account_phone,\n            u.account_status, u.created_at AS account_created_at,\n            reviewer.first_name AS reviewer_first_name,\n            reviewer.last_name AS reviewer_last_name\n        FROM kyc_verifications k\n        INNER JOIN users u ON u.id = k.user_id AND u.deleted_at IS NULL\n        INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role = 'citizen'\n        LEFT JOIN users reviewer ON reviewer.id = k.verified_by\n        WHERE k.user_id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$userId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        platformJsonError(404, 'Verification submission not found.');
    }

    $item['user_id'] = (int) $item['user_id'];
    $item['status'] = (int) $item['status'];
    $item['rejection_count'] = (int) $item['rejection_count'];
    $item['latitude'] = $item['latitude'] !== null ? (float) $item['latitude'] : null;
    $item['longitude'] = $item['longitude'] !== null ? (float) $item['longitude'] : null;
    $item['applicant_name'] = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
    $item['reviewer_name'] = trim(($item['reviewer_first_name'] ?? '') . ' ' . ($item['reviewer_last_name'] ?? '')) ?: null;
    $item['id_image_url'] = '/platform/kyc/media.php?user_id=' . $userId;
    unset($item['first_name'], $item['last_name'], $item['reviewer_first_name'], $item['reviewer_last_name']);

    echo json_encode(['success' => true, 'item' => $item]);
} catch (Throwable $e) {
    error_log('Platform KYC detail failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load this verification submission.');
}
