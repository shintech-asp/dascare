<?php
// citizen/cancel.php
// The requester cancels their own emergency request (citizen by account,
// guest by the request's private key → $_SESSION['guest_request_ids']).
// Allowed until the crew is on scene; see reusables/request_close.php.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/request_close.php';

header('Content-Type: application/json');

function cancelFail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') cancelFail(405, 'Method not allowed.');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['id'] ?? 0);
if ($requestId <= 0) cancelFail(422, 'A valid request id is required.');
$reason = trim((string) ($body['reason'] ?? ''));
if (mb_strlen($reason) > 255) cancelFail(422, 'Please keep the reason under 255 characters.');

$userId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$guestIds = array_map('intval', (array) ($_SESSION['guest_request_ids'] ?? []));

$stmt = $pdo->prepare('SELECT requester_user_id FROM emergency_requests WHERE id = ?');
$stmt->execute([$requestId]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);
$isOwner = $owner && (
    ($userId !== null && (int) $owner['requester_user_id'] === $userId)
    || ($owner['requester_user_id'] === null && in_array($requestId, $guestIds, true))
);
// 404 rather than 403 so request ids aren't enumerable (same as detail.php).
if (!$isOwner) cancelFail(404, 'Request not found.');

try {
    $result = requestClose($pdo, $requestId, 'cancelled', $reason, ['kind' => 'requester', 'user_id' => $userId, 'label' => 'the requester']);
    echo json_encode(['success' => true, 'message' => 'Your request was cancelled. The responders have been told.', 'result' => $result]);
} catch (RuntimeException $e) {
    cancelFail(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Citizen cancel failed: ' . $e->getMessage());
    cancelFail(500, 'Something went wrong. Please try again.');
}
