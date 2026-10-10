<?php
require_once __DIR__ . '/reusables/app_config.php';
// PHP's own clock (config/app.json "timezone", e.g. "Asia/Manila"). Unset locally.
if (is_string(appConfig('timezone')) && in_array(appConfig('timezone'), timezone_identifiers_list(), true)) {
    date_default_timezone_set(appConfig('timezone'));
}

// ----------------------------------
// SESSION COOKIE CONFIG (CRITICAL)
// ----------------------------------
ini_set('session.cookie_httponly', '1');
if (appConfig('secure_cookies', false)) ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');

// ----------------------------------
// CORS CONFIGURATION
// ----------------------------------
$allowed_origins = [
    'http://localhost:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5173',
    // DASCARE Android app (dascare_mobile/): the Capacitor WebView origin,
    // and the app's browser preview (npm run dev in dascare_mobile/).
    'http://localhost',
    'http://localhost:5180'
];
// The deployed site's own origin(s), from config/app.json.
$allowed_origins = array_merge($allowed_origins, (array) appConfig('allowed_origins', []));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Dascare-Client, X-Guest-Tokens');

// ----------------------------------
// HANDLE PREFLIGHT
// ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ----------------------------------
// START SESSION
// ----------------------------------
// Android app requests (dascare_mobile/) identify themselves with
// X-Dascare-Client: mobile and authenticate with a bearer token instead of a
// cookie — see reusables/mobile_auth.php. The web never sends that header,
// so it always takes the session_start() branch exactly as before.
if (($_SERVER['HTTP_X_DASCARE_CLIENT'] ?? '') === 'mobile') {
    require_once __DIR__ . '/db/db.php';
    require_once __DIR__ . '/reusables/mobile_auth.php';
    mobileAuthBootstrap($pdo);
} elseif (session_status() === PHP_SESSION_NONE) {
    session_start();
}