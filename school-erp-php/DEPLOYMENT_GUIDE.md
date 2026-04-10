# 🚀 Deployment Guide - school.kashliv.com
## School ERP PHP v3.0 - Production Deployment

**Domain:** school.kashliv.com  
**URL:** https://school.kashliv.com  
**Date:** April 10, 2026

---

## 📋 Pre-Deployment Checklist

### 1. Server Requirements
- [ ] **PHP 7.4+** installed (`php -v`)
- [ ] **MySQL 5.7+** or MariaDB 10.3+ (`mysql --version`)
- [ ] **Apache 2.4+** with mod_rewrite enabled
- [ ] **SSL Certificate** (Let's Encrypt recommended)
- [ ] **Minimum 2GB RAM**, 10GB disk space

### 2. PHP Extensions Required
- [ ] `pdo_mysql`
- [ ] `mbstring`
- [ ] `json`
- [ ] `openssl`
- [ ] `curl`
- [ ] `gd` or `imagick` (for image processing)
- [ ] `finfo` (file upload validation)
- [ ] `zlib` (backup compression)

Check with:
```bash
php -m | grep -E 'pdo_mysql|mbstring|json|openssl|curl|gd|finfo|zlib'
```

### 3. Enable Apache Modules
```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl
sudo a2enmod expires
sudo a2enmod deflate
sudo systemctl restart apache2
```

---

## 🌐 Step-by-Step Deployment

### Step 1: Upload Files
```bash
# Option A: Git Clone
cd /var/www
git clone https://github.com/your-repo/school-erp-php.git
cd school-erp-php

# Option B: Upload via FTP/SFTP
# Upload all files to /var/www/school-erp-php/
```

### Step 2: Database Setup
```bash
# Create database
mysql -u root -p

CREATE DATABASE school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'school_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON school_erp.* TO 'school_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Import schema
mysql -u school_user -p school_erp < setup_complete.sql

# Add performance indexes
mysql -u school_user -p school_erp < add_indexes.sql
```

### Step 3: Environment Configuration
```bash
# Copy environment file
cp .env.example .env.php

# Edit with production credentials
nano .env.php
```

Update these values in `.env.php`:
```ini
# Database
DB_HOST=localhost
DB_NAME=school_erp
DB_USER=school_user
DB_PASS=YourSecurePassword123!

# Application
APP_URL=https://school.kashliv.com
APP_ENV=production
APP_DEBUG=false

# Security (generate new random strings)
SESSION_SECRET=<run: php -r "echo bin2hex(random_bytes(32));">
CSRF_SECRET=<run: php -r "echo bin2hex(random_bytes(32));">
ENCRYPTION_KEY=<run: php -r "echo bin2hex(random_bytes(32));">

# Email (Optional)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password

# SMS (Optional - Twilio)
SMS_ENABLED=true
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_PHONE=+1234567890
```

### Step 4: Secure File Permissions
```bash
# Set ownership (replace www-data with your web user)
sudo chown -R www-data:www-data /var/www/school-erp-php

# Secure sensitive files
chmod 600 .env.php
chmod 600 config/env.php

# Set directory permissions
chmod 755 uploads/
chmod 755 tmp/
chmod 755 backups/
chmod 755 scripts/

# Create required directories
mkdir -p uploads/students uploads/staff uploads/books tmp/cache backups logs

# Prevent direct access to sensitive directories
chmod 750 includes/ config/ tests/
```

### Step 5: Apache Virtual Host Configuration
```bash
sudo nano /etc/apache2/sites-available/school.kashliv.com.conf
```

Add this configuration:
```apache
# HTTP -> HTTPS redirect
<VirtualHost *:80>
    ServerName school.kashliv.com
    ServerAlias www.school.kashliv.com
    Redirect permanent / https://school.kashliv.com/
</VirtualHost>

# HTTPS virtual host
<VirtualHost *:443>
    ServerName school.kashliv.com
    ServerAlias www.school.kashliv.com
    DocumentRoot /var/www/school-erp-php
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/school.kashliv.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/school.kashliv.com/privkey.pem
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
    
    # Document Root
    <Directory /var/www/school-erp-php>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/school-erp-error.log
    CustomLog ${APACHE_LOG_DIR}/school-erp-access.log combined
    
    # Security Headers (also in .htaccess)
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite school.kashliv.com.conf
sudo a2dissite 000-default.conf
sudo systemctl reload apache2
```

### Step 6: SSL Certificate (Let's Encrypt)
```bash
# Install Certbot
sudo apt update
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d school.kashliv.com -d www.school.kashliv.com

# Auto-renewal is set up automatically
# Test renewal:
sudo certbot renew --dry-run
```

### Step 7: Test the Installation
```bash
# Check PHP configuration
php -r "require '/var/www/school-erp-php/includes/env_loader.php'; echo 'DB: ' . DB_HOST . PHP_EOL; echo 'APP_URL: ' . APP_URL . PHP_EOL;"

# Run automated tests
cd /var/www/school-erp-php
php tests/test_all.php

# Create first backup
php scripts/backup-db.php

# Check file permissions
ls -la .env.php
ls -la uploads/
ls -la backups/
```

### Step 8: Setup Automated Backups
```bash
# Edit crontab
crontab -e

# Add these lines:
0 2 * * * /usr/bin/php /var/www/school-erp-php/scripts/backup-db.php >> /var/www/school-erp-php/logs/backup.log 2>&1
0 3 * * 0 /usr/bin/php /var/www/school-erp-php/scripts/backup-db.php --cleanup >> /var/www/school-erp-php/logs/cleanup.log 2>&1
```

### Step 9: Performance Optimization
```bash
# Enable PHP OPcache
sudo nano /etc/php/8.1/apache2/php.ini

# Add/Update these values:
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

# Restart Apache
sudo systemctl restart apache2
```

### Step 10: Monitoring Setup
```bash
# Create health check script
cat > /var/www/school-erp-php/scripts/health-check.sh << 'EOF'
#!/bin/bash
URL="https://school.kashliv.com/api/health.php"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $URL)

if [ $RESPONSE -eq 200 ]; then
    echo "✅ School ERP is UP"
else
    echo "❌ School ERP is DOWN (HTTP $RESPONSE)"
    # Send alert email (configure mail command)
    # echo "School ERP is down!" | mail -s "ALERT: School ERP Down" admin@kashliv.com
fi
EOF

chmod +x /var/www/school-erp-php/scripts/health-check.sh

# Add to crontab (check every 5 minutes)
*/5 * * * * /var/www/school-erp-php/scripts/health-check.sh >> /var/www/school-erp-php/logs/health.log 2>&1
```

---

## 🔒 Post-Deployment Security

### 1. Verify Security
```bash
# Test SSL
curl -I https://school.kashliv.com

# Check headers
curl -I https://school.kashliv.com | grep -E 'X-Frame|X-Content|Strict'

# Verify .env.php is not accessible
curl https://school.kashliv.com/.env.php  # Should return 403

# Verify includes directory is blocked
curl https://school.kashliv.com/includes/db.php  # Should return 403
```

### 2. Create Admin Account
```bash
# Login via web and create superadmin account
# Default credentials:
# Email: admin@school.com
# Password: admin123
# ⚠️ CHANGE IMMEDIATELY after first login!
```

### 3. Configure Firewall (UFW)
```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

### 4. Setup Log Rotation
```bash
sudo nano /etc/logrotate.d/school-erp

# Add:
/var/www/school-erp-php/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

---

## 📊 Verification Checklist

After deployment, verify each component:

- [ ] **Website loads:** https://school.kashliv.com
- [ ] **Login works:** admin@school.com / admin123
- [ ] **SSL active:** Padlock in browser
- [ ] **.env.php blocked:** Returns 403
- [ ] **Database connected:** No DB errors
- [ ] **File uploads work:** Upload test image
- [ ] **Backups working:** Check backups/ directory
- [ ] **Email configured:** Test password reset
- [ ] **SMS configured:** Test SMS sending
- [ ] **Cron jobs running:** Check logs
- [ ] **All tests pass:** `php tests/test_all.php`

---

## 🐛 Troubleshooting

### Issue: 500 Internal Server Error
```bash
# Check Apache error log
sudo tail -f /var/log/apache2/school-erp-error.log

# Check PHP errors
tail -f /var/www/school-erp-php/logs/error.log
```

### Issue: Database Connection Failed
```bash
# Test database connection
mysql -u school_user -p school_erp -e "SELECT 1"

# Verify .env.php credentials
cat /var/www/school-erp-php/.env.php | grep DB_
```

### Issue: Permission Denied
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/school-erp-php

# Fix permissions
find /var/www/school-erp-php -type d -exec chmod 755 {} \;
find /var/www/school-erp-php -type f -exec chmod 644 {} \;
chmod 600 /var/www/school-erp-php/.env.php
```

### Issue: SSL Not Working
```bash
# Check certificate
sudo certbot certificates

# Renew if needed
sudo certbot renew

# Check Apache config
sudo apache2ctl configtest
```

---

## 📞 Support

For issues or questions:
1. Check `SECURITY_HARDENING.md` for security configuration
2. Check `ALL_FIXES_COMPLETED.md` for completed fixes
3. Run `php tests/test_all.php` to diagnose issues
4. Check Apache and PHP error logs
5. Review `.env.php` configuration

---

## 🎓 Deployment Complete!

**Your School ERP is now live at:** https://school.kashliv.com

### Next Steps:
1. ✅ Login and change default password
2. ✅ Create actual user accounts
3. ✅ Import existing student data (if any)
4. ✅ Configure SMS notifications
5. ✅ Setup email notifications
6. ✅ Train staff on using the system
7. ✅ Monitor logs regularly
8. ✅ Review backups weekly

---

**Deployment Date:** _________________  
**Deployed By:** _________________  
**Server IP:** _________________  
**SSL Expiry:** _________________  
**Next Review:** _________________
