-- Smart Student Attendance System Database Schema
-- Target Database: test (TiDB Cloud)
USE test;

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
INSERT INTO students (section_id, student_number, first_name, last_name, email) VALUES
(1, '2024-00001', 'John 1', 'Reyes', 'student1@school.edu'),
(2, '2024-00002', 'Sarah 2', 'Cruz', 'student2@school.edu'),
(3, '2024-00003', 'Joshua 3', 'Bautista', 'student3@school.edu'),
(4, '2024-00004', 'Christian 4', 'Ocampo', 'student4@school.edu'),
(5, '2024-00005', 'Angel 5', 'Garcia', 'student5@school.edu'),
(6, '2024-00006', 'Princess 6', 'Mendoza', 'student6@school.edu'),
(1, '2024-00007', 'Daniel 7', 'Torres', 'student7@school.edu'),
(2, '2024-00008', 'Michael 8', 'Tomas', 'student8@school.edu'),
(3, '2024-00009', 'Mary 9', 'Aquino', 'student9@school.edu'),
(4, '2024-00010', 'Mark 10', 'Santos', 'student10@school.edu'),
(5, '2024-00011', 'John 11', 'Reyes', 'student11@school.edu'),
(6, '2024-00012', 'Sarah 12', 'Cruz', 'student12@school.edu'),
(1, '2024-00013', 'Joshua 13', 'Bautista', 'student13@school.edu'),
(2, '2024-00014', 'Christian 14', 'Ocampo', 'student14@school.edu'),
(3, '2024-00015', 'Angel 15', 'Garcia', 'student15@school.edu'),
(4, '2024-00016', 'Princess 16', 'Mendoza', 'student16@school.edu'),
(5, '2024-00017', 'Daniel 17', 'Torres', 'student17@school.edu'),
(6, '2024-00018', 'Michael 18', 'Tomas', 'student18@school.edu'),
(1, '2024-00019', 'Mary 19', 'Aquino', 'student19@school.edu'),
(2, '2024-00020', 'Mark 20', 'Santos', 'student20@school.edu'),
(3, '2024-00021', 'John 21', 'Reyes', 'student21@school.edu'),
(4, '2024-00022', 'Sarah 22', 'Cruz', 'student22@school.edu'),
(5, '2024-00023', 'Joshua 23', 'Bautista', 'student23@school.edu'),
(6, '2024-00024', 'Christian 24', 'Ocampo', 'student24@school.edu'),
(1, '2024-00025', 'Angel 25', 'Garcia', 'student25@school.edu'),
(2, '2024-00026', 'Princess 26', 'Mendoza', 'student26@school.edu'),
(3, '2024-00027', 'Daniel 27', 'Torres', 'student27@school.edu'),
(4, '2024-00028', 'Michael 28', 'Tomas', 'student28@school.edu'),
(5, '2024-00029', 'Mary 29', 'Aquino', 'student29@school.edu'),
(6, '2024-00030', 'Mark 30', 'Santos', 'student30@school.edu'),
(1, '2024-00031', 'John 31', 'Reyes', 'student31@school.edu'),
(2, '2024-00032', 'Sarah 32', 'Cruz', 'student32@school.edu'),
(3, '2024-00033', 'Joshua 33', 'Bautista', 'student33@school.edu'),
(4, '2024-00034', 'Christian 34', 'Ocampo', 'student34@school.edu'),
(5, '2024-00035', 'Angel 35', 'Garcia', 'student35@school.edu'),
(6, '2024-00036', 'Princess 36', 'Mendoza', 'student36@school.edu'),
(1, '2024-00037', 'Daniel 37', 'Torres', 'student37@school.edu'),
(2, '2024-00038', 'Michael 38', 'Tomas', 'student38@school.edu'),
(3, '2024-00039', 'Mary 39', 'Aquino', 'student39@school.edu'),
(4, '2024-00040', 'Mark 40', 'Santos', 'student40@school.edu'),
(5, '2024-00041', 'John 41', 'Reyes', 'student41@school.edu'),
(6, '2024-00042', 'Sarah 42', 'Cruz', 'student42@school.edu'),
(1, '2024-00043', 'Joshua 43', 'Bautista', 'student43@school.edu'),
(2, '2024-00044', 'Christian 44', 'Ocampo', 'student44@school.edu'),
(3, '2024-00045', 'Angel 45', 'Garcia', 'student45@school.edu'),
(4, '2024-00046', 'Princess 46', 'Mendoza', 'student46@school.edu'),
(5, '2024-00047', 'Daniel 47', 'Torres', 'student47@school.edu'),
(6, '2024-00048', 'Michael 48', 'Tomas', 'student48@school.edu'),
(1, '2024-00049', 'Mary 49', 'Aquino', 'student49@school.edu'),
(2, '2024-00050', 'Mark 50', 'Santos', 'student50@school.edu'),
(3, '2024-00051', 'John 51', 'Reyes', 'student51@school.edu'),
(4, '2024-00052', 'Sarah 52', 'Cruz', 'student52@school.edu'),
(5, '2024-00053', 'Joshua 53', 'Bautista', 'student53@school.edu'),
(6, '2024-00054', 'Christian 54', 'Ocampo', 'student54@school.edu'),
(1, '2024-00055', 'Angel 55', 'Garcia', 'student55@school.edu'),
(2, '2024-00056', 'Princess 56', 'Mendoza', 'student56@school.edu'),
(3, '2024-00057', 'Daniel 57', 'Torres', 'student57@school.edu'),
(4, '2024-00058', 'Michael 58', 'Tomas', 'student58@school.edu'),
(5, '2024-00059', 'Mary 59', 'Aquino', 'student59@school.edu'),
(6, '2024-00060', 'Mark 60', 'Santos', 'student60@school.edu'),
(1, '2024-00061', 'John 61', 'Reyes', 'student61@school.edu'),
(2, '2024-00062', 'Sarah 62', 'Cruz', 'student62@school.edu'),
(3, '2024-00063', 'Joshua 63', 'Bautista', 'student63@school.edu'),
(4, '2024-00064', 'Christian 64', 'Ocampo', 'student64@school.edu'),
(5, '2024-00065', 'Angel 65', 'Garcia', 'student65@school.edu'),
(6, '2024-00066', 'Princess 66', 'Mendoza', 'student66@school.edu'),
(1, '2024-00067', 'Daniel 67', 'Torres', 'student67@school.edu'),
(2, '2024-00068', 'Michael 68', 'Tomas', 'student68@school.edu'),
(3, '2024-00069', 'Mary 69', 'Aquino', 'student69@school.edu'),
(4, '2024-00070', 'Mark 70', 'Santos', 'student70@school.edu'),
(5, '2024-00071', 'John 71', 'Reyes', 'student71@school.edu'),
(6, '2024-00072', 'Sarah 72', 'Cruz', 'student72@school.edu'),
(1, '2024-00073', 'Joshua 73', 'Bautista', 'student73@school.edu'),
(2, '2024-00074', 'Christian 74', 'Ocampo', 'student74@school.edu'),
(3, '2024-00075', 'Angel 75', 'Garcia', 'student75@school.edu'),
(4, '2024-00076', 'Princess 76', 'Mendoza', 'student76@school.edu'),
(5, '2024-00077', 'Daniel 77', 'Torres', 'student77@school.edu'),
(6, '2024-00078', 'Michael 78', 'Tomas', 'student78@school.edu'),
(1, '2024-00079', 'Mary 79', 'Aquino', 'student79@school.edu'),
(2, '2024-00080', 'Mark 80', 'Santos', 'student80@school.edu'),
(3, '2024-00081', 'John 81', 'Reyes', 'student81@school.edu'),
(4, '2024-00082', 'Sarah 82', 'Cruz', 'student82@school.edu'),
(5, '2024-00083', 'Joshua 83', 'Bautista', 'student83@school.edu'),
(6, '2024-00084', 'Christian 84', 'Ocampo', 'student84@school.edu'),
(1, '2024-00085', 'Angel 85', 'Garcia', 'student85@school.edu'),
(2, '2024-00086', 'Princess 86', 'Mendoza', 'student86@school.edu'),
(3, '2024-00087', 'Daniel 87', 'Torres', 'student87@school.edu'),
(4, '2024-00088', 'Michael 88', 'Tomas', 'student88@school.edu'),
(5, '2024-00089', 'Mary 89', 'Aquino', 'student89@school.edu'),
(6, '2024-00090', 'Mark 90', 'Santos', 'student90@school.edu'),
(1, '2024-00091', 'John 91', 'Reyes', 'student91@school.edu'),
(2, '2024-00092', 'Sarah 92', 'Cruz', 'student92@school.edu'),
(3, '2024-00093', 'Joshua 93', 'Bautista', 'student93@school.edu'),
(4, '2024-00094', 'Christian 94', 'Ocampo', 'student94@school.edu'),
(5, '2024-00095', 'Angel 95', 'Garcia', 'student95@school.edu'),
(6, '2024-00096', 'Princess 96', 'Mendoza', 'student96@school.edu'),
(1, '2024-00097', 'Daniel 97', 'Torres', 'student97@school.edu'),
(2, '2024-00098', 'Michael 98', 'Tomas', 'student98@school.edu'),
(3, '2024-00099', 'Mary 99', 'Aquino', 'student99@school.edu'),
(4, '2024-00100', 'Mark 100', 'Santos', 'student100@school.edu'),
(5, '2024-00101', 'John 101', 'Reyes', 'student101@school.edu'),
(6, '2024-00102', 'Sarah 102', 'Cruz', 'student102@school.edu'),
(1, '2024-00103', 'Joshua 103', 'Bautista', 'student103@school.edu'),
(2, '2024-00104', 'Christian 104', 'Ocampo', 'student104@school.edu'),
(3, '2024-00105', 'Angel 105', 'Garcia', 'student105@school.edu'),
(4, '2024-00106', 'Princess 106', 'Mendoza', 'student106@school.edu'),
(5, '2024-00107', 'Daniel 107', 'Torres', 'student107@school.edu'),
(6, '2024-00108', 'Michael 108', 'Tomas', 'student108@school.edu'),
(1, '2024-00109', 'Mary 109', 'Aquino', 'student109@school.edu'),
(2, '2024-00110', 'Mark 110', 'Santos', 'student110@school.edu'),
(3, '2024-00111', 'John 111', 'Reyes', 'student111@school.edu'),
(4, '2024-00112', 'Sarah 112', 'Cruz', 'student112@school.edu'),
(5, '2024-00113', 'Joshua 113', 'Bautista', 'student113@school.edu'),
(6, '2024-00114', 'Christian 114', 'Ocampo', 'student114@school.edu'),
(1, '2024-00115', 'Angel 115', 'Garcia', 'student115@school.edu'),
(2, '2024-00116', 'Princess 116', 'Mendoza', 'student116@school.edu'),
(3, '2024-00117', 'Daniel 117', 'Torres', 'student117@school.edu'),
(4, '2024-00118', 'Michael 118', 'Tomas', 'student118@school.edu'),
(5, '2024-00119', 'Mary 119', 'Aquino', 'student119@school.edu'),
(6, '2024-00120', 'Mark 120', 'Santos', 'student120@school.edu'),
(1, '2024-00121', 'John 121', 'Reyes', 'student121@school.edu'),
(2, '2024-00122', 'Sarah 122', 'Cruz', 'student122@school.edu'),
(3, '2024-00123', 'Joshua 123', 'Bautista', 'student123@school.edu'),
(4, '2024-00124', 'Christian 124', 'Ocampo', 'student124@school.edu'),
(5, '2024-00125', 'Angel 125', 'Garcia', 'student125@school.edu'),
(6, '2024-00126', 'Princess 126', 'Mendoza', 'student126@school.edu'),
(1, '2024-00127', 'Daniel 127', 'Torres', 'student127@school.edu'),
(2, '2024-00128', 'Michael 128', 'Tomas', 'student128@school.edu'),
(3, '2024-00129', 'Mary 129', 'Aquino', 'student129@school.edu'),
(4, '2024-00130', 'Mark 130', 'Santos', 'student130@school.edu'),
(5, '2024-00131', 'John 131', 'Reyes', 'student131@school.edu'),
(6, '2024-00132', 'Sarah 132', 'Cruz', 'student132@school.edu'),
(1, '2024-00133', 'Joshua 133', 'Bautista', 'student133@school.edu'),
(2, '2024-00134', 'Christian 134', 'Ocampo', 'student134@school.edu'),
(3, '2024-00135', 'Angel 135', 'Garcia', 'student135@school.edu'),
(4, '2024-00136', 'Princess 136', 'Mendoza', 'student136@school.edu'),
(5, '2024-00137', 'Daniel 137', 'Torres', 'student137@school.edu'),
(6, '2024-00138', 'Michael 138', 'Tomas', 'student138@school.edu'),
(1, '2024-00139', 'Mary 139', 'Aquino', 'student139@school.edu'),
(2, '2024-00140', 'Mark 140', 'Santos', 'student140@school.edu'),
(3, '2024-00141', 'John 141', 'Reyes', 'student141@school.edu'),
(4, '2024-00142', 'Sarah 142', 'Cruz', 'student142@school.edu'),
(5, '2024-00143', 'Joshua 143', 'Bautista', 'student143@school.edu'),
(6, '2024-00144', 'Christian 144', 'Ocampo', 'student144@school.edu'),
(1, '2024-00145', 'Angel 145', 'Garcia', 'student145@school.edu'),
(2, '2024-00146', 'Princess 146', 'Mendoza', 'student146@school.edu'),
(3, '2024-00147', 'Daniel 147', 'Torres', 'student147@school.edu'),
(4, '2024-00148', 'Michael 148', 'Tomas', 'student148@school.edu'),
(5, '2024-00149', 'Mary 149', 'Aquino', 'student149@school.edu'),
(6, '2024-00150', 'Mark 150', 'Santos', 'student150@school.edu'),
(1, '2024-00151', 'John 151', 'Reyes', 'student151@school.edu'),
(2, '2024-00152', 'Sarah 152', 'Cruz', 'student152@school.edu'),
(3, '2024-00153', 'Joshua 153', 'Bautista', 'student153@school.edu'),
(4, '2024-00154', 'Christian 154', 'Ocampo', 'student154@school.edu'),
(5, '2024-00155', 'Angel 155', 'Garcia', 'student155@school.edu'),
(6, '2024-00156', 'Princess 156', 'Mendoza', 'student156@school.edu'),
(1, '2024-00157', 'Daniel 157', 'Torres', 'student157@school.edu'),
(2, '2024-00158', 'Michael 158', 'Tomas', 'student158@school.edu'),
(3, '2024-00159', 'Mary 159', 'Aquino', 'student159@school.edu'),
(4, '2024-00160', 'Mark 160', 'Santos', 'student160@school.edu'),
(5, '2024-00161', 'John 161', 'Reyes', 'student161@school.edu'),
(6, '2024-00162', 'Sarah 162', 'Cruz', 'student162@school.edu'),
(1, '2024-00163', 'Joshua 163', 'Bautista', 'student163@school.edu'),
(2, '2024-00164', 'Christian 164', 'Ocampo', 'student164@school.edu'),
(3, '2024-00165', 'Angel 165', 'Garcia', 'student165@school.edu'),
(4, '2024-00166', 'Princess 166', 'Mendoza', 'student166@school.edu'),
(5, '2024-00167', 'Daniel 167', 'Torres', 'student167@school.edu'),
(6, '2024-00168', 'Michael 168', 'Tomas', 'student168@school.edu'),
(1, '2024-00169', 'Mary 169', 'Aquino', 'student169@school.edu'),
(2, '2024-00170', 'Mark 170', 'Santos', 'student170@school.edu'),
(3, '2024-00171', 'John 171', 'Reyes', 'student171@school.edu'),
(4, '2024-00172', 'Sarah 172', 'Cruz', 'student172@school.edu'),
(5, '2024-00173', 'Joshua 173', 'Bautista', 'student173@school.edu'),
(6, '2024-00174', 'Christian 174', 'Ocampo', 'student174@school.edu'),
(1, '2024-00175', 'Angel 175', 'Garcia', 'student175@school.edu'),
(2, '2024-00176', 'Princess 176', 'Mendoza', 'student176@school.edu'),
(3, '2024-00177', 'Daniel 177', 'Torres', 'student177@school.edu'),
(4, '2024-00178', 'Michael 178', 'Tomas', 'student178@school.edu'),
(5, '2024-00179', 'Mary 179', 'Aquino', 'student179@school.edu'),
(6, '2024-00180', 'Mark 180', 'Santos', 'student180@school.edu'),
(1, '2024-00181', 'John 181', 'Reyes', 'student181@school.edu'),
(2, '2024-00182', 'Sarah 182', 'Cruz', 'student182@school.edu'),
(3, '2024-00183', 'Joshua 183', 'Bautista', 'student183@school.edu'),
(4, '2024-00184', 'Christian 184', 'Ocampo', 'student184@school.edu'),
(5, '2024-00185', 'Angel 185', 'Garcia', 'student185@school.edu'),
(6, '2024-00186', 'Princess 186', 'Mendoza', 'student186@school.edu'),
(1, '2024-00187', 'Daniel 187', 'Torres', 'student187@school.edu'),
(2, '2024-00188', 'Michael 188', 'Tomas', 'student188@school.edu'),
(3, '2024-00189', 'Mary 189', 'Aquino', 'student189@school.edu'),
(4, '2024-00190', 'Mark 190', 'Santos', 'student190@school.edu'),
(5, '2024-00191', 'John 191', 'Reyes', 'student191@school.edu'),
(6, '2024-00192', 'Sarah 192', 'Cruz', 'student192@school.edu'),
(1, '2024-00193', 'Joshua 193', 'Bautista', 'student193@school.edu'),
(2, '2024-00194', 'Christian 194', 'Ocampo', 'student194@school.edu'),
(3, '2024-00195', 'Angel 195', 'Garcia', 'student195@school.edu'),
(4, '2024-00196', 'Princess 196', 'Mendoza', 'student196@school.edu'),
(5, '2024-00197', 'Daniel 197', 'Torres', 'student197@school.edu'),
(6, '2024-00198', 'Michael 198', 'Tomas', 'student198@school.edu'),
(1, '2024-00199', 'Mary 199', 'Aquino', 'student199@school.edu'),
(2, '2024-00200', 'Mark 200', 'Santos', 'student200@school.edu'),
(3, '2024-00201', 'John 201', 'Reyes', 'student201@school.edu'),
(4, '2024-00202', 'Sarah 202', 'Cruz', 'student202@school.edu'),
(5, '2024-00203', 'Joshua 203', 'Bautista', 'student203@school.edu'),
(6, '2024-00204', 'Christian 204', 'Ocampo', 'student204@school.edu'),
(1, '2024-00205', 'Angel 205', 'Garcia', 'student205@school.edu'),
(2, '2024-00206', 'Princess 206', 'Mendoza', 'student206@school.edu'),
(3, '2024-00207', 'Daniel 207', 'Torres', 'student207@school.edu'),
(4, '2024-00208', 'Michael 208', 'Tomas', 'student208@school.edu'),
(5, '2024-00209', 'Mary 209', 'Aquino', 'student209@school.edu'),
(6, '2024-00210', 'Mark 210', 'Santos', 'student210@school.edu'),
(1, '2024-00211', 'John 211', 'Reyes', 'student211@school.edu'),
(2, '2024-00212', 'Sarah 212', 'Cruz', 'student212@school.edu'),
(3, '2024-00213', 'Joshua 213', 'Bautista', 'student213@school.edu'),
(4, '2024-00214', 'Christian 214', 'Ocampo', 'student214@school.edu');

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