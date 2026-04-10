<?php
/**
 * Helper Functions for School ERP v3.0
 */

/**
 * Generate Auto ID (Admission Number, Employee ID, Receipt No, etc.)
 */
function generate_auto_id($type, $prefix) {
    $year = date('Y');
    
    // Get current sequence
    $counter = db_fetch(
        "SELECT sequence FROM counters WHERE name = ? AND year = ?",
        [$type, $year]
    );
    
    if ($counter) {
        $newSequence = $counter['sequence'] + 1;
        db_query(
            "UPDATE counters SET sequence = ? WHERE name = ? AND year = ?",
            [$newSequence, $type, $year]
        );
    } else {
        $newSequence = 1;
        db_query(
            "INSERT INTO counters (name, year, sequence) VALUES (?, ?, ?)",
            [$type, $year, $newSequence]
        );
    }
    
    return $prefix . $year . str_pad($newSequence, 5, '0', STR_PAD_LEFT);
}

/**
 * Calculate Grade from marks
 */
function calculate_grade($marksObtained, $totalMarks) {
    if ($totalMarks <= 0) return 'F';
    
    $percentage = ($marksObtained / $totalMarks) * 100;
    
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C';
    if ($percentage >= 40) return 'D';
    if ($percentage >= 33) return 'E';
    return 'F';
}

/**
 * Calculate attendance percentage
 */
function calculate_attendance_percentage($present, $total) {
    if ($total <= 0) return 0;
    return ($present / $total) * 100;
}

/**
 * Date difference in days
 */
function days_between($date1, $date2) {
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    return $d1->diff($d2)->days;
}

/**
 * Format currency
 */
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Time ago format
 */
function time_ago($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

/**
 * Send SMS (Twilio integration placeholder)
 */
function send_sms($to, $message) {
    if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
        return false;
    }
    
    if (empty(TWILIO_SID) || empty(TWILIO_TOKEN) || empty(TWILIO_PHONE)) {
        return false;
    }
    
    // Twilio API integration would go here
    // For now, just log the SMS
    error_log("SMS would be sent to $to: $message");
    return true;
}

/**
 * Send notification
 */
function send_notification($recipientId, $title, $message, $type = 'info', $senderId = null) {
    $table = db_table_exists('notifications_enhanced') ? 'notifications_enhanced' : 'notifications';
    
    if ($table === 'notifications_enhanced') {
        db_query(
            "INSERT INTO notifications_enhanced (recipient_id, sender_id, title, message, type) VALUES (?, ?, ?, ?, ?)",
            [$recipientId, $senderId, $title, $message, $type]
        );
    } else {
        db_query(
            "INSERT INTO notifications (target_user, title, message, is_read) VALUES (?, ?, ?, 0)",
            [$recipientId, $title, $message]
        );
    }
}

/**
 * Get unread notification count
 */
function get_unread_notification_count($userId) {
    if (db_table_exists('notifications_enhanced')) {
        return db_fetch(
            "SELECT COUNT(*) as count FROM notifications_enhanced WHERE recipient_id = ? AND is_read = 0",
            [$userId]
        )['count'];
    }
    
    return db_fetch(
        "SELECT COUNT(*) as count FROM notifications WHERE target_user = ? AND is_read = 0",
        [$userId]
    )['count'];
}
