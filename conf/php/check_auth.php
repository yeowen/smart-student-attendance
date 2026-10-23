<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

echo json_encode([
    'authenticated' => true,
    'user' => [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'Professor',
        'email' => $_SESSION['user_email'] ?? '',
        'faculty_id' => $_SESSION['faculty_id'] ?? '',
        'title' => $_SESSION['user_title'] ?? 'Professor',
        'avatar_initials' => $_SESSION['avatar_initials'] ?? 'CP'
    ]
]);
