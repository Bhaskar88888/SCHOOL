<?php
/**
 * Library Dashboard API
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$stats = db_fetch("SELECT 
    (SELECT COUNT(*) FROM library_books) as total_books,
    (SELECT SUM(total_copies) FROM library_books) as total_copies,
    (SELECT SUM(available_copies) FROM library_books) as available_copies,
    (SELECT COUNT(*) FROM library_issues WHERE is_returned = 0) as active_loans,
    (SELECT COUNT(*) FROM library_issues WHERE is_returned = 0 AND due_date < CURDATE()) as overdue,
    (SELECT COALESCE(SUM(fine_amount), 0) FROM library_issues WHERE is_returned = 0 AND due_date < CURDATE()) as total_fines,
    (SELECT COUNT(*) FROM library_issues) as total_transactions");

// Recent transactions
$recent = db_fetchAll("SELECT li.*, b.title as book_title, s.name as student_name 
                       FROM library_issues li 
                       LEFT JOIN library_books b ON li.book_id = b.id 
                       LEFT JOIN students s ON li.student_id = s.id 
                       ORDER BY li.issue_date DESC 
                       LIMIT 10");

json_response(['stats' => $stats, 'recentTransactions' => $recent]);
