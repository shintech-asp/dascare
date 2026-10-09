<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';

header('Content-Type: application/json');
$ctx = requireOrganizationAccess($pdo, 'hr.members.create');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$data = json_decode(file_get_contents('php://input'), true);
$id = (int) ($data['invitation_id'] ?? 0);
if ($id <= 0) organizationJsonError(422, 'A valid invitation is required.');

try {
    $stmt = $pdo->prepare("UPDATE organization_invitations SET status='cancelled' WHERE id=? AND organization_id=? AND status='pending'");
    $stmt->execute([$id, $ctx['organization_id']]);
    if (!$stmt->rowCount()) organizationJsonError(404, 'Pending invitation not found.');
    $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, ip_address, user_agent) VALUES (?, ?, 'organization.invitation_cancelled', 'organization_invitation', ?, ?, ?)")
        ->execute([$ctx['user_id'], $ctx['organization_id'], $id, $_SERVER['REMOTE_ADDR'] ?? null, mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255)]);
    echo json_encode(['success'=>true,'message'=>'Invitation cancelled.']);
} catch (Throwable $e) {
    error_log('Invitation cancel failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to cancel the invitation.');
}
