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
ADD COLUMN IF NOT EXISTS `occupied_beds` int DEFAULT 0 AFTER `capacity`,
ADD COLUMN IF NOT EXISTS `status` enum('available','occupied','maintenance','blocked') DEFAULT 'available' AFTER `is_active`;

-- Update existing hostel_allocations table
ALTER TABLE `hostel_allocations`
ADD COLUMN IF NOT EXISTS `room_type_id` int AFTER `student_id`,
ADD COLUMN IF NOT EXISTS `fee_structure_id` int AFTER `room_type_id`,
ADD COLUMN IF NOT EXISTS `bed_label` varchar(10) AFTER `is_active`,
ADD COLUMN IF NOT EXISTS `academic_year` varchar(20) AFTER `bed_label`;

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
