<?php
require_once __DIR__ . '/organization_rbac.php';

function careFindAssignment(PDO $pdo, array $ctx, int $assignmentId, bool $forUpdate = false): ?array
{
    $sql = "SELECT da.*, er.reference_number, er.status AS request_status, er.requester_user_id,
                   er.requester_name, er.requester_phone, er.address_text, er.barangay,
                   a.unit_code, a.plate_number, a.ambulance_type,
                   o.name AS organization_name
            FROM dispatch_assignments da
            JOIN emergency_requests er ON er.id = da.emergency_request_id
            JOIN ambulances a ON a.id = da.ambulance_id
            JOIN organizations o ON o.id = da.organization_id
            WHERE da.id = ? AND da.organization_id = ?
            LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$assignmentId, (int) $ctx['organization_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function careUserAssignedToMission(PDO $pdo, array $ctx, int $assignmentId): bool
{
    if (($ctx['user_role'] ?? '') === 'organization_admin') return true;
    $stmt = $pdo->prepare('SELECT 1 FROM crew_assignments WHERE dispatch_assignment_id = ? AND organization_member_id = ? LIMIT 1');
    $stmt->execute([$assignmentId, (int) $ctx['organization_member_id']]);
    return (bool) $stmt->fetchColumn();
}

function requireCareMissionAccess(PDO $pdo, array $ctx, int $assignmentId, string $permission, bool $mustBeAssigned = true): array
{
    if (!organizationHasPermission($pdo, $ctx, $permission)) {
        organizationJsonError(403, 'You do not have permission to perform this action.');
    }
    $assignment = careFindAssignment($pdo, $ctx, $assignmentId, false);
    if (!$assignment) organizationJsonError(404, 'Mission not found.');
    if ($mustBeAssigned && !careUserAssignedToMission($pdo, $ctx, $assignmentId)) {
        organizationJsonError(403, 'Only assigned responders can perform this field action.');
    }
    return $assignment;
}

function careAudit(PDO $pdo, array $ctx, string $action, string $entityType, int $entityId, ?array $oldValues = null, ?array $newValues = null): void
{
    $stmt = $pdo->prepare("INSERT INTO audit_logs
        (user_id, organization_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        (int) $ctx['user_id'],
        (int) $ctx['organization_id'],
        $action,
        $entityType,
        $entityId,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
}
