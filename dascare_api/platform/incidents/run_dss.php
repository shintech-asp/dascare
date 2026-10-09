<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
require_once __DIR__ . '/../../reusables/dispatch_dss.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') platformJsonError(405, 'Method not allowed.');
$userId = requirePlatformExecutive($pdo, 'oversight.emergency_requests.update');
$body = json_decode(file_get_contents('php://input'), true);
$requestId = (int) ($body['request_id'] ?? 0);
if ($requestId <= 0) platformJsonError(422, 'Invalid incident.');

try {
    $result = dssGenerateRecommendations($pdo, $requestId, $userId);
    echo json_encode(['success'=>true,'message'=>$result['candidates'] ? 'DSS recommendations generated and the highest-ranked organization was offered the incident.' : 'DSS completed, but no dispatch-ready organizations are currently eligible.','result'=>$result]);
} catch (RuntimeException $e) {
    platformJsonError(409, $e->getMessage());
} catch (Throwable $e) {
    error_log('Run DSS failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to run DSS recommendations.');
}
