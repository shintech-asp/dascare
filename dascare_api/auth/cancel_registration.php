<?php
require '../cors.php';
header("Content-Type: application/json");
// ==================================================
// DB
// ==================================================
require __DIR__ . '/../db/db.php';

// ==================================================
// INPUT
// ==================================================
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if ($email === '') {
    echo json_encode(["success" => false]);
    exit;
}

// ==================================================
// DELETE ONLY UNVERIFIED USER
// No FK ON DELETE CASCADE in this schema, so clean up dependents
// first (user_security_tokens, user_roles) before the users row.
// ==================================================
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND email_verified_at IS NULL");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $pdo->prepare("DELETE FROM user_security_tokens WHERE user_id = ?")->execute([$user['id']]);
        $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$user['id']]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND email_verified_at IS NULL")->execute([$user['id']]);
    }

    $pdo->commit();
    echo json_encode(["success" => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false]);
}
