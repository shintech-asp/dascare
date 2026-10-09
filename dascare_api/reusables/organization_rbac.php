<?php
require_once __DIR__ . '/organization_guard.php';

function organizationPermissionIdsForMember(PDO $pdo, int $memberId): array
{
    $stmt = $pdo->prepare("\n        SELECT DISTINCT p.id\n        FROM org_member_roles omr\n        INNER JOIN org_role_permissions orp ON orp.role_id = omr.role_id\n        INNER JOIN rbac_permissions p ON p.id = orp.permission_id\n        WHERE omr.organization_member_id = ?\n    ");
    $stmt->execute([$memberId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function organizationHasPermission(PDO $pdo, array $ctx, string $permission): bool
{
    if (($ctx['user_role'] ?? '') === 'organization_admin') return true;
    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM org_member_roles omr\n        INNER JOIN org_role_permissions orp ON orp.role_id = omr.role_id\n        INNER JOIN rbac_permissions p ON p.id = orp.permission_id\n        INNER JOIN rbac_modules m ON m.id = p.module_id\n        WHERE omr.organization_member_id = ?\n          AND CONCAT(m.module_key, '.', p.resource, '.', p.action) = ?\n        LIMIT 1\n    ");
    $stmt->execute([(int) $ctx['organization_member_id'], $permission]);
    return (bool) $stmt->fetchColumn();
}

function requireOrganizationAnyPermission(PDO $pdo, array $permissions): array
{
    $ctx = requireOrganizationAccess($pdo, null);
    foreach ($permissions as $permission) {
        if (organizationHasPermission($pdo, $ctx, $permission)) return $ctx;
    }
    organizationJsonError(403, 'You do not have permission to perform this action.');
}

function validateAssignablePermissionIds(PDO $pdo, array $ctx, array $permissionIds): array
{
    $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds), fn($id) => $id > 0)));
    if (!$permissionIds) return [];

    $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
    $stmt = $pdo->prepare("SELECT id FROM rbac_permissions WHERE id IN ($placeholders)");
    $stmt->execute($permissionIds);
    $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($validIds); $requested = $permissionIds; sort($requested);
    if ($validIds !== $requested) organizationJsonError(422, 'One or more permissions are invalid.');

    if (($ctx['user_role'] ?? '') !== 'organization_admin') {
        $own = organizationPermissionIdsForMember($pdo, (int) $ctx['organization_member_id']);
        $missing = array_diff($permissionIds, $own);
        if ($missing) organizationJsonError(403, 'You cannot grant permissions that you do not possess.');
    }
    return $permissionIds;
}

function logOrganizationRbac(PDO $pdo, array $ctx, string $table, int $recordId, string $action, ?array $oldValues, ?array $newValues): void
{
    $stmt = $pdo->prepare("\n        INSERT INTO rbac_audit_log (organization_id, table_name, record_id, action, old_values, new_values, changed_by)\n        VALUES (?, ?, ?, ?, ?, ?, ?)\n    ");
    $stmt->execute([
        (int) $ctx['organization_id'], $table, $recordId, $action,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        (int) $ctx['user_id'],
    ]);
}
