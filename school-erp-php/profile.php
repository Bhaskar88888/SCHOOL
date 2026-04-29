<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();
$pageTitle = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .profile-container { max-width: 800px; margin: 0 auto; }
        .profile-header { display: flex; align-items: center; gap: 24px; margin-bottom: 30px; background: var(--bg-secondary); padding: 30px; border-radius: var(--radius); border: 1px solid var(--border); }
        .profile-avatar { width: 100px; height: 100px; font-size: 40px; }
        .profile-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .profile-warning { margin-bottom: 20px; padding: 16px 18px; border-radius: var(--radius); border: 1px solid rgba(245, 158, 11, 0.35); background: rgba(245, 158, 11, 0.12); color: var(--text-primary); display: none; }
        @media (max-width: 768px) { .profile-info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/includes/header.php'; ?>

        <div class="profile-container">
            <div class="profile-warning" id="defaultPasswordBanner">
                This account is still using a default or administrator-issued password. Change it now to finish setup.
            </div>
            <div class="profile-header">
                <div class="user-avatar profile-avatar" id="lblAvatar">?</div>
                <div>
                    <h1 id="lblName" style="margin:0; font-size:28px">Loading...</h1>
                    <div id="lblRole" class="badge badge-primary" style="margin-top:8px">...</div>
                    <div id="lblEmail" style="color:var(--text-muted); margin-top:8px; font-size:14px">...</div>
                </div>
            </div>

            <div class="profile-info-grid">
                <div class="card">
                    <h3 style="margin-top:0">Update Information</h3>
                    <form id="profileForm" onsubmit="submitProfile(event)">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" id="inpName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" id="inpEmail" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" id="inpPhone">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">Update Profile</button>
                    </form>
                </div>

                <div class="card" id="change-password">
                    <h3 style="margin-top:0">Change Password</h3>
                    <form id="passForm" onsubmit="submitPassword(event)">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn btn-secondary" style="width:100%">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
async function loadProfile() {
    const user = await apiGet('/api/profile/index.php');
    const warning = document.getElementById('defaultPasswordBanner');
    
    document.getElementById('lblName').textContent = user.name;
    document.getElementById('lblRole').textContent = user.role.toUpperCase();
    document.getElementById('lblEmail').textContent = user.email;
    document.getElementById('lblAvatar').textContent = user.name.charAt(0).toUpperCase();
    
    document.getElementById('inpName').value = user.name;
    document.getElementById('inpEmail').value = user.email;
    document.getElementById('inpPhone').value = user.phone || '';

    if (warning) {
        warning.style.display = user.is_default_password ? 'block' : 'none';
    }
}

async function submitProfile(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res = await apiPost('/api/profile/index.php', data);
    if (res.success) {
        showToast('Profile updated!');
        loadProfile();
        // Update header user name if needed
    } else {
        showToast(res.error || 'Failed to update', 'danger');
    }
}

async function submitPassword(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));

    const res = await apiPost('/api/profile/change-password.php', data);
    if (res.success) {
        showToast('Password changed successfully!');
        e.target.reset();
        loadProfile();
    } else {
        showToast(res.error || 'Failed to change password', 'danger');
    }
}

loadProfile();
</script>
</body>
</html>
