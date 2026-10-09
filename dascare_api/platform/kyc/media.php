<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';

requirePlatformExecutive($pdo, 'citizens.kyc_verifications.read');

$userId = (int) ($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(422);
    exit;
}

$stmt = $pdo->prepare("\n    SELECT k.id_image\n    FROM kyc_verifications k\n    INNER JOIN user_roles ur ON ur.user_id = k.user_id AND ur.role = 'citizen'\n    WHERE k.user_id = ?\n    LIMIT 1\n");
$stmt->execute([$userId]);
$filename = $stmt->fetchColumn();

if (!$filename) {
    http_response_code(404);
    exit;
}

$filename = basename((string) $filename);
require_once __DIR__ . '/../../reusables/upload_paths.php';
$path = dascareUploadsDir('kyc') . $filename;

if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$mime = mime_content_type($path) ?: 'application/octet-stream';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
