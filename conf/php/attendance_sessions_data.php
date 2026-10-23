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

    // 1. Generate New QR Session
    if ($action === 'generate_qr') {
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $sessionDate = !empty($_POST['session_date']) ? trim($_POST['session_date']) : date('Y-m-d');
        $startTime = !empty($_POST['start_time']) ? trim($_POST['start_time']) : date('H:i:s');
        $endTime = !empty($_POST['end_time']) ? trim($_POST['end_time']) : date('H:i:s', strtotime('+90 minutes'));

        if ($sectionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid section']);
            exit;
        }

        $lateThreshold = isset($_POST['late_threshold_minutes']) ? max(0, (int)$_POST['late_threshold_minutes']) : 15;

        try {
            // Invalidate/reset all previous active QR sessions for this section
            // so only the newly generated QR is active and old QRs cannot be reused
            $resetStmt = $pdo->prepare("
                UPDATE attendance_sessions 
                SET status = 'completed'
                WHERE user_id = ? AND section_id = ? AND status = 'active'
            ");
            $resetStmt->execute([$userId, $sectionId]);

            // Generate cryptographically unique QR token
            $qrToken = 'QR-' . strtoupper(bin2hex(random_bytes(8)));

            $insStmt = $pdo->prepare("
                INSERT INTO attendance_sessions (user_id, section_id, session_date, start_time, end_time, qr_token, status, late_threshold_minutes)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $insStmt->execute([$userId, $sectionId, $sessionDate, $startTime, $endTime, $qrToken, $lateThreshold]);

            // Sync with parent section so Reports and Sections immediately reflect the updated time
            $updSec = $pdo->prepare("UPDATE sections SET start_time = ?, end_time = ? WHERE id = ? AND user_id = ?");
            $updSec->execute([$startTime, $endTime, $sectionId, $userId]);

            echo json_encode([
                'success' => true,
                'message' => 'New unique QR session created! Previous QR for this section has been reset.',
                'qr_token' => $qrToken
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to generate session: ' . $e->getMessage()]);
            exit;
        }
    }

    // 2. Update QR Session (Date & Time & Status)
    if ($action === 'update_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $sessionDate = !empty($_POST['session_date']) ? trim($_POST['session_date']) : date('Y-m-d');
        $startTime = !empty($_POST['start_time']) ? trim($_POST['start_time']) : '08:00:00';
        $endTime = !empty($_POST['end_time']) ? trim($_POST['end_time']) : '09:30:00';
        $status = !empty($_POST['status']) ? trim($_POST['status']) : 'active';
        $lateThreshold = isset($_POST['late_threshold_minutes']) ? max(0, (int)$_POST['late_threshold_minutes']) : 15;

        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
            exit;
        }

        try {
            $updStmt = $pdo->prepare("
                UPDATE attendance_sessions 
                SET session_date = ?, start_time = ?, end_time = ?, status = ?, late_threshold_minutes = ?
                WHERE id = ? AND user_id = ?
            ");
            $updStmt->execute([$sessionDate, $startTime, $endTime, $status, $lateThreshold, $sessionId, $userId]);

            // Sync with parent section so Reports and Sections immediately reflect the updated time
            $getSec = $pdo->prepare("SELECT section_id FROM attendance_sessions WHERE id = ?");
            $getSec->execute([$sessionId]);
            $secId = $getSec->fetchColumn();
            if ($secId) {
                $updSec = $pdo->prepare("UPDATE sections SET start_time = ?, end_time = ? WHERE id = ? AND user_id = ?");
                $updSec->execute([$startTime, $endTime, $secId, $userId]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'QR session updated successfully!'
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update session: ' . $e->getMessage()]);
            exit;
        }
    }

    // 3. Delete QR Session
    if ($action === 'delete_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);

        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
            exit;
        }

        try {
            $delStmt = $pdo->prepare("DELETE FROM attendance_sessions WHERE id = ? AND user_id = ?");
            $delStmt->execute([$sessionId, $userId]);
            echo json_encode([
                'success' => true,
                'message' => 'QR session deleted successfully!'
            ]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete session: ' . $e->getMessage()]);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

function getServerLanIp() {
    $wifiIp = null;
    $candidates = [];
    @exec('ipconfig', $lines);
    $currentAdapter = '';
    foreach ((array)$lines as $line) {
        $line = trim($line);
        if (preg_match('/adapter\s+(.+?):/i', $line, $m)) {
            $currentAdapter = $m[1];
        }
        if (preg_match('/IPv4 Address[.\s]+:\s*([0-9.]+)/i', $line, $m)) {
            $foundIp = $m[1];
            if (stripos($currentAdapter, 'Wi-Fi') !== false || stripos($currentAdapter, 'Wireless') !== false) {
                $wifiIp = $foundIp;
            } elseif (stripos($currentAdapter, 'Virtual') === false && stripos($currentAdapter, 'vEthernet') === false && stripos($currentAdapter, 'Loopback') === false) {
                $candidates[] = $foundIp;
            }
        }
    }
    if ($wifiIp) return $wifiIp;
    return !empty($candidates) ? $candidates[0] : (gethostbyname(gethostname()) ?: '127.0.0.1');
}

// GET: Query Sessions & Sections
try {
    $secStmt = $pdo->prepare("SELECT id, section_name, subject_name, schedule_day, start_time, end_time FROM sections WHERE user_id = ? AND is_active = 1 ORDER BY section_name ASC");
    $secStmt->execute([$userId]);
    $sectionsList = $secStmt->fetchAll();

    // Auto-expire past sessions: mark as completed if date has passed or today's end_time has passed
    $pdo->prepare("
        UPDATE attendance_sessions 
        SET status = 'completed'
        WHERE status = 'active'
          AND (
              session_date < CURDATE() 
              OR (session_date = CURDATE() AND end_time <= CURTIME())
          )
    ")->execute();

    $sessStmt = $pdo->prepare("
        SELECT 
            sess.id,
            sess.section_id,
            sess.session_date,
            sess.start_time,
            sess.end_time,
            sess.qr_token,
            sess.status,
            sess.late_threshold_minutes,
            CASE 
                WHEN sess.status = 'active' 
                     AND sess.session_date = CURDATE() 
                     AND CURTIME() BETWEEN sess.start_time AND sess.end_time 
                THEN 1 
                ELSE 0 
            END AS is_currently_active,
            sec.section_name,
            sec.subject_name,
            sec.schedule_day,
            (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = sess.id AND ar.status IN ('present', 'late')) AS present_count
        FROM attendance_sessions sess
        JOIN sections sec ON sess.section_id = sec.id
        WHERE sess.user_id = ? AND sec.is_active = 1
        ORDER BY sess.session_date DESC, sess.id DESC
    ");
    $sessStmt->execute([$userId]);
    $sessions = $sessStmt->fetchAll();

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
        'sessions' => $sessions,
        'sections' => $sectionsList,
        'lan_ip' => getServerLanIp(),
        'today_date' => date('l, F j, Y')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
