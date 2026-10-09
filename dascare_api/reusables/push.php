<?php

/**
 * Push notifications to the DASCARE Android app (Firebase Cloud Messaging,
 * HTTP v1 API). Phase 7 of the mobile app.
 *
 * Entirely inert until Firebase is set up: if
 * dascare_api/config/firebase-service-account.json is missing, every function
 * here returns immediately — no queries, no network. Only phones that
 * registered through mobile/push/register.php ever receive anything, so web
 * users and staff are unaffected.
 *
 * Pushes are QUEUED during the request and sent at shutdown, and each one is
 * re-checked first (the notification row still exists / the request is
 * really in that status). A transaction that rolled back therefore never
 * produces a push.
 *
 *   pushQueueUser($pdo, $userId, $title, $body, $data, $dedupKey)
 *       — pair with an INSERT INTO notifications (same user + dedup_key)
 *   pushQueueRequestFollowers($pdo, $requestId, $title, $body, $expectedStatus)
 *       — guests following that request from their phone, plus everyone whose
 *         report dedup linked to it (they follow this incident's response)
 */

const PUSH_SERVICE_ACCOUNT_FILE = __DIR__ . '/../config/firebase-service-account.json';
const PUSH_CHANNEL_ID = 'dascare_alerts'; // created by the app (high importance)
const PUSH_HTTP_TIMEOUT = 4;

function pushServiceAccount(): ?array
{
    static $account = false;
    if ($account !== false) return $account;
    $account = null;
    if (!is_readable(PUSH_SERVICE_ACCOUNT_FILE)) return null;
    $json = json_decode((string) file_get_contents(PUSH_SERVICE_ACCOUNT_FILE), true);
    if (is_array($json) && !empty($json['project_id']) && !empty($json['client_email']) && !empty($json['private_key'])) {
        $account = $json;
    } else {
        error_log('Push disabled: firebase-service-account.json is not a valid service account key.');
    }
    return $account;
}

function pushEnabled(): bool
{
    return pushServiceAccount() !== null;
}

function pushQueueUser(PDO $pdo, int $userId, string $title, string $body, array $data = [], ?string $dedupKey = null): void
{
    if ($userId <= 0 || !pushEnabled()) return;
    pushEnqueue($pdo, ['kind' => 'user', 'user_id' => $userId, 'title' => $title, 'body' => $body, 'data' => $data, 'dedup' => $dedupKey]);
}

function pushQueueRequestFollowers(PDO $pdo, int $requestId, string $title, string $body, ?string $expectedStatus = null): void
{
    if ($requestId <= 0 || !pushEnabled()) return;
    pushEnqueue($pdo, ['kind' => 'request', 'request_id' => $requestId, 'title' => $title, 'body' => $body, 'expected_status' => $expectedStatus]);
}

function pushEnqueue(PDO $pdo, array $item): void
{
    static $registered = false;
    $GLOBALS['DASCARE_PUSH_QUEUE'][] = $item;
    if (!$registered) {
        $registered = true;
        register_shutdown_function(static function () use ($pdo) {
            try { pushFlush($pdo); } catch (Throwable $e) { error_log('Push flush failed: ' . $e->getMessage()); }
        });
    }
}

/** Resolve, verify and send everything queued during this request. */
function pushFlush(PDO $pdo): void
{
    $queue = $GLOBALS['DASCARE_PUSH_QUEUE'] ?? [];
    $GLOBALS['DASCARE_PUSH_QUEUE'] = [];
    foreach ($queue as $item) {
        foreach (pushResolveTargets($pdo, $item) as $target) {
            pushSend($pdo, $target['device_id'], $target['token'], $item['title'], $item['body'], $target['data']);
        }
    }
}

/** @return array<int, array{device_id:int, token:string, data:array}> */
function pushResolveTargets(PDO $pdo, array $item): array
{
    $targets = [];
    if ($item['kind'] === 'user') {
        if ($item['dedup'] !== null) {
            $check = $pdo->prepare('SELECT 1 FROM notifications WHERE user_id = ? AND dedup_key = ? LIMIT 1');
            $check->execute([$item['user_id'], $item['dedup']]);
            if (!$check->fetchColumn()) return []; // the notification never committed
        }
        $stmt = $pdo->prepare('SELECT id, fcm_token FROM push_devices WHERE user_id = ?');
        $stmt->execute([$item['user_id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $targets[] = ['device_id' => (int) $d['id'], 'token' => $d['fcm_token'], 'data' => $item['data']];
        }
        return $targets;
    }

    // Request followers.
    $requestId = (int) $item['request_id'];
    if ($item['expected_status'] !== null) {
        $check = $pdo->prepare('SELECT status FROM emergency_requests WHERE id = ?');
        $check->execute([$requestId]);
        if ($check->fetchColumn() !== $item['expected_status']) return [];
    }
    // Reports dedup linked to this incident: their requesters/guests follow it.
    $linked = $pdo->prepare("SELECT id, requester_user_id FROM emergency_requests WHERE merged_into_request_id = ? AND status = 'duplicate'");
    $linked->execute([$requestId]);
    $linkedRows = $linked->fetchAll(PDO::FETCH_ASSOC);
    $watchIds = array_merge([$requestId], array_map(fn($r) => (int) $r['id'], $linkedRows));

    $ph = implode(',', array_fill(0, count($watchIds), '?'));
    $stmt = $pdo->prepare("
        SELECT d.id, d.fcm_token, w.emergency_request_id
        FROM push_request_watch w
        INNER JOIN push_devices d ON d.id = w.push_device_id
        WHERE w.emergency_request_id IN ($ph)
    ");
    $stmt->execute($watchIds);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $targets[(int) $d['id']] = ['device_id' => (int) $d['id'], 'token' => $d['fcm_token'], 'data' => ['type' => 'request_update', 'request_id' => (string) $d['emergency_request_id']]];
    }
    $byUser = $pdo->prepare('SELECT id, fcm_token FROM push_devices WHERE user_id = ?');
    foreach ($linkedRows as $r) {
        if (empty($r['requester_user_id'])) continue;
        $byUser->execute([(int) $r['requester_user_id']]);
        foreach ($byUser->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $targets[(int) $d['id']] = ['device_id' => (int) $d['id'], 'token' => $d['fcm_token'], 'data' => ['type' => 'request_update', 'request_id' => (string) $r['id']]];
        }
    }
    return array_values($targets);
}

function pushSend(PDO $pdo, int $deviceId, string $token, string $title, string $body, array $data): void
{
    // Tests can swap the transport: $GLOBALS['DASCARE_PUSH_SENDER'] = fn($token, $title, $body, $data) => ...
    if (isset($GLOBALS['DASCARE_PUSH_SENDER']) && is_callable($GLOBALS['DASCARE_PUSH_SENDER'])) {
        ($GLOBALS['DASCARE_PUSH_SENDER'])($token, $title, $body, $data);
        return;
    }
    $account = pushServiceAccount();
    $accessToken = $account ? pushAccessToken($account) : null;
    if (!$accessToken) return;

    $message = [
        'message' => [
            'token' => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => array_map('strval', $data),
            'android' => [
                'priority' => 'HIGH',
                'notification' => ['channel_id' => PUSH_CHANNEL_ID, 'sound' => 'default', 'default_vibrate_timings' => true],
            ],
        ],
    ];
    [$status, $response] = pushHttp(
        'https://fcm.googleapis.com/v1/projects/' . rawurlencode($account['project_id']) . '/messages:send',
        json_encode($message),
        ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']
    );
    if ($status === 200) return;

    // The app was uninstalled / token rotated: forget this device.
    $error = json_decode((string) $response, true)['error'] ?? [];
    $code = '';
    foreach ($error['details'] ?? [] as $detail) $code = $detail['errorCode'] ?? $code;
    if ($status === 404 || $code === 'UNREGISTERED' || ($status === 400 && $code === 'INVALID_ARGUMENT' && str_contains((string) ($error['message'] ?? ''), 'token'))) {
        $pdo->prepare('DELETE FROM push_devices WHERE id = ?')->execute([$deviceId]);
        return;
    }
    error_log("FCM send failed ($status): " . substr((string) $response, 0, 300));
}

/** OAuth2 access token for FCM from the service account (cached ~55 min). */
function pushAccessToken(array $account): ?string
{
    $cacheFile = sys_get_temp_dir() . '/dascare_fcm_' . md5($account['client_email']) . '.json';
    $cached = is_readable($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
    if (!empty($cached['token']) && ($cached['expires_at'] ?? 0) > time() + 60) return $cached['token'];

    $now = time();
    $b64 = static fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = $b64(json_encode([
        'iss' => $account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $signature = '';
    if (!openssl_sign("$header.$claims", $signature, $account['private_key'], 'sha256WithRSAEncryption')) {
        error_log('Push: could not sign the FCM auth token (check the service account private key).');
        return null;
    }
    [$status, $response] = pushHttp(
        $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$header.$claims." . $b64($signature)]),
        ['Content-Type: application/x-www-form-urlencoded']
    );
    $json = json_decode((string) $response, true);
    if ($status !== 200 || empty($json['access_token'])) {
        error_log("Push: FCM auth failed ($status): " . substr((string) $response, 0, 300));
        return null;
    }
    @file_put_contents($cacheFile, json_encode(['token' => $json['access_token'], 'expires_at' => $now + (int) ($json['expires_in'] ?? 3600)]), LOCK_EX);
    return $json['access_token'];
}

/** @return array{0:int, 1:string|false} */
function pushHttp(string $url, string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => PUSH_HTTP_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $response];
}
