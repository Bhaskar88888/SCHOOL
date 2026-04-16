-- School ERP PHP v3.0 - Database Setup Script
-- ==============================================
-- Run this in phpMyAdmin or via: mysql -u root -p school_erp < setup.sql
-- ==============================================

CREATE DATABASE IF NOT EXISTS school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_erp;

SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','principal','staff','teacher','student','parent','hr','accounts','canteen','conductor','driver','librarian') NOT NULL DEFAULT 'teacher',
    employee_id VARCHAR(50) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    password_must_change TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classes table
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    section VARCHAR(10) DEFAULT NULL,
    class_teacher_id INT DEFAULT NULL,
    capacity INT DEFAULT 50,
    academic_year VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    admission_no VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    class_id INT DEFAULT NULL,
    dob DATE DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT 'male',
    parent_name VARCHAR(255) DEFAULT NULL,
    parent_phone VARCHAR(20) DEFAULT NULL,
    parent_email VARCHAR(255) DEFAULT NULL,
    parent_user_id INT DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    admission_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_admission_no (admission_no),
    INDEX idx_class_id (class_id),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    teacher_id INT DEFAULT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late','half-day') NOT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    sms_sent TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, class_id, date, subject),
    INDEX idx_date (date),
    INDEX idx_student_id (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fees table
CREATE TABLE IF NOT EXISTS fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type VARCHAR(100) NOT NULL DEFAULT 'Tuition Fee',
    total_amount DECIMAL(10,2) NOT NULL,
    original_amount DECIMAL(10,2) DEFAULT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance_amount DECIMAL(10,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
    discount DECIMAL(10,2) DEFAULT 0,
    payment_method VARCHAR(50) DEFAULT 'cash',
    receipt_no VARCHAR(50) UNIQUE NOT NULL,
    paid_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    month VARCHAR(20) DEFAULT NULL,
    year INT DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    collected_by INT DEFAULT NULL,
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_receipt_no (receipt_no),
    INDEX idx_balance (balance_amount),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (collected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default superadmin user (password: Password@123 - must change on first login!)
INSERT IGNORE INTO users (name, email, password, role, is_active, password_must_change)
VALUES ('Super Admin', 'admin@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- Done
SELECT 'Database setup complete! Login with admin@school.com / Password@123 (change on first login!)' AS status;
