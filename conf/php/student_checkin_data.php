<?php
// student_checkin_data.php
// Handles: verify_student + submit_attendance (no auth required — public endpoint for students)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = trim($_POST['action'] ?? '');

// ══════════════════════════════════════════
// 1. VERIFY STUDENT
// ══════════════════════════════════════════
if ($action === 'verify_student') {
    $studentNumber = trim($_POST['student_number'] ?? '');
    $sessionId     = (int)($_POST['session_id'] ?? 0);
    $qrToken       = trim($_POST['qr_token'] ?? '');

    if (empty($studentNumber) || empty($qrToken)) {
        echo json_encode(['success' => false, 'message' => 'Missing student number or session token']);
        exit;
    }

    try {
        // Validate session by token
        $sessStmt = $pdo->prepare("
            SELECT s.id, s.section_id, s.status, s.session_date, s.start_time, s.end_time
            FROM attendance_sessions s
            WHERE s.qr_token = ?
        ");
        $sessStmt->execute([$qrToken]);
        $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Invalid QR session']);
            exit;
        }

        $now = new DateTime();
        $sessionEnd   = new DateTime($session['session_date'] . ' ' . ($session['end_time'] ?? '23:59:59'));
        $sessionStart = new DateTime($session['session_date'] . ' ' . ($session['start_time'] ?? '00:00:00'));
        if ($session['status'] !== 'active' || $now > $sessionEnd) {
            echo json_encode(['success' => false, 'message' => 'This attendance session has already ended and is now inactive.']);
            exit;
        }
        if ($now < $sessionStart) {
            $formattedStart = date('g:i A', $sessionStart->getTimestamp());
            echo json_encode(['success' => false, 'message' => "Attendance session has not started yet. Class opens at {$formattedStart}."]);
            exit;
        }

        $sectionId = (int)$session['section_id'];
        $realSessionId = (int)$session['id'];

        // Find student in masterlist for this section
        $stuStmt = $pdo->prepare("
            SELECT id, student_number, first_name, last_name, course, year_level, email, sex
            FROM students
            WHERE student_number = ? AND section_id = ?
        ");
        $stuStmt->execute([$studentNumber, $sectionId]);
        $student = $stuStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode([
                'success' => false,
                'message' => 'Student number not found in the masterlist for this section'
            ]);
            exit;
        }

        // Check if already submitted
        $dupStmt = $pdo->prepare("
            SELECT id, scanned_at, status FROM attendance_records
            WHERE session_id = ? AND student_id = ?
        ");
        $dupStmt->execute([$realSessionId, $student['id']]);
        $existingRecord = $dupStmt->fetch(PDO::FETCH_ASSOC);
        $alreadySubmitted = !empty($existingRecord);

        echo json_encode([
            'success'           => true,
            'student'           => $student,
            'already_submitted' => $alreadySubmitted,
            'scanned_at'        => $existingRecord ? date('g:i A', strtotime($existingRecord['scanned_at'])) : null,
            'record_status'     => $existingRecord ? $existingRecord['status'] : null,
            'session_id'        => $realSessionId
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════
// 2. SUBMIT ATTENDANCE
// ══════════════════════════════════════════
if ($action === 'submit_attendance') {
    $sessionId  = (int)($_POST['session_id'] ?? 0);
    $studentId  = (int)($_POST['student_id'] ?? 0);
    $qrToken    = trim($_POST['qr_token'] ?? '');
    $remarks    = trim($_POST['remarks'] ?? '');

    if ($sessionId <= 0 || $studentId <= 0 || empty($qrToken)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    try {
        // Re-validate session is still active and token matches
        $sessStmt = $pdo->prepare("
            SELECT id, status, section_id, session_date, start_time, end_time, late_threshold_minutes FROM attendance_sessions
            WHERE id = ? AND qr_token = ?
        ");
        $sessStmt->execute([$sessionId, $qrToken]);
        $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Invalid session or token mismatch']);
            exit;
        }

        $now = new DateTime();
        $sessionEnd   = new DateTime($session['session_date'] . ' ' . ($session['end_time'] ?? '23:59:59'));
        $sessionStart = new DateTime($session['session_date'] . ' ' . ($session['start_time'] ?? '00:00:00'));
        if ($session['status'] !== 'active' || $now > $sessionEnd) {
            echo json_encode(['success' => false, 'message' => 'Attendance session has already ended and is now inactive.']);
            exit;
        }
        if ($now < $sessionStart) {
            echo json_encode(['success' => false, 'message' => 'Attendance session has not started yet.']);
            exit;
        }

        // Confirm student belongs to this section
        $stuStmt = $pdo->prepare("
            SELECT id, first_name, last_name, student_number
            FROM students
            WHERE id = ? AND section_id = ?
        ");
        $stuStmt->execute([$studentId, $session['section_id']]);
        $student = $stuStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not authorized for this section']);
            exit;
        }

        // Check if already submitted for this session — strictly block duplicates
        $dupCheck = $pdo->prepare("
            SELECT id, scanned_at, status FROM attendance_records
            WHERE session_id = ? AND student_id = ?
        ");
        $dupCheck->execute([$sessionId, $studentId]);
        $existing = $dupCheck->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode([
                'success'           => false,
                'already_submitted' => true,
                'scanned_at'        => date('g:i A', strtotime($existing['scanned_at'])),
                'record_status'     => $existing['status'],
                'message'           => 'This Student Number has already submitted attendance for this session.'
            ]);
            exit;
        }

        // Determine status: present or late — use teacher-configured threshold per session
        $now          = new DateTime();
        $sessionStart = new DateTime($session['session_date'] . ' ' . ($session['start_time'] ?? '00:00:00'));
        $lateMinutes  = max(0, (int)($session['late_threshold_minutes'] ?? 15));
        $lateThreshold = clone $sessionStart;
        $lateThreshold->modify("+{$lateMinutes} minutes");
        $attendanceStatus = ($now > $lateThreshold) ? 'late' : 'present';

        // Handle photo upload (e.g. uniform verification photo)
        $photoUrl = null;
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmp  = $_FILES['photo']['tmp_name'];
            $fileName = $_FILES['photo']['name'];
            $fileSize = $_FILES['photo']['size'];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
            if (in_array($ext, $allowedExts) && $fileSize <= 12 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../../uploads/uniforms/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'uniform_sess' . $sessionId . '_stu' . $studentId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath  = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmp, $targetPath)) {
                    $photoUrl = 'uploads/uniforms/' . $newFileName;
                }
            }
        }

        $customData = trim($_POST['custom_data'] ?? '');

        // Insert or update attendance record
        $insStmt = $pdo->prepare("
            INSERT INTO attendance_records (session_id, student_id, status, scanned_at, photo_url, remarks, custom_data)
            VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status), 
                scanned_at = NOW(),
                photo_url = COALESCE(VALUES(photo_url), photo_url),
                remarks = VALUES(remarks),
                custom_data = VALUES(custom_data)
        ");
        $insStmt->execute([
            $sessionId, 
            $studentId, 
            $attendanceStatus, 
            $photoUrl, 
            !empty($remarks) ? $remarks : null, 
            !empty($customData) ? $customData : null
        ]);

        echo json_encode([
            'success'        => true,
            'message'        => 'Attendance recorded successfully!',
            'status'         => $attendanceStatus,
            'name'           => $student['last_name'] . ', ' . $student['first_name'],
            'student_number' => $student['student_number'],
            'photo_url'      => $photoUrl,
            'scanned_at'     => date('Y-m-d H:i:s')
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
