<?php

/**
 * Deployment settings from dascare_api/config/app.json (gitignored).
 * Absent locally, so XAMPP keeps its defaults; on the server it holds:
 *
 * {
 *   "db": { "host": "localhost", "port": 3306, "name": "...", "user": "...", "password": "...", "time_zone": "+08:00" },
 *   "timezone": "Asia/Manila",                        // PHP's clock
 *   "allowed_origins": ["https://example.com"],      // added to cors.php's dev list
 *   "uploads_dir": "/home/me/private/uploads",        // KYC IDs / emergency photos, outside the web root
 *   "secure_cookies": true                            // session cookie only over HTTPS
 * }
 *
 *   appConfig('db.name', 'dascare')  → value at that dotted path, or the default
 */
function appConfig(string $path, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/../config/app.json';
        $config = is_readable($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }
    $value = $config;
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return $default;
        $value = $value[$key];
    }
    return $value;
}
