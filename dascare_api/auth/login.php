<?php
require '../cors.php';
header("Content-Type: application/json");

require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/rate_limit.php';
require __DIR__ . '/../reusables/email_helper.php';

// ==================================================
// READ JSON INPUT
// ==================================================
$data = json_decode(file_get_contents("php://input"), true);

$email         = trim($data['email'] ?? '');
$passwordInput = $data['password'] ?? '';

if ($email === '' || $passwordInput === '') {
    echo json_encode([
        "success" => false,
        "message" => "Missing email or password"
    ]);
    exit;
}

$ip = getClientIp();

// ==================================================
// RATE LIMIT CHECK
// Block by BOTH IP and email to cover two attack vectors:
//   - IP check    → stops bots hammering from one IP with many emails
//   - Email check → stops distributed attacks targeting one account
// ==================================================
checkRateLimit($pdo, 'login', $ip);
checkRateLimit($pdo, 'login', $email);

// ==================================================
// FETCH USER
// Schema notes vs. the Likhavite version this was ported from:
//   - password_hash   -> users.password_hash (not users.password)
//   - account_status   -> 'pending' | 'active' | 'suspended' | 'disabled'
//   - email_verified_at -> timestamp, not a 0/1 flag
//   - no user_addresses table here, so no default-address join
//   - role comes from user_roles, one row per user (UNIQUE user_id)
// ==================================================
$stmt = $pdo->prepare("
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.phone,
        u.email,
        u.password_hash,
        u.email_verified_at,
        u.account_status,
        u.has_two_factor,
        ur.role
    FROM users u
    LEFT JOIN user_roles ur
        ON ur.user_id = u.id
    WHERE u.email = ?
      AND u.deleted_at IS NULL
    LIMIT 1
");

$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Record attempt — email enumeration still feeds the counter
    recordAttempt($pdo, 'login', $ip);
    recordAttempt($pdo, 'login', $email);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"  // unified message (no enumeration)
    ]);
    exit;
}

// ==================================================
// CHECK ACCOUNT STATUS
// ==================================================
if ($user['account_status'] === 'suspended') {
    // Don't count suspended logins against the rate limit
    echo json_encode([
        "success" => false,
        "message" => "Your account has been suspended. Please contact support for assistance.",
        "code"    => "ACCOUNT_SUSPENDED"
    ]);
    exit;
}

if ($user['account_status'] === 'disabled') {
    echo json_encode([
        "success" => false,
        "message" => "Your account has been deactivated. Please contact support for assistance.",
        "code"    => "ACCOUNT_DEACTIVATED"
    ]);
    exit;
}

// ==================================================
// CHECK EMAIL VERIFIED
// (account_status stays 'pending' until verify_otp.php flips it,
// so this also implicitly blocks any not-yet-activated account.)
// ==================================================
if ($user['email_verified_at'] === null) {
    echo json_encode([
        "success" => false,
        "message" => "Please verify your email first"
    ]);
    exit;
}

// ==================================================
// VERIFY PASSWORD
// ==================================================
if (!password_verify($passwordInput, $user['password_hash'])) {
    recordAttempt($pdo, 'login', $ip);
    recordAttempt($pdo, 'login', $email);

    echo json_encode([
        "success" => false,
        "message" => "Invalid email or password"  // unified message (no enumeration)
    ]);
    exit;
}

// ==================================================
// PASSWORD OK — clear login attempts either way
// ==================================================
clearAttempts($pdo, 'login', $ip);
clearAttempts($pdo, 'login', $email);

// ==================================================
// NOTE — dropped from the Likhavite version:
// The shop-slug "wrong door" check doesn't have a DASCARE
// equivalent yet. If organization staff (dispatcher/rescue_personnel)
// end up needing an org-scoped login door later, it'd hang off
// `organization_members` the same way the Likhavite check hung off
// `artisan_shops`/`business_accounts` — not building that speculatively
// here since nothing requested it.
// ==================================================

// ==================================================
// TWO-FACTOR AUTHENTICATION
// If has_two_factor = 1, don't establish the real session yet.
// Generate + email an OTP, stash a short-lived "pending" marker in
// the session, and tell the frontend requires_2fa = true.
// ==================================================
if ((int) $user['has_two_factor'] === 1) {

    $otp        = random_int(100000, 999999);
    $hashedOtp  = password_hash((string) $otp, PASSWORD_DEFAULT);
    $otpExpiry  = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $tokenStmt = $pdo->prepare("SELECT id FROM user_security_tokens WHERE user_id = ?");
    $tokenStmt->execute([$user['id']]);
    $tokenRow = $tokenStmt->fetch();

    if ($tokenRow) {
        $pdo->prepare("
            UPDATE user_security_tokens
            SET login_otp = ?, login_otp_expires_at = ?
            WHERE user_id = ?
        ")->execute([$hashedOtp, $otpExpiry, $user['id']]);
    } else {
        $pdo->prepare("
            INSERT INTO user_security_tokens (user_id, login_otp, login_otp_expires_at)
            VALUES (?, ?, ?)
        ")->execute([$user['id'], $hashedOtp, $otpExpiry]);
    }

    // ==================================================
    // SEND OTP EMAIL
    // ==================================================
    $body = render_email_template(
        'Two-Factor Authentication',
        "Verify your<br><span style='color:#c0392b;'>login attempt</span>",
        "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 28px 0;'>
            We received a login attempt on your account. Use the verification code below to complete sign-in.
         </div>" . render_otp_card((string) $otp),
        "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>If this wasn't you, change your password immediately."
    );

    $result = send_email($user['email'], 'Your Login Verification Code – DASCARE', $body);

    if (!$result['success']) {
        echo json_encode([
            "success" => false,
            "message" => "Failed to send verification code. Please try again."
        ]);
        exit;
    }

    // ==================================================
    // Stash a PENDING login marker only — do NOT set the
    // real user_id/user_level/etc session vars yet.
    // ==================================================
    session_regenerate_id(true);
    $_SESSION['pending_2fa_user_id']    = $user['id'];
    $_SESSION['pending_2fa_expires_at'] = strtotime('+10 minutes');

    echo json_encode([
        "success"      => true,
        "requires_2fa" => true,
        "message"      => "Verification code sent to your email"
    ]);
    exit;
}

// ==================================================
// LOGIN SUCCESS (no 2FA) — establish real session
// ==================================================
session_regenerate_id(true);

$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_phone'] = $user['phone'];
$_SESSION['user_level'] = $user['role'];

// ==================================================
// TRACK LAST LOGIN
// users.last_login_at exists directly on the table in this schema
// (unlike Likhavite's business_accounts.last_login_at, which was
// scoped to employees only) — so this just applies to everyone.
// ==================================================
$pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

echo json_encode([
    "success" => true,
    "user"    => [
        "id"    => $_SESSION['user_id'],
        "name"  => $_SESSION['user_name'],
        "email" => $_SESSION['user_email'],
        "phone" => $_SESSION['user_phone'],
        "level" => $_SESSION['user_level']
    ]
]);
