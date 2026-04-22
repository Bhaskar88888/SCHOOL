<?php
/**
 * SMS Service - Twilio Integration
 * School ERP PHP v3.0
 */

require_once __DIR__ . '/../config/env.php';

class SMSService {
    
    private static $instance = null;
    private $enabled = false;
    private $twilioSid;
    private $twilioToken;
    private $twilioPhone;
    
    private function __construct() {
        $this->enabled = defined('SMS_ENABLED') && SMS_ENABLED;
        $this->twilioSid = defined('TWILIO_SID') ? TWILIO_SID : '';
        $this->twilioToken = defined('TWILIO_TOKEN') ? TWILIO_TOKEN : '';
        $this->twilioPhone = defined('TWILIO_PHONE') ? TWILIO_PHONE : '';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Send SMS via Twilio
     */
    public function send($to, $message) {
        if (!$this->enabled) {
            error_log("SMS not enabled: $to - $message");
            return ['success' => false, 'error' => 'SMS not enabled'];
        }
        
        if (empty($this->twilioSid) || empty($this->twilioToken) || empty($this->twilioPhone)) {
            error_log("SMS credentials not configured: $to - $message");
            return ['success' => false, 'error' => 'SMS credentials not configured'];
        }
        
        // Format phone number
        $to = $this->formatPhone($to);
        
        // Twilio API endpoint
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->twilioSid}/Messages.json";
        
        $data = [
            'From' => $this->twilioPhone,
            'To' => $to,
            'Body' => $message,
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$this->twilioSid}:{$this->twilioToken}",
            CURLOPT_TIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        // Log SMS
        $this->logSMS($to, $message, $httpCode == 201, $result);
        
        if ($httpCode == 201) {
            return ['success' => true, 'sid' => $result['sid'] ?? ''];
        }
        
        return ['success' => false, 'error' => $result['message'] ?? 'Failed to send SMS'];
    }
    
    /**
     * Send bulk SMS
     */
    public function sendBulk($recipients, $message) {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($recipients as $to) {
            $result = $this->send($to, $message);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to send to $to: " . ($result['error'] ?? 'Unknown error');
            }
            
            // Rate limit: 1 SMS per second
            sleep(1);
        }
        
        return $results;
    }
    
    /**
     * Send absence notification to parent
     */
    public function notifyAbsence($studentName, $parentPhone, $date = null) {
        $date = $date ?? date('Y-m-d');
        $message = "Dear Parent, your child $studentName was marked absent on $date. Please contact school if this is an error. - " . APP_NAME;
        return $this->send($parentPhone, $message);
    }
    
    /**
     * Send fee reminder
     */
    public function feeReminder($studentName, $parentPhone, $amount, $dueDate) {
        $message = "Fee Reminder: ₹$amount is due for $studentName by $dueDate. Please pay at the earliest to avoid late fees. - " . APP_NAME;
        return $this->send($parentPhone, $message);
    }
    
    /**
     * Send transport boarding notification
     */
    public function transportBoarding($studentName, $parentPhone, $busNumber) {
        $message = "$studentName has boarded bus $busNumber. - " . APP_NAME;
        return $this->send($parentPhone, $message);
    }
    
    /**
     * Send leave approval notification
     */
    public function leaveStatus($staffName, $phone, $status) {
        $message = "Your leave request has been $status. - " . APP_NAME;
        return $this->send($phone, $message);
    }
    
    /**
     * Format phone number to E.164
     */
    private function formatPhone($phone) {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // If starts with 0, replace with country code
        if (substr($phone, 0, 1) === '0') {
            $phone = '+91' . substr($phone, 1);
        }
        
        // If doesn't start with +, add it
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+91' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Log SMS to database
     */
    private function logSMS($to, $message, $success, $result = null) {
        try {
            require_once __DIR__ . '/../includes/db.php';
            db_query(
                "INSERT INTO audit_logs (user_id, action, module, description, ip_address) VALUES (?, ?, ?, ?, ?)",
                [get_current_user_id() ?? 0, 'SMS_' . ($success ? 'SENT' : 'FAILED'), 'sms', "To: $to, Msg: " . substr($message, 0, 100), $_SERVER['REMOTE_ADDR'] ?? '']
            );
        } catch (Exception $e) {
            error_log("SMS logging failed: " . $e->getMessage());
        }
    }
}

/**
 * Helper function
 */
if (!function_exists('send_sms')) {
function send_sms($to, $message) {
    $result = SMSService::getInstance()->send($to, $message);
    return !empty($result['success']);
}
}

if (!function_exists('send_bulk_sms')) {
function send_bulk_sms($recipients, $message) {
    return SMSService::getInstance()->sendBulk($recipients, $message);
}
}
