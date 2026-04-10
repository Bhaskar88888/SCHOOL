<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limiter.php';

// Redirect to dashboard if already logged in
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate limiting
    RateLimiter::check(RATE_LIMIT_AUTH_REQUESTS, RATE_LIMIT_AUTH_WINDOW);

    if ($email && $password) {
        // Check if account is locked
        if (is_account_locked($email)) {
            $error = 'Account temporarily locked. Please try again later.';
        } else {
            $user = db_fetch("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1", [$email]);
            if ($user && password_verify($password, $user['password'])) {
                login_user_enhanced($user);
                audit_log('LOGIN', 'auth', 'User logged in successfully');
                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            } else {
                // Record failed login attempt
                record_failed_login($email);
                $error = 'Invalid email or password. Please try again.';
            }
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School ERP System - Manage your school efficiently">
    <title>Login — School ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-page {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .login-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px;
            background: radial-gradient(ellipse at top left, rgba(37, 99, 235, 0.06) 0%, transparent 60%),
                radial-gradient(ellipse at bottom right, rgba(37, 99, 235, 0.04) 0%, transparent 60%);
        }

        .login-card {
            width: 100%;
            max-width: 420px;
        }

        .login-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .login-logo-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 20px var(--accent-glow);
        }

        .login-logo-name {
            font-size: 22px;
            font-weight: 800;
        }

        .login-logo-sub {
            font-size: 12px;
            color: var(--text-muted);
        }

        .login-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .login-subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 15px var(--accent-glow);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px var(--accent-glow);
        }

        .login-right {
            width: 480px;
            background: var(--bg-secondary);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px;
            gap: 28px;
        }

        .feature-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .feature-title {
            font-weight: 600;
            font-size: 14px;
        }

        .feature-desc {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 2px;
            line-height: 1.5;
        }

        .demo-creds {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px;
            width: 100%;
            font-size: 12px;
        }

        .demo-creds-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }

        .demo-cred {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            color: var(--text-muted);
        }

        .demo-cred span:first-child {
            color: var(--text-primary);
        }

        @media(max-width:768px) {
            .login-right {
                display: none;
            }

            .login-left {
                padding: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-left">
            <div class="login-card">
                <div class="login-logo">
                    <div class="login-logo-icon">🎓</div>
                    <div>
                        <div class="login-logo-name">School ERP</div>
                        <div class="login-logo-sub">Management System v2.0</div>
                    </div>
                </div>

                <h1 class="login-title">Welcome back</h1>
                <p class="login-subtitle">Sign in to your account to continue</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="your@email.com"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display:flex;justify-content:space-between">
                            <span>Password</span>
                            <a href="forgot-password.php" style="font-weight:400;font-size:11px">Forgot password?</a>
                        </label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="login-btn">Sign In →</button>
                </form>
            </div>
        </div>


    </div>
</body>

</html>