<?php
// Streams one of the organization's own documents (fetched as a blob by the web
// and the app, so the cookie / bearer token authorizes it).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/organization_guard.php';
$ctx = requireOrganizationAccess($pdo, 'org_settings.org_documents.read');
$id = (int) ($_GET['document_id'] ?? 0);
if ($id <= 0) { http_response_code(422); exit; }
$s = $pdo->prepare('SELECT file_path, original_name FROM organization_documents WHERE id = ? AND organization_id = ?');
$s->execute([$id, (int) $ctx['organization_id']]);
$d = $s->fetch(PDO::FETCH_ASSOC);
if (!$d) { http_response_code(404); exit; }
$path = __DIR__ . '/../../storage/organization-documents/' . basename((string) $d['file_path']);
if (!is_file($path) || !is_readable($path)) { http_response_code(404); exit; }
$mime = mime_content_type($path) ?: 'application/octet-stream';
if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . addslashes(preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) ($d['original_name'] ?: 'document'))) . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
