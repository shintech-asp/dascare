<?php
/**
 * POST realtime/test.php — publishes a "test" event to the caller's own user
 * channel so a screen can measure the round trip (Technical → Configuration,
 * the app's Server connection screen). Signed-in users only.
 *
 * Body: { nonce } — echoed back in the event so the screen can match it.
 */
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/realtime.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST.']);
    exit;
}
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sign in to run the live updates test.']);
    exit;
}
if (!realtimeEnabled($pdo)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Live updates are turned off or not set up.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$nonce = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($input['nonce'] ?? '')), 0, 40);

// Published right away (not queued) so the response can report failures.
$ok = realtimePublish(realtimeChannel('user', $userId), [[
    'name' => 'test',
    'data' => json_encode(['nonce' => $nonce, 'sent_at' => (int) round(microtime(true) * 1000)]),
    'encoding' => 'json',
]]);

if (!$ok) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Could not reach Ably from the server. Check the key and that PHP can make outgoing HTTPS requests.']);
    exit;
}
echo json_encode(['success' => true, 'message' => 'Test event published.']);
