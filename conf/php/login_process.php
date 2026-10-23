<?php
// Prevent any PHP notice/warning output from corrupting JSON
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$facultyId = trim($_POST['facultyId'] ?? $input['facultyId'] ?? '');
$password = $_POST['password'] ?? $input['password'] ?? '';
$rememberMe = !empty($_POST['rememberMe']) || !empty($input['rememberMe']);

if (empty($facultyId) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill in both Faculty ID/Email and Password.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email OR faculty_id = :faculty_id LIMIT 1");
    $stmt->execute([
        'email' => $facultyId,
        'faculty_id' => $facultyId
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['faculty_id'] = $user['faculty_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_title'] = $user['title'];
        $_SESSION['avatar_initials'] = $user['avatar_initials'];

        if ($rememberMe) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), time() + (86400 * 30), $params["path"] ?? '/', $params["domain"] ?? '', $params["secure"] ?? false, $params["httponly"] ?? true);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Welcome back, ' . $user['name'] . '!',
            'redirect' => 'dashboard.html',
            'user' => [
                'name' => $user['name'],
                'email' => $user['email'],
                'faculty_id' => $user['faculty_id']
            ]
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Faculty ID/email or password.'
        ]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
