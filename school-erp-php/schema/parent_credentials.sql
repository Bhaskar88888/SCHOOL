-- ============================================================
-- Parent Credentials Schema Migration
-- School ERP v3.0 — Run once on local + VPS
-- ============================================================

-- 1. Add portal-related columns to users table
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS username       VARCHAR(80)  UNIQUE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS phone          VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS portal_generated TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS portal_sent_at DATETIME     DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS last_login_at  DATETIME     DEFAULT NULL;

-- 2. App token / JWT refresh tokens
CREATE TABLE IF NOT EXISTS user_tokens (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    refresh_token VARCHAR(255) NOT NULL,
    device_token  VARCHAR(255) DEFAULT NULL COMMENT 'FCM push notification token',
    expires_at    DATETIME     NOT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_tokens_user (user_id),
    UNIQUE INDEX idx_user_tokens_refresh (refresh_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. WebView SSO one-time tokens
CREATE TABLE IF NOT EXISTS webview_sso_tokens (
    token      VARCHAR(64)  NOT NULL PRIMARY KEY,
    user_id    INT          NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webview_sso_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Notification log (for push delivery tracking)
CREATE TABLE IF NOT EXISTS push_notification_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(255) NOT NULL,
    body       TEXT,
    status     ENUM('sent','failed','queued') DEFAULT 'queued',
    sent_at    DATETIME     DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
