<?php
/**
 * Reset Password API
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/validator.php';

// Rate limiting for auth
require_once __DIR__ . '/../../includes/rate_limiter.php';
RateLimiter::check(RATE_LIMIT_AUTH_REQUESTS, RATE_LIMIT_AUTH_WINDOW);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_post_json();
    
    if (empty($data['token']) || empty($data['password'])) {
        json_response(['error' => 'Token and password are required'], 400);
    }
    
    Validator::password($data['password']);
    
    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }
    
    $user = verify_reset_token($data['token']);
    
    if (!$user) {
        json_response(['error' => 'Invalid or expired reset token'], 400);
    }
    
    $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
    
    db_query(
        "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?",
        [$hashedPassword, $user['id']]
    );
    
    audit_log('PASSWORD_RESET', 'auth', $user['id']);
    
    json_response(['message' => 'Password reset successfully']);
}
