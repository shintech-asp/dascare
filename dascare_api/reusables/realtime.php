<?php

/**
 * Live updates (Ably). Replaces "poll every 15 s" with "tell open screens the
 * moment something changes".
 *
 * Entirely inert until set up: without dascare_api/config/ably.json, or with
 * system_settings 'realtime.enabled' = false, every function here returns
 * immediately and screens keep polling exactly as before.
 *
 * Events are HINTS ("request 12 changed"), not data: a screen that receives
 * one re-fetches the endpoint it already uses, so all authorization stays in
 * the PHP guards. Only ambulance positions carry coordinates.
 *
 * Events are QUEUED and published at shutdown, and dropped if a transaction
 * is still open then (it rolled back). Queue them after commit():
 *
 *   realtimeQueue($pdo, realtimeChannel('org', $orgId), 'offer.created', ['request_id' => 12]);
 *
 * Channels (who may listen is decided by realtime/auth.php):
 *   <prefix>:request:<id>   requester (account or guest key), platform admins
 *   <prefix>:org:<id>       members of that organization
 *   <prefix>:platform       platform executive admins
 *   <prefix>:user:<id>      that user only
 */

const REALTIME_CONFIG_FILE = __DIR__ . '/../config/ably.json';
const REALTIME_TOKEN_TTL_MS = 3600 * 1000;
const REALTIME_HTTP_TIMEOUT = 3;

function realtimeConfig(): ?array
{
    static $config = false;
    if ($config !== false) return $config;
    $config = null;
    if (!is_readable(REALTIME_CONFIG_FILE)) return null;
    $json = json_decode((string) file_get_contents(REALTIME_CONFIG_FILE), true);
    $key = is_array($json) ? trim((string) ($json['key'] ?? '')) : '';
    // Ably API key: "<appId>.<keyId>:<secret>"
    if (!preg_match('/^([^:\s]+\.[^:\s]+):(\S+)$/', $key, $m)) {
        error_log('Realtime disabled: config/ably.json has no valid "key" (expected appId.keyId:secret).');
        return null;
    }
    $prefix = preg_replace('/[^a-z0-9_-]/i', '', (string) ($json['channel_prefix'] ?? 'dascare')) ?: 'dascare';
    $config = ['key' => $key, 'key_name' => $m[1], 'key_secret' => $m[2], 'prefix' => $prefix];
    return $config;
}

/** The on/off switch in Technical → Configuration (defaults to on). */
function realtimeSettingEnabled(PDO $pdo): bool
{
    static $enabled = null;
    if ($enabled !== null) return $enabled;
    $enabled = true;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'realtime.enabled' LIMIT 1");
        $stmt->execute();
        $raw = $stmt->fetchColumn();
        if ($raw !== false) $enabled = json_decode((string) $raw, true) !== false;
    } catch (Throwable $e) {
        error_log('Realtime setting lookup failed: ' . $e->getMessage());
    }
    return $enabled;
}

function realtimeEnabled(PDO $pdo): bool
{
    return realtimeConfig() !== null && realtimeSettingEnabled($pdo);
}

/** realtimeChannel('org', 3) → "dascare:org:3"; realtimeChannel('platform') → "dascare:platform" */
function realtimeChannel(string $type, ?int $id = null): string
{
    $prefix = realtimeConfig()['prefix'] ?? 'dascare';
    return $id === null ? "$prefix:$type" : "$prefix:$type:$id";
}

function realtimeQueue(PDO $pdo, string $channel, string $event, array $data = []): void
{
    if (!realtimeEnabled($pdo)) return;
    $GLOBALS['DASCARE_REALTIME_QUEUE'][] = ['channel' => $channel, 'event' => $event, 'data' => $data];
    realtimeRegisterFlush($pdo);
}

/**
 * "This emergency request changed" (offer sent/answered/expired, assignment,
 * mission status, details, duplicate link). Who hears it is worked out at
 * shutdown, after the changes committed: the request's own channel, the
 * platform channel, and every organization with a live offer or an assignment
 * on it — plus $alsoOrgIds (e.g. the org whose offer just expired or was
 * declined, so its Offers list drops it). Safe to call inside a transaction;
 * if that transaction rolls back the event is at worst a harmless "re-fetch".
 *
 * @param int[] $alsoOrgIds
 */
function realtimeRequestChanged(PDO $pdo, int $requestId, string $event = 'request.updated', array $data = [], array $alsoOrgIds = []): void
{
    if ($requestId <= 0 || !realtimeEnabled($pdo)) return;
    $entry = &$GLOBALS['DASCARE_REALTIME_REQUESTS'][$requestId];
    $entry['events'][$event] = $data;
    foreach ($alsoOrgIds as $orgId) if ((int) $orgId > 0) $entry['orgs'][(int) $orgId] = true;
    unset($entry);
    realtimeRegisterFlush($pdo);
}

/** Turn realtimeRequestChanged() calls into queued channel events. */
function realtimeResolveRequestEvents(PDO $pdo): void
{
    $requests = $GLOBALS['DASCARE_REALTIME_REQUESTS'] ?? [];
    $GLOBALS['DASCARE_REALTIME_REQUESTS'] = [];
    if (!$requests) return;

    $orgsStmt = $pdo->prepare("
        SELECT organization_id FROM incident_offers WHERE emergency_request_id = ? AND offer_status IN ('sent', 'accepted')
        UNION
        SELECT organization_id FROM dispatch_assignments WHERE emergency_request_id = ?
    ");
    $statusStmt = $pdo->prepare('SELECT status, merged_into_request_id FROM emergency_requests WHERE id = ?');
    foreach ($requests as $requestId => $entry) {
        $statusStmt->execute([$requestId]);
        $row = $statusStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $orgsStmt->execute([$requestId, $requestId]);
        $orgIds = array_map('intval', $orgsStmt->fetchAll(PDO::FETCH_COLUMN));
        $orgIds = array_unique(array_merge($orgIds, array_keys($entry['orgs'] ?? [])));

        $channels = [realtimeChannel('request', $requestId), realtimeChannel('platform')];
        foreach ($orgIds as $orgId) $channels[] = realtimeChannel('org', $orgId);
        foreach ($entry['events'] as $event => $data) {
            $payload = ['request_id' => $requestId, 'status' => $row['status']] + $data;
            if ($row['merged_into_request_id']) $payload['merged_into_request_id'] = (int) $row['merged_into_request_id'];
            foreach ($channels as $channel) {
                $GLOBALS['DASCARE_REALTIME_QUEUE'][] = ['channel' => $channel, 'event' => $event, 'data' => $payload];
            }
        }
    }
}

function realtimeRegisterFlush(PDO $pdo): void
{
    static $registered = false;
    if ($registered) return;
    $registered = true;
    register_shutdown_function(static function () use ($pdo) {
        try { realtimeFlush($pdo); } catch (Throwable $e) { error_log('Realtime flush failed: ' . $e->getMessage()); }
    });
}

/** Publish everything queued during this request (one HTTP call per channel). */
function realtimeFlush(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $dropped = count($GLOBALS['DASCARE_REALTIME_QUEUE'] ?? []) + count($GLOBALS['DASCARE_REALTIME_REQUESTS'] ?? []);
        $GLOBALS['DASCARE_REALTIME_QUEUE'] = [];
        $GLOBALS['DASCARE_REALTIME_REQUESTS'] = [];
        error_log("Realtime: dropped $dropped event(s) queued inside a transaction that never committed.");
        return;
    }
    realtimeResolveRequestEvents($pdo);
    $queue = $GLOBALS['DASCARE_REALTIME_QUEUE'] ?? [];
    $GLOBALS['DASCARE_REALTIME_QUEUE'] = [];
    if (!$queue) return;

    // Same event + data twice in one request is one event.
    $byChannel = [];
    foreach ($queue as $item) {
        $sig = $item['event'] . '|' . json_encode($item['data']);
        $byChannel[$item['channel']][$sig] = $item;
    }

    // Let the user's response go first where the server allows it (PHP-FPM).
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

    $sentAt = (int) round(microtime(true) * 1000);
    foreach ($byChannel as $channel => $items) {
        $messages = [];
        foreach ($items as $item) {
            $messages[] = [
                'name' => $item['event'],
                'data' => json_encode($item['data'] + ['sent_at' => $sentAt], JSON_UNESCAPED_SLASHES),
                'encoding' => 'json',
            ];
        }
        realtimePublish($channel, $messages);
    }
}

function realtimePublish(string $channel, array $messages): bool
{
    // Tests can swap the transport: $GLOBALS['DASCARE_REALTIME_SENDER'] = fn($channel, $messages) => ...
    if (isset($GLOBALS['DASCARE_REALTIME_SENDER']) && is_callable($GLOBALS['DASCARE_REALTIME_SENDER'])) {
        ($GLOBALS['DASCARE_REALTIME_SENDER'])($channel, $messages);
        return true;
    }
    $config = realtimeConfig();
    if (!$config) return false;

    $ch = curl_init('https://rest.ably.io/channels/' . rawurlencode($channel) . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($messages, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($config['key'])],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => REALTIME_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($status >= 200 && $status < 300) return true;
    error_log("Realtime publish to $channel failed ($status): " . ($error !== '' ? $error : substr((string) $response, 0, 300)));
    return false;
}

/**
 * Signed Ably token request: the browser/app exchanges it with Ably for a
 * token that can only SUBSCRIBE to the listed channels. Signed locally with
 * the key's secret (no call to Ably), so the secret never leaves the server.
 *
 * @param string[] $channels
 */
function realtimeTokenRequest(array $channels, string $clientId = ''): array
{
    $config = realtimeConfig();
    $capability = [];
    foreach (array_unique($channels) as $channel) $capability[$channel] = ['subscribe', 'history'];
    $request = [
        'keyName' => $config['key_name'],
        'ttl' => REALTIME_TOKEN_TTL_MS,
        'capability' => json_encode($capability, JSON_UNESCAPED_SLASHES),
        'clientId' => $clientId,
        'timestamp' => (int) round(microtime(true) * 1000),
        'nonce' => bin2hex(random_bytes(16)),
    ];
    $signText = implode("\n", [$request['keyName'], $request['ttl'], $request['capability'], $request['clientId'], $request['timestamp'], $request['nonce']]) . "\n";
    $request['mac'] = base64_encode(hash_hmac('sha256', $signText, $config['key_secret'], true));
    if ($clientId === '') unset($request['clientId']);
    return $request;
}
