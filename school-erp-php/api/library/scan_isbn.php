<?php
/**
 * Library ISBN Scanning API (OpenLibrary Integration)
 * School ERP PHP v3.0
 */
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/upload_handler.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];

// GET: Scan ISBN and fetch book details from OpenLibrary
if ($method === 'GET') {
    $isbn = $_GET['isbn'] ?? '';
    
    if (empty($isbn)) {
        json_response(['error' => 'ISBN is required'], 400);
    }
    
    // OpenLibrary API
    $url = "https://openlibrary.org/isbn/{$isbn}.json";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'School ERP PHP v3.0',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        json_response(['error' => 'Book not found. Check ISBN or add manually.'], 404);
    }
    
    $data = json_decode($response, true);
    
    // Extract book details
    $book = [
        'isbn' => $isbn,
        'title' => $data['title'] ?? 'Unknown',
        'author' => isset($data['authors'][0]['key']) ? 
                    getAuthorName($data['authors'][0]['key']) : 'Unknown Author',
        'publisher' => $data['publishers'][0]['name'] ?? 'Unknown Publisher',
        'publish_date' => $data['publish_date'] ?? '',
        'cover_image' => "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg",
        'subjects' => $data['subjects'] ?? [],
        'pages' => $data['number_of_pages'] ?? null,
    ];
    
    json_response($book);
}

// POST: Add book with cover image upload
if ($method === 'POST') {
    require_role(['librarian', 'admin', 'superadmin']);
    
    $data = $_POST;
    
    $sql = "INSERT INTO library_books (isbn, title, author, category, publisher, total_copies, available_copies, shelf_location, cover_image_url) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        $data['isbn'] ?? null,
        $data['title'],
        $data['author'],
        $data['category'] ?? 'General',
        $data['publisher'] ?? null,
        $data['total_copies'] ?? 1,
        $data['total_copies'] ?? 1,
        $data['shelf_location'] ?? null,
        $data['cover_image_url'] ?? null,
    ];
    
    $bookId = db_insert($sql, $params);
    
    // Handle cover image upload if provided
    if (isset($_FILES['cover_image'])) {
        $result = FileUpload::uploadBookCover($_FILES['cover_image'], $bookId);
        if ($result['success']) {
            db_query("UPDATE library_books SET cover_image_url = ? WHERE id = ?", 
                     ['/' . $result['path'], $bookId]);
        }
    }
    
    audit_log('CREATE', 'library', $bookId, null, $data);
    
    json_response(['message' => 'Book added successfully', 'id' => $bookId], 201);
}

// Helper to get author name from OpenLibrary API
function getAuthorName($authorKey) {
    $url = "https://openlibrary.org{$authorKey}.json";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        return $data['name'] ?? 'Unknown Author';
    }
    return 'Unknown Author';
}
