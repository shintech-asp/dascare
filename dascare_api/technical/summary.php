<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../reusables/technical_guard.php';
header('Content-Type: application/json');
requireTechnicalSuperAdmin($pdo);

try {
    $pdo->query('SELECT 1')->fetchColumn();
    $dbOk = true;
} catch (Throwable $e) {
    $dbOk = false;
}

try {
    $stats = $pdo->query("\n        SELECT\n          (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) AS users_total,\n          (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND account_status = 'active') AS users_active,\n          (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND account_status IN ('suspended','disabled')) AS restricted_accounts,\n          (SELECT COUNT(*) FROM organizations WHERE deleted_at IS NULL) AS organizations_total,\n          (SELECT COUNT(*) FROM organizations WHERE deleted_at IS NULL AND status = 'active') AS organizations_active,\n          (SELECT COUNT(*) FROM emergency_requests WHERE status NOT IN ('completed','cancelled','rejected','duplicate','false_alarm')) AS active_incidents,\n          (SELECT COUNT(*) FROM rate_limits WHERE attempts >= 5 AND (expires_at IS NULL OR expires_at >= NOW())) AS rate_limit_alerts,\n          (SELECT COUNT(*) FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS audit_24h\n    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $recent = $pdo->query("\n        SELECT a.id, a.action, a.entity_type, a.entity_id, a.created_at,\n               CONCAT_WS(' ', u.first_name, u.last_name) AS actor_name\n        FROM audit_logs a\n        LEFT JOIN users u ON u.id = a.user_id\n        ORDER BY a.created_at DESC\n        LIMIT 8\n    ")->fetchAll(PDO::FETCH_ASSOC);

    $settingsStmt = $pdo->query("SELECT setting_key, setting_value, updated_at FROM system_settings ORDER BY setting_key");
    $settings = [];
    foreach ($settingsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $decoded = json_decode($row['setting_value'], true);
        $settings[$row['setting_key']] = [
            'value' => json_last_error() === JSON_ERROR_NONE ? $decoded : $row['setting_value'],
            'updated_at' => $row['updated_at'],
        ];
    }

    $uploadRoot = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
    $storageWritable = is_dir($uploadRoot) ? is_writable($uploadRoot) : is_writable(dirname($uploadRoot));

    echo json_encode([
        'success' => true,
        'health' => [
            'api' => true,
            'database' => $dbOk,
            'session' => session_status() === PHP_SESSION_ACTIVE,
            'storage_writable' => $storageWritable,
            'php_version' => PHP_VERSION,
            'environment' => ($_SERVER['SERVER_NAME'] ?? 'local'),
        ],
        'stats' => array_map('intval', $stats),
        'settings' => $settings,
        'recent_activity' => $recent,
        'service_notes' => [
            'mail' => 'Mailer is configured through the application email helper; use a test message before production.',
            'maps' => 'Leaflet/OpenStreetMap + Nominatim are used by the current web prototype.',
            'realtime' => 'Operational updates currently use polling. WebSocket transport is not required for the thesis demo.',
            'backups' => 'No automated backup registry is stored in DASCARE yet. Verify database and upload backups at the hosting layer.',
        ],
    ]);
} catch (Throwable $e) {
    error_log('Technical summary failed: ' . $e->getMessage());
    technicalJsonError(500, 'Unable to load technical platform status.');
}
