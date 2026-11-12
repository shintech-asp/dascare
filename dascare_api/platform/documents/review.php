<?php
// Approve or reject one organization document (typically a replacement upload).
// Approving retires the document it replaces. Rejecting needs a reason; the
// organization is told and keeps its previous document.
// POST {document_id, action: approve|reject, note, expires_at?}
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/organization_documents.php';
require_once __DIR__ . '/../../reusables/input_validation.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') platformJsonError(405, 'Method not allowed.');
requirePlatformExecutive($pdo, 'organizations.org_documents.approve');
$uid = (int) ($_SESSION['user_id'] ?? 0);
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) platformJsonError(422, 'Invalid request body.');
$docId = (int) ($in['document_id'] ?? 0);
$action = (string) ($in['action'] ?? '');
$note = trim((string) ($in['note'] ?? ''));
if ($docId <= 0 || !in_array($action, ['approve', 'reject'], true)) platformJsonError(422, 'Choose a document and a decision.');
if ($action === 'reject' && (mb_strlen($note) < 5 || !inputLooksLikeText($note, 4))) platformJsonError(422, 'Give the organization a short reason (at least 5 characters) so they know what to fix.');
if (mb_strlen($note) > 500) platformJsonError(422, 'The note is limited to 500 characters.');

try {
    $expires = $action === 'approve' && array_key_exists('expires_at', $in) ? orgDocParseExpiry($in['expires_at']) : null;
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT d.*, o.name AS organization_name, o.status AS organization_status FROM organization_documents d INNER JOIN organizations o ON o.id = d.organization_id AND o.deleted_at IS NULL WHERE d.id = ? FOR UPDATE");
    $s->execute([$docId]);
    $d = $s->fetch(PDO::FETCH_ASSOC);
    if (!$d) { $pdo->rollBack(); platformJsonError(404, 'Document not found.'); }
    if ($d['organization_status'] !== 'active') { $pdo->rollBack(); platformJsonError(409, 'Documents of organizations still under application are reviewed with the application.'); }
    if ($d['status'] !== 'pending' || $d['superseded_at'] !== null) { $pdo->rollBack(); platformJsonError(409, 'This document has already been reviewed.'); }

    $label = ucwords(str_replace('_', ' ', $d['doc_type']));
    if ($action === 'approve') {
        $pdo->prepare("UPDATE organization_documents SET status = 'approved', rejection_reason = NULL, reviewed_by = ?, reviewed_at = NOW(), expires_at = COALESCE(?, expires_at), expiry_notice_at = NULL WHERE id = ?")->execute([$uid, $expires, $docId]);
        if ($d['replaces_document_id']) $pdo->prepare('UPDATE organization_documents SET superseded_at = NOW() WHERE id = ? AND superseded_at IS NULL')->execute([(int) $d['replaces_document_id']]);
        $title = 'Document approved';
        $msg = "$label was approved." . ($note !== '' ? " Note: $note" : '');
    } else {
        $pdo->prepare("UPDATE organization_documents SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")->execute([$note, $uid, $docId]);
        $title = 'Document rejected';
        $msg = "$label was rejected: $note" . ($d['replaces_document_id'] ? ' Your previous document stays valid; upload a corrected one.' : ' Upload a corrected copy.');
    }
    $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, ?, ?, 'organization_document', ?, ?, ?, ?)")
        ->execute([$uid, (int) $d['organization_id'], 'platform.document_' . ($action === 'approve' ? 'approved' : 'rejected'), $docId, json_encode(['doc_type' => $d['doc_type'], 'note' => $note, 'expires_at' => $expires]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    $admins = orgDocAdminIds($pdo, (int) $d['organization_id']);
    orgDocNotify($pdo, $admins, 'document_review_result', $title, $msg, $docId, 'doc_result:' . $docId);
    $pdo->commit();
    realtimeNotifyUsers($pdo, $admins);
    realtimePlatformBadgesChanged($pdo, 'applications');
    echo json_encode(['success' => true, 'message' => $action === 'approve' ? 'Document approved.' : 'Document rejected. The organization was notified.']);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    platformJsonError(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Document review failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to save the decision.');
}
