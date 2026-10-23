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

// Handle POST: Update Student Attendance Status manually
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_student_status') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $newStatus = trim($_POST['status'] ?? 'present');

        if (!in_array($newStatus, ['present', 'late', 'absent'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            exit;
        }

        if ($sessionId <= 0 || $studentId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing session or student ID']);
            exit;
        }

        try {
            $upsert = $pdo->prepare("
                INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), scanned_at = NOW()
            ");
            $upsert->execute([$sessionId, $studentId, $newStatus]);

            echo json_encode([
                'success' => true,
                'message' => 'Attendance status updated successfully!'
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// GET: Fetch Sections and Attendance Report Data
try {
    // 1. Fetch all active sections with schedules
    $secStmt = $pdo->prepare("
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
            (SELECT COUNT(*) FROM students stu WHERE stu.section_id = sec.id) AS student_count
        FROM sections sec
        WHERE sec.user_id = ? AND sec.is_active = 1
        ORDER BY sec.section_name ASC
    ");
    $secStmt->execute([$userId]);
    $sections = $secStmt->fetchAll();

    $selectedSectionId = (int)($_GET['section_id'] ?? 0);
    if ($selectedSectionId <= 0 && !empty($sections)) {
        $selectedSectionId = (int)$sections[0]['id'];
    }

    $currentSection = null;
    foreach ($sections as $s) {
        if ((int)$s['id'] === $selectedSectionId) {
            $currentSection = $s;
            break;
        }
    }

    $sessions = [];
    $selectedSession = null;
    $studentsReport = [];
    $stats = [
        'total' => 0,
        'present' => 0,
        'late' => 0,
        'absent' => 0,
        'rate' => 0
    ];

    if ($selectedSectionId > 0) {
        // Fetch sessions for this section
        $sessStmt = $pdo->prepare("
            SELECT id, session_date, start_time, end_time, status, qr_token
            FROM attendance_sessions
            WHERE section_id = ? AND user_id = ?
            ORDER BY session_date DESC, id DESC
        ");
        $sessStmt->execute([$selectedSectionId, $userId]);
        $sessions = $sessStmt->fetchAll();

        $selectedSessionId = (int)($_GET['session_id'] ?? 0);
        if ($selectedSessionId <= 0 && !empty($sessions)) {
            $selectedSessionId = (int)$sessions[0]['id'];
        }

        foreach ($sessions as $ses) {
            if ((int)$ses['id'] === $selectedSessionId) {
                $selectedSession = $ses;
                break;
            }
        }

        // Fetch students in ALPHABETICAL ORDER: last_name ASC, first_name ASC
        $stuStmt = $pdo->prepare("
            SELECT 
                stu.id AS student_id,
                stu.student_number,
                stu.first_name,
                stu.last_name,
                stu.email,
                COALESCE(ar.status, 'absent') AS attendance_status,
                ar.scanned_at,
                ar.photo_url,
                ar.remarks,
                ar.custom_data
            FROM students stu
            LEFT JOIN attendance_records ar 
                ON ar.student_id = stu.id AND ar.session_id = ?
            WHERE stu.section_id = ?
            ORDER BY stu.last_name ASC, stu.first_name ASC
        ");
        $stuStmt->execute([$selectedSessionId, $selectedSectionId]);
        $studentsReport = $stuStmt->fetchAll();

        // Calculate statistics
        $stats['total'] = count($studentsReport);
        foreach ($studentsReport as $r) {
            $st = strtolower($r['attendance_status']);
            if ($st === 'present') $stats['present']++;
            elseif ($st === 'late') $stats['late']++;
            else $stats['absent']++;
        }
        if ($stats['total'] > 0) {
            $attended = $stats['present'] + $stats['late'];
            $stats['rate'] = round(($attended / $stats['total']) * 100, 1);
        }
    }

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
        'selected_section_id' => $selectedSectionId,
        'current_section' => $currentSection,
        'sessions' => $sessions,
        'selected_session_id' => $selectedSession ? (int)$selectedSession['id'] : 0,
        'selected_session' => $selectedSession,
        'students' => $studentsReport,
        'stats' => $stats,
        'today_date' => date('l, F j, Y')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
