<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

requirePlatformExecutive($pdo, 'organizations.org_documents.read');

$documentId = (int) ($_GET['document_id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(422);
    exit;
}

$stmt = $pdo->prepare("\n    SELECT d.file_path, d.original_name, d.mime_type\n    FROM organization_documents d\n    INNER JOIN organizations o ON o.id = d.organization_id AND o.deleted_at IS NULL\n    WHERE d.id = ?\n    LIMIT 1\n");
$stmt->execute([$documentId]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    http_response_code(404);
    exit;
}

$filename = basename((string) $document['file_path']);
$path = __DIR__ . '/../../storage/organization-documents/' . $filename;
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$detected = mime_content_type($path) ?: 'application/octet-stream';
$allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
if (!in_array($detected, $allowed, true)) {
    http_response_code(415);
    exit;
}

$downloadName = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) ($document['original_name'] ?: $filename));
header('Content-Type: ' . $detected);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . addslashes($downloadName) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
