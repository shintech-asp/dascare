<?php
// mobile/auth/logout.php
// Revokes the app's bearer token (sent in the Authorization header). Always
// answers success so the app can clear its stored token either way.
require_once __DIR__ . '/../../cors.php';
require_once __DIR__ . '/../../db/db.php';
require_once __DIR__ . '/../../reusables/mobile_auth.php';
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') mobileJson(405, ['success' => false, 'message' => 'Method not allowed.']);

$tokenId = $GLOBALS['DASCARE_MOBILE']['token_id'] ?? null;
if ($tokenId) mobileRevokeToken($pdo, (int) $tokenId);

mobileJson(200, ['success' => true, 'message' => 'Signed out.']);
