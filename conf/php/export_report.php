<?php
// export_report.php
// Official Multi-Week Matrix Attendance Report Exporter
require_once __DIR__ . '/db.php';
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized access. Please login first.');
}

$userId = (int)$_SESSION['user_id'];
$sectionId = (int)($_GET['section_id'] ?? 0);

if ($sectionId <= 0) {
    die('Error: Section ID is required.');
}

// 1. Fetch Section Details
$secStmt = $pdo->prepare("SELECT * FROM sections WHERE id = ? AND user_id = ?");
$secStmt->execute([$sectionId, $userId]);
$section = $secStmt->fetch(PDO::FETCH_ASSOC);

if (!$section) {
    die('Error: Section not found or unauthorized.');
}

// 2. Fetch Sessions for Section with Date / Coverage Filtering
$term = strtolower(trim($_GET['term'] ?? 'prelim'));
$coverageType = trim($_GET['coverage_type'] ?? 'auto');
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');
$statusFormat = trim($_GET['status_format'] ?? 'full');

$params = [$sectionId, $userId];
$sql = "SELECT id, session_date, start_time, end_time, status FROM attendance_sessions WHERE section_id = ? AND user_id = ?";

if ($coverageType === 'custom' && !empty($startDate)) {
    $sql .= " AND session_date >= ?";
    $params[] = $startDate;
}
if ($coverageType === 'custom' && !empty($endDate)) {
    $sql .= " AND session_date <= ?";
    $params[] = $endDate;
}
$sql .= " ORDER BY session_date ASC, start_time ASC, id ASC";

$sessStmt = $pdo->prepare($sql);
$sessStmt->execute($params);
$rawSessions = $sessStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Filter sessions by Term if in Auto mode
$sessionsToUse = $rawSessions;
$totalSessionsCount = count($rawSessions);

if ($coverageType === 'auto' && $term !== 'all' && $totalSessionsCount > 0) {
    // Standard academic distribution: Prelim (Weeks 1-6), Midterm (Weeks 7-12), Final (Weeks 13+)
    if ($term === 'prelim') {
        $sessionsToUse = array_slice($rawSessions, 0, 6);
    } elseif ($term === 'midterm') {
        if ($totalSessionsCount > 6) {
            $sessionsToUse = array_slice($rawSessions, 6, 6);
        } else {
            $sessionsToUse = $rawSessions; // Fallback to all if fewer than 6 sessions recorded
        }
    } elseif ($term === 'final') {
        if ($totalSessionsCount > 12) {
            $sessionsToUse = array_slice($rawSessions, 12);
        } elseif ($totalSessionsCount > 6) {
            $sessionsToUse = array_slice($rawSessions, 6);
        } else {
            $sessionsToUse = $rawSessions;
        }
    }
}

// 4. Fetch Students in Section (Alphabetical order: last_name, first_name)
$stuStmt = $pdo->prepare("
    SELECT id, student_number, first_name, last_name, course, year_level, sex, email
    FROM students
    WHERE section_id = ?
    ORDER BY last_name ASC, first_name ASC
");
$stuStmt->execute([$sectionId]);
$students = $stuStmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Fetch Attendance Records for the Filtered Sessions
$recordsMap = [];
if (!empty($sessionsToUse)) {
    $sessionIds = array_column($sessionsToUse, 'id');
    $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
    $recStmt = $pdo->prepare("
        SELECT session_id, student_id, status 
        FROM attendance_records 
        WHERE session_id IN ($placeholders)
    ");
    $recStmt->execute($sessionIds);
    while ($row = $recStmt->fetch(PDO::FETCH_ASSOC)) {
        $recordsMap[$row['student_id']][$row['session_id']] = strtolower($row['status']);
    }
}

// 6. Generate CSV Download Headers
$termLabel = strtoupper($term);
if ($termLabel === 'ALL') $termLabel = 'FULL TERM (ALL SESSIONS)';

$safeSectionName = preg_replace('/[^A-Za-z0-9_-]/', '_', $section['section_name']);
$filename = "Attendance_Report_{$safeSectionName}_{$termLabel}_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 Byte Order Mark (BOM) so Excel opens UTF-8 properly (handles ñ, Ñ, special letters)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// A. Title & Institutional Metadata
fputcsv($output, ['SMART STUDENT ATTENDANCE SYSTEM - OFFICIAL CLASS ATTENDANCE REPORT']);
fputcsv($output, [
    'SECTION: ' . $section['section_name'],
    'SUBJECT: ' . $section['subject_code'] . ' - ' . $section['subject_name'],
    'GRADING PERIOD: ' . $termLabel,
    'ROOM: ' . $section['room'],
    'SCHEDULE: ' . $section['schedule_day'] . ' (' . date('g:i A', strtotime($section['start_time'])) . ' - ' . date('g:i A', strtotime($section['end_time'])) . ')'
]);
fputcsv($output, [
    'INSTRUCTOR: Prof. ' . ($_SESSION['user_name'] ?? 'Carlo Owen Panang'),
    'FACULTY ID: ' . ($_SESSION['faculty_id'] ?? '2048'),
    'GENERATED: ' . date('m/d/Y, h:i:s A') . ' (PST)',
    'TOTAL ENROLLED: ' . count($students),
    'SESSIONS RECORDED: ' . count($sessionsToUse)
]);
fputcsv($output, []); // Empty spacer line

// B. Term Header Row (Prelim / Midterm / Final banner spanning across week columns)
$termBanner = ['', '', '', '', '', ''];
$bannerTitle = "=== {$termLabel} ATTENDANCE SESSIONS ===";
if (!empty($sessionsToUse)) {
    $termBanner[] = $bannerTitle;
    for ($i = 1; $i < count($sessionsToUse); $i++) {
        $termBanner[] = '';
    }
} else {
    $termBanner[] = 'NO RECORDED SESSIONS IN THIS PERIOD';
}
$termBanner[] = ''; // Total Present
$termBanner[] = ''; // Total Late
$termBanner[] = ''; // Total Absent
$termBanner[] = ''; // Attendance Rate

fputcsv($output, $termBanner);

// C. Column Headers
$headers = ['NO.', 'STUDENT NUMBER', 'STUDENT NAME', 'COURSE', 'YEAR LEVEL', 'SEX'];
foreach ($sessionsToUse as $idx => $ses) {
    $wNum = $idx + 1;
    $dateLabel = date('m/d/Y', strtotime($ses['session_date']));
    $headers[] = "WEEK $wNum ($dateLabel)";
}
$headers[] = 'TOTAL PRESENT';
$headers[] = 'TOTAL LATE';
$headers[] = 'TOTAL ABSENT';
$headers[] = 'ATTENDANCE RATE (%)';

fputcsv($output, $headers);

// D. Student Rows
$num = 1;
foreach ($students as $stu) {
    $fullName = !empty($stu['last_name']) && $stu['last_name'] !== 'N/A' 
        ? "{$stu['last_name']}, {$stu['first_name']}" 
        : $stu['first_name'];

    $row = [
        $num++,
        $stu['student_number'],
        strtoupper($fullName),
        $stu['course'] ?: 'BSIT',
        $stu['year_level'] ?: 'Third Year',
        strtoupper($stu['sex'] ?: 'M')
    ];

    $presCount = 0;
    $lateCount = 0;
    $absCount = 0;

    foreach ($sessionsToUse as $ses) {
        $sid = $ses['id'];
        $st = $recordsMap[$stu['id']][$sid] ?? 'absent';

        if ($st === 'present') {
            $presCount++;
            $row[] = ($statusFormat === 'short') ? 'P' : 'PRESENT';
        } elseif ($st === 'late') {
            $lateCount++;
            $row[] = ($statusFormat === 'short') ? 'L' : 'LATE';
        } else {
            $absCount++;
            $row[] = ($statusFormat === 'short') ? 'A' : 'ABSENT';
        }
    }

    $totSessions = count($sessionsToUse);
    $rate = $totSessions > 0 ? round((($presCount + $lateCount) / $totSessions) * 100, 1) . '%' : '0.0%';

    $row[] = $presCount;
    $row[] = $lateCount;
    $row[] = $absCount;
    $row[] = $rate;

    fputcsv($output, $row);
}

// E. Summary Row (Total attendees per session)
$summaryRow = ['SUMMARY', 'TOTAL PRESENT PER SESSION', '', '', '', ''];
foreach ($sessionsToUse as $ses) {
    $sid = $ses['id'];
    $attendedCount = 0;
    foreach ($students as $stu) {
        $st = $recordsMap[$stu['id']][$sid] ?? 'absent';
        if ($st === 'present' || $st === 'late') {
            $attendedCount++;
        }
    }
    $summaryRow[] = $attendedCount;
}
$summaryRow[] = '';
$summaryRow[] = '';
$summaryRow[] = '';
$summaryRow[] = '';
fputcsv($output, $summaryRow);

// F. Instructor Signature Section
fputcsv($output, []);
fputcsv($output, ['Certified Correct:']);
fputcsv($output, []);
fputcsv($output, ['Prof. ' . ($_SESSION['user_name'] ?? 'Carlo Owen Panang')]);
fputcsv($output, ['Instructor / Faculty-in-Charge']);

fclose($output);
exit;
