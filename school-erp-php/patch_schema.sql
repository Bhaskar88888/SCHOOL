-- School ERP PHP parity patch
-- Apply on the PHP database before using the expanded Students / HR / Payroll forms.

-- -----------------------------------------------------
-- Students
-- -----------------------------------------------------
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS admission_no varchar(50) UNIQUE AFTER id,
  ADD COLUMN IF NOT EXISTS section varchar(20) AFTER class_id,
  ADD COLUMN IF NOT EXISTS parent_phone varchar(20) AFTER parent_name,
  ADD COLUMN IF NOT EXISTS parent_email varchar(150) AFTER parent_phone,
  ADD COLUMN IF NOT EXISTS father_name varchar(150) AFTER parent_email,
  ADD COLUMN IF NOT EXISTS father_occupation varchar(150) AFTER father_name,
  ADD COLUMN IF NOT EXISTS mother_name varchar(150) AFTER father_occupation,
  ADD COLUMN IF NOT EXISTS mother_phone varchar(20) AFTER mother_name,
  ADD COLUMN IF NOT EXISTS guardian_name varchar(150) AFTER mother_phone,
  ADD COLUMN IF NOT EXISTS guardian_phone varchar(20) AFTER guardian_name,
  ADD COLUMN IF NOT EXISTS aadhaar varchar(20) AFTER guardian_phone,
  ADD COLUMN IF NOT EXISTS blood_group varchar(10) AFTER aadhaar,
  ADD COLUMN IF NOT EXISTS nationality varchar(100) DEFAULT 'Indian' AFTER blood_group,
  ADD COLUMN IF NOT EXISTS religion varchar(100) AFTER nationality,
  ADD COLUMN IF NOT EXISTS category varchar(50) AFTER religion,
  ADD COLUMN IF NOT EXISTS mother_tongue varchar(100) AFTER category,
  ADD COLUMN IF NOT EXISTS address_line1 varchar(255) AFTER address,
  ADD COLUMN IF NOT EXISTS address_line2 varchar(255) AFTER address_line1,
  ADD COLUMN IF NOT EXISTS city varchar(100) AFTER address_line2,
  ADD COLUMN IF NOT EXISTS state varchar(100) AFTER city,
  ADD COLUMN IF NOT EXISTS pincode varchar(10) AFTER state,
  ADD COLUMN IF NOT EXISTS transport_required tinyint(1) DEFAULT 0 AFTER pincode,
  ADD COLUMN IF NOT EXISTS hostel_required tinyint(1) DEFAULT 0 AFTER transport_required,
  ADD COLUMN IF NOT EXISTS medical_conditions text AFTER hostel_required,
  ADD COLUMN IF NOT EXISTS allergies text AFTER medical_conditions,
  ADD COLUMN IF NOT EXISTS discharge_date date NULL AFTER allergies,
  ADD COLUMN IF NOT EXISTS discharge_reason text NULL AFTER discharge_date;

-- -----------------------------------------------------
-- Users / staff
-- -----------------------------------------------------
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS employee_id varchar(50) NULL UNIQUE AFTER phone,
  ADD COLUMN IF NOT EXISTS employment_type enum('permanent','contractual','part-time','visiting') DEFAULT 'permanent' AFTER designation,
  ADD COLUMN IF NOT EXISTS highest_qualification varchar(150) AFTER employment_type,
  ADD COLUMN IF NOT EXISTS experience_years int DEFAULT 0 AFTER highest_qualification,
  ADD COLUMN IF NOT EXISTS aadhaar varchar(20) AFTER experience_years,
  ADD COLUMN IF NOT EXISTS pan varchar(20) AFTER aadhaar,
  ADD COLUMN IF NOT EXISTS joining_date date AFTER pan,
  ADD COLUMN IF NOT EXISTS date_of_birth date AFTER joining_date,
  ADD COLUMN IF NOT EXISTS gender enum('male','female','other') AFTER date_of_birth,
  ADD COLUMN IF NOT EXISTS blood_group varchar(10) AFTER gender,
  ADD COLUMN IF NOT EXISTS emergency_contact_name varchar(150) AFTER blood_group,
  ADD COLUMN IF NOT EXISTS emergency_contact_phone varchar(20) AFTER emergency_contact_name,
  ADD COLUMN IF NOT EXISTS address_line1 varchar(255) AFTER emergency_contact_phone,
  ADD COLUMN IF NOT EXISTS address_line2 varchar(255) AFTER address_line1,
  ADD COLUMN IF NOT EXISTS city varchar(100) AFTER address_line2,
  ADD COLUMN IF NOT EXISTS state varchar(100) AFTER city,
  ADD COLUMN IF NOT EXISTS pincode varchar(10) AFTER state,
  ADD COLUMN IF NOT EXISTS basic_salary decimal(10,2) DEFAULT 0 AFTER pincode,
  ADD COLUMN IF NOT EXISTS hra decimal(10,2) DEFAULT 0 AFTER basic_salary,
  ADD COLUMN IF NOT EXISTS da decimal(10,2) DEFAULT 0 AFTER hra,
  ADD COLUMN IF NOT EXISTS conveyance decimal(10,2) DEFAULT 0 AFTER da,
  ADD COLUMN IF NOT EXISTS medical_allowance decimal(10,2) DEFAULT 0 AFTER conveyance,
  ADD COLUMN IF NOT EXISTS special_allowance decimal(10,2) DEFAULT 0 AFTER medical_allowance,
  ADD COLUMN IF NOT EXISTS pf_deduction decimal(10,2) DEFAULT 0 AFTER special_allowance,
  ADD COLUMN IF NOT EXISTS esi_deduction decimal(10,2) DEFAULT 0 AFTER pf_deduction,
  ADD COLUMN IF NOT EXISTS tax_deduction decimal(10,2) DEFAULT 0 AFTER esi_deduction,
  ADD COLUMN IF NOT EXISTS bank_name varchar(100) AFTER tax_deduction,
  ADD COLUMN IF NOT EXISTS account_number varchar(30) AFTER bank_name,
  ADD COLUMN IF NOT EXISTS ifsc_code varchar(15) AFTER account_number,
  ADD COLUMN IF NOT EXISTS casual_leave_balance int DEFAULT 12 AFTER ifsc_code,
  ADD COLUMN IF NOT EXISTS earned_leave_balance int DEFAULT 15 AFTER casual_leave_balance,
  ADD COLUMN IF NOT EXISTS sick_leave_balance int DEFAULT 10 AFTER earned_leave_balance,
  ADD COLUMN IF NOT EXISTS hr_notes text AFTER sick_leave_balance;

-- -----------------------------------------------------
-- Salary structures used by payroll batch generation
-- -----------------------------------------------------
ALTER TABLE salary_structures
  ADD COLUMN IF NOT EXISTS medical_allowance decimal(10,2) DEFAULT 0 AFTER conveyance,
  ADD COLUMN IF NOT EXISTS special_allowance decimal(10,2) DEFAULT 0 AFTER medical_allowance,
  ADD COLUMN IF NOT EXISTS pf_deduction decimal(10,2) DEFAULT 0 AFTER special_allowance,
  ADD COLUMN IF NOT EXISTS esi_deduction decimal(10,2) DEFAULT 0 AFTER pf_deduction,
  ADD COLUMN IF NOT EXISTS other_deductions decimal(10,2) DEFAULT 0 AFTER tax_deduction;

-- -----------------------------------------------------
-- Library Issues
-- -----------------------------------------------------
ALTER TABLE library_issues
  ADD COLUMN IF NOT EXISTS staff_id int DEFAULT NULL AFTER student_id;

-- -----------------------------------------------------
-- Hostel compatibility columns used by current UI/API
-- -----------------------------------------------------
ALTER TABLE hostel_rooms
  ADD COLUMN IF NOT EXISTS block varchar(100) DEFAULT NULL AFTER room_no,
  ADD COLUMN IF NOT EXISTS type varchar(50) DEFAULT NULL AFTER floor,
  ADD COLUMN IF NOT EXISTS monthly_fee decimal(10,2) DEFAULT 0 AFTER type;

ALTER TABLE hostel_allocations
  ADD COLUMN IF NOT EXISTS check_in_date date DEFAULT NULL AFTER allocated_date,
  ADD COLUMN IF NOT EXISTS check_out_date date DEFAULT NULL AFTER vacated_date,
  ADD COLUMN IF NOT EXISTS allotment_date datetime DEFAULT NULL AFTER check_out_date,
  ADD COLUMN IF NOT EXISTS vacated_on datetime DEFAULT NULL AFTER allotment_date,
  ADD COLUMN IF NOT EXISTS status varchar(20) DEFAULT 'ACTIVE' AFTER vacated_on;

-- -----------------------------------------------------
-- Canteen Items
-- -----------------------------------------------------
ALTER TABLE canteen_items
  ADD COLUMN IF NOT EXISTS available_qty INT DEFAULT 0 AFTER price;

-- -----------------------------------------------------
-- Transport Allocations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS transport_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  route_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY(student_id, route_id)
);

-- -----------------------------------------------------
-- Notices
-- -----------------------------------------------------
ALTER TABLE notices
  ADD COLUMN IF NOT EXISTS priority enum('normal','high','urgent') DEFAULT 'normal' AFTER target_roles,
  ADD COLUMN IF NOT EXISTS expiry_date date NULL AFTER priority;

-- -----------------------------------------------------
-- Chatbot Logs
-- -----------------------------------------------------
ALTER TABLE chatbot_logs
  ADD COLUMN IF NOT EXISTS personality varchar(20) DEFAULT 'friendly' AFTER language,
  ADD COLUMN IF NOT EXISTS suggestions text NULL AFTER response;

-- -----------------------------------------------------
-- Inter-Role Communication Hub Schema Updates
-- -----------------------------------------------------

-- 1. Fix complaints table (missing columns)
ALTER TABLE complaints
  ADD COLUMN IF NOT EXISTS type varchar(50) DEFAULT 'general' AFTER category,
  ADD COLUMN IF NOT EXISTS target_user_id int DEFAULT NULL AFTER type,
  ADD COLUMN IF NOT EXISTS raised_by_role varchar(50) DEFAULT NULL AFTER target_user_id,
  ADD COLUMN IF NOT EXISTS assigned_to_role varchar(50) DEFAULT NULL AFTER raised_by_role,
  ADD COLUMN IF NOT EXISTS resolution_note text DEFAULT NULL AFTER resolved_at,
  ADD COLUMN IF NOT EXISTS is_visible_to_target tinyint(1) DEFAULT 1 AFTER resolution_note;

-- 2. Add parent_user_id to students
ALTER TABLE students
  ADD COLUMN IF NOT EXISTS parent_user_id int DEFAULT NULL AFTER user_id,
  ADD FOREIGN KEY IF NOT EXISTS fk_student_parent (parent_user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 3. New/Enhanced: notifications_enhanced
CREATE TABLE IF NOT EXISTS notifications_enhanced (
  id int AUTO_INCREMENT PRIMARY KEY,
  recipient_id int NOT NULL,
  sender_id int,
  title varchar(255) NOT NULL,
  message text NOT NULL,
  type varchar(50) DEFAULT 'info',
  is_read tinyint(1) DEFAULT 0,
  read_at datetime,
  created_at datetime DEFAULT NOW(),
  FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE notifications_enhanced
  ADD COLUMN IF NOT EXISTS related_module varchar(60) DEFAULT NULL AFTER type,
  ADD COLUMN IF NOT EXISTS related_id int DEFAULT NULL AFTER related_module,
  ADD COLUMN IF NOT EXISTS action_url varchar(255) DEFAULT NULL AFTER read_at;

-- 4. Fee notifications log
CREATE TABLE IF NOT EXISTS fee_notifications (
  id int AUTO_INCREMENT PRIMARY KEY,
  student_id int NOT NULL,
  fee_id int DEFAULT NULL,
  message text NOT NULL,
  sent_by int DEFAULT NULL,
  channel enum('in_app','sms','email') DEFAULT 'in_app',
  is_sent tinyint(1) DEFAULT 1,
  created_at datetime DEFAULT NOW(),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Messaging schema expected by api/messages and sidebar
CREATE TABLE IF NOT EXISTS message_threads (
  id int AUTO_INCREMENT PRIMARY KEY,
  subject varchar(255) NOT NULL DEFAULT 'No Subject',
  type enum('direct','group','system') DEFAULT 'direct',
  created_by int DEFAULT NULL,
  created_at datetime DEFAULT NOW(),
  updated_at datetime DEFAULT NOW() ON UPDATE NOW(),
  INDEX idx_message_threads_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS thread_participants (
  id int AUTO_INCREMENT PRIMARY KEY,
  thread_id int NOT NULL,
  user_id int NOT NULL,
  last_read_at datetime DEFAULT NULL,
  created_at datetime DEFAULT NOW(),
  UNIQUE KEY uq_thread_participant (thread_id, user_id),
  INDEX idx_thread_participants_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id int AUTO_INCREMENT PRIMARY KEY,
  thread_id int NOT NULL,
  sender_id int NOT NULL,
  receiver_id int DEFAULT NULL,
  body text NOT NULL,
  is_read tinyint(1) DEFAULT 0,
  is_deleted tinyint(1) DEFAULT 0,
  related_complaint_id int DEFAULT NULL,
  created_at datetime DEFAULT NOW(),
  INDEX idx_messages_thread (thread_id),
  INDEX idx_messages_sender (sender_id),
  INDEX idx_messages_deleted (is_deleted),
  INDEX idx_messages_receiver (receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS is_deleted tinyint(1) DEFAULT 0 AFTER is_read,
  MODIFY COLUMN receiver_id int DEFAULT NULL;
