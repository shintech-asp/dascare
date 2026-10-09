<?php
/**
 * Platform Executive authorization helper.
 *
 * Requires cors.php and db.php to have been loaded first so the PHP session
 * and PDO connection are available. Technical Super Admin intentionally does
 * not pass this guard: KYC review is an LGU/platform operational function.
 */

function platformJsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function requirePlatformExecutive(PDO $pdo, ?string $permission = null): int
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $level = (string) ($_SESSION['user_level'] ?? '');

    if ($userId <= 0) {
        platformJsonError(401, 'Authentication required.');
    }

    // Re-resolve the role from the database instead of trusting only the
    // cached session level. This also prevents a stale session from retaining
    // review authority after an account/role change.
    $roleStmt = $pdo->prepare("\n        SELECT ur.role, u.account_status\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        WHERE u.id = ? AND u.deleted_at IS NULL\n        LIMIT 1\n    ");
    $roleStmt->execute([$userId]);
    $account = $roleStmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || $account['account_status'] !== 'active') {
        platformJsonError(403, 'This account is not active.');
    }

    if ($account['role'] !== 'platform_executive_admin' || $level !== 'platform_executive_admin') {
        platformJsonError(403, 'Platform Executive access is required.');
    }

    if ($permission !== null) {
        $permissionStmt = $pdo->prepare("\n            SELECT 1\n            FROM platform_account_roles par\n            INNER JOIN platform_role_permissions prp ON prp.role_id = par.role_id\n            INNER JOIN platform_permissions p ON p.id = prp.permission_id\n            INNER JOIN platform_modules m ON m.id = p.module_id\n            INNER JOIN platform_roles r ON r.id = par.role_id\n            WHERE par.user_id = ?\n              AND r.deleted_at IS NULL\n              AND CONCAT(m.module_key, '.', p.resource, '.', p.action) = ?\n            LIMIT 1\n        ");
        $permissionStmt->execute([$userId, $permission]);

        if (!$permissionStmt->fetchColumn()) {
            platformJsonError(403, 'You do not have permission to perform this action.');
        }
    }

    return $userId;
}
