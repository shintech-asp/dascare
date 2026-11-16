<?php
// A sponsor's logo (public, only while the sponsor is active).
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/support.php';
$id = (int) ($_GET['id'] ?? 0);
$s = $pdo->prepare("SELECT logo_file FROM sponsors WHERE id = ? AND status = 'active' AND logo_file IS NOT NULL AND (starts_on IS NULL OR starts_on <= CURDATE()) AND (ends_on IS NULL OR ends_on >= CURDATE())");
$s->execute([$id]);
$file = $s->fetchColumn();
$path = $file ? dascareUploadsDir('sponsors') . basename((string) $file) : '';
if (!$file || !is_file($path)) { http_response_code(404); exit; }
$mime = mime_content_type($path) ?: '';
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
