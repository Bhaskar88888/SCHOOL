# 🏫 School ERP (EduGlass) - Setup Guide

## 📋 Table of Contents
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Configuration](#configuration)
- [Running the Application](#running-the-application)
- [Default Credentials](#default-credentials)
- [Features Overview](#features-overview)
- [API Endpoints](#api-endpoints)
- [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

Before you begin, ensure you have the following installed:

- **Node.js** (v16 or higher) - [Download](https://nodejs.org/)
- **MongoDB** (v6 or higher) - [Download](https://www.mongodb.com/try/download/community)
- **Git** (optional) - [Download](https://git-scm.com/)

### Verify Installation
```bash
node --version  # Should show v16.x.x or higher
npm --version   # Should show 8.x.x or higher
mongod --version # Should show v6.x or higher
```

---

## 📦 Installation

### 1. Start MongoDB
Make sure MongoDB is running on your system:

**Windows:**
```bash
# If MongoDB is installed as a service, it should start automatically
# Otherwise, start it manually:
mongod
```

**macOS/Linux:**
```bash
# Start MongoDB service
sudo systemctl start mongod
# Or if using Homebrew on macOS:
brew services start mongodb-community
```

### 2. Install Backend Dependencies

```bash
cd server
npm install
```

### 3. Install Frontend Dependencies

```bash
cd ../client
npm install
```

---

## ⚙️ Configuration

### Backend Configuration (.env)

The server `.env` file should contain:

```env
PORT=5000
MONGODB_URI=mongodb://127.0.0.1:27017/school_erp
JWT_SECRET=super_secret_key_1234567890abcdefghijklmnopqrstuvwxyz
JWT_EXPIRES_IN=7d
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_twilio_auth_token
TWILIO_PHONE_NUMBER=+1234567890
SCHOOL_NAME=St. Xavier's School
NODE_ENV=development
```

### Frontend Configuration

Create a `.env` file in the `client` folder:

```env
REACT_APP_API_URL=http://localhost:5000/api
REACT_APP_SCHOOL_NAME=St. Xavier's School
```

---

## 🚀 Running the Application

### Option 1: Run Separately (Recommended for Development)

**Terminal 1 - Backend:**
```bash
cd server
npm run dev
```
Server will start on `http://localhost:5000`

**Terminal 2 - Frontend:**
```bash
cd client
npm start
```
Frontend will start on `http://localhost:3000`

### Option 2: Seed Database First (First Time Only)

Before running the application for the first time, seed the database with admin credentials:

```bash
cd server
node seed.js
```

---

## 👤 Default Credentials

After running `node seed.js`, use these credentials to login:

| Role | Email | Password |
|------|-------|----------|
| **Super Admin** | `admin@school.com` | `admin123` |

### Creating Additional Users

After logging in as Super Admin:
1. Navigate to **User Management**
2. Click **+ Create User**
3. Fill in the details and assign a role

### User Roles

| Role | Access |
|------|--------|
| `superadmin` | Full system access |
| `teacher` | Attendance, homework, exams, routine |
| `student` | View attendance, results, fees, homework |
| `parent` | Monitor children's progress |
| `accounts` | Fee collection, payroll |
| `hr` | Staff management, leave approval |
| `canteen` | Canteen POS, wallet management |
| `conductor` | Transport attendance |
| `driver` | View transport routes |

---

## ✨ Features Overview

### 🎓 Student Management
- ✅ Student admission with comprehensive form
- ✅ Document upload (TC, Birth Certificate)
- ✅ Parent account creation
- ✅ Auto-generated admission numbers
- ✅ Bulk import support
- ✅ Student promotion
- ✅ Advanced search and filters

### 📅 Attendance System
- ✅ Bulk attendance marking (class-wise)
- ✅ Individual attendance
- ✅ Daily/Monthly reports
- ✅ Parent notifications (SMS)
- ✅ Attendance defaulters list
- ✅ Attendance percentage calculation

### 💰 Fee Management
- ✅ Flexible fee structure creation
- ✅ Fee collection with receipts (PDF)
- ✅ Multiple payment modes
- ✅ Payment history
- ✅ Defaulters list
- ✅ Collection reports
- ✅ Receipt printing

### 📚 Exam & Results
- ✅ Exam scheduling
- ✅ Marks entry (bulk/single)
- ✅ Grade calculation
- ✅ Report cards (PDF)
- ✅ Result analytics
- ✅ Historical results

### 📖 Library Management
- ✅ Book cataloging
- ✅ ISBN scanning (OpenLibrary API)
- ✅ Issue/Return system
- ✅ Fine calculation
- ✅ Overdue tracking
- ✅ Borrowing history

### 🍽️ Canteen POS
- ✅ Menu management
- ✅ Order processing
- ✅ RFID wallet integration
- ✅ Wallet recharge
- ✅ Sales reports
- ✅ Inventory tracking

### 🏠 Hostel Management
- ✅ Room types configuration
- ✅ Room allocation
- ✅ Student allotment
- ✅ Vacancy tracking
- ✅ Hostel fee structure
- ✅ Vacate process

### 🚌 Transport Management
- ✅ Vehicle registration
- ✅ Route management
- ✅ Student assignment
- ✅ Boarding/Dropping attendance
- ✅ Parent notifications
- ✅ Driver/Conductor interface

### 👥 HR & Payroll
- ✅ Employee profiles
- ✅ Leave management
- ✅ Leave balance tracking
- ✅ Salary structure
- ✅ Payroll generation
- ✅ Payslip (PDF)
- ✅ Staff attendance

### 📢 Communication
- ✅ Notice board
- ✅ Push notifications
- ✅ Remarks (Teacher-Parent)
- ✅ Complaint management
- ✅ SMS integration

### 📊 Dashboard & Analytics
- ✅ Role-based dashboards
- ✅ Real-time statistics
- ✅ Revenue trends
- ✅ Attendance charts
- ✅ Quick actions
- ✅ Notifications

---

## 🔌 API Endpoints

### Authentication
```
POST   /api/auth/login          - User login
POST   /api/auth/register       - Register new user (SuperAdmin)
GET    /api/auth/users          - Get all users
PUT    /api/auth/users/:id      - Update user
DELETE /api/auth/users/:id      - Delete user
POST   /api/auth/forgot-password - Password reset request
```

### Students
```
POST   /api/students/admit          - Admit new student
GET    /api/students                - Get all students (with filters)
GET    /api/students/:id            - Get student by ID
PUT    /api/students/:id            - Update student
DELETE /api/students/:id            - Discharge student
GET    /api/students/stats/summary  - Get student statistics
POST   /api/students/bulk-import    - Bulk import students
GET    /api/students/class/:classId - Get students by class
PUT    /api/students/:id/promote    - Promote student
```

### Attendance
```
POST   /api/attendance/bulk          - Mark bulk attendance
POST   /api/attendance/mark          - Mark individual attendance
PUT    /api/attendance/:id           - Update attendance
GET    /api/attendance/class/:classId/:date - Get class attendance
GET    /api/attendance/student/:id   - Get student attendance history
GET    /api/attendance/student/:id/stats - Get attendance statistics
GET    /api/attendance/report/daily  - Daily attendance report
GET    /api/attendance/report/monthly - Monthly attendance report
GET    /api/attendance/defaulters    - Get attendance defaulters
```

### Fees
```
POST   /api/fee/structure           - Create fee structure
GET    /api/fee/structures          - Get fee structures
PUT    /api/fee/structure/:id       - Update fee structure
DELETE /api/fee/structures/:id      - Delete fee structure
POST   /api/fee/collect             - Collect fee payment
GET    /api/fee/payments            - Get all payments
GET    /api/fee/my                  - Get own payments
GET    /api/fee/student/:id         - Get student payment history
GET    /api/fee/receipt/:id         - Download receipt PDF
DELETE /api/fee/payment/:id         - Void payment
GET    /api/fee/defaulters          - Get fee defaulters
GET    /api/fee/collection-report   - Collection report
```

### Exams
```
POST   /api/exams/schedule          - Schedule exam
GET    /api/exams/schedule          - Get exam schedules
PUT    /api/exams/schedule/:id      - Update exam schedule
DELETE /api/exams/schedule/:id      - Delete exam
POST   /api/exams/results           - Save single result
POST   /api/exams/results/bulk      - Save bulk results
GET    /api/exams/results/exam/:examId - Get exam results
GET    /api/exams/results/student/:studentId - Get student results
PUT    /api/exams/results/:id       - Update result
DELETE /api/exams/results/:id       - Delete result
GET    /api/exams/report-card/:studentId - Generate report card PDF
GET    /api/exams/analytics         - Exam analytics
```

### Library
```
GET    /api/library/dashboard       - Library dashboard
GET    /api/library/books           - Get all books
POST   /api/library/scan            - Scan ISBN and add book
POST   /api/library/manual          - Add book manually
POST   /api/library/issue           - Issue book to student
PATCH  /api/library/transactions/:id/return - Return book
GET    /api/library/transactions    - Get all transactions
DELETE /api/library/books/:id       - Delete book
```

### Classes
```
POST   /api/classes                 - Create class
GET    /api/classes                 - Get all classes
GET    /api/classes/:id             - Get class detail
PUT    /api/classes/:id             - Update class
DELETE /api/classes/:id             - Delete class
POST   /api/classes/:id/assign-teacher - Assign teacher to subject
GET    /api/classes/teachers/list   - Get all teachers
GET    /api/classes/stats/summary   - Class statistics
```

### Dashboard
```
GET    /api/dashboard/stats         - Dashboard statistics
GET    /api/dashboard/quick-actions - Quick actions by role
GET    /api/dashboard/notifications - User notifications
PUT    /api/dashboard/notifications/read - Mark notifications read
```

---

## 🐛 Troubleshooting

### MongoDB Connection Error
```
Error: connect ECONNREFUSED 127.0.0.1:27017
```
**Solution:** Start MongoDB service
```bash
# Windows
net start MongoDB

# macOS
brew services start mongodb-community

# Linux
sudo systemctl start mongod
```

### Port Already in Use
```
Error: listen EADDRINUSE: address already in use :::5000
```
**Solution:** Kill the process or change the port in `.env`

**Windows:**
```bash
netstat -ano | findstr :5000
taskkill /PID <PID> /F
```

**macOS/Linux:**
```bash
lsof -ti:5000 | xargs kill -9
```

### Module Not Found
```
Error: Cannot find module 'xxx'
```
**Solution:** Reinstall dependencies
```bash
cd server
npm install

cd ../client
npm install
```

### CORS Error
```
Access to XMLHttpRequest blocked by CORS policy
```
**Solution:** Ensure backend is running and CORS settings in `server.js` include your frontend URL

### Login Not Working
1. Ensure MongoDB is running
2. Run `node seed.js` to create admin account
3. Check browser console for errors
4. Verify `REACT_APP_API_URL` in frontend `.env`

---

## 📱 Browser Support

- Chrome (Recommended)
- Firefox
- Safari
- Edge

---

## 🎨 Tech Stack

### Backend
- Node.js + Express.js
- MongoDB + Mongoose
- JWT Authentication
- bcryptjs (Password hashing)
- Multer (File uploads)
- jsPDF (PDF generation)

### Frontend
- React 19
- React Router
- Tailwind CSS
- Axios
- Recharts (Analytics)

---

## 📞 Support

For issues or questions:
1. Check the troubleshooting section
2. Review the implementation plan in `IMPLEMENTATION_PLAN.md`
3. Check server logs in `server/` terminal
4. Check browser console for frontend errors

---

## 📄 License

This project is part of the EduGlass ERP system.

---

**Version:** 1.0  
**Last Updated:** March 27, 2026
