<?php
/**
 * JWT Helper — Lightweight HS256 JWT encode/decode
 * School ERP v3.0
 *
 * No external library needed — pure PHP.
 * Config key: JWT_SECRET (from .env.php / bootstrap constants)
 */

class JWT
{
    // Token lifetimes
    const ACCESS_TTL  = 30 * 24 * 3600;   // 30 days  (mobile app)
    const REFRESH_TTL = 90 * 24 * 3600;   // 90 days
    const SSO_TTL     = 5  * 60;           // 5  minutes (WebView SSO)

    // ------------------------------------------------------------------
    // Encode
    // ------------------------------------------------------------------
    /**
     * Create a signed JWT token.
     *
     * @param array $payload  e.g. ['user_id'=>1, 'role'=>'parent', 'exp'=>time()+TTL]
     * @return string  JWT string
     */
    public static function encode(array $payload): string
    {
        $secret = self::secret();
        $header  = self::base64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $claims  = self::base64url(json_encode($payload));
        $sig     = self::base64url(hash_hmac('sha256', "$header.$claims", $secret, true));
        return "$header.$claims.$sig";
    }

    // ------------------------------------------------------------------
    // Decode & validate
    // ------------------------------------------------------------------
    /**
     * Decode and validate a JWT. Returns payload array or throws RuntimeException.
     *
     * @param string $token  Raw JWT string (without "Bearer " prefix)
     * @return array  Decoded payload
     * @throws RuntimeException  on invalid/expired token
     */
    public static function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid token format');
        }

        [$headerB64, $claimsB64, $sigB64] = $parts;

        // Verify signature
        $secret      = self::secret();
        $expected    = self::base64url(hash_hmac('sha256', "$headerB64.$claimsB64", $secret, true));
        if (!hash_equals($expected, $sigB64)) {
            throw new RuntimeException('Invalid token signature');
        }

        $payload = json_decode(self::base64urlDecode($claimsB64), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid token payload');
        }

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new RuntimeException('Token expired');
        }

        return $payload;
    }

    // ------------------------------------------------------------------
    // Middleware: require valid Bearer JWT (for app API endpoints)
    // ------------------------------------------------------------------
    /**
     * Validate Bearer token from Authorization header.
     * On failure, sends 401 JSON and exits.
     *
     * @return array  Decoded payload (user_id, role, etc.)
     */
    public static function requireBearer(): array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Some environments deliver it as REDIRECT_HTTP_AUTHORIZATION
        if (empty($authHeader)) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            http_response_code(401);
            die(json_encode(['error' => 'Missing Authorization header']));
        }

        $token = substr($authHeader, 7);

        try {
            return self::decode($token);
        } catch (RuntimeException $e) {
            http_response_code(401);
            die(json_encode(['error' => $e->getMessage()]));
        }
    }

    // ------------------------------------------------------------------
    // Detect app request — returns JWT payload or null
    // ------------------------------------------------------------------
    /**
     * If the request comes from the Android app (X-App-Client: android header),
     * validate the Bearer token and return its payload.
     * Otherwise returns null (fall through to normal session auth).
     *
     * @return array|null
     */
    public static function detectAppRequest(): ?array
    {
        $client = $_SERVER['HTTP_X_APP_CLIENT'] ?? '';
        if (strtolower($client) !== 'android') {
            return null;
        }
        return self::requireBearer();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private static function secret(): string
    {
        $secret = defined('JWT_SECRET') ? JWT_SECRET : '';
        if (empty($secret)) {
            throw new RuntimeException('JWT_SECRET not configured');
        }
        return $secret;
    }
}
