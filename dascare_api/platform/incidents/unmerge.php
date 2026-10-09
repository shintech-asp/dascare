<?php
// platform/incidents/unmerge.php
// Platform override for a wrong dedup merge: the linked report becomes its
// own incident again and gets its own DSS cycle (reusables/dispatch_dedup.php).
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dedup.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') platformJsonError(405, 'Method not allowed.');
$userId = requirePlatformExecutive($pdo, 'oversight.emergency_requests.update');
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$requestId = (int) ($body['request_id'] ?? 0);
if ($requestId <= 0) platformJsonError(422, 'Invalid incident.');
$reason = trim((string) ($body['reason'] ?? ''));
if (mb_strlen($reason) > 255) platformJsonError(422, 'Please keep the reason under 255 characters.');

try {
    $result = dedupUnmergeRequest($pdo, $requestId, $userId, null, 'a platform administrator', $reason);
    echo json_encode([
        'success' => true,
        'message' => $result['dss_started'] ? 'Report unlinked. It is now its own incident and DSS screening has started.' : 'Report unlinked, but DSS could not start automatically — run it from the incident review.',
        'result' => $result,
    ]);
} catch (RuntimeException $e) {
    platformJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Platform unmerge failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to unlink this report.');
}
