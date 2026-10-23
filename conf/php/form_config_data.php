<?php
// form_config_data.php — Save/Load form configuration
header('Content-Type: application/json; charset=utf-8');
session_start();

// Auth check (skip for GET if loading config for student form)
$CONFIG_FILE = __DIR__ . '/../form_config.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require auth to save
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'save_config') {
        $rawConfig = $_POST['config'] ?? '';
        if (empty($rawConfig)) {
            echo json_encode(['success' => false, 'message' => 'No config received']);
            exit;
        }
        $decoded = json_decode($rawConfig, true);
        if (!is_array($decoded)) {
            echo json_encode(['success' => false, 'message' => 'Invalid config format']);
            exit;
        }
        file_put_contents($CONFIG_FILE, json_encode($decoded, JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'message' => 'Form config saved to server']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// GET — Load config (public, used by student_checkin.php)
if (file_exists($CONFIG_FILE)) {
    $raw = file_get_contents($CONFIG_FILE);
    $cfg = json_decode($raw, true);
    if (is_array($cfg)) {
        echo json_encode(['success' => true, 'config' => $cfg]);
        exit;
    }
}

// Default config fallback
echo json_encode([
    'success' => true,
    'config'  => [
        'fields'        => ['student_number' => true, 'full_name' => true, 'course' => true, 'year_level' => true, 'email' => false, 'remarks' => false],
        'custom_fields' => [],
        'settings'      => ['confirm' => true, 'success_page' => true, 'block_duplicate' => true, 'masterlist_only' => true]
    ]
]);
