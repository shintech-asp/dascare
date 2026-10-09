<?php

/**
 * DASCARE mobile app (dascare_mobile/) authentication.
 *
 * The Android app can't rely on cookies, so it sends:
 *   X-Dascare-Client: mobile          — always; switches this request to app mode
 *   Authorization: Bearer <token>     — when a citizen is logged in
 *   X-Guest-Tokens: <key>,<key>       — keys for guest SOS requests sent from this phone
 *
 * cors.php calls mobileAuthBootstrap() ONLY when X-Dascare-Client is
 * "mobile". In that mode no PHP session/cookie is started; instead $_SESSION
 * is filled for this one request from the token, with the same keys
 * auth/login.php sets (user_id, user_name, user_email, user_phone,
 * user_level). Every existing endpoint that reads $_SESSION therefore works
 * for the app unchanged. Web requests never send that header and take the
 * normal session_start() path.
 *
 * Tokens are only ever issued to active citizens (mobile/auth/login.php) and
 * re-checked on every request, so a staff/admin account can't use the app
 * even with a token. An invalid/expired token never blocks a request — it
 * is treated as a guest (so an SOS still goes through) and the response
 * carries X-Dascare-Token-Status: invalid so the app can sign out.
 */

const MOBILE_ACCESS_TTL_SECONDS = 60 * 24 * 3600; // 60 days, revocable via logout
const MOBILE_2FA_TTL_SECONDS = 10 * 60;           // same 10 min as the web's 2FA window
const MOBILE_MAX_GUEST_TOKENS = 20;

function mobileRequestHeader(string $name): string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (!empty($_SERVER[$key])) return trim((string) $_SERVER[$key]);
    // Apache can strip Authorization before PHP sees it in $_SERVER.
    if ($name === 'Authorization' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) return trim((string) $v);
        }
    }
    return '';
}

function mobileTokenHash(string $plain): string
{
    return hash('sha256', $plain);
}

function mobileBearerToken(): string
{
    $auth = mobileRequestHeader('Authorization');
    return stripos($auth, 'Bearer ') === 0 ? trim(substr($auth, 7)) : '';
}

/** Issue a token and return the PLAIN value (only its hash is stored). */
function mobileIssueToken(PDO $pdo, int $userId, string $type, ?string $deviceName = null): string
{
    $ttl = $type === 'login_2fa' ? MOBILE_2FA_TTL_SECONDS : MOBILE_ACCESS_TTL_SECONDS;
    $plain = ($type === 'login_2fa' ? 'dc2fa_' : 'dcm_') . bin2hex(random_bytes(32));
    // Expiry on the DB clock, like the rest of the API (PHP and MariaDB
    // timezones differ on this machine). $ttl is a constant int.
    $pdo->prepare("
        INSERT INTO api_tokens (user_id, token_hash, token_type, device_name, expires_at)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL {$ttl} SECOND))
    ")->execute([$userId, mobileTokenHash($plain), $type, $deviceName !== null ? mb_substr($deviceName, 0, 120) : null]);
    return $plain;
}

/**
 * Look up a live token of $type and its user. Returns null unless the token
 * exists, isn't revoked/expired, and belongs to an active, verified citizen.
 */
function mobileFindToken(PDO $pdo, string $plain, string $type): ?array
{
    if ($plain === '' || strlen($plain) > 200) return null;
    $stmt = $pdo->prepare("
        SELECT t.id AS token_id, t.last_used_at,
               (t.last_used_at IS NULL OR t.last_used_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)) AS needs_touch,
               u.id, u.first_name, u.last_name, u.email, u.phone, u.account_status, u.email_verified_at,
               ur.role
        FROM api_tokens t
        INNER JOIN users u ON u.id = t.user_id AND u.deleted_at IS NULL
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        WHERE t.token_hash = ?
          AND t.token_type = ?
          AND t.revoked_at IS NULL
          AND t.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([mobileTokenHash($plain), $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if ($row['role'] !== 'citizen' || $row['account_status'] !== 'active' || $row['email_verified_at'] === null) return null;
    return $row;
}

function mobileRevokeToken(PDO $pdo, int $tokenId): void
{
    $pdo->prepare('UPDATE api_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL')->execute([$tokenId]);
}

/** Same user shape auth/login.php returns to the web. */
function mobileUserPayload(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
        'email' => $user['email'],
        'phone' => $user['phone'],
        'level' => $user['role'],
    ];
}

/** Called from cors.php for app requests only (see file header). */
function mobileAuthBootstrap(PDO $pdo): void
{
    header('Access-Control-Expose-Headers: X-Dascare-Token-Status');
    $_SESSION = [];
    $GLOBALS['DASCARE_MOBILE'] = ['token_id' => null];

    $bearer = mobileBearerToken();
    if ($bearer !== '') {
        try {
            $row = mobileFindToken($pdo, $bearer, 'access');
        } catch (Throwable $e) {
            error_log('Mobile token lookup failed: ' . $e->getMessage());
            $row = null;
        }
        if ($row) {
            $_SESSION['user_id'] = (int) $row['id'];
            $_SESSION['user_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_phone'] = $row['phone'];
            $_SESSION['user_level'] = $row['role'];
            $GLOBALS['DASCARE_MOBILE']['token_id'] = (int) $row['token_id'];
            if ((int) $row['needs_touch'] === 1) {
                $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?')->execute([(int) $row['token_id']]);
            }
            header('X-Dascare-Token-Status: valid');
        } else {
            header('X-Dascare-Token-Status: invalid');
        }
    }

    // Guest SOS keys → the same $_SESSION['guest_request_ids'] that
    // citizen/unmerge.php already checks for same-session web guests.
    $guestHeader = mobileRequestHeader('X-Guest-Tokens');
    if ($guestHeader !== '') {
        $keys = array_slice(array_filter(array_map('trim', explode(',', $guestHeader))), 0, MOBILE_MAX_GUEST_TOKENS);
        if ($keys) {
            try {
                $ph = implode(',', array_fill(0, count($keys), '?'));
                $stmt = $pdo->prepare("SELECT emergency_request_id FROM guest_request_tokens WHERE token_hash IN ($ph)");
                $stmt->execute(array_map('mobileTokenHash', $keys));
                $_SESSION['guest_request_ids'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (Throwable $e) {
                error_log('Mobile guest token lookup failed: ' . $e->getMessage());
            }
        }
    }
}

/** Create the private key for a guest SOS sent from the app; returns it. */
function mobileIssueGuestRequestToken(PDO $pdo, int $requestId): string
{
    $plain = 'dcg_' . bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO guest_request_tokens (emergency_request_id, token_hash) VALUES (?, ?)')
        ->execute([$requestId, mobileTokenHash($plain)]);
    return $plain;
}

/**
 * Generate + email a login 2FA code — same code format, storage
 * (user_security_tokens.login_otp, hashed), 10-minute expiry and email
 * template as the web's auth/login.php. Returns false if the email failed.
 */
function mobileSend2faCode(PDO $pdo, array $user): bool
{
    require_once __DIR__ . '/email_helper.php';
    $otp = random_int(100000, 999999);
    $hashedOtp = password_hash((string) $otp, PASSWORD_DEFAULT);
    $otpExpiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $exists = $pdo->prepare('SELECT id FROM user_security_tokens WHERE user_id = ?');
    $exists->execute([$user['id']]);
    if ($exists->fetch()) {
        $pdo->prepare('UPDATE user_security_tokens SET login_otp = ?, login_otp_expires_at = ? WHERE user_id = ?')
            ->execute([$hashedOtp, $otpExpiry, $user['id']]);
    } else {
        $pdo->prepare('INSERT INTO user_security_tokens (user_id, login_otp, login_otp_expires_at) VALUES (?, ?, ?)')
            ->execute([$user['id'], $hashedOtp, $otpExpiry]);
    }

    $body = render_email_template(
        'Two-Factor Authentication',
        "Verify your<br><span style='color:#c0392b;'>login attempt</span>",
        "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 28px 0;'>
            We received a login attempt on your account from the DASCARE app. Use the verification code below to complete sign-in.
         </div>" . render_otp_card((string) $otp),
        "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>If this wasn't you, change your password immediately."
    );
    $result = send_email($user['email'], 'Your Login Verification Code – DASCARE', $body);
    return !empty($result['success']);
}

function isMobileAppRequest(): bool
{
    return isset($GLOBALS['DASCARE_MOBILE']);
}

function mobileJson(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}
