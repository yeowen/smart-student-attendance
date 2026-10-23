<?php
/**
 * Vercel Serverless Function Router for PHP
 * Dispatches incoming API requests and check-in pages to their respective handlers.
 */

// Set working directory to project root so all relative requires (__DIR__ / ../) function normally
$rootDir = dirname(__DIR__);
chdir($rootDir);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// 1. Student Check-in endpoints
if ($uri === '/checkin' || strpos($uri, '/checkin/') === 0 || $uri === '/student_checkin.php') {
    require $rootDir . '/student_checkin.php';
    exit;
}

// 2. Conf PHP API endpoints (e.g. /conf/php/login_process.php, /conf/php/dashboard_data.php)
if (strpos($uri, '/conf/php/') !== false) {
    $scriptName = basename($uri);
    $targetFile = $rootDir . '/conf/php/' . $scriptName;
    if (file_exists($targetFile)) {
        require $targetFile;
        exit;
    }
}

// 3. Fallback for any other root/subfolder PHP script (except api/index.php)
$cleanPath = ltrim($uri, '/');
if (!empty($cleanPath) && $cleanPath !== 'api/index.php' && substr($cleanPath, -4) === '.php' && file_exists($rootDir . '/' . $cleanPath)) {
    require $rootDir . '/' . $cleanPath;
    exit;
}

// 4. Default Not Found
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found for: ' . htmlspecialchars($uri)
]);
