<?php
// Prevent any PHP notice/warning from corrupting JSON API outputs
error_reporting(0);
ini_set('display_errors', '0');

// Set PHP timezone to Philippine Standard Time (PST / Asia/Manila, UTC+8)
date_default_timezone_set('Asia/Manila');

// Database Connection Configuration
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db_name = getenv('DB_NAME') ?: 'smart_attendance_db';
$db_port = getenv('DB_PORT') ?: '3306';

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    // Enable SSL for remote cloud databases (e.g. TiDB Cloud)
    if ($db_host !== '127.0.0.1' && $db_host !== 'localhost') {
        $caCert = __DIR__ . '/../isrgrootx1.pem';
        if (file_exists($caCert)) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $caCert;
        } elseif (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        } elseif (file_exists('/etc/pki/tls/certs/ca-bundle.crt')) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = '/etc/pki/tls/certs/ca-bundle.crt';
        }
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $db_user, $db_pass, $pdoOptions);
    // Set MySQL session time zone to Philippine Standard Time (UTC+8)
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}
