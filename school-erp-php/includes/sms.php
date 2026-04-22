<?php
/**
 * SMS Gateway Wrapper — School ERP v3.0
 *
 * Supported gateways (set SMS_GATEWAY in .env.php):
 *   fast2sms  — recommended for India (https://www.fast2sms.com)
 *   msg91     — enterprise Indian gateway (https://msg91.com)
 *   twilio    — global (https://twilio.com)
 *
 * Config keys:
 *   SMS_ENABLED       true|false
 *   SMS_GATEWAY       fast2sms | msg91 | twilio
 *   SMS_API_KEY       API key / auth token
 *   SMS_SENDER_ID     6-char sender ID (e.g. SCHOOL)
 *   TWILIO_SID        Twilio account SID (only for twilio)
 *   TWILIO_TOKEN      Twilio auth token  (only for twilio)
 *   TWILIO_PHONE      Twilio from number (only for twilio)
 */

class SMS
{
    /**
     * Send an SMS message. Returns true on success, false on failure.
     *
     * @param string $phone   Recipient phone (10-digit Indian or E.164 for Twilio)
     * @param string $message Message text (max ~160 chars recommended)
     */
    public static function send(string $phone, string $message): bool
    {
        if (!defined('SMS_ENABLED') || !filter_var(SMS_ENABLED, FILTER_VALIDATE_BOOLEAN)) {
            error_log("[SMS] SMS_ENABLED is false. Would have sent to: $phone | $message");
            return false;
        }

        $gateway = defined('SMS_GATEWAY') ? strtolower(trim(SMS_GATEWAY)) : 'fast2sms';

        switch ($gateway) {
            case 'fast2sms':
                return self::sendFast2Sms($phone, $message);
            case 'msg91':
                return self::sendMsg91($phone, $message);
            case 'twilio':
                return self::sendTwilio($phone, $message);
            default:
                error_log("[SMS] Unknown gateway: $gateway");
                return false;
        }
    }

    // ------------------------------------------------------------------
    // Fast2SMS (India) — DLT route
    // ------------------------------------------------------------------
    private static function sendFast2Sms(string $phone, string $message): bool
    {
        $apiKey   = defined('SMS_API_KEY')    ? SMS_API_KEY    : '';
        $senderId = defined('SMS_SENDER_ID')  ? SMS_SENDER_ID  : 'SCHOOL';

        if (empty($apiKey)) {
            error_log("[SMS:fast2sms] SMS_API_KEY not configured.");
            return false;
        }

        // Normalize phone — strip country code if present
        $phone = preg_replace('/^\+91/', '', $phone);
        $phone = preg_replace('/\D/', '', $phone);

        $payload = json_encode([
            'route'    => 'q',     // Quick transactional route
            'message'  => $message,
            'numbers'  => $phone,
            'flash'    => '0',
        ]);

        $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'authorization: ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            error_log("[SMS:fast2sms] cURL error $errno for $phone");
            return false;
        }

        $data = json_decode($response, true);
        if (!empty($data['return']) && $data['return'] === true) {
            return true;
        }

        error_log("[SMS:fast2sms] Failed response for $phone: $response");
        return false;
    }

    // ------------------------------------------------------------------
    // MSG91 (India) — transactional
    // ------------------------------------------------------------------
    private static function sendMsg91(string $phone, string $message): bool
    {
        $apiKey   = defined('SMS_API_KEY')   ? SMS_API_KEY   : '';
        $senderId = defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'SCHOOL';

        if (empty($apiKey)) {
            error_log("[SMS:msg91] SMS_API_KEY not configured.");
            return false;
        }

        // E.164 format for MSG91 — prepend 91 if not present
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        $url = "https://api.msg91.com/api/sendhttp.php?"
            . http_build_query([
                'authkey'  => $apiKey,
                'mobiles'  => $phone,
                'message'  => $message,
                'sender'   => $senderId,
                'route'    => '4',    // 4 = transactional
                'country'  => '91',
            ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno) {
            error_log("[SMS:msg91] cURL error $errno for $phone");
            return false;
        }

        // MSG91 returns "Request Submitted" or similar on success
        if (stripos($response, 'request') !== false) {
            return true;
        }

        error_log("[SMS:msg91] Unexpected response for $phone: $response");
        return false;
    }

    // ------------------------------------------------------------------
    // Twilio — global
    // ------------------------------------------------------------------
    private static function sendTwilio(string $phone, string $message): bool
    {
        $sid   = defined('TWILIO_SID')   ? TWILIO_SID   : '';
        $token = defined('TWILIO_TOKEN') ? TWILIO_TOKEN : '';
        $from  = defined('TWILIO_PHONE') ? TWILIO_PHONE : '';

        if (empty($sid) || empty($token) || empty($from)) {
            error_log("[SMS:twilio] Twilio credentials not configured.");
            return false;
        }

        // Ensure E.164
        if (!str_starts_with($phone, '+')) {
            $phone = '+91' . preg_replace('/\D/', '', $phone);
        }

        $url  = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
        $data = http_build_query(['To' => $phone, 'From' => $from, 'Body' => $message]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERPWD        => "$sid:$token",
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            error_log("[SMS:twilio] cURL error $errno for $phone");
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        error_log("[SMS:twilio] HTTP $httpCode for $phone: $response");
        return false;
    }
}
