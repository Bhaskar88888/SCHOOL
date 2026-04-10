# School ERP PHP v3.0 - Complete Implementation Guide

**Live URL:** https://school.kashliv.com  
**Domain:** school.kashliv.com  

## Overview
This is the PHP version of the School ERP system, matching all features of the Node.js version.

## Tech Stack
- **Backend**: Plain PHP (no framework)
- **Database**: MySQL/MariaDB via PDO
- **Frontend**: Vanilla HTML/CSS/JavaScript
- **Auth**: PHP Sessions with enhanced security
- **AI Chatbot**: Rule-based + Gemini API fallback

## Installation

### 1. Database Setup
```bash
# Run the complete schema setup
mysql -u username -p database_name < setup_complete.sql

# Add performance indexes
mysql -u username -p database_name < add_indexes.sql
```

### 2. Configuration
```bash
# Copy environment file
cp .env.example .env.php

# Edit with your credentials
nano .env.php

# Secure the file
chmod 600 .env.php
```

Update in `.env.php`:
- Database credentials
- Session secrets (auto-generated)
- Email SMTP (optional)
- SMS Twilio credentials (optional)
- Gemini API key (optional)

### 3. Directory Permissions
```bash
chmod 755 uploads/
chmod 755 tmp/
chmod 755 backups/
mkdir -p uploads/students uploads/staff uploads/books tmp/cache
```

### 4. Web Server Setup (Apache)
- Point document root to project directory
- Ensure `.htaccess` is enabled (Apache mod_rewrite)
- Enable SSL certificate for HTTPS
- PHP 7.4+ required

#### Apache Virtual Host Example:
```apache
<VirtualHost *:80>
    ServerName school.kashliv.com
    Redirect permanent / https://school.kashliv.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName school.kashliv.com
    DocumentRoot /var/www/school-erp-php
    
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/privkey.pem
    
    <Directory /var/www/school-erp-php>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 5. Automated Backups
```bash
# Add to crontab
crontab -e

# Daily backup at 2 AM
0 2 * * * /usr/bin/php /var/www/school-erp-php/scripts/backup-db.php
# Cleanup old backups weekly
0 3 * * 0 /usr/bin/php /var/www/school-erp-php/scripts/backup-db.php --cleanup
```

## Security Features (v3.0)
✅ CSRF Protection
✅ Rate Limiting (API & Auth)
✅ Account Lockout (after 5 failed attempts)
✅ Password Reset with Token
✅ Session Security (regeneration, timeout)
✅ Input Validation
✅ SQL Injection Prevention (PDO prepared statements)
✅ XSS Prevention (htmlspecialchars)
✅ Audit Logging

## Modules Implemented

### Core Modules
1. ✅ Authentication & Authorization (JWT-like sessions)
2. ✅ User Management (CRUD, roles, search)
3. ✅ Student Management (admission, bulk import, archive)
4. ✅ Attendance (subject-level, reports, defaulters)
5. ✅ Fee Management (structures, receipts, multiple payment modes)
6. ✅ Exams & Results (grading, report cards, analytics)
7. ✅ Library (ISBN scanning, fine calculation)
8. ✅ Payroll (salary structures, auto-generation, payslips)
9. ✅ Transport (routes, stops, attendance)
10. ✅ Hostel (room types, allocations, fee structures)
11. ✅ Canteen (POS, wallet, sales)
12. ✅ Homework (assignments, notifications)
13. ✅ Notices (notice board, audience targeting)
14. ✅ Routine/Timetable (manual entry, auto-generation)
15. ✅ Leave Management (types, approval workflow)
16. ✅ Complaints (multi-directional, resolution tracking)
17. ✅ Remarks (teacher feedback)
18. ✅ Classes (CRUD, subject assignment)
19. ✅ Notifications (push, unread count)
20. ✅ Archive (historical data)
21. ✅ Export/Import (CSV, Excel, PDF)
22. ✅ PDF Generation (receipts, payslips, report cards, TC)
23. ✅ AI Chatbot (multi-intent, multi-language support)
24. ✅ Audit Log (detailed tracking)
25. ✅ Dashboard (charts, analytics, role-based stats)

## User Roles
- **superadmin**: Full access
- **admin**: Administrative access
- **teacher**: Academic modules
- **student**: Read-only own data
- **parent**: Read-only children's data
- **hr**: Staff management
- **accounts**: Fee & payroll
- **librarian**: Library management
- **canteen**: Canteen POS
- **conductor**: Transport attendance
- **driver**: View-only routes

## API Endpoints

### Authentication
- POST `/api/auth/login` - User login
- POST `/api/auth/logout` - Logout
- POST `/api/auth/forgot_password.php` - Request password reset
- POST `/api/auth/reset_password.php` - Reset password
- POST `/api/auth/register.php` - Register user (admin only)

### Users
- GET `/api/users/` - List users (paginated, searchable)
- POST `/api/users/` - Create/Update user
- DELETE `/api/users/` - Delete user

### Students
- GET `/api/students/index.php` - List students
- POST `/api/students/index.php` - Create/Update student
- DELETE `/api/students/index.php` - Delete/Archive student
- GET `/api/students/export.php` - Export students CSV
- POST `/api/students/import.php` - Import students CSV

### Attendance
- GET `/api/attendance/index.php` - List attendance
- POST `/api/attendance/index.php` - Mark attendance (single/bulk)
- DELETE `/api/attendance/index.php` - Delete attendance

### Fees
- GET `/api/fee/index.php` - List fees
- POST `/api/fee/index.php` - Create/Update fee
- DELETE `/api/fee/index.php` - Delete fee

### Exams
- GET `/api/exams/index.php` - List exams
- POST `/api/exams/index.php` - Create/Update exam
- DELETE `/api/exams/index.php` - Delete exam

### Export
- GET `/api/export/index.php?module=students&format=csv` - Export students
- GET `/api/export/index.php?module=attendance&format=csv` - Export attendance
- GET `/api/export/index.php?module=fees&format=csv` - Export fees
- GET `/api/export/index.php?module=exams&format=csv` - Export exams
- GET `/api/export/index.php?module=library&format=csv` - Export library
- GET `/api/export/index.php?module=staff&format=csv` - Export staff

### Import
- POST `/api/import/index.php?module=students` - Import students
- POST `/api/import/index.php?module=staff` - Import staff
- POST `/api/import/index.php?module=fees` - Import fees

### Archive
- GET `/api/archive/index.php?action=students` - Archived students
- GET `/api/archive/index.php?action=staff` - Archived staff
- GET `/api/archive/index.php?action=fees` - Archived fees
- GET `/api/archive/index.php?action=exams` - Archived exams

### PDF
- GET `/api/pdf/generate.php?action=fee_receipt&id=1` - Fee receipt
- GET `/api/pdf/generate.php?action=payslip&id=1` - Payslip
- GET `/api/pdf/generate.php?action=report_card&student_id=1` - Report card
- GET `/api/pdf/generate.php?action=transfer_certificate&student_id=1` - TC

### Notifications
- GET `/api/notifications/list.php` - List notifications
- GET `/api/notifications/unread_count.php` - Unread count
- POST `/api/notifications/mark_read.php` - Mark as read
- POST `/api/notifications/mark_all_read.php` - Mark all as read

### Chatbot
- POST `/api/chatbot/chat.php` - Send message

### Dashboard
- GET `/api/dashboard/stats.php` - Dashboard statistics

### Profile
- GET `/api/profile/index.php` - Get profile
- POST `/api/profile/index.php` - Update profile/password

## Frontend Pages
- `index.php` - Login
- `dashboard.php` - Dashboard
- `students.php` - Student Management
- `attendance.php` - Attendance
- `fee.php` - Fee Management
- `exams.php` - Exams & Results
- `hr.php` - Staff Management
- `payroll.php` - Payroll
- `library.php` - Library
- `hostel.php` - Hostel
- `transport.php` - Transport
- `canteen.php` - Canteen
- `homework.php` - Homework
- `notices.php` - Notices
- `routine.php` - Timetable
- `leave.php` - Leave Management
- `complaints.php` - Complaints
- `remarks.php` - Remarks
- `classes.php` - Classes
- `notifications.php` - Notifications
- `audit.php` - Audit Log
- `profile.php` - User Profile

## Features Parity with Node.js
✅ 100% Feature parity achieved
✅ All database models matched
✅ All API endpoints implemented
✅ All frontend pages created
✅ Security enhanced (CSRF, rate limiting, lockout)
✅ Export/Import system complete
✅ PDF generation system complete
✅ Archive system complete
✅ User management complete
✅ Enhanced notifications
✅ Auto ID generation
✅ Audit logging
✅ Multi-role access control

## Differences from Node.js Version
- Uses PHP sessions instead of JWT (more secure for web apps)
- PDO prepared statements instead of Prisma ORM
- Server-rendered PHP + inline JS vs React SPA
- File-per-page routing vs Express routers
- No build step required (runs directly on Apache/PHP)

## Performance
- No Node.js runtime overhead
- Direct PHP execution (faster response times)
- MySQL native driver (PDO)
- Server-side rendering (better SEO)
- No client-side bundle size

## Support
For issues or questions, check the inline code documentation or contact the development team.
