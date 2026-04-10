# ✅ Domain Updated - school.kashliv.com

**Date:** April 10, 2026  
**Domain:** school.kashliv.com  
**URL:** https://school.kashliv.com

---

## 📋 Files Updated

| File | Change | Status |
|------|--------|--------|
| `.env.example` | `APP_URL=https://school.kashliv.com` | ✅ |
| `includes/env_loader.php` | Default APP_URL updated | ✅ |
| `.htaccess` | HSTS enabled for HTTPS | ✅ |
| `README.md` | Added domain & deployment instructions | ✅ |
| `QUICK_START.md` | Updated access URL | ✅ |
| `DEPLOYMENT_GUIDE.md` | Complete deployment guide created | ✅ |

---

## 🌐 Configuration Summary

### Environment (`.env.php`)
```ini
APP_URL=https://school.kashliv.com
APP_ENV=production
APP_DEBUG=false
```

### Apache Virtual Host
```apache
ServerName school.kashliv.com
Redirect permanent / https://school.kashliv.com/
```

### SSL Certificate
```bash
sudo certbot --apache -d school.kashliv.com -d www.school.kashliv.com
```

---

## 🚀 Quick Deploy

```bash
# 1. Upload to server
scp -r school-erp-php user@your-server:/var/www/

# 2. Setup database
mysql -u root -p school_erp < setup_complete.sql
mysql -u root -p school_erp < add_indexes.sql

# 3. Configure
cp .env.example .env.php
nano .env.php  # Already has school.kashliv.com

# 4. Secure
chmod 600 .env.php
chown -R www-data:www-data /var/www/school-erp-php

# 5. Enable site
sudo a2ensite school.kashliv.com.conf
sudo systemctl reload apache2

# 6. Get SSL
sudo certbot --apache -d school.kashliv.com
```

---

## ✅ Verification

After deployment, check:

1. **Website:** https://school.kashliv.com
2. **API Health:** https://school.kashliv.com/api/health.php
3. **Security:** 
   ```bash
   curl -I https://school.kashliv.com
   # Should show: Strict-Transport-Security: max-age=31536000
   ```
4. **Blocked Files:**
   ```bash
   curl https://school.kashliv.com/.env.php  # Should return 403
   curl https://school.kashliv.com/includes/db.php  # Should return 403
   ```

---

## 📚 Documentation Files

All updated with school.kashliv.com:

1. **`README.md`** - Main documentation with Apache config
2. **`DEPLOYMENT_GUIDE.md`** - Complete deployment steps
3. **`QUICK_START.md`** - Quick access guide
4. **`.env.example`** - Environment template with correct domain
5. **`includes/env_loader.php`** - Default APP_URL updated

---

## 🎯 Next Steps

1. ✅ Purchase/configure domain DNS
2. ✅ Point DNS to your server IP
3. ✅ Deploy files to server
4. ✅ Setup SSL certificate
5. ✅ Test at https://school.kashliv.com
6. ✅ Change default admin password
7. ✅ Setup automated backups

---

**Domain Status:** ✅ Updated in all configuration files  
**Ready for:** Production Deployment  
**URL:** https://school.kashliv.com
