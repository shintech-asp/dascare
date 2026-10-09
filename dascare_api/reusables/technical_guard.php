<?php

function technicalJsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function requireTechnicalSuperAdmin(PDO $pdo): int
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) technicalJsonError(401, 'Authentication required.');

    $stmt = $pdo->prepare("\n        SELECT ur.role, u.account_status\n        FROM users u\n        INNER JOIN user_roles ur ON ur.user_id = u.id\n        WHERE u.id = ? AND u.deleted_at IS NULL\n        LIMIT 1\n    ");
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || $account['account_status'] !== 'active') {
        technicalJsonError(403, 'This account is not active.');
    }
    if ($account['role'] !== 'technical_super_admin') {
        technicalJsonError(403, 'Technical Super Admin access is required.');
    }

    return $userId;
}
