<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_rbac.php';
header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'rbac.roles.update');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$roleId = (int) ($data['role_id'] ?? 0);
$requestedName = trim(strip_tags((string) ($data['role_name'] ?? '')));
$requestedDescription = trim(strip_tags((string) ($data['description'] ?? '')));
$permissionIds = is_array($data['permission_ids'] ?? null) ? $data['permission_ids'] : [];
if ($roleId <= 0) organizationJsonError(422, 'A valid role is required.');
$permissionIds = validateAssignablePermissionIds($pdo, $ctx, $permissionIds);

try {
    $stmt = $pdo->prepare("SELECT id, role_name, description, is_system FROM org_roles WHERE id = ? AND organization_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$roleId, $ctx['organization_id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$role) organizationJsonError(404, 'Role not found.');

    $isSystem = (int) $role['is_system'] === 1;
    $name = $isSystem ? (string) $role['role_name'] : $requestedName;
    $description = $isSystem ? (string) ($role['description'] ?? '') : $requestedDescription;
    if (!$isSystem && (mb_strlen($name) < 3 || mb_strlen($name) > 100)) organizationJsonError(422, 'Role name must be between 3 and 100 characters.');

    if (!$isSystem) {
        $dup = $pdo->prepare('SELECT 1 FROM org_roles WHERE organization_id = ? AND LOWER(role_name) = LOWER(?) AND id <> ? AND deleted_at IS NULL LIMIT 1');
        $dup->execute([$ctx['organization_id'], $name, $roleId]);
        if ($dup->fetchColumn()) organizationJsonError(409, 'A role with this name already exists.');
    }

    if ($ctx['user_role'] !== 'organization_admin') {
        $oldPerm = $pdo->prepare('SELECT permission_id FROM org_role_permissions WHERE role_id = ?');
        $oldPerm->execute([$roleId]);
        $notOwned = array_diff(array_map('intval', $oldPerm->fetchAll(PDO::FETCH_COLUMN)), organizationPermissionIdsForMember($pdo, $ctx['organization_member_id']));
        if ($notOwned) organizationJsonError(403, 'You cannot edit a role that contains permissions outside your own access.');
    }

    $pdo->beginTransaction();
    $oldP = $pdo->prepare('SELECT permission_id FROM org_role_permissions WHERE role_id = ?');
    $oldP->execute([$roleId]);
    $oldIds = array_map('intval', $oldP->fetchAll(PDO::FETCH_COLUMN));
    if (!$isSystem) {
        $pdo->prepare('UPDATE org_roles SET role_name = ?, description = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$name, $description !== '' ? $description : null, $roleId]);
    } else {
        $pdo->prepare('UPDATE org_roles SET updated_at = NOW() WHERE id = ?')->execute([$roleId]);
    }
    $pdo->prepare('DELETE FROM org_role_permissions WHERE role_id = ?')->execute([$roleId]);
    $link = $pdo->prepare('INSERT INTO org_role_permissions (role_id, permission_id) VALUES (?, ?)');
    foreach ($permissionIds as $pid) $link->execute([$roleId, $pid]);

    logOrganizationRbac($pdo, $ctx, 'org_roles', $roleId, $isSystem ? 'update_permissions' : 'update',
        ['role_name'=>$role['role_name'],'description'=>$role['description'],'permission_ids'=>$oldIds],
        ['role_name'=>$name,'description'=>$description ?: null,'permission_ids'=>$permissionIds]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>$isSystem ? 'Starter role permissions updated.' : 'Role updated.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Organization role update failed: '.$e->getMessage());
    organizationJsonError(500,'Unable to update this role.');
}
