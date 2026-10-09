<?php
// mobile/push/unregister.php
// On logout: stop account notifications to this phone (the device stays
// registered for any guest requests it follows). POST JSON {token}.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/mobile_auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isMobileAppRequest()) mobileJson(405, ['success' => false, 'message' => 'Method not allowed.']);

$token = trim((string) ((json_decode(file_get_contents('php://input'), true) ?: [])['token'] ?? ''));
if ($token !== '') {
    $pdo->prepare('UPDATE push_devices SET user_id = NULL WHERE fcm_token = ?')->execute([$token]);
}
mobileJson(200, ['success' => true]);
