<?php
/**
 * Push Notification Helper — FCM (Firebase Cloud Messaging)
 * School ERP v3.0
 *
 * Config keys (.env.php):
 *   FCM_SERVER_KEY   Your Firebase Cloud Messaging server key
 *   FCM_ENABLED      true|false  (default false on local)
 */

require_once __DIR__ . '/db.php';

class PushNotification
{
    const FCM_URL = 'https://fcm.googleapis.com/fcm/send';

    /**
     * Send a push notification to a specific user.
     *
     * @param int    $userId  Target user's ID (looks up device_token from user_tokens)
     * @param string $title   Notification title
     * @param string $body    Notification body
     * @param array  $data    Optional key-value data payload for the app
     */
    public static function send(int $userId, string $title, string $body, array $data = []): bool
    {
        if (!defined('FCM_ENABLED') || !filter_var(FCM_ENABLED, FILTER_VALIDATE_BOOLEAN)) {
            error_log("[FCM] FCM_ENABLED is false. Would notify user $userId: $title");
            self::logNotification($userId, $title, $body, 'queued');
            return false;
        }

        $serverKey = defined('FCM_SERVER_KEY') ? FCM_SERVER_KEY : '';
        if (empty($serverKey)) {
            error_log("[FCM] FCM_SERVER_KEY not configured.");
            self::logNotification($userId, $title, $body, 'failed');
            return false;
        }

        // Get device token(s) for this user
        $tokens = db_fetchAll(
            "SELECT device_token FROM user_tokens 
             WHERE user_id = ? AND device_token IS NOT NULL AND device_token != '' AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 5",
            [$userId]
        );

        if (empty($tokens)) {
            error_log("[FCM] No device token found for user $userId");
            return false;
        }

        $success = false;
        foreach ($tokens as $row) {
            $sent = self::dispatchFcm($serverKey, $row['device_token'], $title, $body, $data);
            if ($sent) {
                $success = true;
            }
        }

        self::logNotification($userId, $title, $body, $success ? 'sent' : 'failed');
        return $success;
    }

    /**
     * Send to multiple users at once.
     *
     * @param int[]  $userIds
     */
    public static function sendToMany(array $userIds, string $title, string $body, array $data = []): void
    {
        foreach ($userIds as $uid) {
            self::send((int)$uid, $title, $body, $data);
        }
    }

    /**
     * Notify a student's parent (looks up parent_user_id from students table).
     *
     * @param int $studentId
     */
    public static function notifyParent(int $studentId, string $title, string $body, array $data = []): void
    {
        if (!db_column_exists('students', 'parent_user_id')) return;

        $student = db_fetch("SELECT parent_user_id FROM students WHERE id = ?", [$studentId]);
        if (!empty($student['parent_user_id'])) {
            self::send((int)$student['parent_user_id'], $title, $body, $data);
        }
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------
    private static function dispatchFcm(
        string $serverKey,
        string $deviceToken,
        string $title,
        string $body,
        array $data
    ): bool {
        $payload = json_encode([
            'to'           => $deviceToken,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
            ],
            'data'         => array_merge($data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']),
            'priority'     => 'high',
        ]);

        $ch = curl_init(self::FCM_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'Authorization: key=' . $serverKey,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            error_log("[FCM] cURL error $errno for token: " . substr($deviceToken, 0, 20));
            return false;
        }

        $result = json_decode($response, true);
        if (!empty($result['failure']) && $result['failure'] > 0) {
            error_log("[FCM] Delivery failure. Response: $response");
            return false;
        }

        return $httpCode >= 200 && $httpCode < 300 && !empty($result['success']);
    }

    private static function logNotification(int $userId, string $title, string $body, string $status): void
    {
        try {
            if (!db_table_exists('push_notification_log')) return;
            db_query(
                "INSERT INTO push_notification_log (user_id, title, body, status, sent_at) VALUES (?, ?, ?, ?, NOW())",
                [$userId, $title, substr($body, 0, 500), $status]
            );
        } catch (Throwable $e) {
            // Log table might not exist in all environments — ignore
        }
    }
}
