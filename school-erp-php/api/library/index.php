<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    require_once __DIR__ . '/../../includes/csrf.php';
    CSRFProtection::verifyToken();
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - Dashboard stats
if ($method === 'GET' && isset($_GET['dashboard'])) {
    $booksCount = db_count("SELECT COUNT(*) FROM library_books");
    $txCount = db_count("SELECT COUNT(*) FROM library_issues");
    $activeLoans = db_count("SELECT COUNT(*) FROM library_issues WHERE is_returned = 0");
    json_response(['booksCount' => (int) $booksCount, 'transactionsCount' => (int) $txCount, 'activeLoansCount' => (int) $activeLoans]);
}

// GET - ISBN Scan from OpenLibrary
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'scan') {
    require_role(['superadmin', 'admin', 'teacher', 'staff', 'hr', 'librarian']);
    $isbn = preg_replace('/[^0-9Xx]/', '', $_GET['isbn'] ?? '');
    if (!$isbn)
        json_response(['error' => 'ISBN required'], 400);

    $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$isbn}&format=json&jscmd=data";
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false)
        json_response(['error' => 'Could not reach Open Library API'], 503);

    $apiData = json_decode($response, true);
    $bookData = $apiData["ISBN:{$isbn}"] ?? null;
    if (!$bookData)
        json_response(['error' => 'Book not found in public registry. Add manually.'], 404);

    $title = $bookData['title'] ?? 'Unknown Title';
    $author = $bookData['authors'][0]['name'] ?? 'Unknown Author';
    $cover = $bookData['cover']['large'] ?? $bookData['cover']['medium'] ?? null;

    $existing = db_fetch("SELECT * FROM library_books WHERE isbn=?", [$isbn]);
    if ($existing) {
        db_query("UPDATE library_books SET total_copies=total_copies+1, available_copies=available_copies+1 WHERE id=?", [$existing['id']]);
        json_response(['message' => 'Added another copy.', 'book' => db_fetch("SELECT * FROM library_books WHERE id=?", [$existing['id']])]);
    }

    $id = db_insert(
        "INSERT INTO library_books (isbn, title, author, cover_image_url, total_copies, available_copies, created_at) VALUES (?,?,?,?,1,1,NOW())",
        [$isbn, $title, $author, $cover]
    );
    json_response(['message' => 'New book added.', 'book' => db_fetch("SELECT * FROM library_books WHERE id=?", [$id])], 201);
}

// Books
if ($method === 'GET' && !isset($_GET['issues'])) {
    $search = '%' . sanitize($_GET['search'] ?? '') . '%';
    $books = db_fetchAll("SELECT * FROM library_books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? ORDER BY title", [$search, $search, $search]);
    json_response($books);
}
// Issues
if ($method === 'GET' && isset($_GET['issues'])) {
    $issues = db_fetchAll("SELECT li.*, b.title as book_title, s.name as student_name, u.name as staff_name FROM library_issues li LEFT JOIN library_books b ON li.book_id = b.id LEFT JOIN students s ON li.student_id = s.id LEFT JOIN users u ON li.staff_id = u.id WHERE li.is_returned = 0 ORDER BY li.due_date ASC");
    json_response($issues);
}
if ($method === 'POST') {
    require_role(['superadmin', 'admin', 'librarian']);
    $data = get_post_json();
    $action = $data['action'] ?? 'add_book';
    if ($action === 'add_book') {
        $id = db_insert(
            "INSERT INTO library_books (title, author, isbn, category, publisher, total_copies, available_copies, shelf_location) VALUES (?,?,?,?,?,?,?,?)",
            [sanitize($data['title']), sanitize($data['author'] ?? ''), sanitize($data['isbn'] ?? ''), sanitize($data['category'] ?? ''), sanitize($data['publisher'] ?? ''), (int) ($data['copies'] ?? 1), (int) ($data['copies'] ?? 1), sanitize($data['shelf'] ?? '')]
        );
        json_response(['success' => true, 'id' => $id]);
    }
    if ($action === 'edit_book') {
        $id = (int) $data['id'];
        db_query(
            "UPDATE library_books SET title=?, author=?, isbn=?, category=?, publisher=?, total_copies=?, shelf_location=? WHERE id=?",
            [sanitize($data['title']), sanitize($data['author'] ?? ''), sanitize($data['isbn'] ?? ''), sanitize($data['category'] ?? ''), sanitize($data['publisher'] ?? ''), (int) ($data['copies'] ?? 1), sanitize($data['shelf'] ?? ''), $id]
        );
        json_response(['success' => true]);
    }
    if ($action === 'issue') {
        $bookId = (int) $data['book_id'];
        $userType = $data['user_type'] ?? 'student';
        $userId = (int) $data['user_id'];
        $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

        // Atomic book issue with transaction
        try {
            db_beginTransaction();
            $book = db_fetch("SELECT available_copies FROM library_books WHERE id = ? FOR UPDATE", [$bookId]);
            if (!$book || $book['available_copies'] < 1) {
                db_rollback();
                json_response(['error' => 'No copies available'], 400);
            }
            db_query("UPDATE library_books SET available_copies = available_copies - 1 WHERE id = ?", [$bookId]);

            if ($userType === 'staff') {
                $id = db_insert(
                    "INSERT INTO library_issues (book_id, staff_id, student_id, issue_date, due_date, issued_by) VALUES (?,?,NULL,?,?,?)",
                    [$bookId, $userId, date('Y-m-d'), $dueDate, get_current_user_id()]
                );
            } else {
                $id = db_insert(
                    "INSERT INTO library_issues (book_id, student_id, staff_id, issue_date, due_date, issued_by) VALUES (?,?,NULL,?,?,?)",
                    [$bookId, $userId, date('Y-m-d'), $dueDate, get_current_user_id()]
                );
            }
            db_commit();
            json_response(['success' => true, 'id' => $id, 'message' => 'Book issued.'], 201);
        } catch (Exception $e) {
            db_rollback();
            json_response(['error' => 'Issue failed: ' . $e->getMessage()], 500);
        }
    }
    if ($action === 'return') {
        $issueId = (int) $data['issue_id'];
        try {
            db_beginTransaction();
            $issue = db_fetch("SELECT * FROM library_issues WHERE id = ? FOR UPDATE", [$issueId]);
            if (!$issue) {
                db_rollback();
                json_response(['error' => 'Issue record not found'], 404);
            }
            if ($issue['is_returned']) {
                db_rollback();
                json_response(['error' => 'Book already returned'], 400);
            }
            $fine = 0;
            if (strtotime(date('Y-m-d')) > strtotime($issue['due_date'])) {
                $days = (strtotime(date('Y-m-d')) - strtotime($issue['due_date'])) / 86400;
                $finePerDay = (float) ($issue['fine_per_day'] ?? 5); // Use configured rate, default ₹5/day
                $fine = $days * $finePerDay;
            }
            db_query("UPDATE library_issues SET is_returned=1, return_date=?, fine_amount=? WHERE id=?", [date('Y-m-d'), $fine, $issueId]);
            db_query("UPDATE library_books SET available_copies = available_copies + 1 WHERE id=?", [$issue['book_id']]);
            db_commit();
            json_response(['success' => true, 'fine' => $fine]);
        } catch (Exception $e) {
            db_rollback();
            json_response(['error' => 'Return failed: ' . $e->getMessage()], 500);
        }
    }
}
if ($method === 'DELETE') {
    require_role(['superadmin', 'admin', 'librarian']);
    db_query("DELETE FROM library_books WHERE id = ?", [(int) ($_GET['id'] ?? 0)]);
    json_response(['success' => true]);
}
json_response(['error' => 'Method not allowed'], 405);
