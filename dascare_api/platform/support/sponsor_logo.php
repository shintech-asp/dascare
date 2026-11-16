<?php
// A sponsor's logo for the admin screens (any status).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/support.php';
requirePlatformExecutive($pdo, 'support.sponsors.read');
$s = $pdo->prepare('SELECT logo_file FROM sponsors WHERE id = ? AND logo_file IS NOT NULL');
$s->execute([(int) ($_GET['id'] ?? 0)]);
$file = $s->fetchColumn();
$path = $file ? dascareUploadsDir('sponsors') . basename((string) $file) : '';
if (!$file || !is_file($path)) { http_response_code(404); exit; }
$mime = mime_content_type($path) ?: '';
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
