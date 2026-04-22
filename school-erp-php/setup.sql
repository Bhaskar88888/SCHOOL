-- School ERP PHP v3.0 - Database Setup Script
-- ==============================================
-- Run this in phpMyAdmin or via: mysql -u root -p school_erp < setup.sql
-- ==============================================

-- CREATE DATABASE IF NOT EXISTS school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE school_erp;

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

-- Compatibility columns used by expanded PHP modules
ALTER TABLE classes
    ADD COLUMN IF NOT EXISTS teacher_id INT DEFAULT NULL AFTER class_teacher_id;

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS section VARCHAR(20) DEFAULT NULL AFTER class_id,
    ADD COLUMN IF NOT EXISTS roll_number VARCHAR(50) DEFAULT NULL AFTER section;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS date_of_birth DATE DEFAULT NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS joining_date DATE DEFAULT NULL AFTER date_of_birth,
    ADD COLUMN IF NOT EXISTS gender ENUM('male','female','other') DEFAULT NULL AFTER joining_date,
    ADD COLUMN IF NOT EXISTS blood_group VARCHAR(10) DEFAULT NULL AFTER gender,
    ADD COLUMN IF NOT EXISTS highest_qualification VARCHAR(150) DEFAULT NULL AFTER blood_group,
    ADD COLUMN IF NOT EXISTS experience_years INT DEFAULT 0 AFTER highest_qualification,
    ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(150) DEFAULT NULL AFTER experience_years,
    ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20) DEFAULT NULL AFTER emergency_contact_name,
    ADD COLUMN IF NOT EXISTS staff_address TEXT DEFAULT NULL AFTER emergency_contact_phone,
    ADD COLUMN IF NOT EXISTS casual_leave_balance INT DEFAULT 12 AFTER staff_address,
    ADD COLUMN IF NOT EXISTS earned_leave_balance INT DEFAULT 15 AFTER casual_leave_balance,
    ADD COLUMN IF NOT EXISTS sick_leave_balance INT DEFAULT 10 AFTER earned_leave_balance;

-- Core module tables required by setup_complete.sql and module APIs
CREATE TABLE IF NOT EXISTS exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    class_id INT NOT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    exam_date DATE DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    max_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 33,
    description TEXT DEFAULT NULL,
    is_archived TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_exams_class (class_id),
    INDEX idx_exams_date (exam_date),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    marks_obtained DECIMAL(8,2) DEFAULT 0,
    grade VARCHAR(10) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    remarks TEXT DEFAULT NULL,
    entered_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exam_student (exam_id, student_id),
    INDEX idx_exam_results_exam (exam_id),
    INDEX idx_exam_results_student (student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    isbn VARCHAR(50) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    publisher VARCHAR(150) DEFAULT NULL,
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    shelf_location VARCHAR(100) DEFAULT NULL,
    cover_image_url TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_library_books_isbn (isbn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT DEFAULT NULL,
    staff_id INT DEFAULT NULL,
    issue_date DATE DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    return_date DATE DEFAULT NULL,
    fine_amount DECIMAL(8,2) DEFAULT 0,
    fine_per_day DECIMAL(8,2) DEFAULT 5.00,
    is_returned TINYINT(1) DEFAULT 0,
    issued_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_library_issues_book (book_id),
    INDEX idx_library_issues_due_date (due_date),
    FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_no VARCHAR(100) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    capacity INT DEFAULT 50,
    driver_name VARCHAR(150) DEFAULT NULL,
    driver_phone VARCHAR(20) DEFAULT NULL,
    route_id INT DEFAULT NULL,
    number_plate VARCHAR(50) DEFAULT NULL,
    conductor_name VARCHAR(150) DEFAULT NULL,
    conductor_phone VARCHAR(20) DEFAULT NULL,
    driver_id INT DEFAULT NULL,
    conductor_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transport_vehicles_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bus_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(150) NOT NULL,
    route_code VARCHAR(50) DEFAULT NULL,
    vehicle_id INT DEFAULT NULL,
    driver_id INT DEFAULT NULL,
    conductor_id INT DEFAULT NULL,
    stops TEXT DEFAULT NULL,
    monthly_fee DECIMAL(10,2) DEFAULT 0,
    total_distance DECIMAL(8,2) DEFAULT 0,
    capacity INT DEFAULT 50,
    is_active TINYINT(1) DEFAULT 1,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bus_routes_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_available TINYINT(1) DEFAULT 1,
    available_qty INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_canteen_item_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS canteen_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT DEFAULT NULL,
    ordered_by INT DEFAULT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_canteen_orders_item (item_id),
    FOREIGN KEY (item_id) REFERENCES canteen_items(id) ON DELETE SET NULL,
    FOREIGN KEY (ordered_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hostel_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_no VARCHAR(50) NOT NULL,
    block VARCHAR(100) DEFAULT NULL,
    room_type_id INT DEFAULT NULL,
    capacity INT DEFAULT 1,
    occupied_beds INT DEFAULT 0,
    floor VARCHAR(50) DEFAULT NULL,
    type VARCHAR(50) DEFAULT NULL,
    monthly_fee DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'available',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_hostel_room_no (room_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hostel_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    room_type_id INT DEFAULT NULL,
    fee_structure_id INT DEFAULT NULL,
    bed_label VARCHAR(10) DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    allocated_date DATE DEFAULT NULL,
    check_in_date DATE DEFAULT NULL,
    vacated_date DATE DEFAULT NULL,
    check_out_date DATE DEFAULT NULL,
    allotment_date DATETIME DEFAULT NULL,
    vacated_on DATETIME DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'ACTIVE',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_hostel_allocations_student (student_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES hostel_rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id INT NOT NULL,
    leave_type VARCHAR(50) NOT NULL,
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    reason TEXT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    review_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leave_applicant (applicant_id),
    INDEX idx_leave_status (status),
    FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    month TINYINT NOT NULL,
    year SMALLINT NOT NULL,
    basic_salary DECIMAL(10,2) DEFAULT 0,
    allowances DECIMAL(10,2) DEFAULT 0,
    deductions DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'pending',
    paid_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll_staff_month_year (staff_id, month, year),
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    target_roles VARCHAR(255) DEFAULT 'all',
    priority VARCHAR(20) DEFAULT 'normal',
    expiry_date DATE DEFAULT NULL,
    published TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    related_class_id INT DEFAULT NULL,
    related_student_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_notices_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    type VARCHAR(50) DEFAULT 'general',
    priority VARCHAR(20) DEFAULT 'medium',
    submitted_by INT DEFAULT NULL,
    target_user_id INT DEFAULT NULL,
    student_id INT DEFAULT NULL,
    class_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    assigned_to_role VARCHAR(50) DEFAULT NULL,
    raised_by_role VARCHAR(50) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    resolution_note TEXT DEFAULT NULL,
    resolution TEXT DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    is_visible_to_target TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_complaints_status (status),
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS homework (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    attachments TEXT DEFAULT NULL,
    class_id INT NOT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    assigned_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_homework_class (class_id),
    INDEX idx_homework_due (due_date),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routine (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    day VARCHAR(20) NOT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    teacher_id INT DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    room VARCHAR(100) DEFAULT NULL,
    timetable TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_routine_class (class_id),
    INDEX idx_routine_day (day),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT DEFAULT NULL,
    remark TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_remarks_student (student_id),
    INDEX idx_remarks_teacher (teacher_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_user INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_user (target_user),
    FOREIGN KEY (target_user) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL DEFAULT 'No Subject',
    type ENUM('direct','group','system') DEFAULT 'direct',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_message_threads_type (type),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thread_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_thread_participant (thread_id, user_id),
    INDEX idx_thread_participants_user (user_id),
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT DEFAULT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    related_complaint_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_messages_thread (thread_id),
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_deleted (is_deleted),
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default superadmin user (password: Password@123 - must change on first login!)
INSERT IGNORE INTO users (name, email, password, role, is_active, password_must_change)
VALUES ('Super Admin', 'admin@school.com', '$2y$10$CJDIfbKeT12yns6zLpWXdOsL1ooYxHVmj9Mnzoo/Wr58ox4zTY0IO', 'superadmin', 1, 1);

SET FOREIGN_KEY_CHECKS = 1;

-- Done
SELECT 'Database setup complete! Login with admin@school.com / Password@123 (change on first login!)' AS status;
