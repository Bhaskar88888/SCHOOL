<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');
// Books
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['issues'])) {
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $books  = db_fetchAll("SELECT * FROM library_books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY title", [$search,$search,$search]);
    json_response($books);
}
// Issues
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['issues'])) {
    $issues = db_fetchAll("SELECT li.*, b.title as book_title, s.name as student_name FROM library_issues li LEFT JOIN library_books b ON li.book_id = b.id LEFT JOIN students s ON li.student_id = s.id WHERE li.is_returned = 0 ORDER BY li.due_date ASC");
    json_response($issues);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_role(['superadmin','admin','librarian']);
    $data = get_post_json();
    $action = $data['action'] ?? 'add_book';
    if ($action === 'add_book') {
        $id = db_insert("INSERT INTO library_books (title, author, isbn, category, publisher, total_copies, available_copies, shelf_location) VALUES (?,?,?,?,?,?,?,?)",
            [sanitize($data['title']), sanitize($data['author'] ?? ''), sanitize($data['isbn'] ?? ''), sanitize($data['category'] ?? ''), sanitize($data['publisher'] ?? ''), (int)($data['copies'] ?? 1), (int)($data['copies'] ?? 1), sanitize($data['shelf'] ?? '')]);
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'issue') {
        $bookId = (int)$data['book_id'];
        $book = db_fetch("SELECT available_copies FROM library_books WHERE id = ?", [$bookId]);
        if (!$book || $book['available_copies'] < 1) json_response(['error' => 'Book not available'], 400);
        db_query("UPDATE library_books SET available_copies = available_copies - 1 WHERE id = ?", [$bookId]);
        $id = db_insert("INSERT INTO library_issues (book_id, student_id, issue_date, due_date, issued_by) VALUES (?,?,?,?,?)",
            [$bookId, (int)$data['student_id'], date('Y-m-d'), $data['due_date'] ?? date('Y-m-d', strtotime('+14 days')), get_current_user_id()]);
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'return') {
        $issueId = (int)$data['issue_id'];
        $issue = db_fetch("SELECT * FROM library_issues WHERE id = ?", [$issueId]);
        $fine = 0;
        if ($issue && strtotime(date('Y-m-d')) > strtotime($issue['due_date'])) {
            $days = (strtotime(date('Y-m-d')) - strtotime($issue['due_date'])) / 86400;
            $fine = $days * 2; // ₹2 per day
        }
        db_query("UPDATE library_issues SET is_returned=1, return_date=?, fine_amount=? WHERE id=?", [date('Y-m-d'), $fine, $issueId]);
        if ($issue) db_query("UPDATE library_books SET available_copies = available_copies + 1 WHERE id=?", [$issue['book_id']]);
        json_response(['success' => true, 'fine' => $fine]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_role(['superadmin','admin','librarian']);
    db_query("DELETE FROM library_books WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
