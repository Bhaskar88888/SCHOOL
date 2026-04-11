<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle  = 'Library Management';
$needsStudents = true;
$needsStaff = true;
require_once __DIR__ . '/includes/data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .tabs { display:flex;gap:16px;border-bottom:1px solid var(--border);margin-bottom:20px; }
        .tab { padding:10px 16px;cursor:pointer;color:var(--text-secondary);font-weight:600;border-bottom:2px solid transparent;transition:all 0.2s; }
        .tab.active { color:var(--accent);border-bottom:2px solid var(--accent); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('books')" id="tabBooks">📚 Book Inventory</div>
            <div class="tab" onclick="switchTab('issues')" id="tabIssues">🔄 Issued Books</div>
        </div>

        <!-- Books Tab -->
        <div id="viewBooks">
            <div class="page-toolbar">
                <input type="text" class="form-control" id="searchBooks" placeholder="🔍 Search title or ISBN..." style="width:260px" oninput="loadBooks()">
                <button class="btn btn-primary" onclick="openModal('addBookModal')">+ Add New Book</button>
            </div>
            <div class="card"><div class="table-wrap">
                <table>
                    <thead><tr><th>Title</th><th>Author / ISBN</th><th>Category</th><th>Shelf</th><th>Available</th><th>Actions</th></tr></thead>
                    <tbody id="booksBody"></tbody>
                </table>
            </div></div>
        </div>

        <!-- Issues Tab -->
        <div id="viewIssues" style="display:none">
            <div class="page-toolbar">
                <div style="font-weight:600">Currently Issued Books</div>
                <button class="btn btn-primary" onclick="openModal('issueBookModal')">+ Issue Book</button>
            </div>
            <div class="card"><div class="table-wrap">
                <table>
                    <thead><tr><th>Book Title</th><th>Issued To (Student)</th><th>Issue Date</th><th>Due Date</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="issuesBody"></tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>

<!-- Add Book -->
<div class="modal-overlay" id="addBookModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">📚 Add Book</div><button class="modal-close" onclick="closeModal('addBookModal')">✕</button></div>
        <form id="addBookForm" onsubmit="submitBook(event)">
            <input type="hidden" name="action" id="addBookAction" value="add_book">
            <input type="hidden" name="id" id="editBookId" value="">
            <div class="form-group"><label class="form-label">Book Title *</label><input type="text" class="form-control" name="title" required></div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Author</label><input type="text" class="form-control" name="author"></div>
                <div class="form-group"><label class="form-label">ISBN</label><input type="text" class="form-control" name="isbn"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Category</label><input type="text" class="form-control" name="category"></div>
                <div class="form-group"><label class="form-label">Publisher</label><input type="text" class="form-control" name="publisher"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label">No. of Copies *</label><input type="number" class="form-control" name="copies" value="1" required></div>
                <div class="form-group"><label class="form-label">Shelf Location</label><input type="text" class="form-control" name="shelf"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addBookModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Book</button>
            </div>
        </form>
    </div>
</div>

<!-- Issue Book -->
<div class="modal-overlay" id="issueBookModal">
    <div class="modal">
        <div class="modal-header"><div class="modal-title">🔄 Issue Book</div><button class="modal-close" onclick="closeModal('issueBookModal')">✕</button></div>
        <form id="issueBookForm" onsubmit="submitIssue(event)">
            <input type="hidden" name="action" value="issue">
            <div class="form-group"><label class="form-label">Select Book *</label>
                <select class="form-control" name="book_id" id="selBookList" required><option value="">Loading...</option></select>
            </div>
            <div class="form-group row">
                <div class="col-md-6" style="margin-bottom:10px">
                    <label class="form-label">User Type *</label>
                    <select class="form-control" name="user_type" id="userTypeSel" onchange="toggleUserType()" required>
                        <option value="student">Student</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="col-md-6" id="studentSelectWrapper">
                    <label class="form-label">Select Student *</label>
                    <select class="form-control" name="user_id_student">
                        <option value="">Search Student</option>
                        <?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['class_name']?:'-') ?>)</option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="staffSelectWrapper" style="display:none">
                    <label class="form-label">Select Staff *</label>
                    <select class="form-control" name="user_id_staff">
                        <option value="">Select Staff</option>
                        <?php foreach($staff as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Due Date *</label><input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required></div>
            <div style="display:flex;justify-content:flex-end;gap:10px">
                <button type="button" class="btn btn-secondary" onclick="closeModal('issueBookModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Issue Book</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let bookList = [];

function switchTab(t) {
    document.getElementById('viewBooks').style.display = t==='books'?'block':'none';
    document.getElementById('viewIssues').style.display = t==='issues'?'block':'none';
    document.getElementById('tabBooks').className = 'tab' + (t==='books'?' active':'');
    document.getElementById('tabIssues').className = 'tab' + (t==='issues'?' active':'');
    if(t==='books') loadBooks(); else loadIssues();
}

async function loadBooks() {
    const s = document.getElementById('searchBooks').value;
    bookList = await apiGet(`/api/library/index.php?search=${encodeURIComponent(s)}`);
    document.getElementById('booksBody').innerHTML = bookList.map(b => `
        <tr>
            <td><strong>${escHtml(b.title)}</strong></td>
            <td><div style="font-size:12px">${escHtml(b.author||'-')}</div><div style="font-size:11px;color:var(--text-muted)">ISBN: ${escHtml(b.isbn||'-')}</div></td>
            <td>${escHtml(b.category||'-')}</td><td>${escHtml(b.shelf_location||'-')}</td>
            <td><span class="badge ${b.available_copies>0?'badge-success':'badge-danger'}">${b.available_copies} / ${b.total_copies}</span></td>
            <td>
                <div style="display:flex;gap:4px;">
                    ${b.available_copies>0 ? `<button class="btn btn-primary btn-sm" onclick="openIssue(${b.id})">Issue</button>` : ''}
                    <button class="btn btn-secondary btn-sm" onclick='editBook(${JSON.stringify(b).replace(/'/g, "&apos;")})'>✏️</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteBook(${b.id})">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('')||'<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No books found</td></tr>';
    
    document.getElementById('selBookList').innerHTML = bookList.filter(b=>b.available_copies>0).map(b=>`<option value="${b.id}">${escHtml(b.title)}</option>`).join('');
}

async function loadIssues() {
    const data = await apiGet('/api/library/index.php?issues=1');
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('issuesBody').innerHTML = data.map(i => {
        const isOverdue = i.due_date < today;
        return `
        <tr>
            <td><strong>${escHtml(i.book_title)}</strong></td>
            <td>${escHtml(i.student_name || i.staff_name || 'Unknown')} <span class="badge badge-info">${i.staff_id ? 'Staff' : 'Student'}</span></td>
            <td>${new Date(i.issue_date).toLocaleDateString()}</td>
            <td style="${isOverdue?'color:var(--danger);font-weight:700':''}">${new Date(i.due_date).toLocaleDateString()}</td>
            <td><span class="badge ${isOverdue?'badge-danger':'badge-warning'}">${isOverdue?'Overdue':'Issued'}</span></td>
            <td><button class="btn btn-success btn-sm" onclick="returnBook(${i.id})">Return Book</button></td>
        </tr>
    `}).join('')||'<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No books currently issued</td></tr>';
}

function openIssue(bId) {
    document.getElementById('selBookList').value = bId;
    openModal('issueBookModal');
}

async function submitBook(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(document.getElementById('addBookForm')));
    const res = await apiPost('/api/library/index.php', data);
    if(res.success){ showToast('Book added'); closeModal('addBookModal'); document.getElementById('addBookForm').reset(); loadBooks(); }
    else showToast(res.error||'Error','danger');
}

function toggleUserType() {
    const type = document.getElementById('userTypeSel').value;
    if (type === 'student') {
        document.getElementById('studentSelectWrapper').style.display = 'block';
        document.getElementById('staffSelectWrapper').style.display = 'none';
        document.querySelector('select[name="user_id_student"]').required = true;
        document.querySelector('select[name="user_id_staff"]').required = false;
    } else {
        document.getElementById('studentSelectWrapper').style.display = 'none';
        document.getElementById('staffSelectWrapper').style.display = 'block';
        document.querySelector('select[name="user_id_student"]').required = false;
        document.querySelector('select[name="user_id_staff"]').required = true;
    }
}

function editBook(b) {
    const f = document.getElementById('addBookForm');
    f.reset();
    document.getElementById('addBookAction').value = 'edit_book';
    document.getElementById('editBookId').value = b.id;
    f.title.value = b.title;
    f.author.value = b.author || '';
    f.isbn.value = b.isbn || '';
    f.category.value = b.category || '';
    f.publisher.value = b.publisher || '';
    f.copies.value = b.total_copies || 1;
    f.shelf.value = b.shelf_location || '';
    document.querySelector('#addBookModal .modal-title').textContent = '✏️ Edit Book';
    openModal('addBookModal');
}

async function submitIssue(e) {
    e.preventDefault();
    const form = document.getElementById('issueBookForm');
    const data = Object.fromEntries(new FormData(form));
    
    // Convert to proper API payload
    data.user_id = data.user_type === 'student' ? data.user_id_student : data.user_id_staff;
    delete data.user_id_student;
    delete data.user_id_staff;
    if (!data.user_id) return showToast('Please select a user', 'danger');

    const res = await apiPost('/api/library/index.php', data);
    if(res.success){ showToast('Book Issued successfully'); closeModal('issueBookModal'); form.reset(); switchTab('issues'); }
    else showToast(res.error||'Error','danger');
}

async function returnBook(id) {
    if(!confirm('Mark this book as returned?')) return;
    const res = await apiPost('/api/library/index.php', { action:'return', issue_id:id });
    if(res.success){
        if(res.fine > 0) alert('Book returned. Late Fine Applied: ₹' + res.fine);
        else showToast('Book returned successfully');
        loadIssues(); loadBooks();
    } else showToast(res.error||'Error','danger');
}

async function deleteBook(id) {
    if(!confirm('Remove this book?')) return;
    await fetch(`/api/library/index.php?id=${id}`,{method:'DELETE'});
    showToast('Book removed'); loadBooks();
}

loadBooks();
</script>
</body>
</html>
