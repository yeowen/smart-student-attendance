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

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_section') {
        $sectionName = trim($_POST['section_name'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $room = trim($_POST['room'] ?? 'Lab 304');
        $scheduleDay = '';
        if (!empty($_POST['schedule_days']) && is_array($_POST['schedule_days'])) {
            $scheduleDay = implode(', ', array_map('trim', $_POST['schedule_days']));
        } elseif (!empty($_POST['schedule_day'])) {
            $scheduleDay = trim($_POST['schedule_day']);
        } else {
            $scheduleDay = date('l');
        }
        $startTime = $_POST['start_time'] ?? '08:00:00';
        $endTime = $_POST['end_time'] ?? '09:30:00';

        if (empty($sectionName)) {
            echo json_encode(['success' => false, 'message' => 'Section name is required']);
            exit;
        }
        // Schedule conflict validation: prevent overlapping sections on same day(s)
        $conflictFound = false;
        $conflictSectionName = '';
        if (!empty($_POST['schedule_days']) && is_array($_POST['schedule_days'])) {
            $conflictStmt = $pdo->prepare("SELECT section_name FROM sections WHERE user_id = ? AND is_active = 1 AND (FIND_IN_SET(?, REPLACE(schedule_day, ' ', '')) > 0) AND NOT (end_time <= ? OR start_time >= ?)");
            foreach ($_POST['schedule_days'] as $day) {
                $conflictStmt->execute([$userId, trim($day), $startTime, $endTime]);
                $foundName = $conflictStmt->fetchColumn();
                if ($foundName) { $conflictFound = true; $conflictSectionName = $foundName; break; }
            }
        } elseif (!empty($_POST['schedule_day'])) {
            $conflictStmt = $pdo->prepare("SELECT section_name FROM sections WHERE user_id = ? AND is_active = 1 AND (FIND_IN_SET(?, REPLACE(schedule_day, ' ', '')) > 0) AND NOT (end_time <= ? OR start_time >= ?)");
            $conflictStmt->execute([$userId, trim($_POST['schedule_day']), $startTime, $endTime]);
            $foundName = $conflictStmt->fetchColumn();
            if ($foundName) { $conflictFound = true; $conflictSectionName = $foundName; }
        }
        if ($conflictFound) {
            echo json_encode([
                'success' => false,
                'conflict' => true,
                'message' => "Schedule conflict: overlaps with section '$conflictSectionName' on the same day and time."
            ]);
            exit;
        }

        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO sections (user_id, section_name, subject_code, subject_name, room, schedule_day, start_time, end_time, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $insertStmt->execute([$userId, $sectionName, $subjectCode, $subjectName, $room, $scheduleDay, $startTime, $endTime]);
            $newSectionId = (int)$pdo->lastInsertId();

            // Automatically create an initial attendance session for this section
            $qrToken = 'QR-' . strtoupper(bin2hex(random_bytes(6)));
            $today = date('Y-m-d');
            $sessStmt = $pdo->prepare("
                INSERT INTO attendance_sessions (user_id, section_id, session_date, start_time, end_time, qr_token, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $sessStmt->execute([$userId, $newSectionId, $today, $startTime, $endTime, $qrToken]);

            echo json_encode(['success' => true, 'message' => "Section '$sectionName' added successfully!"]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error adding section: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'update_section') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $sectionName = trim($_POST['section_name'] ?? '');
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $room = trim($_POST['room'] ?? 'Lab 304');
        $startTime = $_POST['start_time'] ?? '08:00:00';
        $endTime = $_POST['end_time'] ?? '09:30:00';

        $scheduleDay = '';
        if (!empty($_POST['schedule_days']) && is_array($_POST['schedule_days'])) {
            $scheduleDay = implode(', ', array_map('trim', $_POST['schedule_days']));
        } elseif (!empty($_POST['schedule_day'])) {
            $scheduleDay = trim($_POST['schedule_day']);
        } else {
            $scheduleDay = 'Monday';
        }

        if (empty($sectionId) || empty($sectionName)) {
            echo json_encode(['success' => false, 'message' => 'Section ID and section name are required']);
            exit;
        }

        // Schedule conflict validation for updates (exclude current section)
        $conflictFound = false;
        $conflictSectionName = '';
        if (!empty($_POST['schedule_days']) && is_array($_POST['schedule_days'])) {
            $conflictStmt = $pdo->prepare("SELECT section_name FROM sections WHERE user_id = ? AND is_active = 1 AND id != ? AND (FIND_IN_SET(?, REPLACE(schedule_day, ' ', '')) > 0) AND NOT (end_time <= ? OR start_time >= ?)");
            foreach ($_POST['schedule_days'] as $day) {
                $conflictStmt->execute([$userId, $sectionId, trim($day), $startTime, $endTime]);
                $foundName = $conflictStmt->fetchColumn();
                if ($foundName) { $conflictFound = true; $conflictSectionName = $foundName; break; }
            }
        } elseif (!empty($_POST['schedule_day'])) {
            $conflictStmt = $pdo->prepare("SELECT section_name FROM sections WHERE user_id = ? AND is_active = 1 AND id != ? AND (FIND_IN_SET(?, REPLACE(schedule_day, ' ', '')) > 0) AND NOT (end_time <= ? OR start_time >= ?)");
            $conflictStmt->execute([$userId, $sectionId, trim($_POST['schedule_day']), $startTime, $endTime]);
            $foundName = $conflictStmt->fetchColumn();
            if ($foundName) { $conflictFound = true; $conflictSectionName = $foundName; }
        }
        if ($conflictFound) {
            echo json_encode([
                'success' => false,
                'conflict' => true,
                'message' => "Schedule conflict: overlaps with section '$conflictSectionName' on the same day and time."
            ]);
            exit;
        }

        try {
            $updateStmt = $pdo->prepare("
                UPDATE sections 
                SET section_name = ?, subject_code = ?, subject_name = ?, room = ?, schedule_day = ?, start_time = ?, end_time = ?
                WHERE id = ? AND user_id = ?
            ");
            $updateStmt->execute([$sectionName, $subjectCode, $subjectName, $room, $scheduleDay, $startTime, $endTime, $sectionId, $userId]);
            echo json_encode(['success' => true, 'message' => "Section '$sectionName' updated successfully!"]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error updating section: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'delete_section') {
        $sectionId = (int)($_POST['section_id'] ?? 0);

        if (empty($sectionId)) {
            echo json_encode(['success' => false, 'message' => 'Section ID is required']);
            exit;
        }

        try {
            $delStmt = $pdo->prepare("UPDATE sections SET is_active = 0 WHERE id = ? AND user_id = ?");
            $delStmt->execute([$sectionId, $userId]);
            echo json_encode(['success' => true, 'message' => 'Section deleted successfully!']);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error deleting section: ' . $e->getMessage()]);
            exit;
        }
    }

    if ($action === 'get_section') {
        $sectionId = (int)($_POST['section_id'] ?? $_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ? AND user_id = ? AND is_active = 1");
        $stmt->execute([$sectionId, $userId]);
        $sec = $stmt->fetch();
        if ($sec) {
            echo json_encode(['success' => true, 'section' => $sec]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Section not found']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Handle GET Requests
$getAction = $_GET['action'] ?? '';
if ($getAction === 'get_section') {
    $sectionId = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM sections WHERE id = ? AND user_id = ? AND is_active = 1");
    $stmt->execute([$sectionId, $userId]);
    $sec = $stmt->fetch();
    if ($sec) {
        echo json_encode(['success' => true, 'section' => $sec]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
    }
    exit;
}

try {
    $sectionsStmt = $pdo->prepare("
        SELECT 
            sec.id,
            sec.section_name,
            sec.subject_code,
            sec.subject_name,
            sec.room,
            sec.schedule_day,
            COALESCE(
                (SELECT s.start_time FROM attendance_sessions s WHERE s.section_id = sec.id AND s.user_id = sec.user_id ORDER BY (s.status = 'active') DESC, s.session_date DESC, s.id DESC LIMIT 1),
                sec.start_time
            ) AS start_time,
            COALESCE(
                (SELECT s.end_time FROM attendance_sessions s WHERE s.section_id = sec.id AND s.user_id = sec.user_id ORDER BY (s.status = 'active') DESC, s.session_date DESC, s.id DESC LIMIT 1),
                sec.end_time
            ) AS end_time,
            (SELECT COUNT(*) FROM students stu WHERE stu.section_id = sec.id) AS student_count,
            (SELECT COUNT(*) FROM attendance_sessions sess WHERE sess.section_id = sec.id AND sess.status = 'active') AS active_sessions_count
        FROM sections sec
        WHERE sec.user_id = ? AND sec.is_active = 1
        ORDER BY sec.section_name ASC
    ");
    $sectionsStmt->execute([$userId]);
    $sections = $sectionsStmt->fetchAll();

    $totalStudentsStmt = $pdo->prepare("
        SELECT COUNT(s.id) 
        FROM students s 
        JOIN sections sec ON s.section_id = sec.id 
        WHERE sec.user_id = ? AND sec.is_active = 1
    ");
    $totalStudentsStmt->execute([$userId]);
    $totalStudents = (int)$totalStudentsStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $userId,
            'faculty_id' => $_SESSION['faculty_id'] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'title' => $_SESSION['user_title'] ?? 'Professor',
            'avatar_initials' => $_SESSION['avatar_initials'] ?? 'CP'
        ],
        'sections' => $sections,
        'total_sections' => count($sections),
        'total_students' => $totalStudents,
        'snapshot_time' => date('m/d/Y, g:i:s A'),
        'today_date' => date('l, F j, Y')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
