<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';

// Authentication Check
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Handle POST: Update Profile / Password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $facultyId = trim($_POST['faculty_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $title = trim($_POST['title'] ?? 'Professor');

        if ($facultyId === '' || $name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Enter a Faculty ID, name, and valid email address.']);
            exit;
        }

        try {
            $initials = '';
            foreach (preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                preg_match('/^./us', $part, $firstCharacter);
                $initials .= $firstCharacter[0] ?? substr($part, 0, 1);
                if (preg_match_all('/./us', $initials) >= 2) break;
            }
            $initials = strtoupper($initials ?: 'F');
            $upStmt = $pdo->prepare("UPDATE users SET faculty_id = ?, name = ?, email = ?, title = ?, avatar_initials = ? WHERE id = ?");
            $upStmt->execute([$facultyId, $name, $email, $title, $initials, $userId]);

            $_SESSION['faculty_id'] = $facultyId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_title'] = $title;
            $_SESSION['avatar_initials'] = $initials;

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                echo json_encode(['success' => false, 'message' => 'That Faculty ID or email address is already in use.']);
                exit;
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update profile. Please try again.']);
            exit;
        }
    }

    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        if (strlen($newPass) < 4) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 4 characters']);
            exit;
        }

        $chkStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $chkStmt->execute([$userId]);
        $currentHashed = $chkStmt->fetchColumn();

        if (!password_verify($oldPass, $currentHashed)) {
            echo json_encode(['success' => false, 'message' => 'Current password does not match']);
            exit;
        }

        $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
        $upPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upPass->execute([$newHashed, $userId]);

        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
        exit;
    }

    if ($action === 'toggle_schedule_reminder') {
        $enabled = !empty($_POST['schedule_reminder']) ? 1 : 0;
        try {
            $upRem = $pdo->prepare("UPDATE users SET schedule_reminder = ? WHERE id = ?");
            $upRem->execute([$enabled, $userId]);
            echo json_encode([
                'success' => true,
                'schedule_reminder' => $enabled,
                'message' => $enabled ? 'Class schedule reminder turned ON!' : 'Class schedule reminder turned OFF.'
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update reminder: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// GET: Professor Profile
try {
    $stmt = $pdo->prepare("SELECT id, faculty_id, name, email, title, avatar_initials, schedule_reminder, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'user' => $user,
        'today_date' => date('l, F j, Y')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
