<?php
$pageTitle = 'Privacy Policy';
$lastUpdated = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Privacy policy for the School ERP portal.">
    <title><?= htmlspecialchars($pageTitle) ?> - School ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f1e8;
            --panel: rgba(255, 255, 255, 0.9);
            --ink: #1f1b16;
            --muted: #6f6558;
            --line: rgba(31, 27, 22, 0.08);
            --accent: #8b5e3c;
            --shadow: 0 20px 60px rgba(54, 38, 24, 0.12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top right, rgba(139, 94, 60, 0.18), transparent 28%),
                linear-gradient(180deg, #f8f4ec 0%, var(--bg) 100%);
        }

        .page-shell {
            max-width: 920px;
            margin: 0 auto;
            padding: 40px 20px 64px;
        }

        .page-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
        }

        .brand {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .policy-card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 40px;
            backdrop-filter: blur(10px);
        }

        h1,
        h2 {
            font-family: 'Outfit', sans-serif;
            margin: 0;
        }

        h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 10px;
        }

        .subtitle {
            margin: 0 0 28px;
            color: var(--muted);
            font-size: 1rem;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .policy-content {
            line-height: 1.8;
            font-size: 1rem;
        }

        .policy-content h2 {
            margin-top: 30px;
            margin-bottom: 12px;
            font-size: 1.35rem;
        }

        .policy-content p,
        .policy-content ul {
            margin: 0 0 14px;
        }

        .policy-content ul {
            padding-left: 22px;
        }

        .page-footer {
            margin-top: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 0.92rem;
        }

        @media (max-width: 640px) {
            .page-shell {
                padding: 24px 14px 40px;
            }

            .policy-card {
                padding: 26px 20px;
                border-radius: 18px;
            }

            .page-nav {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="page-nav">
            <div class="brand">School ERP</div>
            <a class="back-link" href="index.php">Back to Sign In</a>
        </div>

        <main class="policy-card">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="subtitle">Last updated: <?= htmlspecialchars($lastUpdated) ?></p>

            <div class="policy-content">
                <h2>1. Introduction</h2>
                <p>Welcome to our School ERP system. We respect your privacy and are committed to protecting your personal data. This policy explains how we collect, use, and protect information when you use the portal.</p>

                <h2>2. Data We Collect</h2>
                <p>We may collect and process personal information required for academic administration and communication.</p>
                <ul>
                    <li>Identity data such as name, date of birth, gender, and identifiers.</li>
                    <li>Contact data such as phone numbers, addresses, and email addresses.</li>
                    <li>Academic data such as attendance, examinations, assignments, and behavioural records.</li>
                    <li>Financial data such as fee records and payment references.</li>
                </ul>

                <h2>3. How We Use Your Data</h2>
                <p>We use personal data to operate the school ERP, manage academic and administrative workflows, communicate with users, and comply with legal or regulatory obligations.</p>

                <h2>4. Data Security</h2>
                <p>We apply reasonable administrative and technical controls to protect personal information from unauthorized access, loss, misuse, or disclosure.</p>

                <h2>5. Your Rights</h2>
                <p>Depending on applicable law, you may request access, correction, restriction, or deletion of personal data maintained in the system.</p>

                <h2>6. Contact</h2>
                <p>If you have questions about privacy practices for this ERP, contact your school administration or the designated privacy coordinator.</p>
            </div>
        </main>

        <div class="page-footer">School ERP</div>
    </div>
</body>
</html>
