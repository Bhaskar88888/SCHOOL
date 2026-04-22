-- ============================================
-- School ERP PHP v3.0 - Complete Database Schema
-- Matches Node.js project features exactly
-- Run this to upgrade from v2.0 to v3.0
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- EXISTING TABLES UPDATES
-- ============================================

-- Update users table with additional fields
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `employee_id` varchar(50) UNIQUE AFTER `id`,
ADD COLUMN IF NOT EXISTS `department` varchar(100) AFTER `role`,
ADD COLUMN IF NOT EXISTS `designation` varchar(100) AFTER `department`,
ADD COLUMN IF NOT EXISTS `basic_salary` decimal(10,2) DEFAULT 0 AFTER `designation`,
ADD COLUMN IF NOT EXISTS `hra` decimal(10,2) DEFAULT 0 AFTER `basic_salary`,
ADD COLUMN IF NOT EXISTS `da` decimal(10,2) DEFAULT 0 AFTER `hra`,
ADD COLUMN IF NOT EXISTS `conveyance` decimal(10,2) DEFAULT 0 AFTER `da`,
ADD COLUMN IF NOT EXISTS `medical_allowance` decimal(10,2) DEFAULT 0 AFTER `conveyance`,
ADD COLUMN IF NOT EXISTS `special_allowance` decimal(10,2) DEFAULT 0 AFTER `medical_allowance`,
ADD COLUMN IF NOT EXISTS `pf_deduction` decimal(10,2) DEFAULT 0 AFTER `special_allowance`,
ADD COLUMN IF NOT EXISTS `tax_deduction` decimal(10,2) DEFAULT 0 AFTER `pf_deduction`,
ADD COLUMN IF NOT EXISTS `leave_balance_casual` int DEFAULT 0 AFTER `tax_deduction`,
ADD COLUMN IF NOT EXISTS `leave_balance_earned` int DEFAULT 0 AFTER `leave_balance_casual`,
ADD COLUMN IF NOT EXISTS `leave_balance_sick` int DEFAULT 0 AFTER `leave_balance_earned`,
ADD COLUMN IF NOT EXISTS `login_attempts` int DEFAULT 0 AFTER `leave_balance_sick`,
ADD COLUMN IF NOT EXISTS `locked_until` datetime DEFAULT NULL AFTER `login_attempts`,
ADD COLUMN IF NOT EXISTS `reset_token` varchar(255) DEFAULT NULL AFTER `locked_until`,
ADD COLUMN IF NOT EXISTS `reset_token_expiry` datetime DEFAULT NULL AFTER `reset_token`,
ADD COLUMN IF NOT EXISTS `updated_at` datetime DEFAULT NOW() ON UPDATE NOW() AFTER `created_at`;

-- Update students table with additional fields
ALTER TABLE `students`
ADD COLUMN IF NOT EXISTS `user_id` int UNIQUE AFTER `id`,
ADD COLUMN IF NOT EXISTS `section` varchar(20) AFTER `class_id`,
ADD COLUMN IF NOT EXISTS `roll_number` varchar(50) AFTER `section`,
ADD COLUMN IF NOT EXISTS `parent_phone` varchar(20) AFTER `phone`,
ADD COLUMN IF NOT EXISTS `parent_email` varchar(150) AFTER `parent_phone`,
ADD COLUMN IF NOT EXISTS `structured_address` text AFTER `address`,
ADD COLUMN IF NOT EXISTS `transport_required` tinyint(1) DEFAULT 0 AFTER `structured_address`,
ADD COLUMN IF NOT EXISTS `hostel_required` tinyint(1) DEFAULT 0 AFTER `transport_required`,
ADD COLUMN IF NOT EXISTS `canteen_balance` decimal(10,2) DEFAULT 0 AFTER `hostel_required`,
ADD COLUMN IF NOT EXISTS `rfid_tag` varchar(50) UNIQUE AFTER `canteen_balance`,
ADD COLUMN IF NOT EXISTS `admission_date` date DEFAULT NOW() AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `discharge_date` date DEFAULT NULL AFTER `admission_date`,
ADD COLUMN IF NOT EXISTS `discharge_reason` text AFTER `discharge_date`,
ADD COLUMN IF NOT EXISTS `previous_school` varchar(200) AFTER `discharge_reason`,
ADD COLUMN IF NOT EXISTS `bank_account` varchar(50) AFTER `previous_school`,
ADD COLUMN IF NOT EXISTS `bank_ifsc` varchar(20) AFTER `bank_account`;

ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `date_of_birth` date DEFAULT NULL AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `joining_date` date DEFAULT NULL AFTER `date_of_birth`,
ADD COLUMN IF NOT EXISTS `gender` enum('male','female','other') DEFAULT NULL AFTER `joining_date`,
ADD COLUMN IF NOT EXISTS `blood_group` varchar(10) DEFAULT NULL AFTER `gender`,
ADD COLUMN IF NOT EXISTS `highest_qualification` varchar(150) DEFAULT NULL AFTER `blood_group`,
ADD COLUMN IF NOT EXISTS `experience_years` int DEFAULT 0 AFTER `highest_qualification`,
ADD COLUMN IF NOT EXISTS `emergency_contact_name` varchar(150) DEFAULT NULL AFTER `experience_years`,
ADD COLUMN IF NOT EXISTS `emergency_contact_phone` varchar(20) DEFAULT NULL AFTER `emergency_contact_name`,
ADD COLUMN IF NOT EXISTS `staff_address` text DEFAULT NULL AFTER `emergency_contact_phone`,
ADD COLUMN IF NOT EXISTS `casual_leave_balance` int DEFAULT 12 AFTER `staff_address`,
ADD COLUMN IF NOT EXISTS `earned_leave_balance` int DEFAULT 15 AFTER `casual_leave_balance`,
ADD COLUMN IF NOT EXISTS `sick_leave_balance` int DEFAULT 10 AFTER `earned_leave_balance`;

ALTER TABLE `classes`
ADD COLUMN IF NOT EXISTS `teacher_id` int DEFAULT NULL AFTER `class_teacher_id`;

-- ============================================
-- LEGACY MODULE TABLES REQUIRED BEFORE LATER ALTERS
-- ============================================

CREATE TABLE IF NOT EXISTS `exams` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `class_id` int NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `max_marks` int DEFAULT 100,
  `pass_marks` int DEFAULT 33,
  `description` text,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_exams_class` (`class_id`),
  INDEX `idx_exams_date` (`exam_date`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `exam_results` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `exam_id` int NOT NULL,
  `student_id` int NOT NULL,
  `marks_obtained` decimal(8,2) DEFAULT 0,
  `grade` varchar(10) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `remarks` text,
  `entered_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY `unique_exam_student` (`exam_id`, `student_id`),
  INDEX `idx_exam_results_exam` (`exam_id`),
  INDEX `idx_exam_results_student` (`student_id`),
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`entered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `library_books` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `publisher` varchar(150) DEFAULT NULL,
  `total_copies` int DEFAULT 1,
  `available_copies` int DEFAULT 1,
  `shelf_location` varchar(100) DEFAULT NULL,
  `cover_image_url` text,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_library_books_isbn` (`isbn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `library_issues` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `book_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `staff_id` int DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `fine_amount` decimal(8,2) DEFAULT 0,
  `fine_per_day` decimal(8,2) DEFAULT 5.00,
  `is_returned` tinyint(1) DEFAULT 0,
  `issued_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  INDEX `idx_library_issues_book` (`book_id`),
  INDEX `idx_library_issues_due_date` (`due_date`),
  FOREIGN KEY (`book_id`) REFERENCES `library_books`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`issued_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transport_vehicles` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `vehicle_no` varchar(100) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `capacity` int DEFAULT 50,
  `driver_name` varchar(150) DEFAULT NULL,
  `driver_phone` varchar(20) DEFAULT NULL,
  `route_id` int DEFAULT NULL,
  `number_plate` varchar(50) DEFAULT NULL,
  `conductor_name` varchar(150) DEFAULT NULL,
  `conductor_phone` varchar(20) DEFAULT NULL,
  `driver_id` int DEFAULT NULL,
  `conductor_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_transport_vehicles_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bus_routes` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `route_name` varchar(150) NOT NULL,
  `route_code` varchar(50) DEFAULT NULL,
  `vehicle_id` int DEFAULT NULL,
  `driver_id` int DEFAULT NULL,
  `conductor_id` int DEFAULT NULL,
  `stops` text,
  `monthly_fee` decimal(10,2) DEFAULT 0,
  `total_distance` decimal(8,2) DEFAULT 0,
  `capacity` int DEFAULT 50,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_bus_routes_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `canteen_items` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `available_qty` int DEFAULT 0,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY `unique_canteen_item_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `canteen_orders` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `item_id` int DEFAULT NULL,
  `ordered_by` int DEFAULT NULL,
  `quantity` int DEFAULT 1,
  `total_price` decimal(10,2) DEFAULT 0,
  `created_at` datetime DEFAULT NOW(),
  INDEX `idx_canteen_orders_item` (`item_id`),
  FOREIGN KEY (`item_id`) REFERENCES `canteen_items`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`ordered_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hostel_rooms` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `room_no` varchar(50) NOT NULL,
  `block` varchar(100) DEFAULT NULL,
  `room_type_id` int DEFAULT NULL,
  `capacity` int DEFAULT 1,
  `occupied_beds` int DEFAULT 0,
  `floor` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `monthly_fee` decimal(10,2) DEFAULT 0,
  `status` varchar(20) DEFAULT 'available',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY `unique_hostel_room_no` (`room_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hostel_allocations` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `student_id` int NOT NULL,
  `room_id` int NOT NULL,
  `room_type_id` int DEFAULT NULL,
  `fee_structure_id` int DEFAULT NULL,
  `bed_label` varchar(10) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `allocated_date` date DEFAULT NULL,
  `check_in_date` date DEFAULT NULL,
  `vacated_date` date DEFAULT NULL,
  `check_out_date` date DEFAULT NULL,
  `allotment_date` datetime DEFAULT NULL,
  `vacated_on` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'ACTIVE',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT NOW(),
  INDEX `idx_hostel_allocations_student` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`room_id`) REFERENCES `hostel_rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_applications` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `applicant_id` int NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `reason` text,
  `status` varchar(20) DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `review_note` text,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_leave_applicant` (`applicant_id`),
  INDEX `idx_leave_status` (`status`),
  FOREIGN KEY (`applicant_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `staff_id` int NOT NULL,
  `month` tinyint NOT NULL,
  `year` smallint NOT NULL,
  `basic_salary` decimal(10,2) DEFAULT 0,
  `allowances` decimal(10,2) DEFAULT 0,
  `deductions` decimal(10,2) DEFAULT 0,
  `net_salary` decimal(10,2) DEFAULT 0,
  `status` varchar(20) DEFAULT 'pending',
  `paid_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  UNIQUE KEY `uq_payroll_staff_month_year` (`staff_id`, `month`, `year`),
  FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notices` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `target_roles` varchar(255) DEFAULT 'all',
  `priority` varchar(20) DEFAULT 'normal',
  `expiry_date` date DEFAULT NULL,
  `published` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int DEFAULT NULL,
  `related_class_id` int DEFAULT NULL,
  `related_student_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_notices_active` (`is_active`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `complaints` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(50) DEFAULT 'general',
  `type` varchar(50) DEFAULT 'general',
  `priority` varchar(20) DEFAULT 'medium',
  `submitted_by` int DEFAULT NULL,
  `target_user_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `class_id` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `assigned_to_role` varchar(50) DEFAULT NULL,
  `raised_by_role` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `resolution_note` text,
  `resolution` text,
  `resolved_at` datetime DEFAULT NULL,
  `is_visible_to_target` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_complaints_status` (`status`),
  FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `homework` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `description` text,
  `attachments` text,
  `class_id` int NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `assigned_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_homework_class` (`class_id`),
  INDEX `idx_homework_due` (`due_date`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `routine` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `class_id` int NOT NULL,
  `day` varchar(20) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `timetable` text,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_routine_class` (`class_id`),
  INDEX `idx_routine_day` (`day`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `remarks` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `student_id` int NOT NULL,
  `teacher_id` int DEFAULT NULL,
  `remark` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_remarks_student` (`student_id`),
  INDEX `idx_remarks_teacher` (`teacher_id`),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_user` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NOW(),
  INDEX `idx_notifications_read` (`is_read`),
  INDEX `idx_notifications_user` (`target_user`),
  FOREIGN KEY (`target_user`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `message_threads` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `subject` varchar(255) NOT NULL DEFAULT 'No Subject',
  `type` enum('direct','group','system') DEFAULT 'direct',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  `updated_at` datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX `idx_message_threads_type` (`type`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `thread_participants` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `thread_id` int NOT NULL,
  `user_id` int NOT NULL,
  `last_read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  UNIQUE KEY `uq_thread_participant` (`thread_id`, `user_id`),
  INDEX `idx_thread_participants_user` (`user_id`),
  FOREIGN KEY (`thread_id`) REFERENCES `message_threads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `thread_id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int DEFAULT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `related_complaint_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NOW(),
  INDEX `idx_messages_thread` (`thread_id`),
  INDEX `idx_messages_sender` (`sender_id`),
  INDEX `idx_messages_deleted` (`is_deleted`),
  FOREIGN KEY (`thread_id`) REFERENCES `message_threads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- NEW TABLES - MISSING FROM v2.0
-- ============================================

-- Fee Structures table
CREATE TABLE IF NOT EXISTS `fee_structures` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `class_id` int NOT NULL,
  `fee_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `term` varchar(50) DEFAULT 'Annual',
  `due_date` date,
  `late_fee` decimal(10,2) DEFAULT 0,
  `description` text,
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Class Subjects table
CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `subject` varchar(100) NOT NULL,
  `periods_per_week` int DEFAULT 5,
  `class_id` int NOT NULL,
  `teacher_id` int,
  `created_at` datetime DEFAULT NOW(),
  UNIQUE KEY `unique_class_subject` (`class_id`, `subject`),
  FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Salary Structures table
CREATE TABLE IF NOT EXISTS `salary_structures` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `staff_id` int NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `hra` decimal(10,2) DEFAULT 0,
  `da` decimal(10,2) DEFAULT 0,
  `conveyance` decimal(10,2) DEFAULT 0,
  `medical_allowance` decimal(10,2) DEFAULT 0,
  `special_allowance` decimal(10,2) DEFAULT 0,
  `pf_deduction` decimal(10,2) DEFAULT 0,
  `tax_deduction` decimal(10,2) DEFAULT 0,
  `effective_from` date NOT NULL,
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bus Stops table
CREATE TABLE IF NOT EXISTS `bus_stops` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `route_id` int NOT NULL,
  `stop_name` varchar(200) NOT NULL,
  `sequence` int NOT NULL,
  `arrival_time` time,
  `departure_time` time,
  `latitude` decimal(10,8),
  `longitude` decimal(11,8),
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`route_id`) REFERENCES `bus_routes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hostel Room Types table
CREATE TABLE IF NOT EXISTS `hostel_room_types` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `occupancy` int DEFAULT 2,
  `gender_policy` varchar(20) DEFAULT 'co-ed',
  `default_fee` decimal(10,2) DEFAULT 0,
  `amenities` text,
  `created_at` datetime DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hostel Fee Structures table
CREATE TABLE IF NOT EXISTS `hostel_fee_structures` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `room_type_id` int NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `term` varchar(50) DEFAULT 'Annual',
  `billing_cycle` varchar(50) DEFAULT 'Monthly',
  `amount` decimal(10,2) NOT NULL,
  `caution_deposit` decimal(10,2) DEFAULT 0,
  `mess_charge` decimal(10,2) DEFAULT 0,
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`room_type_id`) REFERENCES `hostel_room_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Canteen Sale Items table
CREATE TABLE IF NOT EXISTS `canteen_sale_items` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `sale_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`sale_id`) REFERENCES `canteen_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `canteen_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transport Attendance table
CREATE TABLE IF NOT EXISTS `transport_attendance` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `bus_id` int NOT NULL,
  `student_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('boarded','not_boarded','dropped','not_dropped') DEFAULT 'boarded',
  `marked_by` int,
  `created_at` datetime DEFAULT NOW(),
  UNIQUE KEY `unique_transport_attendance` (`student_id`, `date`, `status`),
  FOREIGN KEY (`bus_id`) REFERENCES `transport_vehicles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Logs Enhanced table
CREATE TABLE IF NOT EXISTS `audit_logs_enhanced` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `user_id` int,
  `action` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `record_id` varchar(50),
  `old_value` text,
  `new_value` text,
  `ip_address` varchar(50),
  `user_agent` varchar(255),
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Chatbot Logs table
CREATE TABLE IF NOT EXISTS `chatbot_logs` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `user_id` int,
  `user_role` varchar(50),
  `message` text NOT NULL,
  `language` varchar(20) DEFAULT 'en',
  `intent` varchar(100),
  `response` text,
  `response_time` float,
  `session_id` varchar(100),
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Counters table (for ID generation)
CREATE TABLE IF NOT EXISTS `counters` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(50) NOT NULL,
  `year` varchar(10) NOT NULL,
  `sequence` int DEFAULT 0,
  UNIQUE KEY `unique_counter` (`name`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Archived Students table
CREATE TABLE IF NOT EXISTS `archived_students` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `original_id` int,
  `name` varchar(150) NOT NULL,
  `admission_no` varchar(50),
  `class_id` int,
  `dob` date,
  `gender` varchar(20),
  `parent_name` varchar(150),
  `phone` varchar(20),
  `admission_date` date,
  `discharge_date` date,
  `discharge_reason` text,
  `archived_at` datetime DEFAULT NOW(),
  `archived_by` int
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Archived Staff table
CREATE TABLE IF NOT EXISTS `archived_staff` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `original_id` int,
  `name` varchar(150) NOT NULL,
  `email` varchar(150),
  `employee_id` varchar(50),
  `role` varchar(50),
  `department` varchar(100),
  `designation` varchar(100),
  `archived_at` datetime DEFAULT NOW(),
  `archived_by` int
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications Enhanced table (replace existing)
CREATE TABLE IF NOT EXISTS `notifications_enhanced` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `recipient_id` int NOT NULL,
  `sender_id` int,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime,
  `created_at` datetime DEFAULT NOW(),
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff Attendance Enhanced table
CREATE TABLE IF NOT EXISTS `staff_attendance_enhanced` (
  `id` int AUTO_INCREMENT PRIMARY KEY,
  `staff_id` int NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','half_day','on_leave') DEFAULT 'present',
  `check_in_time` time,
  `check_out_time` time,
  `remarks` text,
  `marked_by` int,
  `created_at` datetime DEFAULT NOW(),
  UNIQUE KEY `unique_staff_attendance` (`staff_id`, `date`),
  FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update existing fees table
ALTER TABLE `fees`
ADD COLUMN IF NOT EXISTS `fee_structure_id` int AFTER `student_id`,
ADD COLUMN IF NOT EXISTS `original_amount` decimal(10,2) AFTER `total_amount`,
ADD COLUMN IF NOT EXISTS `discount` decimal(10,2) DEFAULT 0 AFTER `original_amount`,
ADD COLUMN IF NOT EXISTS `academic_year` varchar(20) AFTER `year`,
ADD COLUMN IF NOT EXISTS `sms_sent` tinyint(1) DEFAULT 0 AFTER `remarks`;

-- Update existing attendance table
ALTER TABLE `attendance`
ADD COLUMN IF NOT EXISTS `subject` varchar(100) AFTER `status`,
ADD COLUMN IF NOT EXISTS `sms_sent` tinyint(1) DEFAULT 0 AFTER `note`;

-- Update existing exam_results table
ALTER TABLE `exam_results`
ADD COLUMN IF NOT EXISTS `total_marks` int DEFAULT 100 AFTER `marks_obtained`,
ADD COLUMN IF NOT EXISTS `percentage` decimal(5,2) GENERATED ALWAYS AS ((`marks_obtained` / `total_marks`) * 100) STORED AFTER `total_marks`;

-- Update existing hostel_rooms table
ALTER TABLE `hostel_rooms`
ADD COLUMN IF NOT EXISTS `room_type_id` int AFTER `id`,
ADD COLUMN IF NOT EXISTS `block` varchar(100) DEFAULT NULL AFTER `room_no`,
ADD COLUMN IF NOT EXISTS `occupied_beds` int DEFAULT 0 AFTER `capacity`,
ADD COLUMN IF NOT EXISTS `type` varchar(50) DEFAULT NULL AFTER `floor`,
ADD COLUMN IF NOT EXISTS `monthly_fee` decimal(10,2) DEFAULT 0 AFTER `type`,
ADD COLUMN IF NOT EXISTS `status` enum('available','occupied','maintenance','blocked') DEFAULT 'available' AFTER `is_active`;

-- Update existing hostel_allocations table
ALTER TABLE `hostel_allocations`
ADD COLUMN IF NOT EXISTS `room_type_id` int AFTER `student_id`,
ADD COLUMN IF NOT EXISTS `fee_structure_id` int AFTER `room_type_id`,
ADD COLUMN IF NOT EXISTS `bed_label` varchar(10) AFTER `is_active`,
ADD COLUMN IF NOT EXISTS `academic_year` varchar(20) AFTER `bed_label`,
ADD COLUMN IF NOT EXISTS `check_in_date` date DEFAULT NULL AFTER `allocated_date`,
ADD COLUMN IF NOT EXISTS `check_out_date` date DEFAULT NULL AFTER `vacated_date`,
ADD COLUMN IF NOT EXISTS `allotment_date` datetime DEFAULT NULL AFTER `check_out_date`,
ADD COLUMN IF NOT EXISTS `vacated_on` datetime DEFAULT NULL AFTER `allotment_date`,
ADD COLUMN IF NOT EXISTS `status` varchar(20) DEFAULT 'ACTIVE' AFTER `vacated_on`;

-- Update existing transport_vehicles table
ALTER TABLE `transport_vehicles`
ADD COLUMN IF NOT EXISTS `route_id` int AFTER `id`,
ADD COLUMN IF NOT EXISTS `number_plate` varchar(50) AFTER `vehicle_no`,
ADD COLUMN IF NOT EXISTS `conductor_name` varchar(150) AFTER `driver_name`,
ADD COLUMN IF NOT EXISTS `conductor_phone` varchar(20) AFTER `driver_phone`;

-- Update existing bus_routes table
ALTER TABLE `bus_routes`
ADD COLUMN IF NOT EXISTS `route_code` varchar(50) UNIQUE AFTER `route_name`,
ADD COLUMN IF NOT EXISTS `driver_id` int AFTER `vehicle_id`,
ADD COLUMN IF NOT EXISTS `conductor_id` int AFTER `driver_id`,
ADD COLUMN IF NOT EXISTS `total_distance` decimal(8,2) DEFAULT 0 AFTER `monthly_fee`,
ADD COLUMN IF NOT EXISTS `capacity` int DEFAULT 50 AFTER `total_distance`;

-- Update existing canteen_orders table to be canteen_sales
RENAME TABLE `canteen_orders` TO `canteen_sales`;

ALTER TABLE `canteen_sales`
ADD COLUMN IF NOT EXISTS `payment_mode` enum('cash','card','upi','wallet') DEFAULT 'cash' AFTER `total_price`,
ADD COLUMN IF NOT EXISTS `sold_to` int AFTER `ordered_by`,
ADD COLUMN IF NOT EXISTS `sold_by` int AFTER `sold_to`,
ADD COLUMN IF NOT EXISTS `sale_date` datetime DEFAULT NOW() AFTER `created_at`;

-- Update existing leave_applications table
ALTER TABLE `leave_applications`
ADD COLUMN IF NOT EXISTS `leave_type_enum` enum('casual','earned','sick','maternity','paternity','loss_of_pay') DEFAULT 'sick' AFTER `leave_type`,
ADD COLUMN IF NOT EXISTS `days_count` int GENERATED ALWAYS AS (DATEDIFF(`to_date`, `from_date`) + 1) STORED AFTER `to_date`;

-- Update existing payroll table
ALTER TABLE `payroll`
ADD COLUMN IF NOT EXISTS `total_earnings` decimal(10,2) GENERATED ALWAYS AS (`basic_salary` + `allowances`) STORED AFTER `allowances`,
ADD COLUMN IF NOT EXISTS `total_deductions` decimal(10,2) GENERATED ALWAYS AS (`deductions`) STORED AFTER `total_earnings`,
ADD COLUMN IF NOT EXISTS `is_paid` tinyint(1) DEFAULT 0 AFTER `status`,
ADD COLUMN IF NOT EXISTS `attendance_days` int AFTER `is_paid`,
ADD COLUMN IF NOT EXISTS `attendance_percentage` decimal(5,2) AFTER `attendance_days`;

-- Update existing library_books table
ALTER TABLE `library_books`
ADD COLUMN IF NOT EXISTS `cover_image_url` text AFTER `isbn`;

-- Update existing library_issues table
ALTER TABLE `library_issues`
ADD COLUMN IF NOT EXISTS `fine_per_day` decimal(8,2) DEFAULT 5.00 AFTER `fine_amount`;

-- Update existing notices table
ALTER TABLE `notices`
ADD COLUMN IF NOT EXISTS `priority` enum('low','medium','high','urgent') DEFAULT 'medium' AFTER `target_roles`,
ADD COLUMN IF NOT EXISTS `published` tinyint(1) DEFAULT 1 AFTER `is_active`,
ADD COLUMN IF NOT EXISTS `related_class_id` int AFTER `created_by`,
ADD COLUMN IF NOT EXISTS `related_student_id` int AFTER `related_class_id`;

-- Update existing complaints table
ALTER TABLE `complaints`
ADD COLUMN IF NOT EXISTS `target_user_id` int AFTER `submitted_by`,
ADD COLUMN IF NOT EXISTS `student_id` int AFTER `target_user_id`,
ADD COLUMN IF NOT EXISTS `class_id` int AFTER `student_id`,
ADD COLUMN IF NOT EXISTS `raised_by_role` varchar(50) AFTER `submitted_by`,
ADD COLUMN IF NOT EXISTS `assigned_to_role` varchar(50) AFTER `assigned_to`,
ADD COLUMN IF NOT EXISTS `resolution` text AFTER `resolved_at`;

-- Update existing homework table
ALTER TABLE `homework`
ADD COLUMN IF NOT EXISTS `attachments` text AFTER `description`;

-- Update existing routine table
ALTER TABLE `routine`
ADD COLUMN IF NOT EXISTS `timetable` text AFTER `room`;

-- ============================================
-- ADD MISSING ROLES TO ENUMS
-- ============================================

-- Add conductor and driver roles
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('superadmin','admin','teacher','student','parent','accountant','librarian','hr','canteen','conductor','driver') DEFAULT 'teacher';

-- ============================================
-- SEED DATA
-- ============================================

-- Add default counters
INSERT IGNORE INTO `counters` (`name`, `year`, `sequence`) VALUES
('admission', '2025-2026', 0),
('employee', '2025-2026', 0),
('receipt', '2025-2026', 0),
('payroll', '2025-2026', 0);

-- Add HR role user if not exists
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `employee_id`) VALUES
('HR Manager', 'hr@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'hr', 'EMP001'),
('Canteen Manager', 'canteen@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'canteen', 'EMP002'),
('Bus Driver 1', 'driver1@school.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'conductor', 'EMP003');

-- Add default room types
INSERT IGNORE INTO `hostel_room_types` (`name`, `occupancy`, `gender_policy`, `default_fee`, `amenities`) VALUES
('Single Room', 1, 'separate', 15000.00, '["AC", "Attached Bathroom", "Study Table"]'),
('Double Sharing', 2, 'separate', 10000.00, '["AC", "Shared Bathroom", "Study Table"]'),
('Triple Sharing', 3, 'separate', 7000.00, '["Non-AC", "Shared Bathroom", "Study Table"]'),
('Dormitory', 8, 'separate', 4000.00, '["Non-AC", "Common Bathroom", "Study Area"]');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- VERIFICATION QUERY
-- ============================================
-- Run this to verify all tables exist:
-- SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = 'u668948495_school' ORDER BY TABLE_NAME;
