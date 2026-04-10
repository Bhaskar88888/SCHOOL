# 🚀 LOCALHOST DEPLOYMENT GUIDE
## School ERP PHP v3.0 - Windows Setup

**Quick Start:** Double-click `start_server.bat`  
**Setup First:** Run `setup_localhost.bat` (one-time only)

---

## 📋 PREREQUISITES

### Option 1: XAMPP (Recommended - Easiest)
1. **Download:** https://www.apachefriends.org/download.html
2. **Install:** Default settings (C:\xampp)
3. **Start:** Open XAMPP Control Panel
   - ✅ Start Apache
   - ✅ Start MySQL
4. **Verify:** 
   - http://localhost (Apache working)
   - http://localhost/phpmyadmin (MySQL working)

### Option 2: Manual PHP Installation
1. **Download PHP:** https://windows.php.net/download/
   - Choose: PHP 8.x Thread Safe x64
2. **Extract:** To `C:\php`
3. **Add to PATH:**
   - System Properties → Environment Variables
   - Edit PATH → Add `C:\php`
4. **Install MySQL:** https://dev.mysql.com/downloads/installer/
5. **Verify:**
   ```cmd
   php -v
   mysql --version
   ```

---

## 🛠️ ONE-TIME SETUP

### Step 1: Run Setup Script
```cmd
cd c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php
setup_localhost.bat
```

**What this does:**
- ✅ Creates `.env.php` with localhost settings
- ✅ Creates required directories (uploads, tmp, backups)
- ✅ Sets up database `school_erp`
- ✅ Imports schema (40+ tables)
- ✅ Adds performance indexes (30+ indexes)

### Step 2: Verify Database
1. Open: http://localhost/phpmyadmin
2. Check database `school_erp` exists
3. Should have 40+ tables

**If database setup failed:**
```cmd
# Manual database setup
mysql -u root
CREATE DATABASE school_erp CHARACTER SET utf8mb4;
USE school_erp;
SOURCE setup_complete.sql;
SOURCE add_indexes.sql;
EXIT;
```

---

## 🚀 START SERVER

### Quick Start (Every Time)
```cmd
# Double-click this file:
start_server.bat

# OR from command line:
cd c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php
start_server.bat
```

**Server will start at:** http://localhost:8000

### Manual Start
```cmd
cd c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp-php
php -S localhost:8000 -t .
```

---

## 🔐 DEFAULT LOGIN

| Role | Email | Password |
|------|-------|----------|
| **Super Admin** | admin@school.com | admin123 |
| **Teacher** | teacher@school.com | teacher123 |
| **HR** | hr@school.com | admin123 |

**⚠️ CHANGE DEFAULT PASSWORDS after first login!**

---

## ✅ VERIFICATION CHECKLIST

After starting server, verify:

### 1. Homepage
- [ ] http://localhost:8000 loads
- [ ] Login page displays correctly
- [ ] Dark theme working

### 2. Login
- [ ] Login with admin@school.com / admin123
- [ ] Redirects to dashboard
- [ ] Dashboard shows stats

### 3. Core Modules
- [ ] Students page loads
- [ ] Attendance page loads
- [ ] Fee page loads
- [ ] Exams page loads
- [ ] Library page loads

### 4. Chatbot
- [ ] Open chatbot widget
- [ ] Type "hi" → Gets greeting
- [ ] Type "help" → Shows help menu
- [ ] Type "How many students?" → Shows count

### 5. Features
- [ ] Add a test student
- [ ] Mark attendance
- [ ] Create a fee record
- [ ] Export to CSV
- [ ] Upload a file

---

## 📂 PROJECT STRUCTURE

```
school-erp-php/
├── 📁 api/                    # API endpoints (56 files)
│   ├── auth/                  # Authentication
│   ├── students/              # Student management
│   ├── attendance/            # Attendance tracking
│   ├── fee/                   # Fee management
│   ├── exams/                 # Exams & results
│   ├── chatbot/               # AI chatbot
│   └── ... (30 modules total)
├── 📁 includes/               # Core libraries
│   ├── db.php                 # Database connection
│   ├── auth.php               # Authentication
│   ├── validator.php          # Input validation
│   └── ... (15 files)
├── 📁 uploads/                # File uploads
│   ├── students/              # Student photos
│   ├── staff/                 # Staff photos
│   └── books/                 # Book covers
├── 📁 tmp/                    # Temporary files
│   └── cache/                 # Cache files
├── 📁 backups/                # Database backups
├── 📁 assets/                 # CSS, JS, images
├── 📁 tests/                  # Test files
├── .env.php                   # Configuration (gitignored)
├── .htaccess                  # Apache config
├── setup_complete.sql         # Database schema
├── add_indexes.sql            # Performance indexes
├── setup_localhost.bat        # One-time setup
├── start_server.bat           # Quick start server
└── index.php                  # Login page
```

---

## 🐛 TROUBLESHOOTING

### Issue: "PHP not found"
**Solution:**
1. Install XAMPP: https://www.apachefriends.org/
2. OR add PHP to PATH:
   ```cmd
   set PATH=%PATH%;C:\php
   ```

### Issue: "MySQL not running"
**Solution:**
1. Open XAMPP Control Panel
2. Start MySQL
3. Check green indicator

### Issue: "Database connection failed"
**Solution:**
1. Check `.env.php` credentials:
   ```php
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=school_erp
   ```
2. Verify MySQL is running
3. Test connection:
   ```cmd
   mysql -u root -p school_erp
   ```

### Issue: "Table doesn't exist"
**Solution:**
```cmd
mysql -u root school_erp < setup_complete.sql
mysql -u root school_erp < add_indexes.sql
```

### Issue: "Port 8000 already in use"
**Solution:**
```cmd
# Use different port
php -S localhost:8001 -t .

# OR kill process using port 8000
netstat -ano | findstr :8000
taskkill /PID <PID> /F
```

### Issue: "Permission denied" on uploads
**Solution:**
```cmd
# Windows - no chmod needed, but check:
icacls uploads /grant Everyone:F /T
```

### Issue: "Chatbot not working"
**Solution:**
1. Check `api/chatbot/chat.php` exists
2. Check browser console for errors
3. Test locally: `tests/chatbot_test.html`

### Issue: "CSS/JS not loading"
**Solution:**
1. Check `.htaccess` exists
2. Verify `assets/` directory structure
3. Clear browser cache (Ctrl+F5)

---

## 🔧 CONFIGURATION

### Edit `.env.php` for Custom Settings
```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_erp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application
define('APP_URL', 'http://localhost:8000');
define('APP_DEBUG', 'true');  // Set to 'false' for production

// SMS (Optional)
define('SMS_ENABLED', 'false');

// Email (Optional)
define('SMTP_HOST', '');

// Chatbot (Optional)
define('GEMINI_API_KEY', '');
```

### PHP Settings (php.ini)
If using XAMPP, edit `C:\xampp\php\php.ini`:
```ini
upload_max_filesize = 5M
post_max_size = 10M
max_execution_time = 60
memory_limit = 256M
```

---

## 📊 TESTING

### Run Automated Tests
```cmd
php tests\run_all_tests.php
```

### Test Chatbot Locally
1. Open: `tests\chatbot_test.html` in browser
2. Test all 50+ intents
3. Verify responses

### Manual Testing Checklist
See: `tests\TESTING_CHECKLIST.md`

---

## 🔄 DAILY WORKFLOW

### Start Working
1. Start XAMPP (Apache + MySQL)
2. Double-click `start_server.bat`
3. Browser opens at http://localhost:8000
4. Login and work!

### Stop Working
1. Press Ctrl+C in server window
2. (Optional) Stop XAMPP services

### Backup Database
```cmd
php scripts\backup-db.php
```

### Restore Database
```cmd
php scripts\backup-db.php --restore backup_2025-04-10.sql.gz
```

---

## 🌐 ACCESS FROM OTHER DEVICES

### Allow Network Access
```cmd
# Instead of localhost, use your IP:
php -S 0.0.0.0:8000 -t .

# Find your IP:
ipconfig

# Access from phone/other PC:
http://YOUR_IP:8000
```

### Firewall (if blocked)
```cmd
# Allow PHP through firewall
netsh advfirewall firewall add rule name="PHP Server" dir=in action=allow program="C:\php\php.exe" enable=yes
```

---

## 📈 PERFORMANCE TIPS

### Enable OPcache (Faster)
Edit `php.ini`:
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
```

### Use XAMPP Instead of Built-in Server
For production-like performance:
1. Copy project to `C:\xampp\htdocs\school-erp`
2. Access at: http://localhost/school-erp
3. Uses Apache (better than PHP built-in server)

---

## 📞 SUPPORT

### Documentation Files
- `README.md` - Complete feature guide
- `QUICK_START.md` - Quick reference
- `DEPLOYMENT_GUIDE.md` - Production deployment
- `SECURITY_HARDENING.md` - Security guide
- `BUG_ANALYSIS_REPORT.md` - Bug analysis

### Common Commands
```cmd
# Start server
start_server.bat

# Setup (one-time)
setup_localhost.bat

# Backup database
php scripts\backup-db.php

# Run tests
php tests\run_all_tests.php

# Clear cache
php -r "require 'includes/cache.php'; cache_clear();"
```

---

## ✅ SUCCESS INDICATORS

You know it's working when:
- ✅ http://localhost:8000 shows login page
- ✅ Can login with admin@school.com / admin123
- ✅ Dashboard shows statistics
- ✅ All 28 pages load without errors
- ✅ Chatbot responds to queries
- ✅ Can add/edit/delete records
- ✅ Export to CSV works
- ✅ File uploads work

---

**Status:** Ready for Local Deployment  
**Estimated Setup Time:** 5-10 minutes  
**Difficulty:** Easy (automated setup)  
**Next Step:** Run `setup_localhost.bat` 🚀
