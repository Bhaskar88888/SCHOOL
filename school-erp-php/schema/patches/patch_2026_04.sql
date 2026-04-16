-- ============================================================
-- School ERP PHP — Schema Patches (April 2026)
-- Purpose: Add missing columns/tables for Node.js parity
-- Run: mysql -u user -p database < patch_2026_04.sql
-- ============================================================

-- ─── 1. Student Additional Fields ───────────────────────────
ALTER TABLE students ADD COLUMN IF NOT EXISTS apaar_id VARCHAR(50) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS pen VARCHAR(50) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS enrollment_no VARCHAR(100) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS previous_school VARCHAR(255) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(255) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(20) NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS admission_notes TEXT NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS rfid_tag_hex VARCHAR(100) UNIQUE NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS canteen_balance DECIMAL(10,2) DEFAULT 0.00;

-- ─── 2. Leave Balances on Users ─────────────────────────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS casual_leave_balance INT DEFAULT 12;
ALTER TABLE users ADD COLUMN IF NOT EXISTS earned_leave_balance INT DEFAULT 18;
ALTER TABLE users ADD COLUMN IF NOT EXISTS sick_leave_balance INT DEFAULT 10;

-- ─── 3. Transport Attendance ────────────────────────────────
CREATE TABLE IF NOT EXISTS transport_attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bus_id INT NOT NULL,
  student_id INT NOT NULL,
  date DATE NOT NULL,
  status VARCHAR(50) DEFAULT 'boarded',
  marked_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bus_student_date (bus_id, student_id, date)
);

-- ─── 4. Canteen Sales (Wallet / RFID) ───────────────────────
CREATE TABLE IF NOT EXISTS canteen_sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  total_amount DECIMAL(10,2) DEFAULT 0,
  sold_to VARCHAR(100) NULL,
  sold_by INT NULL,
  payment_mode VARCHAR(50) DEFAULT 'cash',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS canteen_sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  item_id INT NULL,
  quantity INT DEFAULT 1,
  price DECIMAL(10,2) NULL
);

-- ─── 5. Hostel Room Status Normalization ────────────────────
ALTER TABLE hostel_rooms ADD COLUMN IF NOT EXISTS occupied_beds INT DEFAULT 0;

-- ─── 6. Payroll ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payroll (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  month TINYINT NOT NULL,
  year SMALLINT NOT NULL,
  basic_salary DECIMAL(10,2) DEFAULT 0,
  hra DECIMAL(10,2) DEFAULT 0,
  da DECIMAL(10,2) DEFAULT 0,
  conveyance DECIMAL(10,2) DEFAULT 0,
  medical_allowance DECIMAL(10,2) DEFAULT 0,
  special_allowance DECIMAL(10,2) DEFAULT 0,
  total_earnings DECIMAL(10,2) DEFAULT 0,
  pf_deduction DECIMAL(10,2) DEFAULT 0,
  tax_deduction DECIMAL(10,2) DEFAULT 0,
  other_deductions DECIMAL(10,2) DEFAULT 0,
  total_deductions DECIMAL(10,2) DEFAULT 0,
  net_pay DECIMAL(10,2) DEFAULT 0,
  is_paid TINYINT DEFAULT 0,
  paid_on DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_month_year (staff_id, month, year)
);

-- ─── 7. Salary Structures ───────────────────────────────────
CREATE TABLE IF NOT EXISTS salary_structures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  staff_id INT NOT NULL,
  basic_salary DECIMAL(10,2) DEFAULT 0,
  hra DECIMAL(10,2) DEFAULT 0,
  da DECIMAL(10,2) DEFAULT 0,
  conveyance DECIMAL(10,2) DEFAULT 0,
  medical_allowance DECIMAL(10,2) DEFAULT 0,
  special_allowance DECIMAL(10,2) DEFAULT 0,
  pf_deduction DECIMAL(10,2) DEFAULT 0,
  tax_deduction DECIMAL(10,2) DEFAULT 0,
  other_deductions DECIMAL(10,2) DEFAULT 0,
  effective_from DATE DEFAULT (CURRENT_DATE),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── 8. Receipt Number Unique Constraint ────────────────────
ALTER TABLE fees ADD COLUMN IF NOT EXISTS receipt_no VARCHAR(50) NULL;
CREATE INDEX IF NOT EXISTS idx_fees_receipt_no ON fees(receipt_no);

-- ─── 9. Library Cover Image ─────────────────────────────────
ALTER TABLE library_books ADD COLUMN IF NOT EXISTS cover_image_url VARCHAR(500) NULL;

-- ─── 10. Transport Vehicles (Driver/Conductor Assignment) ───
ALTER TABLE transport_vehicles ADD COLUMN IF NOT EXISTS driver_id INT NULL;
ALTER TABLE transport_vehicles ADD COLUMN IF NOT EXISTS conductor_id INT NULL;
ALTER TABLE transport_vehicles ADD COLUMN IF NOT EXISTS is_active TINYINT DEFAULT 1;

-- ─── 11. Complaint Routing Columns ──────────────────────────
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'general';
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS target_user_id INT NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS assigned_to_role VARCHAR(50) NULL;
ALTER TABLE complaints ADD COLUMN IF NOT EXISTS raised_by_role VARCHAR(50) NULL;
CREATE INDEX IF NOT EXISTS idx_complaints_type ON complaints(type);
CREATE INDEX IF NOT EXISTS idx_complaints_target ON complaints(target_user_id);

-- ─── Done ────────────────────────────────────────────────────
-- Verify: SHOW TABLES;  DESCRIBE students;  DESCRIBE users;
-- ============================================================
