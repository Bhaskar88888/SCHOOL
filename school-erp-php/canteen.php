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

        <div class="tabs" style="margin-bottom: 20px; display:flex; gap:10px;">
            <button class="btn btn-primary tab-btn" id="btn-menu" onclick="switchTab('menu')">Menu Items</button>
            <button class="btn btn-secondary tab-btn" id="btn-sales" onclick="switchTab('sales')">Sales History</button>
        </div>

        <div id="tab-menu">
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

        <div id="tab-sales" style="display:none">
            <div class="card">
                <div style="margin-bottom:15px; display:flex; gap:10px; align-items:center;">
                    <label>Filter Date:</label>
                    <input type="date" id="saleDateFilter" class="form-control" style="width:200px" value="<?= date('Y-m-d') ?>" onchange="loadSales()">
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Buyer</th>
                                <th>Item(s)</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Date/Time</th>
                            </tr>
                        </thead>
                        <tbody id="salesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- POS Cart Sidebar -->
        <div id="posCart" style="position:fixed;top:0;right:-380px;width:360px;height:100vh;background:var(--bg-card);border-left:1px solid var(--border);box-shadow:-4px 0 24px rgba(0,0,0,.15);z-index:1000;display:flex;flex-direction:column;transition:right .3s ease">
            <div style="padding:20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
                <div style="font-weight:700;font-size:16px">🛒 POS Cart</div>
                <button class="btn btn-secondary btn-sm" onclick="closeCart()">✕ Close</button>
            </div>
            <div id="cartLines" style="flex:1;overflow-y:auto;padding:16px"></div>
            <div style="padding:16px;border-top:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;margin-bottom:12px">
                    <span>Total</span>
                    <span style="color:var(--accent)" id="cartTotal">₹0.00</span>
                </div>
                <select class="form-control" id="cartPaymentMode" style="margin-bottom:12px">
                    <option value="cash">Cash</option>
                    <option value="upi">UPI</option>
                    <option value="card">Card</option>
                    <option value="wallet">Wallet</option>
                </select>
                <button class="btn btn-primary" style="width:100%" onclick="checkoutCart()">⚡ Checkout</button>
            </div>
        </div>
        <div id="cartOverlay" onclick="closeCart()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:999"></div>
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

<!-- Edit Item Modal -->
<div class="modal-overlay" id="editItemModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">✏️ Edit Item</div>
            <button class="modal-close" onclick="closeModal('editItemModal')">✕</button>
        </div>
        <form id="editItemForm" onsubmit="submitEditItem(event)">
            <input type="hidden" name="id" id="editItemId">
            <div class="form-group">
                <label class="form-label">Item Name *</label>
                <input type="text" class="form-control" name="name" id="editItemName" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select class="form-control" name="category" id="editItemCategory">
                        <option value="Snacks">Snacks</option>
                        <option value="Drinks">Drinks</option>
                        <option value="Meals">Meals</option>
                        <option value="Desserts">Desserts</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Price (₹) *</label>
                    <input type="number" step="0.01" class="form-control" name="price" id="editItemPrice" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Available Qty</label>
                    <input type="number" class="form-control" name="available_qty" id="editItemQty" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-control" name="is_available" id="editItemStatus">
                        <option value="1">In Stock</option>
                        <option value="0">Out of Stock</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editItemModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
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
                <td>${i.available_qty ?? '-'}</td>
                <td><span class="badge ${parseInt(i.is_available) ? 'badge-success' : 'badge-danger'}">${parseInt(i.is_available) ? 'In Stock' : 'Out of Stock'}</span></td>
                <td>
                    <div style="display:flex;gap:6px">
                        ${parseInt(i.is_available) ? `<button class="btn btn-success btn-sm" onclick="addToCart(${i.id}, '${escHtml(i.name)}', ${i.price})">🛒 Add</button>` : ''}
                        <?php if (in_array(get_current_role(), ['admin', 'superadmin'])): ?>
                        <button class="btn btn-secondary btn-sm" onclick="openEditItem(${i.id}, '${escHtml(i.name)}', '${escHtml(i.category)}', ${i.price}, ${i.available_qty}, ${i.is_available})">✏️</button>
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

function switchTab(tab) {
    document.getElementById('tab-menu').style.display = tab === 'menu' ? 'block' : 'none';
    document.getElementById('tab-sales').style.display = tab === 'sales' ? 'block' : 'none';
    document.getElementById('btn-menu').className = tab === 'menu' ? 'btn btn-primary tab-btn' : 'btn btn-secondary tab-btn';
    document.getElementById('btn-sales').className = tab === 'sales' ? 'btn btn-primary tab-btn' : 'btn btn-secondary tab-btn';
    if(tab === 'sales') loadSales();
}

async function loadSales() {
    const date = document.getElementById('saleDateFilter').value;
    const body = document.getElementById('salesBody');
    body.innerHTML = '<tr><td colspan="6" style="text-align:center">Loading...</td></tr>';
    const sales = await apiGet(`/api/canteen/index.php?sales=1&date=${date}`);
    if (!sales.length) {
        body.innerHTML = '<tr><td colspan="6"><div class="empty-state"><div class="empty-state-text">No sales on this date</div></div></td></tr>';
    } else {
        body.innerHTML = sales.map(s => `
            <tr>
                <td>#${s.id}</td>
                <td>${escHtml(s.buyer_name || 'Walk-in')}</td>
                <td>${escHtml(s.item_name)}</td>
                <td>${s.quantity}</td>
                <td style="color:var(--success);font-weight:600">₹${parseFloat(s.total_price).toFixed(2)}</td>
                <td>${new Date(s.order_date).toLocaleTimeString()}</td>
            </tr>
        `).join('');
    }
}

function openEditItem(id, name, category, price, qty, status) {
    document.getElementById('editItemId').value = id;
    document.getElementById('editItemName').value = name;
    document.getElementById('editItemCategory').value = category || 'Snacks';
    document.getElementById('editItemPrice').value = price;
    document.getElementById('editItemQty').value = qty || 0;
    document.getElementById('editItemStatus').value = status;
    openModal('editItemModal');
}

async function submitEditItem(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const id = data.id;
    const res = await fetch(`/api/canteen/index.php?action=update&id=${id}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(data)
    }).then(r => r.json());
    
    if (res.success) {
        showToast('Item updated successfully!');
        closeModal('editItemModal');
        loadItems();
    } else {
        showToast(res.error || 'Failed to update item', 'danger');
    }
}

// ── POS Cart ─────────────────────────────────────────────────────────────────
let cart = {}; // { itemId: { id, name, price, qty } }

function addToCart(id, name, price) {
    if (cart[id]) {
        cart[id].qty++;
    } else {
        cart[id] = { id, name, price: parseFloat(price), qty: 1 };
    }
    renderCart();
    openCart();
}

function openCart() {
    document.getElementById('posCart').style.right = '0';
    document.getElementById('cartOverlay').style.display = 'block';
}

function closeCart() {
    document.getElementById('posCart').style.right = '-380px';
    document.getElementById('cartOverlay').style.display = 'none';
}

function renderCart() {
    const lines = Object.values(cart);
    if (!lines.length) {
        document.getElementById('cartLines').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted)">Cart is empty.<br>Click 🛒 Add on any item.</div>';
        document.getElementById('cartTotal').textContent = '₹0.00';
        return;
    }
    const total = lines.reduce((s, l) => s + l.price * l.qty, 0);
    document.getElementById('cartLines').innerHTML = lines.map(l => `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px;background:var(--surface-container-lowest);border-radius:8px;margin-bottom:8px">
            <div>
                <strong style="font-size:13px">${escHtml(l.name)}</strong>
                <div style="font-size:12px;color:var(--text-muted)">₹${l.price.toFixed(2)} × ${l.qty} = ₹${(l.price * l.qty).toFixed(2)}</div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
                <button class="btn btn-secondary btn-sm" onclick="changeQty(${l.id},-1)">−</button>
                <span>${l.qty}</span>
                <button class="btn btn-secondary btn-sm" onclick="changeQty(${l.id},1)">+</button>
                <button class="btn btn-danger btn-sm" onclick="removeFromCart(${l.id})">✕</button>
            </div>
        </div>`).join('');
    document.getElementById('cartTotal').textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function changeQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty <= 0) delete cart[id];
    renderCart();
}

function removeFromCart(id) {
    delete cart[id];
    renderCart();
}

async function checkoutCart() {
    const lines = Object.values(cart);
    if (!lines.length) { showToast('Cart is empty', 'warning'); return; }
    const paymentMode = document.getElementById('cartPaymentMode').value;
    const cartPayload = lines.map(l => ({ item_id: l.id, quantity: l.qty }));
    const res = await fetch('/api/canteen/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
        body: JSON.stringify({ action: 'cart_checkout', cart: cartPayload, payment_mode: paymentMode }),
    }).then(r => r.json());

    if (res.success) {
        const total = lines.reduce((s, l) => s + l.price * l.qty, 0);
        showToast(`✅ Sale recorded! Total: ₹${total.toFixed(2)}`);
        cart = {};
        renderCart();
        closeCart();
        loadItems();
        updateSalesToday();
    } else {
        showToast(res.error || 'Checkout failed', 'danger');
    }
}
// ─────────────────────────────────────────────────────────────────────────────

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

async function deleteItem(id) {
    if (!confirm('Remove this item from menu?')) return;
    const res = await fetch(`/api/canteen/index.php?id=${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': getCsrfToken() }
    }).then(r => r.json());
    if (res.success) {
        showToast('Item removed.');
        loadItems();
    }
}

async function updateSalesToday() {
    const data = await apiGet('/api/canteen/index.php?stats=1');
    document.getElementById('saleCount').textContent = '₹' + (data.today_revenue || 0).toFixed(2);
}

loadItems();
updateSalesToday();
</script>
</body>
</html>
