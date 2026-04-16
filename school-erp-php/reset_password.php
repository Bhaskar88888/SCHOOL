<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - School ERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .auth-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .auth-card h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: var(--text-primary);
        }
        
        .auth-card p {
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 14px;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #28a745;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-bar {
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            margin-top: 4px;
        }
        
        .strength-fill {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s;
        }
        
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>🔑 Reset Password</h1>
            <p>Enter your new password below.</p>

            <div id="alertBox"></div>

            <form id="resetForm">
                <input type="hidden" id="token" value="<?= htmlspecialchars($_GET['token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="password">New Password *</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    <div class="password-strength">
                        <div class="strength-bar">
                            <div class="strength-fill" id="strengthBar"></div>
                        </div>
                        <small id="strengthText"></small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required minlength="8">
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    Reset Password
                </button>
            </form>
        </div>
    </div>

    <script>
        const token = document.getElementById('token').value;
        
        if (!token) {
            showAlert('error', 'Invalid or missing reset token. Please request a new reset link.');
            setTimeout(() => window.location.href = 'forgot_password.php', 3000);
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            bar.className = 'strength-fill';
            if (strength <= 2) {
                bar.classList.add('strength-weak');
                text.textContent = 'Weak password';
            } else if (strength <= 3) {
                bar.classList.add('strength-medium');
                text.textContent = 'Medium strength';
            } else {
                bar.classList.add('strength-strong');
                text.textContent = 'Strong password';
            }
        });

        document.getElementById('resetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (password !== confirmPassword) {
                showAlert('error', 'Passwords do not match');
                return;
            }
            
            if (password.length < 8) {
                showAlert('error', 'Password must be at least 8 characters');
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Resetting...';
            
            try {
                const response = await fetch('/api/auth/reset_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, password })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    showAlert('success', data.message || 'Password reset successfully! Redirecting to login...');
                    setTimeout(() => window.location.href = 'index.php', 2000);
                } else {
                    showAlert('error', data.error || 'Failed to reset password');
                }
            } catch (error) {
                showAlert('error', 'Network error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Reset Password';
            }
        });

        function showAlert(type, message) {
            const alertBox = document.getElementById('alertBox');
            alertBox.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            
            setTimeout(() => {
                alertBox.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>
