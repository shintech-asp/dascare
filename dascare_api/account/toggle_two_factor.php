<?php
require '../cors.php';
header("Content-Type: application/json");
require '../db/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to update multi-factor authentication.'
    ]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

/* ================= READ JSON BODY ================= */
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

$current_password = trim($data['current_password'] ?? '');
$enable           = isset($data['enable']) ? (int) $data['enable'] : null;

/* ================= VALIDATION ================= */
if ($current_password === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Current password is required.'
    ]);
    exit;
}

if (!in_array($enable, [0, 1], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid multi-factor authentication action.'
    ]);
    exit;
}

try {
    /* ================= FETCH USER =================
     * FIX: this used to select `password` and verify against it, but
     * DASCARE's users table (see dascare.sql) stores the hash in
     * `password_hash`, same column login.php authenticates against.
     * The old code would fail password_verify() for every real user.
     */
    $stmt = $pdo->prepare("
        SELECT id, password_hash, has_two_factor
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found.'
        ]);
        exit;
    }

    /* ================= VERIFY PASSWORD ================= */
    if (!password_verify($current_password, $user['password_hash'])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect.'
        ]);
        exit;
    }

    $new_value = $enable ? 1 : 0;

    /* ================= NO-OP SAFE RESPONSE ================= */
    if ((int) $user['has_two_factor'] === $new_value) {
        echo json_encode([
            'success' => true,
            'message' => $new_value
                ? 'Multi-factor authentication is already enabled.'
                : 'Multi-factor authentication is already disabled.',
            'has_two_factor' => $new_value
        ]);
        exit;
    }

    /* ================= UPDATE ================= */
    $update = $pdo->prepare("
        UPDATE users
        SET has_two_factor = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$new_value, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => $new_value
            ? 'Multi-factor authentication enabled successfully.'
            : 'Multi-factor authentication disabled successfully.',
        'has_two_factor' => $new_value
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong. Please try again.'
        // 'error' => $e->getMessage() // debugging only
    ]);
}
