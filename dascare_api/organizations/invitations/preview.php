<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';

header('Content-Type: application/json');
$token = trim((string) ($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'This invitation link is invalid.']);
    exit;
}

try {
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT i.id, i.first_name, i.last_name, i.email, i.phone, i.employee_code,
               i.status, i.expires_at, o.name AS organization_name, o.status AS organization_status,
               r.role_name, r.description AS role_description
        FROM organization_invitations i
        INNER JOIN organizations o ON o.id = i.organization_id AND o.deleted_at IS NULL
        INNER JOIN org_roles r ON r.id = i.role_id AND r.deleted_at IS NULL
        WHERE i.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $invite = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invite) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'This invitation could not be found.']);
        exit;
    }

    if ($invite['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success'=>false,'message'=>$invite['status'] === 'accepted' ? 'This invitation has already been accepted.' : 'This invitation is no longer active.']);
        exit;
    }
    if (strtotime($invite['expires_at']) < time()) {
        $pdo->prepare("UPDATE organization_invitations SET status = 'expired' WHERE id = ? AND status = 'pending'")->execute([$invite['id']]);
        http_response_code(410);
        echo json_encode(['success'=>false,'message'=>'This invitation has expired. Ask the organization to send a new one.']);
        exit;
    }
    if ($invite['organization_status'] !== 'active') {
        http_response_code(409);
        echo json_encode(['success'=>false,'message'=>'This organization is not currently active on DASCARE.']);
        exit;
    }

    unset($invite['status'], $invite['organization_status']);
    $invite['id'] = (int) $invite['id'];
    echo json_encode(['success'=>true,'invitation'=>$invite]);
} catch (Throwable $e) {
    error_log('Invitation preview failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to load this invitation.']);
}
