<?php
require '../cors.php';
header("Content-Type: application/json");

// ==================================================
// AUTOLOAD (Symfony Mailer)
// ==================================================
require __DIR__ . '/../vendor/autoload.php';

// ==================================================
// DB
// ==================================================
require __DIR__ . '/../db/db.php';
require __DIR__ . '/../reusables/password_helpers.php';
require __DIR__ . '/../reusables/rate_limit.php';
require __DIR__ . '/../reusables/email_helper.php';

// ==================================================
// READ INPUT
// ==================================================
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON payload"
    ]);
    exit;
}

$first_name = trim($data['first_name'] ?? '');
$last_name  = trim($data['last_name']  ?? '');
$email      = trim($data['email']      ?? '');
$phone      = trim($data['phone']      ?? '');
$password   = $data['password']        ?? '';

if ($first_name === '' || $last_name === '' || $email === '' || $phone === '' || $password === '') {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields"
    ]);
    exit;
}

$ip = getClientIp();

// ==================================================
// RATE LIMIT — throttle mass signups per IP
// ==================================================
checkRateLimit($pdo, 'register', $ip);

// ==================================================
// NAME VALIDATION
// ==================================================
if (strlen($first_name) < 2 || strlen($first_name) > 50) {
    echo json_encode(["success" => false, "message" => "First name must be between 2 and 50 characters"]);
    exit;
}
if (!preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ' -]+$/u", $first_name)) {
    echo json_encode(["success" => false, "message" => "First name contains invalid characters"]);
    exit;
}

if (strlen($last_name) < 2 || strlen($last_name) > 50) {
    echo json_encode(["success" => false, "message" => "Last name must be between 2 and 50 characters"]);
    exit;
}
if (!preg_match("/^[A-Za-zÀ-ÖØ-öø-ÿÑñ' -]+$/u", $last_name)) {
    echo json_encode(["success" => false, "message" => "Last name contains invalid characters"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address"]);
    exit;
}

// ==================================================
// PHONE VALIDATION
// PH mobile numbers, with or without country code: 09XXXXXXXXX or
// +639XXXXXXXXX. Adjust if DASCARE needs to accept landlines / other
// countries too — `users.phone` is UNIQUE and NOT NULL either way.
// ==================================================
$normalizedPhone = preg_replace('/[\s-]/', '', $phone);
if (!preg_match('/^(\+639\d{9}|09\d{9})$/', $normalizedPhone)) {
    echo json_encode(["success" => false, "message" => "Please enter a valid PH mobile number"]);
    exit;
}

// ==================================================
// PASSWORD VALIDATION
// ==================================================
$strengthError = passwordStrengthError($password, [$email, $first_name, $last_name]);
if ($strengthError !== null) {
    echo json_encode(["success" => false, "message" => $strengthError]);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, email_verified_at FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        if ($existingUser['email_verified_at'] !== null) {
            $pdo->rollBack();
            echo json_encode(["success" => false, "message" => "Email already registered"]);
            exit;
        }

        // No FK ON DELETE CASCADE in this schema, so clean up the
        // dependent row ourselves before removing the stale unverified user.
        $pdo->prepare("DELETE FROM user_security_tokens WHERE user_id = :id")
            ->execute([':id' => $existingUser['id']]);
        $pdo->prepare("DELETE FROM user_roles WHERE user_id = :id")
            ->execute([':id' => $existingUser['id']]);
        $pdo->prepare("
            DELETE FROM users
            WHERE id = :id
              AND email_verified_at IS NULL
        ")->execute([':id' => $existingUser['id']]);
    }

    // Phone is UNIQUE — check separately so we can give a clear message
    // instead of surfacing a raw constraint-violation error.
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$normalizedPhone]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => "Phone number already registered"]);
        exit;
    }

    // ==================================================
    // GENERATE OTP
    // ==================================================
    $otp        = random_int(100000, 999999);
    $hashedOtp  = password_hash((string) $otp, PASSWORD_DEFAULT);
    $otpExpiry  = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // ==================================================
    // INSERT USER (UNVERIFIED / PENDING)
    // ==================================================
    $stmt = $pdo->prepare("
        INSERT INTO users
        (first_name, last_name, email, phone, password_hash, account_status, email_verified_at)
        VALUES (:first_name, :last_name, :email, :phone, :password_hash, 'pending', NULL)
    ");
    $stmt->execute([
        ':first_name'    => $first_name,
        ':last_name'     => $last_name,
        ':email'         => $email,
        ':phone'         => $normalizedPhone,
        ':password_hash' => $hashedPassword,
    ]);

    $userId = $pdo->lastInsertId();

    // ==================================================
    // ASSIGN DEFAULT ROLE
    // Every public signup through this form is a citizen. Org staff
    // (dispatcher / rescue_personnel / organization_admin) get added
    // through the organization-onboarding flow instead, same
    // separation Likhavite draws between this form and "Start Selling".
    // ==================================================
    $stmt = $pdo->prepare("
        INSERT INTO user_roles (user_id, role)
        VALUES (:user_id, 'citizen')
    ");
    $stmt->execute([':user_id' => $userId]);

    // ==================================================
    // STORE OTP
    // ==================================================
    $stmt = $pdo->prepare("
        INSERT INTO user_security_tokens (user_id, email_otp, email_otp_expires_at)
        VALUES (:user_id, :email_otp, :email_otp_expires_at)
    ");
    $stmt->execute([
        ':user_id'              => $userId,
        ':email_otp'            => $hashedOtp,
        ':email_otp_expires_at' => $otpExpiry,
    ]);

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    recordAttempt($pdo, 'register', $ip);

    echo json_encode([
        "success" => false,
        "message" => "Registration failed. Please try again."
    ]);
    exit;
}

// ==================================================
// SEND OTP EMAIL
// ==================================================
$body = render_email_template(
    'Welcome to DASCARE',
    "Verify your<br><span style='color:#c0392b;'>email address</span>",
    "<div style='font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.8; color:#6b7280; margin:0 0 28px 0;'>
        Thanks for signing up! Please use the verification code below to confirm your email address and complete your DASCARE account registration.
     </div>" . render_otp_card((string) $otp),
    "<strong style='color:#0f203a;'>This code expires in 10 minutes.</strong><br>If you did not create an account with DASCARE, you can safely ignore this email."
);

$result = send_email($email, 'Verify Your Email – DASCARE', $body);

if (!$result['success']) {
    // Rollback: remove the unverified user since the email failed to send
    $pdo->prepare("DELETE FROM user_security_tokens WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
    $pdo->prepare("DELETE FROM users WHERE id = ? AND email_verified_at IS NULL")->execute([$userId]);

    recordAttempt($pdo, 'register', $ip);

    echo json_encode([
        "success" => false,
        "message" => "Failed to send verification email"
    ]);
    exit;
}

// Record the attempt AFTER a successful send too — otherwise
// successful signups (the exact case 'register' is meant to
// throttle) never count against the per-IP cap.
recordAttempt($pdo, 'register', $ip);

// ==================================================
// RESPONSE
// ==================================================
echo json_encode([
    "success" => true,
    "message" => "OTP sent to your email",
    "email"   => $email
]);