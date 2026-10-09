<?php
// citizen/unmerge.php
// "Not the same emergency? Request separately." — lets the person who sent a
// report that dedup linked to an earlier one undo that link. The report goes
// back to submitted and gets its own DSS cycle.
//
// Only the requester can do this: a logged-in citizen for their own request,
// or a guest for a request created in this same browser session (create.php
// remembers merged guest request ids in $_SESSION['guest_request_ids']).
// Worst case if abused is one extra ambulance — the same as no dedup at all —
// which is why the requester's word is trusted without review.
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/dispatch_dedup.php';

header('Content-Type: application/json');

function unmergeFail(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') unmergeFail(405, 'Method not allowed.');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['id'] ?? 0);
if ($requestId <= 0) unmergeFail(422, 'A valid request id is required.');

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
if (!$isOwner) unmergeFail(404, 'Request not found.');

try {
    $result = dedupUnmergeRequest($pdo, $requestId, $userId, null, 'the requester', 'Requester said this is a different emergency.');
    echo json_encode([
        'success' => true,
        'message' => 'Your report is now its own emergency. Dispatch is finding a unit for it.',
        'dssStarted' => $result['dss_started'],
    ]);
} catch (RuntimeException $e) {
    unmergeFail(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Citizen unmerge failed: ' . $e->getMessage());
    unmergeFail(500, 'Something went wrong. Please try again, or call your local emergency hotline.');
}
