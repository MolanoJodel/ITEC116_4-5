-- ClassTrack: import once in phpMyAdmin on a fresh local database.
-- No DROP statements. Demo credentials are for local testing only.
CREATE DATABASE IF NOT EXISTS student_monitor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_monitor;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('teacher','student') NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    must_change TINYINT(1) NOT NULL DEFAULT 1,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    teacher_id INT UNSIGNED NOT NULL,
    student_number VARCHAR(32) NOT NULL UNIQUE,
    section VARCHAR(60) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_teacher_section (teacher_id, section)
) ENGINE=InnoDB;

CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present','Absent') NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance (student_id, attendance_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE grades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    subject VARCHAR(100) NOT NULL,
    period ENUM('Prelim','Midterm','Final') NOT NULL,
    grade DECIMAL(5,2) NOT NULL CHECK (grade BETWEEN 0 AND 100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grade (student_id, subject, period),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE login_limits (
    limit_key CHAR(64) PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL,
    window_start DATETIME NOT NULL
) ENGINE=InnoDB;

-- Demo accounts: both must change their temporary password on first login.
INSERT INTO users (id,username,password_hash,role,display_name,must_change) VALUES
(1,'teacher','$2y$12$xnjLbn5srAfi3JLADg9aWuRIwik7POhrwR.wdDI.eyj2AI6HdP1Q2','teacher','Demo Teacher',1),
(2,'2026-001','$2y$12$642QnmzgtdpLnnpiq4d0fun5Wl/WeEEpJ5lFgJCPFxxjmsfqgEmju','student','Alex Reyes',1);
INSERT INTO students (id,user_id,teacher_id,student_number,section) VALUES (1,2,1,'2026-001','BSIT 3-1');
INSERT INTO attendance (student_id,attendance_date,status) VALUES
(1,CURDATE()-INTERVAL 3 DAY,'Present'),(1,CURDATE()-INTERVAL 2 DAY,'Absent'),(1,CURDATE()-INTERVAL 1 DAY,'Present');
INSERT INTO grades (student_id,subject,period,grade) VALUES
(1,'ITEC 116','Prelim',92.50),(1,'ITEC 116','Midterm',94.00),(1,'Web Development','Prelim',90.00);
