<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/db.php';

// Authentication Check
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login.'
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // 1. Fetch Professor Data
    $userStmt = $pdo->prepare("SELECT id, faculty_id, name, email, title, avatar_initials FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $nameParts = explode(' ', trim($user['name']));
    $lastName = end($nameParts);

    $hour = (int)date('H');
    $timeGreeting = 'Good morning';
    if ($hour >= 12 && $hour < 18) {
        $timeGreeting = 'Good afternoon';
    } elseif ($hour >= 18 || $hour < 5) {
        $timeGreeting = 'Good evening';
    }

    $todayDayName = date('l');
    $todayDateFormatted = date('l, F j, Y');
    $todayHeaderDate = strtoupper(date('l, F j, Y'));

    // 2. Active Sections
    $secStmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE user_id = ? AND is_active = 1");
    $secStmt->execute([$userId]);
    $activeSectionsCount = (int)$secStmt->fetchColumn();

    // Today scheduled classes
    $schedStmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE user_id = ? AND schedule_day = ? AND is_active = 1");
    $schedStmt->execute([$userId, $todayDayName]);
    $todayScheduledCount = (int)$schedStmt->fetchColumn();

    // 3. Total Students
    $stuStmt = $pdo->prepare("
        SELECT COUNT(s.id) 
        FROM students s 
        JOIN sections sec ON s.section_id = sec.id 
        WHERE sec.user_id = ? AND sec.is_active = 1
    ");
    $stuStmt->execute([$userId]);
    $totalStudentsCount = (int)$stuStmt->fetchColumn();

    // 4. Today's Attendance
    $todayAttStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) AS present_count,
            COUNT(ar.id) AS total_recorded
        FROM attendance_records ar
        JOIN attendance_sessions sess ON ar.session_id = sess.id
        WHERE sess.user_id = ? AND sess.session_date = CURDATE()
    ");
    $todayAttStmt->execute([$userId]);
    $todayAttData = $todayAttStmt->fetch();

    $presentToday = (int)($todayAttData['present_count'] ?? 0);
    $recordedToday = (int)($todayAttData['total_recorded'] ?? 0);

    $expStmt = $pdo->prepare("
        SELECT COUNT(s.id)
        FROM students s
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.user_id = ? AND sec.schedule_day = ?
    ");
    $expStmt->execute([$userId, $todayDayName]);
    $totalExpectedToday = (int)$expStmt->fetchColumn();
    if ($totalExpectedToday === 0) {
        $totalExpectedToday = $recordedToday;
    }

    $todayAttendancePercent = ($totalExpectedToday > 0 && $presentToday > 0) 
        ? round(($presentToday / $totalExpectedToday) * 100, 1) 
        : ($recordedToday > 0 ? round(($presentToday / $recordedToday) * 100, 1) : 0);

    // 5. Active Sessions
    $activeSessStmt = $pdo->prepare("
        SELECT sess.*, sec.section_name, sec.subject_name 
        FROM attendance_sessions sess
        JOIN sections sec ON sess.section_id = sec.id
        WHERE sess.user_id = ? AND sess.status = 'active' AND sec.is_active = 1
        ORDER BY sess.id DESC
        LIMIT 1
    ");
    $activeSessStmt->execute([$userId]);
    $activeSession = $activeSessStmt->fetch();

    $activeSessionsCountStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM attendance_sessions sess 
        JOIN sections sec ON sess.section_id = sec.id 
        WHERE sess.user_id = ? AND sess.status = 'active' AND sec.is_active = 1
    ");
    $activeSessionsCountStmt->execute([$userId]);
    $activeSessionsCount = (int)$activeSessionsCountStmt->fetchColumn();

    // 6. Weekly Chart Data (Mon - Fri)
    $weeklyDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    $weeklyChartData = [];
    $totalWeekPresent = 0;
    $totalWeekExpected = 0;

    foreach ($weeklyDays as $index => $dayLabel) {
        $dateOffset = 4 - $index;
        $targetDate = date('Y-m-d', strtotime("-$dateOffset days"));

        $dayStmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN ar.status IN ('present', 'late') THEN 1 END) AS present_count,
                COUNT(ar.id) AS total_count
            FROM attendance_records ar
            JOIN attendance_sessions sess ON ar.session_id = sess.id
            WHERE sess.user_id = ? AND sess.session_date = ?
        ");
        $dayStmt->execute([$userId, $targetDate]);
        $dayRow = $dayStmt->fetch();

        $pCount = (int)($dayRow['present_count'] ?? 0);
        $tCount = (int)($dayRow['total_count'] ?? 0);

        if ($tCount > 0) {
            $pct = round(($pCount / $tCount) * 100, 1);
            $totalWeekPresent += $pCount;
            $totalWeekExpected += $tCount;
        } else {
            $pct = 0;
        }

        $weeklyChartData[] = [
            'day' => $dayLabel,
            'percent' => $pct
        ];
    }

    $weeklyAveragePercent = ($totalWeekExpected > 0) 
        ? round(($totalWeekPresent / $totalWeekExpected) * 100, 1) 
        : ($todayAttendancePercent > 0 ? $todayAttendancePercent : 0);

    // 7. Today's Classes List
    $classesStmt = $pdo->prepare("
        SELECT 
            sec.id,
            sec.section_name,
            sec.subject_code,
            sec.subject_name,
            sec.room,
            sec.start_time,
            sec.end_time,
            (SELECT COUNT(*) FROM students stu WHERE stu.section_id = sec.id) AS student_count,
            (SELECT sess.status FROM attendance_sessions sess WHERE sess.section_id = sec.id AND sess.session_date = CURDATE() ORDER BY sess.id DESC LIMIT 1) AS session_status
        FROM sections sec
        WHERE sec.user_id = ? AND sec.is_active = 1 AND (sec.schedule_day = ? OR ? IN ('Saturday', 'Sunday'))
        ORDER BY sec.start_time ASC
    ");
    $classesStmt->execute([$userId, $todayDayName, $todayDayName]);
    $todayClasses = $classesStmt->fetchAll();

    if (empty($todayClasses)) {
        $fallbackClassesStmt = $pdo->prepare("
            SELECT 
                sec.id,
                sec.section_name,
                sec.subject_code,
                sec.subject_name,
                sec.room,
                sec.start_time,
                sec.end_time,
                (SELECT COUNT(*) FROM students stu WHERE stu.section_id = sec.id) AS student_count,
                (SELECT sess.status FROM attendance_sessions sess WHERE sess.section_id = sec.id ORDER BY sess.id DESC LIMIT 1) AS session_status
            FROM sections sec
            WHERE sec.user_id = ? AND sec.is_active = 1
            ORDER BY sec.id ASC
        ");
        $fallbackClassesStmt->execute([$userId]);
        $todayClasses = $fallbackClassesStmt->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'user' => $user,
        'greeting' => [
            'time_greeting' => $timeGreeting,
            'last_name' => $lastName,
            'header_date' => $todayHeaderDate,
            'date_formatted' => $todayDateFormatted
        ],
        'metrics' => [
            'active_sections' => $activeSectionsCount,
            'today_scheduled_classes' => $todayScheduledCount,
            'total_students' => $totalStudentsCount,
            'today_attendance_percent' => $todayAttendancePercent,
            'present_today' => $presentToday,
            'total_expected_today' => $totalExpectedToday,
            'active_sessions_count' => $activeSessionsCount,
            'active_session_name' => $activeSession ? $activeSession['section_name'] : null
        ],
        'weekly_chart' => [
            'average_percent' => $weeklyAveragePercent,
            'bars' => $weeklyChartData
        ],
        'today_classes' => $todayClasses
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
