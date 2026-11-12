<?php
// Upload a new document, or a replacement for one the organization already has.
// multipart: doc_type, expires_at (optional, YYYY-MM-DD), file.
// The new row is 'pending' for platform review; the old one stays current until
// the platform approves the replacement (platform/documents/review.php).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
require_once __DIR__ . '/../../reusables/organization_documents.php';
require_once __DIR__ . '/../../reusables/rate_limit.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') organizationJsonError(405, 'Method not allowed.');
$ctx = requireOrganizationAccess($pdo, 'org_settings.org_documents.create');
$orgId = (int) $ctx['organization_id'];
$userId = (int) $ctx['user_id'];

$docType = trim((string) ($_POST['doc_type'] ?? ''));
$typeStmt = $pdo->prepare('SELECT organization_type, name FROM organizations WHERE id = ? AND deleted_at IS NULL');
$typeStmt->execute([$orgId]);
$org = $typeStmt->fetch(PDO::FETCH_ASSOC);
if (!$org) organizationJsonError(404, 'Organization not found.');
$allowedTypes = array_column(ORGANIZATION_DOCUMENT_REQUIREMENTS[$org['organization_type']] ?? [], 'key');
$allowedTypes[] = 'other_supporting';
if (!in_array($docType, $allowedTypes, true)) organizationJsonError(422, 'Choose which document this is.');

checkRateLimit($pdo, 'org_document_upload', 'org:' . $orgId);
try {
    $upload = orgDocValidateUpload($_FILES['file'] ?? []);
    $expires = orgDocParseExpiry($_POST['expires_at'] ?? '');
} catch (RuntimeException $e) {
    organizationJsonError(422, $e->getMessage());
}

$dest = null;
try {
    $filename = 'org_' . $orgId . '_' . bin2hex(random_bytes(16)) . '.' . $upload['extension'];
    $dest = orgDocStorageDir() . $filename;
    if (!move_uploaded_file($upload['tmp_name'], $dest)) throw new RuntimeException('Could not store the document.');

    $pdo->beginTransaction();
    // The document this one replaces: the current approved (else latest current) row of the same type.
    $prev = $pdo->prepare("SELECT id FROM organization_documents WHERE organization_id = ? AND doc_type = ? AND superseded_at IS NULL ORDER BY (status = 'approved') DESC, id DESC LIMIT 1 FOR UPDATE");
    $prev->execute([$orgId, $docType]);
    $replaces = $prev->fetchColumn() ?: null;
    // Only one replacement may wait for review per type: retire an earlier unreviewed upload.
    $pdo->prepare("UPDATE organization_documents SET superseded_at = NOW() WHERE organization_id = ? AND doc_type = ? AND status = 'pending' AND superseded_at IS NULL AND replaces_document_id IS NOT NULL")->execute([$orgId, $docType]);

    $pdo->prepare("INSERT INTO organization_documents (organization_id, doc_type, file_path, original_name, mime_type, file_size, expires_at, status, uploaded_by_user_id, replaces_document_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)")
        ->execute([$orgId, $docType, $filename, $upload['original_name'], $upload['mime'], $upload['size'], $expires, $userId, $replaces]);
    $docId = (int) $pdo->lastInsertId();
    recordAttempt($pdo, 'org_document_upload', 'org:' . $orgId);

    $pdo->prepare("INSERT INTO audit_logs (user_id, organization_id, action, entity_type, entity_id, new_values, ip_address, user_agent) VALUES (?, ?, 'organization.document_uploaded', 'organization_document', ?, ?, ?, ?)")
        ->execute([$userId, $orgId, $docId, json_encode(['doc_type' => $docType, 'replaces' => $replaces, 'expires_at' => $expires]), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);

    $label = ucwords(str_replace('_', ' ', $docType));
    $platform = orgDocPlatformIds($pdo);
    orgDocNotify($pdo, $platform, 'document_review', $replaces ? 'Replacement document to review' : 'New document to review', "{$org['name']} uploaded " . ($replaces ? 'a replacement for ' : '') . "$label.", $docId, 'doc_review:' . $docId);
    $pdo->commit();
    realtimeNotifyRole($pdo, 'platform_executive_admin');
    realtimePlatformBadgesChanged($pdo, 'applications');
    echo json_encode(['success' => true, 'document_id' => $docId, 'message' => $replaces ? 'Replacement uploaded. It stays pending until the platform approves it; your current document remains valid meanwhile.' : 'Document uploaded and sent for review.']);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($dest && is_file($dest)) @unlink($dest);
    organizationJsonError(422, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($dest && is_file($dest)) @unlink($dest);
    error_log('Document upload failed: ' . $e->getMessage());
    organizationJsonError(500, 'Unable to upload the document.');
}
