<?php
// Sets/changes an employee's government ID (Drivers: driver's license; other roles: any accepted ID).
// POST {organization_member_id, id_type, id_number}. Needs hr.members.update.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
require_once __DIR__ . '/../../reusables/input_validation.php';
header('Content-Type: application/json');

$ctx = requireOrganizationAccess($pdo, 'hr.members.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) organizationJsonError(422, 'Invalid request body.');
$memberId = (int) ($data['organization_member_id'] ?? 0);
$idType = trim((string) ($data['id_type'] ?? ''));
$number = trim((string) ($data['id_number'] ?? ''));
if ($memberId <= 0) organizationJsonError(422, 'A valid organization member is required.');

try {
    $stmt = $pdo->prepare("SELECT om.id, EXISTS (SELECT 1 FROM org_member_roles omr INNER JOIN org_roles r ON r.id = omr.role_id AND r.deleted_at IS NULL WHERE omr.organization_member_id = om.id AND LOWER(TRIM(r.role_name)) = 'driver') AS is_driver
                           FROM organization_members om WHERE om.id = ? AND om.organization_id = ? AND om.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$memberId, (int) $ctx['organization_id']]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$member) organizationJsonError(404, 'Member not found in this organization.');

    if ((int) $member['is_driver'] === 1) $idType = 'drivers_license';
    if (!isset(EMPLOYEE_ID_TYPES[$idType])) organizationJsonError(422, 'Choose which government ID this is.');
    $normalized = inputNormalizeIdNumber($idType, $number);
    if ($normalized === null) organizationJsonError(422, $idType === 'drivers_license' ? 'Enter a valid license number like N01-12-345678.' : 'Enter a valid ' . EMPLOYEE_ID_TYPES[$idType] . ' number (letters/digits, at least 4 digits).');
    if (inputIdInUse($pdo, $idType, $normalized, $memberId)) organizationJsonError(409, 'That ID number is already registered to another employee.');

    $pdo->beginTransaction();
    $old = $pdo->prepare('SELECT license_number, id_type FROM personnel_profiles WHERE organization_member_id = ? FOR UPDATE');
    $old->execute([$memberId]);
    $before = $old->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($before) {
        $pdo->prepare('UPDATE personnel_profiles SET license_number = ?, id_type = ? WHERE organization_member_id = ?')->execute([$normalized, $idType, $memberId]);
    } else {
        $pdo->prepare("INSERT INTO personnel_profiles (organization_member_id, license_number, id_type, availability_status) VALUES (?, ?, ?, 'off_duty')")->execute([$memberId, $normalized, $idType]);
    }
    $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, 'organization.member_id_updated', 'organization_member', ?, ?, ?, ?, ?)")
        ->execute([(int) $ctx['user_id'], (int) $ctx['organization_id'], $memberId, json_encode($before), json_encode(['id_type' => $idType, 'id_number' => $normalized]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'ID saved.', 'id_type' => $idType, 'id_number' => $normalized]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Member ID update failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to save the ID.');
}
