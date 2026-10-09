<?php
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/platform_guard.php';
header('Content-Type: application/json');
requirePlatformExecutive($pdo, 'analytics.reports.read');

try {
    $days = isset($_GET['days']) ? max(1, min(365, (int) $_GET['days'])) : 30;
    $limit = isset($_GET['limit']) ? max(50, min(1500, (int) $_GET['limit'])) : 600;

    $stmt = $pdo->prepare("\n        SELECT er.id, er.reference_number, er.latitude, er.longitude, er.severity, er.status,\n               er.barangay, er.address_text, er.submitted_at, ec.name AS category_name\n        FROM emergency_requests er\n        LEFT JOIN emergency_categories ec ON ec.id = er.emergency_category_id\n        WHERE er.latitude IS NOT NULL\n          AND er.longitude IS NOT NULL\n          AND er.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)\n        ORDER BY er.submitted_at DESC\n        LIMIT {$limit}\n    ");
    $stmt->execute([$days]);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $barangayStmt = $pdo->prepare("\n        SELECT COALESCE(NULLIF(barangay,''), 'Unknown') AS barangay, COUNT(*) AS incident_count,\n               SUM(severity='critical') AS critical_count, SUM(severity='high') AS high_count\n        FROM emergency_requests\n        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)\n        GROUP BY COALESCE(NULLIF(barangay,''), 'Unknown')\n        ORDER BY incident_count DESC, barangay ASC\n        LIMIT 12\n    ");
    $barangayStmt->execute([$days]);
    $hotspots = $barangayStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'days' => $days,
        'points' => $points,
        'hotspots' => $hotspots,
        'total' => count($points),
    ]);
} catch (Throwable $e) {
    error_log('Platform heatmap failed: ' . $e->getMessage());
    platformJsonError(500, 'Unable to load incident heatmap data.');
}
