<?php
/**
 * ParentCredentials — Auto-generate parent portal accounts
 * School ERP v3.0
 *
 * Called from api/students/index.php POST handler immediately after
 * a new student is inserted. Also used on CSV import.
 *
 * Behaviour:
 *   1. Look up users table for existing parent with same email OR phone.
 *   2. If found  → return existing user_id (link, do not create duplicate).
 *   3. If not    → generate unique username + random password.
 *   4.           → INSERT users row (role = parent).
 *   5.           → Send email (via Mailer) + SMS (via SMS class).
 *   6.           → Return new user_id.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/sms.php';

class ParentCredentials
{
    // Role stored in DB for parent accounts
    const PARENT_ROLE = 'parent';

    /**
     * Ensure a parent account exists for the given email/phone.
     * Returns user_id on success, null if no email/phone provided or on hard failure.
     *
     * @param string $email        Parent email (may be empty)
     * @param string $phone        Parent phone (may be empty)
     * @param string $admissionNo  Student admission number (used in username)
     * @param string $parentName   Parent display name
     * @return int|null
     */
    public static function ensureAccount(
        string $email,
        string $phone,
        string $admissionNo = '',
        string $parentName  = 'Parent'
    ): ?int {
        $email = trim(strtolower($email));
        $phone = preg_replace('/\D/', '', $phone);   // digits only

        if (empty($email) && empty($phone)) {
            return null; // nothing to work with
        }

        // ---------------------------------------------------------
        // 1. Find existing parent user
        // ---------------------------------------------------------
        $existing = self::findExisting($email, $phone);
        if ($existing) {
            return (int) $existing['id'];
        }

        // ---------------------------------------------------------
        // 2. Generate credentials
        // ---------------------------------------------------------
        $username = self::generateUsername($email, $phone, $admissionNo);
        $password = self::generatePassword(10);
        $hash     = password_hash($password, PASSWORD_BCRYPT);

        // ---------------------------------------------------------
        // 3. Insert user row
        // ---------------------------------------------------------
        $insertData = [
            'name'              => $parentName,
            'email'             => $email ?: null,
            'username'          => $username,
            'password'          => $hash,
            'role'              => self::PARENT_ROLE,
            'portal_generated'  => 1,
            'portal_sent_at'    => date('Y-m-d H:i:s'),
            'is_active'         => 1,
            'created_at'        => date('Y-m-d H:i:s'),
        ];

        if (!empty($phone) && db_column_exists('users', 'phone')) {
            $insertData['phone'] = $phone;
        }
        if (db_column_exists('users', 'username')) {
            // Already in array
        }

        // Filter to only existing columns
        $insertData = db_filter_data_for_table('users', $insertData);

        $columns      = array_keys($insertData);
        $placeholders = array_fill(0, count($columns), '?');
        $params       = array_values($insertData);

        $sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        try {
            $userId = db_insert($sql, $params);
        } catch (Throwable $e) {
            error_log("[ParentCredentials] DB insert failed: " . $e->getMessage());
            return null;
        }

        // ---------------------------------------------------------
        // 4. Deliver credentials (non-blocking — errors are logged)
        // ---------------------------------------------------------
        self::deliverCredentials($email, $phone, $username, $password, $parentName, $admissionNo);

        return (int) $userId;
    }

    // ------------------------------------------------------------------
    // Resend / reset credentials for an existing parent user
    // ------------------------------------------------------------------
    /**
     * Generate a new password, update the DB, and resend credentials.
     * Returns true on success.
     *
     * @param int $userId  Existing parent user_id
     */
    public static function resendCredentials(int $userId): bool
    {
        $user = db_fetch("SELECT * FROM users WHERE id = ? AND role = ?", [$userId, self::PARENT_ROLE]);
        if (!$user) {
            return false;
        }

        $password = self::generatePassword(10);
        $hash     = password_hash($password, PASSWORD_BCRYPT);

        db_query(
            "UPDATE users SET password = ?, portal_generated = 1, portal_sent_at = NOW() WHERE id = ?",
            [$hash, $userId]
        );

        $username = $user['username'] ?? $user['email'] ?? '';
        $email    = $user['email']    ?? '';
        $phone    = $user['phone']    ?? '';

        self::deliverCredentials($email, $phone, $username, $password, $user['name'] ?? 'Parent', '');
        return true;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------
    private static function findExisting(string $email, string $phone): ?array
    {
        // Try email first
        if (!empty($email)) {
            $row = db_fetch(
                "SELECT id, email FROM users WHERE email = ? AND role = ?",
                [$email, self::PARENT_ROLE]
            );
            if ($row) return $row;
        }

        // Try phone if column exists
        if (!empty($phone) && db_column_exists('users', 'phone')) {
            $row = db_fetch(
                "SELECT id, email FROM users WHERE phone = ? AND role = ?",
                [$phone, self::PARENT_ROLE]
            );
            if ($row) return $row;
        }

        return null;
    }

    /**
     * Build a unique, readable username.
     * Format: PRN-XXXXXX-<admNo> where XXXXXX is a hash of email|phone
     */
    private static function generateUsername(string $email, string $phone, string $admissionNo): string
    {
        $seed = $email ?: $phone;
        $hash = strtoupper(substr(md5($seed . microtime()), 0, 6));
        $adm  = preg_replace('/\D/', '', $admissionNo);
        $base = 'PRN-' . $hash . ($adm ? '-' . $adm : '');

        // Ensure uniqueness by checking DB
        $candidate = $base;
        $counter   = 1;
        while (db_fetch("SELECT id FROM users WHERE username = ?", [$candidate])) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }
        return $candidate;
    }

    /**
     * Random alphanumeric password — avoids visually confusing chars (0, O, I, l, 1).
     */
    private static function generatePassword(int $length = 10): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        return $password;
    }

    /**
     * Send credentials via email and/or SMS.
     */
    private static function deliverCredentials(
        string $email,
        string $phone,
        string $username,
        string $password,
        string $parentName,
        string $admissionNo
    ): void {
        $schoolName = defined('APP_NAME') ? APP_NAME : 'School ERP';
        $loginUrl   = (defined('APP_URL') ? rtrim(APP_URL, '/') : '') . '/index.php';

        // ---- EMAIL ----
        if (!empty($email)) {
            $subject = "[$schoolName] Your Parent Portal Credentials";

            $contentHtml = <<<HTML
<p>Dear <strong>{$parentName}</strong>,</p>
<p>A student account has been registered at <strong>{$schoolName}</strong>.
Your parent portal account is now active. Use the credentials below to log in.</p>

<div class="cred-box">
  <div class="cred-row">
    <div class="cred-label">Username / Login ID</div>
    <div class="cred-value">{$username}</div>
  </div>
  <div class="cred-row">
    <div class="cred-label">Password</div>
    <div class="cred-value">{$password}</div>
  </div>
</div>

<a href="{$loginUrl}" class="btn">Login to Portal →</a>

<div class="warning">
  ⚠ Please change your password after first login. Do not share these credentials with anyone.
</div>
HTML;

            $htmlBody = Mailer::buildHtml("Your Parent Portal Access", $contentHtml);
            Mailer::send($email, $subject, $htmlBody);
        }

        // ---- SMS ----
        if (!empty($phone)) {
            $smsText = "{$schoolName}: Your portal login — Username: {$username} | Password: {$password} | URL: {$loginUrl} | Please change your password after first login.";
            SMS::send($phone, $smsText);
        }
    }
}
