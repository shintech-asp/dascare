<?php
// mobile/push/register.php
// The app reports its Firebase (FCM) registration token so the server can
// push "a unit is on the way" etc. while the app is closed. Called after
// login, after a guest SOS, and whenever FCM rotates the token.
//
// POST JSON {token}
//   - logged-in citizen (Bearer token): the device is linked to that account
//   - guest: the device follows the requests whose keys it sends in
//     X-Guest-Tokens (reusables/mobile_auth.php → guest_request_ids)
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/mobile_auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isMobileAppRequest()) mobileJson(405, ['success' => false, 'message' => 'Method not allowed.']);

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$token = trim((string) ($data['token'] ?? ''));
if ($token === '' || strlen($token) > 255 || !preg_match('/^[A-Za-z0-9_:\-\.]+$/', $token)) {
    mobileJson(422, ['success' => false, 'message' => 'Invalid push token.']);
}

$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$guestRequestIds = array_values(array_filter(array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? []))));

try {
    $pdo->beginTransaction();
    // One row per token; whoever is signed in on the phone now owns it
    // (signing out / in as someone else re-points it).
    $pdo->prepare("
        INSERT INTO push_devices (fcm_token, user_id, platform) VALUES (?, ?, 'android')
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), updated_at = NOW()
    ")->execute([$token, $userId]);
    $deviceId = (int) $pdo->query('SELECT id FROM push_devices WHERE fcm_token = ' . $pdo->quote($token))->fetchColumn();

    if ($guestRequestIds) {
        $watch = $pdo->prepare('INSERT IGNORE INTO push_request_watch (push_device_id, emergency_request_id) VALUES (?, ?)');
        foreach ($guestRequestIds as $requestId) $watch->execute([$deviceId, $requestId]);
    }
    $pdo->commit();
    mobileJson(200, ['success' => true, 'linked_to_account' => $userId !== null, 'watching' => count($guestRequestIds)]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Push register failed: ' . $e->getMessage());
    mobileJson(500, ['success' => false, 'message' => 'Could not register for notifications.']);
}
