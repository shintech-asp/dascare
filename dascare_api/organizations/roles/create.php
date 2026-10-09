<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'rbac.roles.create');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim(strip_tags((string) ($data['role_name'] ?? '')));
$description = trim(strip_tags((string) ($data['description'] ?? '')));
$permissionIds = is_array($data['permission_ids'] ?? null) ? $data['permission_ids'] : [];
if (mb_strlen($name) < 3 || mb_strlen($name) > 100) organizationJsonError(422, 'Role name must be between 3 and 100 characters.');
if (mb_strlen($description) > 1000) organizationJsonError(422, 'Role description must be 1,000 characters or fewer.');
$permissionIds = validateAssignablePermissionIds($pdo, $ctx, $permissionIds);
try {
    $duplicate = $pdo->prepare('SELECT 1 FROM org_roles WHERE organization_id = ? AND LOWER(role_name) = LOWER(?) AND deleted_at IS NULL LIMIT 1');
    $duplicate->execute([$ctx['organization_id'], $name]);
    if ($duplicate->fetchColumn()) organizationJsonError(409, 'A role with this name already exists.');
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO org_roles (organization_id, role_name, description, is_system, created_by) VALUES (?, ?, ?, 0, ?)');
    $stmt->execute([$ctx['organization_id'], $name, $description !== '' ? $description : null, $ctx['user_id']]);
    $roleId = (int) $pdo->lastInsertId();
    $link = $pdo->prepare('INSERT IGNORE INTO org_role_permissions (role_id, permission_id) VALUES (?, ?)');
    foreach ($permissionIds as $permissionId) $link->execute([$roleId, $permissionId]);
    logOrganizationRbac($pdo, $ctx, 'org_roles', $roleId, 'create', null, ['role_name'=>$name,'description'=>$description ?: null,'permission_ids'=>$permissionIds]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Custom role created.','role_id'=>$roleId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Organization role create failed: '.$e->getMessage());
    organizationJsonError(500, 'Unable to create this role.');
}
