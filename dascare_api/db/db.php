<?php
require_once __DIR__ . '/../reusables/app_config.php';

// Local XAMPP defaults; the server overrides them in config/app.json ("db").
$host = (string) appConfig('db.host', 'localhost');
$port = (int) appConfig('db.port', 3306);
$dbname = (string) appConfig('db.name', 'dascare');
$username = (string) appConfig('db.user', 'root');
$password = (string) appConfig('db.password', '');

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    // Server DB clock may be UTC; DASCARE's timestamps are Philippine time
    // (config/app.json "db.time_zone", e.g. "+08:00"). Unset locally.
    $dbTimeZone = appConfig('db.time_zone');
    if (is_string($dbTimeZone) && preg_match('/^[+-]\d{2}:\d{2}$/', $dbTimeZone)) {
        $pdo->exec("SET time_zone = '$dbTimeZone'");
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Database connection failed: ' . $e->getMessage());
    echo json_encode([
        "error" => "Database connection failed",
        // Details only on local XAMPP; never expose them on the public server.
        "message" => appConfig('db') === null ? $e->getMessage() : "Please try again shortly."
    ]);
    exit;
}
