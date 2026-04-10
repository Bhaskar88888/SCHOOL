# 🚀 Quick Start Guide - School ERP PHP v3.0

## Installation (5 Minutes)

### Step 1: Database Setup
```sql
-- Run this in phpMyAdmin or MySQL CLI
source setup_complete.sql;
```

### Step 2: Configure (Optional)
Edit `config/env.php` if you need to change database credentials.

### Step 3: Access
Open browser and go to: **https://school.kashliv.com**

### Step 4: Login
- **Super Admin**: `admin@school.com` / `admin123`
- **Teacher**: `teacher@school.com` / `teacher123`

---

## Feature Overview

### 👥 User Management
**URL**: `/users.php`
- Add/Edit/Delete users
- 11 roles: superadmin, admin, teacher, student, parent, hr, accounts, librarian, canteen, conductor, driver
- Search and filter by role
- Set active/inactive status

### 📚 Student Management
**URL**: `/students.php`
- Admit new students
- View/edit student details
- Bulk import from CSV/Excel
- Export to CSV
- Archive discharged students

### ✅ Attendance
**URL**: `/attendance.php`
- Mark daily attendance
- Subject-level tracking
- View attendance reports
- Calculate percentages

### 💰 Fee Management
**URL**: `/fee.php`
- Create fee structures
- Collect fees (Cash/Card/UPI/Bank)
- Generate receipts (PDF)
- View defaulters
- Export fee records

### 📝 Exams & Results
**URL**: `/exams.php`
- Schedule exams
- Enter marks (single/bulk)
- Auto grade calculation (A+ to F)
- Generate report cards (PDF)
- Export results

### 📖 Library
**URL**: `/library.php`
- Add books (manual or ISBN scan)
- Issue/return books
- Calculate fines
- Track borrowing history

### 💼 Payroll
**URL**: `/payroll.php`
- Set salary structures
- Generate monthly payroll
- Auto-calculate based on attendance
- Generate payslips (PDF)
- Mark as paid

### 🚌 Transport
**URL**: `/transport.php`
- Manage vehicles
- Create routes with stops
- Assign drivers/conductors
- Mark boarding attendance

### 🏨 Hostel
**URL**: `/hostel.php`
- Configure room types
- Allocate rooms to students
- Manage hostel fees
- Track occupancy

### 🍔 Canteen
**URL**: `/canteen.php`
- Manage menu items
- Process sales
- RFID wallet integration
- Track inventory

### 📚 Homework
**URL**: `/homework.php`
- Assign homework to classes
- Track submissions
- Notify parents

### 📢 Notices
**URL**: `/notices.php`
- Create notices
- Target specific audiences
- Set priority levels

### ⏰ Routine/Timetable
**URL**: `/routine.php`
- Create class timetables
- Auto-generate schedules
- Detect conflicts

### 🏖️ Leave Management
**URL**: `/leave.php`
- Request leave (casual/earned/sick)
- HR approval workflow
- Track leave balances

### ⚠️ Complaints
**URL**: `/complaints.php`
- File complaints (multi-directional)
- Assign to staff
- Track resolution

### 💬 Remarks
**URL**: `/remarks.php`
- Teachers add remarks on students
- Parents/students can view
- Categorize by type

### 🏫 Classes
**URL**: `/classes.php`
- Create/manage classes
- Assign teachers to subjects
- View class statistics

### 🔔 Notifications
**URL**: `/notifications.php`
- View all notifications
- Mark as read
- Unread count in header

### 📦 Archive
**URL**: `/archive.php`
- View archived students
- View archived staff
- View historical fees/exams
- Search archived records

### 🔍 Audit Log
**URL**: `/audit.php` (Super Admin only)
- View all user actions
- Filter by module/user/date
- Track old/new values

### 👤 Profile
**URL**: `/profile.php`
- Update personal info
- Change password
- View role and permissions

---

## API Quick Reference

### Authentication
```
POST /api/auth/login               - Login
POST /api/auth/logout              - Logout
POST /api/auth/forgot_password.php - Request reset
POST /api/auth/reset_password.php  - Reset password
POST /api/auth/register.php        - Register user (admin only)
```

### Users
```
GET  /api/users/       - List users (paginated)
POST /api/users/       - Create/Update user
DELETE /api/users/     - Delete user
```

### Export
```
GET /api/export/?module=students&format=csv  - Export students
GET /api/export/?module=attendance&format=csv - Export attendance
GET /api/export/?module=fees&format=csv       - Export fees
GET /api/export/?module=exams&format=csv      - Export exams
GET /api/export/?module=library&format=csv    - Export library
GET /api/export/?module=staff&format=csv      - Export staff
```

### Import
```
POST /api/import/?module=students  - Import students (CSV/Excel)
POST /api/import/?module=staff     - Import staff (CSV/Excel)
POST /api/import/?module=fees      - Import fees (CSV/Excel)
```

### Archive
```
GET /api/archive/?action=students  - Archived students
GET /api/archive/?action=staff     - Archived staff
GET /api/archive/?action=fees      - Archived fees
GET /api/archive/?action=exams     - Archived exams
```

### PDF Generation
```
GET /api/pdf/generate.php?action=fee_receipt&id=1           - Fee receipt
GET /api/pdf/generate.php?action=payslip&id=1               - Payslip
GET /api/pdf/generate.php?action=report_card&student_id=1   - Report card
GET /api/pdf/generate.php?action=transfer_certificate&student_id=1 - TC
```

### Notifications
```
GET  /api/notifications/             - List notifications
GET  /api/notifications/unread_count.php - Unread count
POST /api/notifications/             - Mark as read / Mark all read
DELETE /api/notifications/           - Delete notification
```

---

## Import File Formats

### Students CSV Format
```csv
Name,Admission No,Class,DOB,Gender,Parent Name,Parent Phone,Phone,Email,Address
John Doe,ADM202500001,Class 10 A,2010-05-15,Male,Jane Doe,9876543210,9876543211,john@email.com,123 Main St
```

### Staff CSV Format
```csv
Name,Email,Role,Employee ID,Department,Designation,Phone,Password
Jane Smith,jane@school.com,teacher,EMP20250001,Science,Teacher,9876543212,password123
```

### Fees CSV Format
```csv
Admission No,Fee Type,Total Amount,Amount Paid,Payment Method,Paid Date,Month,Year,Receipt No
ADM202500001,Tuition Fee,50000,50000,cash,2025-04-01,April,2025,REC202500001
```

---

## Security Features

### Enabled by Default
- ✅ **CSRF Protection** - All forms protected
- ✅ **Rate Limiting** - 100 requests/hour, 10 auth requests/hour
- ✅ **Account Lockout** - 5 failed attempts = 15 min lock
- ✅ **Password Reset** - Token-based with 1-hour expiry
- ✅ **Session Security** - Auto-regeneration, 8-hour timeout
- ✅ **Input Validation** - Server-side on all inputs
- ✅ **SQL Injection Prevention** - PDO prepared statements
- ✅ **XSS Prevention** - htmlspecialchars on outputs
- ✅ **Audit Logging** - All actions logged with IP

### Configuration
All security settings in `config/env.php`:
```php
RATE_LIMIT_ENABLED = true
LOCKOUT_ENABLED = true
LOCKOUT_MAX_ATTEMPTS = 5
LOCKOUT_DURATION = 900 (15 minutes)
SESSION_LIFETIME = 28800 (8 hours)
```

---

## Troubleshooting

### "Database connection failed"
- Check `config/env.php` for correct credentials
- Ensure MySQL is running
- Verify database exists

### "CSRF token validation failed"
- Clear browser cache and cookies
- Ensure PHP sessions are working
- Check browser console for JavaScript errors

### "Too many requests" (Rate Limited)
- Wait 1 hour for reset
- Or clear `tmp/rate_limits/` directory

### "Account locked"
- Wait 15 minutes
- Or admin can reset in database: `UPDATE users SET locked_until = NULL WHERE email = 'user@email.com'`

### File upload failing
- Check `uploads/` directory exists
- Set permissions: `chmod 755 uploads/`
- Check PHP `upload_max_filesize` in php.ini

### Import errors
- Verify CSV format matches template
- Check required columns are present
- Ensure no duplicate admission numbers/emails

---

## Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@school.com | admin123 |
| Teacher | teacher@school.com | teacher123 |
| HR | hr@school.com | admin123 |
| Canteen | canteen@school.com | admin123 |
| Conductor | driver1@school.com | admin123 |

**⚠️ Change all default passwords in production!**

---

## Support

For issues or questions:
1. Check `README.md` for detailed documentation
2. Check `COMPLETION_SUMMARY.md` for implementation status
3. Check inline code comments
4. Review `config/env.php` for configuration options

---

**Version**: 3.0  
**Last Updated**: April 2025  
**Status**: Production Ready ✅
