<?php
require '../cors.php';
header("Content-Type: application/json");
require '../db/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update your profile.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// ==================================================
// READ INPUT
// Sent as multipart/form-data (not JSON) so this can grow to accept
// a profile photo later without changing the request shape — same
// reason the Likhavite version this was ported from used $_POST.
// DASCARE's users table has no image column yet, so there's no
// upload branch here.
// ==================================================
$first_name              = trim($_POST['first_name'] ?? '');
$last_name                = trim($_POST['last_name'] ?? '');
$phone                    = trim($_POST['phone'] ?? '');
$current_password         = $_POST['current_password'] ?? '';
$new_password             = $_POST['new_password'] ?? '';
$new_password_confirmation = $_POST['new_password_confirmation'] ?? '';

/* ================= NAME ================= */
if ($first_name === '' || $last_name === '') {
    echo json_encode(['success' => false, 'message' => 'First name and last name are required.']);
    exit;
}

if (
    !preg_match('/^[a-zA-Z\s\-]+$/', $first_name) ||
    !preg_match('/^[a-zA-Z\s\-]+$/', $last_name)
) {
    echo json_encode(['success' => false, 'message' => 'Names may only contain letters, spaces, and hyphens.']);
    exit;
}

/* ================= PHONE ================= */
if ($phone === '') {
    echo json_encode(['success' => false, 'message' => 'Mobile number is required.']);
    exit;
}
if (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid mobile number.']);
    exit;
}

/* ==================================================
 * Minimal self-contained strength check. DASCARE doesn't have a
 * shared reusables/password_helpers.php confirmed in this codebase
 * (unlike the Likhavite system edit_profile.php was ported from),
 * so this is inlined rather than requiring a file that may not exist.
 * If a shared helper does exist here, swap this block for that call.
 * ================================================== */
function dascare_password_strength_error(string $password, array $disallowedFragments): ?string
{
    if (strlen($password) < 10) {
        return 'New password must be at least 10 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'New password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'New password must include at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'New password must include at least one number.';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'New password must include at least one symbol.';
    }

    $lowerPassword = strtolower($password);
    foreach ($disallowedFragments as $fragment) {
        $fragment = trim((string) $fragment);
        if ($fragment !== '' && strlen($fragment) >= 3 && str_contains($lowerPassword, strtolower($fragment))) {
            return 'New password should not contain your name or email.';
        }
    }

    return null;
}

try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    /* ================= PASSWORD CHANGE (optional) ================= */
    $wantsPasswordChange = $new_password !== '' || $new_password_confirmation !== '' || $current_password !== '';
    $newHashedPassword = null;

    if ($wantsPasswordChange) {
        if ($current_password === '') {
            echo json_encode(['success' => false, 'message' => 'Enter your current password to change it.']);
            exit;
        }

        if (!password_verify($current_password, $existingUser['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'The current password you entered is incorrect.']);
            exit;
        }

        if ($new_password === '') {
            echo json_encode(['success' => false, 'message' => 'Enter a new password.']);
            exit;
        }

        if ($new_password !== $new_password_confirmation) {
            echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
            exit;
        }

        $strengthError = dascare_password_strength_error($new_password, [
            $existingUser['email'] ?? '',
            $first_name,
            $last_name,
        ]);
        if ($strengthError !== null) {
            echo json_encode(['success' => false, 'message' => $strengthError]);
            exit;
        }

        if (password_verify($new_password, $existingUser['password_hash'])) {
            echo json_encode(['success' => false, 'message' => 'New password must be different from your current password.']);
            exit;
        }

        $newHashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
    }

    /* ================= BUILD UPDATE ================= */
    $fields = "first_name = ?, last_name = ?, phone = ?";
    $params = [$first_name, $last_name, $phone];

    if ($newHashedPassword !== null) {
        $fields  .= ", password_hash = ?";
        $params[] = $newHashedPassword;
    }

    $params[] = $user_id;

    $update = $pdo->prepare("UPDATE users SET $fields, updated_at = NOW() WHERE id = ?");
    $update->execute($params);

    /* ================= KEEP SESSION IN SYNC ================= */
    $_SESSION['user_name']  = $first_name . ' ' . $last_name;
    $_SESSION['user_phone'] = $phone;

    echo json_encode([
        'success' => true,
        'message' => $newHashedPassword !== null
            ? 'Your profile and password have been updated.'
            : 'Your profile has been updated.',
        'user' => [
            'name'  => $_SESSION['user_name'],
            'phone' => $_SESSION['user_phone'],
        ],
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong. Please try again.'
        // 'error' => $e->getMessage() // debugging only
    ]);
}
