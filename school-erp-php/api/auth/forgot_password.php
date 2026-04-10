<?php
/**
 * Forgot Password API
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
    
    if (empty($data['email'])) {
        json_response(['error' => 'Email is required'], 400);
    }
    
    Validator::email($data['email']);
    
    if (Validator::hasErrors()) {
        json_response(['errors' => Validator::errors()], 422);
    }
    
    $user = db_fetch("SELECT id, name, email FROM users WHERE email = ?", [$data['email']]);
    
    // Always return success to prevent email enumeration
    if ($user) {
        $token = generate_reset_token($user['email']);
        
        if ($token) {
            // Try to send email, but don't fail if SMTP not configured
            send_reset_email($user['email'], $token);
            
            audit_log('PASSWORD_RESET_REQUESTED', 'auth', $user['id']);
        }
    }
    
    json_response([
        'message' => 'If an account exists with that email, a password reset link has been sent.'
    ]);
}
