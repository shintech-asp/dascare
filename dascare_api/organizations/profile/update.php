<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'org_settings.org_profile.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) organizationJsonError(422, 'Invalid request body.');

$email = strtolower(trim((string) ($data['email'] ?? '')));
$phone = trim((string) ($data['phone'] ?? ''));
$address = trim(strip_tags((string) ($data['address_line'] ?? '')));
$latitude = isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
$longitude = isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null;
$serviceAreas = $data['service_areas'] ?? [];

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) organizationJsonError(422, 'Enter a valid organization email address.');
if (mb_strlen($email) > 190) organizationJsonError(422, 'Organization email is too long.');
if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) organizationJsonError(422, 'Enter a valid organization contact number.');
if ($address === '' || mb_strlen($address) > 255) organizationJsonError(422, 'Enter the organization base/station address.');
if ($latitude === null || $longitude === null || $latitude < 14.26 || $latitude > 14.40 || $longitude < 120.87 || $longitude > 121.00) {
    organizationJsonError(422, 'Choose a valid base location within Dasmariñas City.');
}
if (!is_array($serviceAreas)) organizationJsonError(422, 'Service areas must be a list.');
$serviceAreas = array_values(array_unique(array_filter(array_map(static function ($area) {
    $area = trim(strip_tags((string) $area));
    return mb_strlen($area) <= 120 ? $area : '';
}, $serviceAreas))));
if (!$serviceAreas) organizationJsonError(422, 'Select at least one service area.');
if (count($serviceAreas) > 100) organizationJsonError(422, 'Too many service areas were selected.');

try {
    $pdo->beginTransaction();

    $oldStmt = $pdo->prepare('SELECT email, phone, address_line, latitude, longitude FROM organizations WHERE id = ? FOR UPDATE');
    $oldStmt->execute([$ctx['organization_id']]);
    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
    if (!$old) {
        $pdo->rollBack();
        organizationJsonError(404, 'Organization not found.');
    }

    $update = $pdo->prepare("
        UPDATE organizations
        SET email = NULLIF(?, ''), phone = NULLIF(?, ''), address_line = ?, latitude = ?, longitude = ?
        WHERE id = ?
    ");
    $update->execute([$email, $phone, $address, $latitude, $longitude, $ctx['organization_id']]);

    $pdo->prepare('DELETE FROM organization_service_areas WHERE organization_id = ?')->execute([$ctx['organization_id']]);
    $insertArea = $pdo->prepare('INSERT INTO organization_service_areas (organization_id, barangay) VALUES (?, ?)');
    foreach ($serviceAreas as $area) $insertArea->execute([$ctx['organization_id'], $area]);

    $audit = $pdo->prepare("
        INSERT INTO audit_logs
            (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
        VALUES (?, ?, 'organization.profile_updated', 'organization', ?, ?, ?, ?, ?)
    ");
    $audit->execute([
        $ctx['user_id'], $ctx['organization_id'], $ctx['organization_id'],
        json_encode($old, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode(['email'=>$email ?: null,'phone'=>$phone ?: null,'address_line'=>$address,'latitude'=>$latitude,'longitude'=>$longitude,'service_areas'=>$serviceAreas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Organization profile updated.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Organization profile update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to update the organization profile.');
}
