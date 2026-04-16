-- ============================================
-- Database Indexes for Performance
-- School ERP PHP v3.0
-- Run this AFTER setup_complete.sql
-- ============================================

-- Attendance indexes (queried by date constantly)
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_attendance_student_date ON attendance(student_id, date);
CREATE INDEX IF NOT EXISTS idx_attendance_class_date ON attendance(class_id, date);

-- Fees indexes (queried by paid_date constantly)
CREATE INDEX IF NOT EXISTS idx_fees_paid_date ON fees(paid_date);
CREATE INDEX IF NOT EXISTS idx_fees_student_id ON fees(student_id);
CREATE INDEX IF NOT EXISTS idx_fees_balance ON fees(balance_amount);

-- Exam Results indexes
CREATE INDEX IF NOT EXISTS idx_exam_results_exam ON exam_results(exam_id);
CREATE INDEX IF NOT EXISTS idx_exam_results_student ON exam_results(student_id);
CREATE INDEX IF NOT EXISTS idx_exam_results_grade ON exam_results(grade);

-- Students indexes
CREATE INDEX IF NOT EXISTS idx_students_class ON students(class_id);
CREATE INDEX IF NOT EXISTS idx_students_active ON students(is_active);
CREATE INDEX IF NOT EXISTS idx_students_admission ON students(admission_no);

-- Users/Staff indexes
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Library indexes
CREATE INDEX IF NOT EXISTS idx_library_issues_returned ON library_issues(is_returned);
CREATE INDEX IF NOT EXISTS idx_library_issues_due_date ON library_issues(due_date);

-- Notices indexes
CREATE INDEX IF NOT EXISTS idx_notices_active ON notices(is_active);
CREATE INDEX IF NOT EXISTS idx_notices_created ON notices(created_at);

-- Leave indexes
CREATE INDEX IF NOT EXISTS idx_leave_status ON leave_applications(status);
CREATE INDEX IF NOT EXISTS idx_leave_applicant ON leave_applications(applicant_id);

-- Complaints indexes
CREATE INDEX IF NOT EXISTS idx_complaints_status ON complaints(status);
CREATE INDEX IF NOT EXISTS idx_complaints_submitted ON complaints(submitted_by);

-- Homework indexes
CREATE INDEX IF NOT EXISTS idx_homework_class ON homework(class_id);
CREATE INDEX IF NOT EXISTS idx_homework_due ON homework(due_date);

-- Payroll indexes
CREATE INDEX IF NOT EXISTS idx_payroll_staff_month ON payroll(staff_id, month, year);
CREATE INDEX IF NOT EXISTS idx_payroll_paid ON payroll(is_paid);

-- Transport attendance indexes
CREATE INDEX IF NOT EXISTS idx_transport_attendance_date ON transport_attendance(date);
CREATE INDEX IF NOT EXISTS idx_transport_attendance_student ON transport_attendance(student_id);

-- Audit logs indexes
CREATE INDEX IF NOT EXISTS idx_audit_logs_date ON audit_logs_enhanced(created_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_module ON audit_logs_enhanced(module);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs_enhanced(user_id);

-- Chatbot logs indexes
CREATE INDEX IF NOT EXISTS idx_chatbot_logs_user ON chatbot_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_chatbot_logs_date ON chatbot_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_chatbot_logs_intent ON chatbot_logs(intent);

-- Notifications indexes
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(target_user);

-- Hostel allocation indexes
CREATE INDEX IF NOT EXISTS idx_hostel_allocations_active ON hostel_allocations(is_active);
CREATE INDEX IF NOT EXISTS idx_hostel_allocations_student ON hostel_allocations(student_id);

-- Fee structures indexes
CREATE INDEX IF NOT EXISTS idx_fee_structures_class ON fee_structures(class_id);

-- Salary structures indexes
CREATE INDEX IF NOT EXISTS idx_salary_structures_staff ON salary_structures(staff_id);

-- Bus routes indexes
CREATE INDEX IF NOT EXISTS idx_bus_routes_active ON bus_routes(is_active);

-- Bus stops indexes
CREATE INDEX IF NOT EXISTS idx_bus_stops_route ON bus_stops(route_id);

-- Canteen indexes
CREATE INDEX IF NOT EXISTS idx_canteen_items_available ON canteen_items(is_available);

-- Routine indexes
CREATE INDEX IF NOT EXISTS idx_routine_class ON routine(class_id);
CREATE INDEX IF NOT EXISTS idx_routine_day ON routine(day);

-- Remarks indexes
CREATE INDEX IF NOT EXISTS idx_remarks_student ON remarks(student_id);
CREATE INDEX IF NOT EXISTS idx_remarks_teacher ON remarks(teacher_id);

-- Hostel rooms indexes
CREATE INDEX IF NOT EXISTS idx_hostel_rooms_status ON hostel_rooms(status);
CREATE INDEX IF NOT EXISTS idx_hostel_rooms_type ON hostel_rooms(room_type_id);
