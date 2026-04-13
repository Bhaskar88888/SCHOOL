<?php
/**
 * Fee Management Enhanced API
 * School ERP PHP v3.0 - Fee structures, defaulters, collection reports, receipts
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/sms_service.php';

require_auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET' && $method !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

// ============================================
// FEE STRUCTURES
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'structures') {
    // GET - List fee structures
    if ($method === 'GET') {
        $sql = "SELECT fs.*, c.name as class_name 
                FROM fee_structures fs 
                LEFT JOIN classes c ON fs.class_id = c.id 
                ORDER BY c.name, fs.fee_type, fs.due_date";
        $structures = db_fetchAll($sql);
        json_response(['structures' => $structures]);
    }

    // POST - Create fee structure
    if ($method === 'POST') {
        require_role(['admin', 'superadmin', 'accounts']);
        $data = get_post_json();

        $sql = "INSERT INTO fee_structures (class_id, fee_type, amount, academic_year, term, due_date, late_fee, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $id = db_insert($sql, [
            $data['class_id'],
            $data['fee_type'],
            $data['amount'],
            $data['academic_year'] ?? date('Y') . '-' . (date('Y') + 1),
            $data['term'] ?? 'Annual',
            $data['due_date'] ?? null,
            $data['late_fee'] ?? 0,
            $data['description'] ?? null
        ]);

        audit_log('CREATE', 'fee_structure', $id, null, $data);
        json_response(['message' => 'Fee structure created', 'id' => $id], 201);
    }
}

// ============================================
// FEE DEFAULTERS
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'defaulters') {
    $sql = "SELECT s.name, s.admission_no, s.parent_phone, c.name as class_name,
                   SUM(f.balance_amount) as total_pending,
                   COUNT(f.id) as pending_count
            FROM students s 
            LEFT JOIN classes c ON s.class_id = c.id 
            LEFT JOIN fees f ON f.student_id = s.id 
            WHERE s.is_active = 1 AND f.balance_amount > 0
            GROUP BY s.id 
            HAVING total_pending > 0
            ORDER BY total_pending DESC";

    $defaulters = db_fetchAll($sql);
    json_response(['defaulters' => $defaulters]);
}

// ============================================
// COLLECTION REPORT
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'collection-report') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');

    // By fee type
    $byType = db_fetchAll(
        "SELECT f.fee_type, COUNT(*) as count, SUM(f.amount_paid) as total 
         FROM fees f WHERE f.paid_date BETWEEN ? AND ? 
         GROUP BY f.fee_type ORDER BY total DESC",
        [$dateFrom, $dateTo]
    );

    // By payment mode
    $byMode = db_fetchAll(
        "SELECT f.payment_method, COUNT(*) as count, SUM(f.amount_paid) as total 
         FROM fees f WHERE f.paid_date BETWEEN ? AND ? 
         GROUP BY f.payment_method ORDER BY total DESC",
        [$dateFrom, $dateTo]
    );

    // Summary
    $summary = db_fetch(
        "SELECT COUNT(*) as total_payments, SUM(amount_paid) as total_collected
         FROM fees WHERE paid_date BETWEEN ? AND ?",
        [$dateFrom, $dateTo]
    );

    json_response(['byType' => $byType, 'byMode' => $byMode, 'summary' => $summary]);
}

// ============================================
// MY PAYMENTS (Student/Parent)
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'my') {
    $userId = get_current_user_id();
    $role = get_current_role();

    if ($role === 'student') {
        $student = db_fetch("SELECT id FROM students WHERE user_id = ?", [$userId]);
        $studentId = $student['id'] ?? null;
    } elseif ($role === 'parent') {
        $students = db_fetchAll("SELECT id FROM students WHERE parent_user_id = ?", [$userId]);
        $studentIds = array_column($students, 'id');
        $studentId = $studentIds;
    }

    if (empty($studentId)) {
        json_response(['payments' => []]);
    }

    if (is_array($studentId)) {
        $placeholders = implode(',', array_fill(0, count($studentId), '?'));
        $payments = db_fetchAll("SELECT f.*, s.name as student_name, COALESCE(f.balance_amount, f.total_amount - f.amount_paid) AS balance_amount FROM fees f LEFT JOIN students s ON f.student_id = s.id WHERE f.student_id IN ($placeholders) ORDER BY f.paid_date DESC", $studentId);
    } else {
        $payments = db_fetchAll("SELECT f.*, s.name as student_name, COALESCE(f.balance_amount, f.total_amount - f.amount_paid) AS balance_amount FROM fees f LEFT JOIN students s ON f.student_id = s.id WHERE f.student_id = ? ORDER BY f.paid_date DESC", [$studentId]);
    }

    json_response(['payments' => $payments]);
}

// ============================================
// PAYMENTS LIST (Paginated with filters)
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'payments') {
    require_role(['superadmin', 'admin', 'accounts']);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $where = ['1=1'];
    $params = [];
    if (!empty($_GET['student_id'])) {
        $where[] = 'f.student_id = ?';
        $params[] = (int) $_GET['student_id'];
    }
    if (!empty($_GET['fee_type'])) {
        $where[] = 'f.fee_type = ?';
        $params[] = sanitize($_GET['fee_type']);
    }
    if (!empty($_GET['start_date'])) {
        $where[] = 'f.paid_date >= ?';
        $params[] = sanitize($_GET['start_date']);
    }
    if (!empty($_GET['end_date'])) {
        $where[] = 'f.paid_date <= ?';
        $params[] = sanitize($_GET['end_date']);
    }
    if (!empty($_GET['class_id'])) {
        $where[] = 's.class_id = ?';
        $params[] = (int) $_GET['class_id'];
    }
    $whereSql = implode(' AND ', $where);
    $total = db_count("SELECT COUNT(*) FROM fees f LEFT JOIN students s ON f.student_id=s.id WHERE $whereSql", $params);
    $paramsPage = array_merge($params, [$limit, $offset]);
    $payments = db_fetchAll("SELECT f.*, s.name as student_name, c.name as class_name, COALESCE(f.balance_amount, f.total_amount - f.amount_paid) AS balance_amount FROM fees f LEFT JOIN students s ON f.student_id=s.id LEFT JOIN classes c ON s.class_id=c.id WHERE $whereSql ORDER BY f.paid_date DESC LIMIT ? OFFSET ?", $paramsPage);
    json_response(['data' => $payments, 'payments' => $payments, 'total' => (int) $total, 'page' => $page, 'pages' => (int) ceil($total / $limit)]);
}

// ============================================
// FEE RECEIPT
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'receipt' && isset($_GET['id'])) {
    $fee = db_fetch("SELECT f.*, s.name as student_name, s.admission_no, c.name as class_name, u.name as collected_by_name 
                     FROM fees f 
                     LEFT JOIN students s ON f.student_id = s.id 
                     LEFT JOIN classes c ON s.class_id = c.id 
                     LEFT JOIN users u ON f.collected_by = u.id 
                     WHERE f.id = ?", [$_GET['id']]);

    if (!$fee) {
        json_response(['error' => 'Fee record not found'], 404);
    }

    // Return receipt data for PDF generation
    json_response(['receipt' => $fee]);
}

// ============================================
// SEND FEE REMINDER SMS
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'send-reminders') {
    require_role(['admin', 'superadmin', 'accounts']);

    $defaulters = db_fetchAll(
        "SELECT DISTINCT s.name, s.parent_phone, SUM(f.balance_amount) as total_due 
         FROM students s 
         LEFT JOIN fees f ON f.student_id = s.id 
         WHERE s.is_active = 1 AND s.parent_phone IS NOT NULL AND f.balance_amount > 0
         GROUP BY s.id"
    );

    $smsService = SMSService::getInstance();
    $sent = 0;

    foreach ($defaulters as $defaulter) {
        $result = $smsService->feeReminder($defaulter['name'], $defaulter['parent_phone'], $defaulter['total_due'], date('Y-m-d'));
        if ($result['success'])
            $sent++;
        sleep(1); // Rate limit
    }

    json_response(['message' => "Reminders sent to $sent parents", 'sent' => $sent]);
}

// ============================================
// FEE STUDENT PAYMENT HISTORY
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'student') {
    $studentId = $_GET['id'] ?? null;
    if (!$studentId) {
        json_response(['error' => 'student_id required'], 400);
    }

    $payments = db_fetchAll("SELECT f.*, c.name as class_name 
                            FROM fees f 
                            LEFT JOIN students s ON f.student_id = s.id 
                            LEFT JOIN classes c ON s.class_id = c.id 
                            WHERE f.student_id = ? 
                            ORDER BY f.paid_date DESC", [$studentId]);

    $summary = db_fetch("SELECT 
        COUNT(*) as total_payments,
        SUM(amount_paid) as total_paid,
        SUM(balance_amount) as total_pending,
        COUNT(CASE WHEN balance_amount > 0 THEN 1 END) as pending_count
        FROM fees WHERE student_id = ?", [$studentId]);

    json_response(['payments' => $payments, 'summary' => $summary]);
}

// ============================================
// REGULAR FEE CRUD (existing)
// ============================================

// PUT - Update fee structure
if ($method === 'PUT') {
    require_role(['admin', 'superadmin', 'accounts']);
    $data = get_post_json();

    if (empty($data['id'])) {
        json_response(['error' => 'Structure ID required'], 400);
    }

    $updates = [];
    $params = [];

    foreach (['fee_type', 'amount', 'academic_year', 'term', 'due_date', 'late_fee', 'description'] as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (!empty($updates)) {
        $params[] = $data['id'];
        db_query("UPDATE fee_structures SET " . implode(', ', $updates) . " WHERE id = ?", $params);
        audit_log('UPDATE', 'fee_structure', $data['id'], null, $data);
        json_response(['message' => 'Fee structure updated']);
    }

    json_response(['error' => 'No data to update'], 400);
}

// DELETE - Delete fee structure
if ($method === 'DELETE') {
    require_role(['admin', 'superadmin', 'accounts']);
    $id = $_GET['id'] ?? null;

    if (!$id) {
        json_response(['error' => 'Structure ID required'], 400);
    }

    // Check if payments exist
    $paymentCount = db_count("SELECT COUNT(*) FROM fees WHERE fee_structure_id = ?", [$id]);
    if ($paymentCount > 0) {
        json_response(['error' => 'Cannot delete fee structure with existing payments'], 400);
    }

    db_query("DELETE FROM fee_structures WHERE id = ?", [$id]);
    audit_log('DELETE', 'fee_structure', $id);
    json_response(['message' => 'Fee structure deleted']);
}

json_response(['message' => 'Use ?action=structures|defaulters|collection-report|my|receipt|send-reminders']);
