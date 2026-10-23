-- Smart Student Attendance System Database Schema
-- Target Database: smart_attendance_db

DROP DATABASE IF EXISTS smart_attendance_db;
CREATE DATABASE smart_attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smart_attendance_db;

-- 1. Users / Faculty Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_id VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    title VARCHAR(50) DEFAULT 'Professor',
    avatar_initials VARCHAR(10) DEFAULT 'CP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Sections Table
CREATE TABLE sections (
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
) ENGINE=InnoDB;

-- 3. Students Table
CREATE TABLE students (
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
) ENGINE=InnoDB;

-- 4. Attendance Sessions Table
CREATE TABLE attendance_sessions (
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
) ENGINE=InnoDB;

-- 5. Attendance Records Table
CREATE TABLE attendance_records (
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
) ENGINE=InnoDB;

-- =====================================================
-- SEED DATA (Dynamic Relational Records)
-- User: Carlo Owen Soria Panang
-- Email: carlowensoriapanang@gmail.com
-- Password: Panang@12345
-- =====================================================

INSERT INTO users (id, faculty_id, name, email, password, title, avatar_initials) VALUES
(1, '2048', 'Carlo Owen Panang', 'carlowensoriapanang@gmail.com', '$2y$10$nXR3PEYmEAMpzSHEQfCYDeYH0MSsftMpbqge5Ejs/sgh.JY7INThW', 'Professor', 'CP');

-- Sections for Prof. Carlo Owen Panang
INSERT INTO sections (id, user_id, section_name, subject_code, subject_name, room, schedule_day, start_time, end_time, is_active) VALUES
(1, 1, 'BSIT 3A', 'IT 311', 'Advanced Web Systems', 'Lab 304', DAYNAME(CURDATE()), '08:00:00', '09:30:00', 1),
(2, 1, 'BSCS 2B', 'CS 220', 'Data Structures & Algorithms', 'Room 205', DAYNAME(CURDATE()), '10:00:00', '11:30:00', 1),
(3, 1, 'BSIT 2A', 'IT 214', 'Database Management Systems', 'Lab 202', 'Wednesday', '13:00:00', '14:30:00', 1),
(4, 1, 'BSIS 1A', 'IS 101', 'Fundamentals of Info Systems', 'Room 108', 'Tuesday', '09:00:00', '10:30:00', 1),
(5, 1, 'BSCS 3A', 'CS 312', 'Software Engineering', 'Lab 301', 'Thursday', '14:00:00', '15:30:00', 1),
(6, 1, 'ACT 2B', 'ACT 201', 'Web Application Development', 'Lab 105', 'Friday', '11:00:00', '12:30:00', 1);

-- Students (Distributed across sections, totaling 214 students)
-- Generating realistic student batches
DELIMITER $$
CREATE PROCEDURE SeedStudents()
BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE sec_id INT;
    DECLARE fname VARCHAR(50);
    DECLARE lname VARCHAR(50);
    
    WHILE i <= 214 DO
        SET sec_id = ((i - 1) % 6) + 1;
        SET fname = ELT(((i % 10) + 1), 'Mark', 'John', 'Sarah', 'Joshua', 'Christian', 'Angel', 'Princess', 'Daniel', 'Michael', 'Mary');
        SET lname = ELT(((i % 10) + 1), 'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Aquino');
        
        INSERT INTO students (section_id, student_number, first_name, last_name, email)
        VALUES (
            sec_id, 
            CONCAT('2024-', LPAD(i, 5, '0')), 
            CONCAT(fname, ' ', i), 
            lname, 
            CONCAT('student', i, '@school.edu')
        );
        SET i = i + 1;
    END WHILE;
END$$
DELIMITER ;

CALL SeedStudents();
DROP PROCEDURE SeedStudents();

-- Attendance Sessions for past week and today
INSERT INTO attendance_sessions (id, user_id, section_id, session_date, start_time, end_time, qr_token, status) VALUES
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:00:00', '09:30:00', UUID(), 'completed'),
(2, 1, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10:00:00', '11:30:00', UUID(), 'completed'),
(3, 1, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:00:00', '14:30:00', UUID(), 'completed'),
(4, 1, 4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:00:00', '10:30:00', UUID(), 'completed'),
(5, 1, 1, CURDATE(), '08:00:00', '09:30:00', UUID(), 'completed'),
(6, 1, 2, CURDATE(), '10:00:00', '11:30:00', UUID(), 'active');

-- Attendance Records for session 5 (Today Session 1)
INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
SELECT 5, s.id, 
       IF(MOD(s.id, 12) = 0, 'absent', IF(MOD(s.id, 15) = 0, 'late', 'present')),
       NOW()
FROM students s
WHERE s.section_id = 1;

-- Attendance Records for session 6 (Today Active Session)
INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
SELECT 6, s.id, 
       IF(MOD(s.id, 10) = 0, 'late', 'present'),
       NOW()
FROM students s
WHERE s.section_id = 2
LIMIT 30;

-- Attendance Records for earlier sessions (to compute weekly averages)
INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
SELECT 1, s.id, IF(MOD(s.id, 14) = 0, 'absent', 'present'), DATE_SUB(NOW(), INTERVAL 4 DAY)
FROM students s WHERE s.section_id = 1;

INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
SELECT 2, s.id, IF(MOD(s.id, 10) = 0, 'absent', 'present'), DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM students s WHERE s.section_id = 2;

INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
SELECT 3, s.id, IF(MOD(s.id, 12) = 0, 'absent', 'present'), DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM students s WHERE s.section_id = 3;

INSERT INTO attendance_records (session_id, student_id, status, scanned_at)
SELECT 4, s.id, IF(MOD(s.id, 15) = 0, 'absent', 'present'), DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM students s WHERE s.section_id = 4;
