<?php
// Database Initialization Script
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'smart_attendance_db';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // 1. Users Table
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        faculty_id VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        title VARCHAR(50) DEFAULT 'Professor',
        avatar_initials VARCHAR(10) DEFAULT 'CP',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // 2. Sections Table
    $pdo->exec("CREATE TABLE sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        section_name VARCHAR(50) NOT NULL,
        subject_code VARCHAR(50) NOT NULL,
        subject_name VARCHAR(150) NOT NULL,
        room VARCHAR(50) NOT NULL,
        schedule_day VARCHAR(20) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // 3. Students Table
    $pdo->exec("CREATE TABLE students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_id INT NOT NULL,
        student_number VARCHAR(50) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        course VARCHAR(50) DEFAULT 'BSIT',
        year_level VARCHAR(50) DEFAULT 'Third Year',
        email VARCHAR(150),
        sex VARCHAR(10) DEFAULT 'M',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_section_student (section_id, student_number),
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // 4. Attendance Sessions Table
    $pdo->exec("CREATE TABLE attendance_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        section_id INT NOT NULL,
        session_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        qr_token VARCHAR(100) NOT NULL UNIQUE,
        status ENUM('active', 'completed', 'scheduled') DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // 5. Attendance Records Table
    $pdo->exec("CREATE TABLE attendance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('present', 'late', 'absent') DEFAULT 'present',
        scanned_at DATETIME,
        photo_url VARCHAR(255) NULL,
        remarks TEXT NULL,
        custom_data TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_session_student (session_id, student_id),
        FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Seed Professor Carlo Owen Soria Panang
    $hashedPassword = password_hash('Panang@12345', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (faculty_id, name, email, password, title, avatar_initials) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['2048', 'Carlo Owen Panang', 'carlowensoriapanang@gmail.com', $hashedPassword, 'Professor', 'CP']);
    $userId = $pdo->lastInsertId();

    $todayName = date('l');

    // Seed Sections matching user curriculum & screenshot
    $sectionsData = [
        ['LFCA322N012', 'IT 322', 'Web Development & Frameworks', 'Lab 304', $todayName, '08:00:00', '09:30:00'],
        ['LFCA411N121', 'IT 411', 'Systems Integration & Architecture', 'Room 205', $todayName, '10:00:00', '11:30:00'],
        ['LFCA411N122', 'IT 412', 'Capstone Project & Monitoring', 'Lab 202', 'Wednesday', '13:00:00', '14:30:00'],
    ];

    $secStmt = $pdo->prepare("INSERT INTO sections (user_id, section_name, subject_code, subject_name, room, schedule_day, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $sectionIds = [];
    foreach ($sectionsData as $s) {
        $secStmt->execute([$userId, $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6]]);
        $sectionIds[] = $pdo->lastInsertId();
    }

    // Seed Students (3 students per section = 9 registered students total, matching screenshot)
    $studentsList = [
        // Section 1: LFCA322N012
        [1, '2023-01041', 'Juan', 'Dela Cruz', 'juan.delacruz@school.edu'],
        [1, '2023-01042', 'Maria', 'Santos', 'maria.santos@school.edu'],
        [1, '2023-01043', 'Mark', 'Reyes', 'mark.reyes@school.edu'],
        
        // Section 2: LFCA411N121
        [2, '2022-02011', 'Christian', 'Bautista', 'christian.bautista@school.edu'],
        [2, '2022-02012', 'Sarah', 'Garcia', 'sarah.garcia@school.edu'],
        [2, '2022-02013', 'Joshua', 'Ocampo', 'joshua.ocampo@school.edu'],

        // Section 3: LFCA411N122
        [3, '2022-03021', 'Daniel', 'Mendoza', 'daniel.mendoza@school.edu'],
        [3, '2022-03022', 'Princess', 'Torres', 'princess.torres@school.edu'],
        [3, '2022-03023', 'Angelo', 'Aquino', 'angelo.aquino@school.edu'],
    ];

    $stuStmt = $pdo->prepare("INSERT INTO students (section_id, student_number, first_name, last_name, email) VALUES (?, ?, ?, ?, ?)");
    $createdStudents = [];
    foreach ($studentsList as $st) {
        $secId = $sectionIds[$st[0] - 1];
        $stuStmt->execute([$secId, $st[1], $st[2], $st[3], $st[4]]);
        $createdStudents[] = [
            'id' => $pdo->lastInsertId(),
            'section_id' => $secId
        ];
    }

    // Seed Sessions & Records
    $sessStmt = $pdo->prepare("INSERT INTO attendance_sessions (user_id, section_id, session_date, start_time, end_time, qr_token, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Past completed sessions
    $today = new DateTime();
    $pastDate1 = (clone $today)->modify("-2 day")->format('Y-m-d');
    $pastDate2 = (clone $today)->modify("-1 day")->format('Y-m-d');

    $sessStmt->execute([$userId, $sectionIds[0], $pastDate1, '08:00:00', '09:30:00', bin2hex(random_bytes(16)), 'completed']);
    $sess1 = $pdo->lastInsertId();

    $sessStmt->execute([$userId, $sectionIds[1], $pastDate2, '10:00:00', '11:30:00', bin2hex(random_bytes(16)), 'completed']);
    $sess2 = $pdo->lastInsertId();

    // Today's completed session
    $sessStmt->execute([$userId, $sectionIds[0], $today->format('Y-m-d'), '08:00:00', '09:30:00', bin2hex(random_bytes(16)), 'completed']);
    $sessToday = $pdo->lastInsertId();

    // Attendance records
    $recStmt = $pdo->prepare("INSERT INTO attendance_records (session_id, student_id, status, scanned_at) VALUES (?, ?, ?, ?)");
    
    foreach ($createdStudents as $cs) {
        if ($cs['section_id'] === $sectionIds[0]) {
            $recStmt->execute([$sess1, $cs['id'], 'present', $pastDate1 . ' 08:12:00']);
            $recStmt->execute([$sessToday, $cs['id'], 'present', $today->format('Y-m-d') . ' 08:05:00']);
        }
        if ($cs['section_id'] === $sectionIds[1]) {
            $recStmt->execute([$sess2, $cs['id'], ($cs['id'] % 2 === 0 ? 'late' : 'present'), $pastDate2 . ' 10:14:00']);
        }
    }

    echo "SUCCESS: Database reseeded with LFCA322N012, LFCA411N121, LFCA411N122, 9 students, and real attendance records.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
