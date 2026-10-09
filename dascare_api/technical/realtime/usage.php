<?php
/**
 * GET technical/realtime/usage.php — free-tier usage of the live-update and
 * routing providers (Technical → Configuration, thesis evidence).
 *   ably:    today / this month from Ably's stats API (cached 60 s)
 *   routing: TomTom calls today / this month from routing_api_usage
 * Secrets never leave the server.
 */
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/technical_guard.php';
require_once __DIR__ . '/../../reusables/realtime.php';
require_once __DIR__ . '/../../reusables/routing.php';
header('Content-Type: application/json');
requireTechnicalSuperAdmin($pdo);

// Free plans (Ably Free, TomTom freemium) — what the counts are compared against.
const USAGE_ABLY_MONTHLY_MESSAGES = 6000000;
const USAGE_ABLY_PEAK_CONNECTIONS = 200;

function usageAblyStats(array $config, string $unit): ?array
{
    $ch = curl_init('https://rest.ably.io/stats?unit=' . $unit . '&limit=1');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($config['key'])],
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) return null;
    $row = json_decode((string) $body, true)[0] ?? null;
    if (!is_array($row)) return ['messages' => 0, 'published' => 0, 'delivered' => 0, 'peak_connections' => 0, 'peak_channels' => 0];
    return [
        'messages' => (int) ($row['all']['messages']['count'] ?? 0),
        'published' => (int) ($row['inbound']['all']['messages']['count'] ?? 0),
        'delivered' => (int) ($row['outbound']['all']['messages']['count'] ?? 0),
        'peak_connections' => (int) ($row['connections']['all']['peak'] ?? 0),
        'peak_channels' => (int) ($row['channels']['peak'] ?? 0),
    ];
}

$ably = ['configured' => false];
$config = realtimeConfig();
if ($config) {
    $cacheFile = sys_get_temp_dir() . '/dascare_ably_usage_' . md5($config['key_name']) . '.json';
    $cached = is_readable($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
    if (!empty($cached['at']) && $cached['at'] > time() - 60) {
        $ably = $cached['data'];
    } else {
        $today = usageAblyStats($config, 'day');
        $month = usageAblyStats($config, 'month');
        $ably = [
            'configured' => true,
            'available' => $today !== null,
            'today' => $today,
            'month' => $month,
            'limits' => ['messages_per_month' => USAGE_ABLY_MONTHLY_MESSAGES, 'peak_connections' => USAGE_ABLY_PEAK_CONNECTIONS],
        ];
        if ($today !== null) @file_put_contents($cacheFile, json_encode(['at' => time(), 'data' => $ably]), LOCK_EX);
    }
}

$usage = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN usage_date = CURDATE() THEN calls END), 0) AS calls_today,
        COALESCE(SUM(CASE WHEN usage_date = CURDATE() THEN failures END), 0) AS failures_today,
        COALESCE(SUM(calls), 0) AS calls_month
    FROM routing_api_usage
    WHERE provider = 'tomtom' AND usage_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
")->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'ably' => $ably,
    'routing' => [
        'configured' => routingConfig() !== null,
        'provider' => 'tomtom',
        'calls_today' => (int) $usage['calls_today'],
        'failures_today' => (int) $usage['failures_today'],
        'calls_month' => (int) $usage['calls_month'],
        'daily_limit' => ROUTING_DAILY_LIMIT,
    ],
]);
