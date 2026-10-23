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
$sectionId = (int)($_REQUEST['id'] ?? 0);

if ($sectionId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
    exit;
}

// Check Section Ownership
$secStmt = $pdo->prepare("SELECT * FROM sections WHERE id = ? AND user_id = ?");
$secStmt->execute([$sectionId, $userId]);
$section = $secStmt->fetch();

if (!$section) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Section not found']);
    exit;
}

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Single Manual Add Student
    if ($action === 'add_student') {
        $snum = trim($_POST['student_number'] ?? '');
        $fname = trim($_POST['first_name'] ?? '');
        $lname = trim($_POST['last_name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $sex = trim($_POST['sex'] ?? '');

        if (empty($snum) || empty($fname) || empty($lname)) {
            echo json_encode(['success' => false, 'message' => 'Student ID, first name, and last name are required']);
            exit;
        }

        try {
            $insStmt = $pdo->prepare("
                INSERT INTO students (section_id, student_number, first_name, last_name, course, year_level, email, sex)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    course = VALUES(course),
                    year_level = VALUES(year_level),
                    email = VALUES(email),
                    sex = VALUES(sex)
            ");
            $insStmt->execute([$sectionId, $snum, $fname, $lname, $course, $yearLevel, $email, $sex]);
            echo json_encode(['success' => true, 'message' => "Student $fname $lname enrolled successfully!"]);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to enroll student: ' . $e->getMessage()]);
            exit;
        }
    }

    // 2. Bulk Excel Masterlist Import
    if ($action === 'import_masterlist') {
        $rawStudents = $_POST['students'] ?? '';
        if (is_string($rawStudents)) {
            $studentsList = json_decode($rawStudents, true);
        } else {
            $studentsList = $rawStudents;
        }

        if (empty($studentsList) || !is_array($studentsList)) {
            echo json_encode(['success' => false, 'message' => 'No student data received from Excel']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            $importedCount = 0;
            $insStmt = $pdo->prepare("
                INSERT INTO students (section_id, student_number, first_name, last_name, course, year_level, email, sex)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    course = VALUES(course),
                    year_level = VALUES(year_level),
                    email = VALUES(email),
                    sex = VALUES(sex)
            ");

            foreach ($studentsList as $stu) {
                $snum = trim((string)($stu['student_number'] ?? $stu['student_no'] ?? ''));
                if (empty($snum)) continue;

                $fullName = trim((string)($stu['name'] ?? $stu['full_name'] ?? ''));
                $fname = trim((string)($stu['first_name'] ?? ''));
                $lname = trim((string)($stu['last_name'] ?? ''));

                // Parse "LASTNAME, FIRSTNAME" format if not already separated
                if (empty($fname) && empty($lname) && !empty($fullName)) {
                    if (strpos($fullName, ',') !== false) {
                        $parts = explode(',', $fullName, 2);
                        $lname = trim($parts[0]);
                        $fname = trim($parts[1]);
                    } else {
                        $parts = preg_split('/\s+/', $fullName);
                        $lname = count($parts) > 1 ? array_pop($parts) : 'N/A';
                        $fname = implode(' ', $parts) ?: $fullName;
                    }
                }

                if (empty($fname)) $fname = $fullName ?: 'Student';
                if (empty($lname)) $lname = 'N/A';

                $course = trim((string)($stu['course'] ?? ''));
                if (empty($course)) {
                    $course = !empty($section['subject_code']) ? $section['subject_code'] : 'BSIT';
                }

                $yearLevel = trim((string)($stu['year_level'] ?? ''));
                if (empty($yearLevel)) {
                    $yearLevel = 'Third Year';
                }

                $email = trim((string)($stu['email'] ?? ''));
                $rawSex = strtoupper(trim((string)($stu['sex'] ?? '')));
                $sex = 'M';
                if ($rawSex === 'F' || strpos($rawSex, 'FEM') === 0 || strpos($rawSex, 'GIRL') === 0) {
                    $sex = 'F';
                } elseif ($rawSex === 'M' || strpos($rawSex, 'MAL') === 0 || strpos($rawSex, 'BOY') === 0) {
                    $sex = 'M';
                } elseif (!empty($rawSex)) {
                    $sex = substr($rawSex, 0, 10);
                }

                $insStmt->execute([$sectionId, $snum, $fname, $lname, $course, $yearLevel, $email, $sex]);
                $importedCount++;
            }

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Successfully imported $importedCount students into section {$section['section_name']}!",
                'imported_count' => $importedCount
            ]);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Database error during import: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // 3. Delete Student from Section
    if ($action === 'delete_student') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        if ($studentId > 0) {
            $delStmt = $pdo->prepare("DELETE FROM students WHERE id = ? AND section_id = ?");
            $delStmt->execute([$studentId, $sectionId]);
            echo json_encode(['success' => true, 'message' => 'Student removed from section roster']);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// GET: Student Masterlist & Sessions for this Section
try {
    $studentsStmt = $pdo->prepare("
        SELECT 
            s.id,
            s.student_number,
            s.first_name,
            s.last_name,
            s.course,
            s.year_level,
            s.email,
            s.sex,
            COUNT(ar.id) AS total_sessions_attended
        FROM students s
        LEFT JOIN attendance_records ar ON s.id = ar.student_id AND ar.status IN ('present', 'late')
        WHERE s.section_id = ?
        GROUP BY s.id
        ORDER BY s.last_name ASC, s.first_name ASC
    ");
    $studentsStmt->execute([$sectionId]);
    $students = $studentsStmt->fetchAll();

    $sessStmt = $pdo->prepare("
        SELECT 
            sess.id,
            sess.session_date,
            sess.start_time,
            sess.end_time,
            sess.status,
            sess.qr_token,
            COUNT(ar.id) AS present_count
        FROM attendance_sessions sess
        LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.status = 'present'
        WHERE sess.section_id = ? AND sess.user_id = ?
        GROUP BY sess.id
        ORDER BY sess.session_date DESC
    ");
    $sessStmt->execute([$sectionId, $userId]);
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
        'section' => $section,
        'students' => $students,
        'sessions' => $sessions,
        'total_students' => count($students),
        'total_sessions' => count($sessions),
        'today_date' => date('l, F j, Y')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
