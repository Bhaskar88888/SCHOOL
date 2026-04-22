<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limiter.php';

// Redirect to dashboard if already logged in
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (empty($_POST['csrf_token'])) {
        $error = 'Security token missing. Please refresh and try again.';
    } else {
        CSRFProtection::verifyToken();
    }

    if (empty($error)) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        RateLimiter::check(RATE_LIMIT_AUTH_REQUESTS, RATE_LIMIT_AUTH_WINDOW);

        if ($email && $password) {
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
                    record_failed_login($email);
                    $error = 'Invalid email or password. Please try again.';
                }
            }
        } else {
            $error = 'Please enter both email and password.';
        }
    } // end if (empty($error))
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="School ERP — Manage your school with elegance">
    <title>Sign In — School ERP</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
        }

        .login-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 100vh;
            padding: 48px 24px;
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 52px;
        }

        .login-brand-logo {
            width: 34px;
            height: 34px;
            background: #0a0a0a;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-brand-logo svg {
            width: 16px;
            height: 16px;
            fill: #fff;
        }

        .login-brand-name {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: #0a0a0a;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
        }

        .login-title {
            font-family: 'Outfit', sans-serif;
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -.5px;
            color: #0a0a0a;
            margin-bottom: 6px;
        }

        .login-sub {
            font-size: 14px;
            color: #777;
            margin-bottom: 36px;
            line-height: 1.6;
        }

        .login-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 500;
            color: #555;
            margin-bottom: 7px;
        }

        .login-label a {
            font-size: 12px;
            color: #999;
            text-decoration: none;
        }

        .login-label a:hover {
            color: #0a0a0a;
        }

        .login-input {
            width: 100%;
            background: #f5f5f5;
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #0a0a0a;
            outline: none;
            transition: all .18s ease;
            margin-bottom: 16px;
            display: block;
        }

        .login-input:focus {
            background: #fff;
            border-color: #ddd;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .05);
        }

        .login-input::placeholder {
            color: #bbb;
        }

        .login-btn {
            width: 100%;
            padding: 13px;
            background: #0a0a0a;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all .18s ease;
            margin-top: 4px;
            letter-spacing: -.1px;
        }

        .login-btn:hover {
            background: #2d2d2d;
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, .12);
        }

        .login-footer {
            text-align: center;
            margin-top: 40px;
            font-size: 12px;
            color: #bbb;
        }

        .login-error {
            background: #fff0f0;
            color: #dc2626;
            border-radius: 7px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }
    </style>
</head>

<body>
    <div class="login-wrap">

        <!-- Brand mark -->
        <div class="login-brand">
            <div class="login-brand-logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                </svg>
            </div>
            <div class="login-brand-name">School ERP</div>
        </div>

        <!-- Card -->
        <div class="login-card">
            <h1 class="login-title">Sign in</h1>
            <p class="login-sub">Enter your credentials to access the dashboard.</p>

            <?php if ($error): ?>
                <div class="login-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= CSRFProtection::field() ?>
                <label class="login-label">Email address</label>
                <input type="email" name="email" class="login-input" placeholder="admin@school.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>

                <label class="login-label">
                    <span>Password</span>
                    <a href="<?= BASE_URL ?>/forgot_password.php">Forgot?</a>
                </label>
                <input type="password" name="password" class="login-input" placeholder="••••••••" required>

                <button type="submit" class="login-btn">Continue &rarr;</button>
            </form>
        </div>

        <div class="login-footer">School ERP &copy; <?= date('Y') ?> &middot; v3.0</div>
    </div>
</body>

</html>