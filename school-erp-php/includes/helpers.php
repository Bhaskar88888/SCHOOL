<?php
/**
 * Helper Functions for School ERP v3.0
 */

/**
 * Generate Auto ID (Admission Number, Employee ID, Receipt No, etc.)
 */
function ensure_counters_table()
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    db_query(
        "CREATE TABLE IF NOT EXISTS counters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            year VARCHAR(10) NOT NULL,
            sequence INT DEFAULT 0,
            UNIQUE KEY unique_counter (name, year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $ensured = true;
}

function generate_auto_id($type, $prefix, $attempt = 0) {
    ensure_counters_table();

    $year = date('Y');
    $ownsTransaction = !db_in_transaction();

    try {
        if ($ownsTransaction) {
            db_beginTransaction();
        }

        $counter = db_fetch(
            "SELECT sequence FROM counters WHERE name = ? AND year = ? FOR UPDATE",
            [$type, $year]
        );

        if ($counter) {
            $newSequence = (int) $counter['sequence'] + 1;
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

        if ($ownsTransaction) {
            db_commit();
        }

        return $prefix . $year . str_pad($newSequence, 5, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        if ($ownsTransaction) {
            db_rollback();
        }

        if ($attempt < 1) {
            ensure_counters_table();
            return generate_auto_id($type, $prefix, $attempt + 1);
        }

        error_log('Auto ID generation failed for ' . $type . ': ' . $e->getMessage());
        throw $e;
    }
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
if (!function_exists('send_sms')) {
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
}
