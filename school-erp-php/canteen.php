<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'Canteen Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canteen — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="page-toolbar">
            <div class="toolbar-left">
                <input type="text" class="form-control" id="itemSearch" placeholder="🔍 Search menu items..." oninput="loadItems()" style="width:260px">
            </div>
            <div class="toolbar-right">
                <?php if (in_array(get_current_role(), ['admin', 'superadmin'])): ?>
                <button class="btn btn-primary" onclick="openModal('addItemModal')">+ Add Item</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="--stat-color:#3fb950">
                <div class="stat-icon" style="--stat-color:#3fb950">🍔</div>
                <div class="stat-info"><div class="stat-value" id="menuCount">0</div><div class="stat-label">Menu Items</div></div>
            </div>
            <div class="stat-card" style="--stat-color:#4f8ef7">
                <div class="stat-icon" style="--stat-color:#4f8ef7">💰</div>
                <div class="stat-info"><div class="stat-value" id="saleCount">0</div><div class="stat-label">Sales Today</div></div>
            </div>
        </div>

        <div class="card">
            <div id="tableLoading" style="text-align:center;padding:40px"><div class="spinner"></div></div>
            <div class="table-wrap" id="tableWrap" style="display:none">
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🍔 Add Canteen Item</div>
            <button class="modal-close" onclick="closeModal('addItemModal')">✕</button>
        </div>
        <form id="addItemForm" onsubmit="submitAddItem(event)">
            <div class="form-group">
                <label class="form-label">Item Name *</label>
                <input type="text" class="form-control" name="name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-control" name="category">
                        <option value="Snacks">Snacks</option>
                        <option value="Drinks">Drinks</option>
                        <option value="Meals">Meals</option>
                        <option value="Desserts">Desserts</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" step="0.01" class="form-control" name="price" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Initial Stock Qty</label>
                <input type="number" class="form-control" name="available_qty" value="100">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Sell Item Modal -->
<div class="modal-overlay" id="sellModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">💰 Record Sale</div>
            <button class="modal-close" onclick="closeModal('sellModal')">✕</button>
        </div>
        <form id="sellForm" onsubmit="submitSale(event)">
            <input type="hidden" name="item_id" id="sellItemId">
            <div class="form-group">
                <label class="form-label">Item</label>
                <div id="sellItemName" style="font-weight:600; font-size:18px; color:var(--text-primary)"></div>
                <div id="sellItemPrice" style="font-size:14px; color:var(--text-muted)"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity *</label>
                <input type="number" class="form-control" name="quantity" id="sellQty" value="1" min="1" oninput="updateTotal()">
            </div>
            <div style="background:var(--bg-secondary); padding:15px; border-radius:var(--radius-sm); margin-bottom:15px">
                <div style="display:flex; justify-content:space-between">
                    <span>Total Amount:</span>
                    <strong style="color:var(--success); font-size:20px" id="sellTotal">₹0.00</strong>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('sellModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Sale</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
let currentPrice = 0;

async function loadItems() {
    const search = document.getElementById('itemSearch').value;
    document.getElementById('tableLoading').style.display = 'block';
    document.getElementById('tableWrap').style.display = 'none';

    const items = await apiGet(`/api/canteen/index.php?search=${encodeURIComponent(search)}`);
    
    document.getElementById('menuCount').textContent = items.length;
    
    const body = document.getElementById('itemBody');
    if (!items.length) {
        body.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-icon">🍔</div><div class="empty-state-text">No items found</div></div></td></tr>';
    } else {
        body.innerHTML = items.map(i => `
            <tr>
                <td><strong>${escHtml(i.name)}</strong></td>
                <td><span class="badge badge-secondary">${escHtml(i.category)}</span></td>
                <td>₹${parseFloat(i.price).toFixed(2)}</td>
                <td>-</td>
                <td><span class="badge ${parseInt(i.is_available) ? 'badge-success' : 'badge-danger'}">${parseInt(i.is_available) ? 'In Stock' : 'Out of Stock'}</span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        ${parseInt(i.is_available) ? `<button class="btn btn-success btn-sm" onclick="openSell(${i.id}, '${escHtml(i.name)}', ${i.price})">💰 Sell</button>` : ''}
                        <?php if (in_array(get_current_role(), ['admin', 'superadmin'])): ?>
                        <button class="btn btn-danger btn-sm" onclick="deleteItem(${i.id})">🗑️</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    document.getElementById('tableLoading').style.display = 'none';
    document.getElementById('tableWrap').style.display = 'block';
}

function openSell(id, name, price) {
    document.getElementById('sellItemId').value = id;
    document.getElementById('sellItemName').textContent = name;
    document.getElementById('sellItemPrice').textContent = `Price: ₹${parseFloat(price).toFixed(2)}`;
    document.getElementById('sellQty').value = 1;
    currentPrice = price;
    updateTotal();
    openModal('sellModal');
}

function updateTotal() {
    const qty = document.getElementById('sellQty').value;
    const total = qty * currentPrice;
    document.getElementById('sellTotal').textContent = `₹${total.toFixed(2)}`;
}

async function submitAddItem(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res = await apiPost('/api/canteen/index.php', data);
    if (res.success) {
        showToast('Item added successfully!');
        closeModal('addItemModal');
        e.target.reset();
        loadItems();
    } else {
        showToast(res.error || 'Failed to add item', 'danger');
    }
}

async function submitSale(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res = await fetch('/api/canteen/index.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    }).then(r => r.json());
    
    if (res.success) {
        showToast(`Sale recorded! Total: ₹${res.total.toFixed(2)}`);
        closeModal('sellModal');
        loadItems();
        updateSalesToday();
    } else {
        showToast(res.error || 'Sale failed', 'danger');
    }
}

async function deleteItem(id) {
    if (!confirm('Remove this item from menu?')) return;
    const res = await fetch(`/api/canteen/index.php?id=${id}`, {method: 'DELETE'}).then(r => r.json());
    if (res.success) {
        showToast('Item removed.');
        loadItems();
    }
}

async function updateSalesToday() {
    // This could be a separate API call to get stats
    // For now we just refresh count if we had it
}

loadItems();
</script>
</body>
</html>
