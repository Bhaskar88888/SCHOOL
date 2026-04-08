# 🏫 EduGlass School ERP

> A comprehensive, modern, and production-ready School Management System built with the MERN Stack

![Status](https://img.shields.io/badge/status-production%20ready-brightgreen)
![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-MIT-green)

---

## 📖 Table of Contents

- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Quick Start](#-quick-start)
- [Documentation](#-documentation)
- [Screenshots](#-screenshots)
- [User Roles](#-user-roles)
- [API Endpoints](#-api-endpoints)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### 🎓 Student Management
- Complete admission workflow with document upload
- Bulk import from CSV/Excel
- Student promotion system
- Parent account creation
- Comprehensive search and filters

### 📅 Attendance System
- Bulk attendance marking (class-wise)
- SMS notifications to parents for absent students
- Daily/Monthly reports
- Attendance defaulters list
- Percentage calculation

### 💰 Fee Management
- Flexible fee structure creation
- Multiple payment modes (Cash, Card, UPI, Bank Transfer)
- PDF receipt generation
- Payment history tracking
- Defaulters list and reports

### 📚 Exam & Results
- Exam scheduling
- Marks entry (single/bulk)
- Automatic grade calculation
- Report card PDF generation
- Result analytics and historical data

### 📖 Library Management
- Book cataloging with ISBN scanning
- Issue/Return system
- Fine calculation for overdue books
- Borrowing history tracking
- OpenLibrary API integration

### 🍽️ Canteen POS
- Menu and inventory management
- RFID wallet integration
- Wallet recharge and balance tracking
- Sales reports
- Quick billing interface

### 🏠 Hostel Management
- Room type configuration
- Room allocation and vacancy tracking
- Student allotment process
- Hostel fee structure
- Vacate management

### 🚌 Transport Management
- Vehicle and route management
- Driver/Conductor assignment
- Boarding/Dropping attendance
- Parent notifications
- Transport fee calculation

### 👥 HR & Payroll
- Employee profile management
- Leave application and approval workflow
- Salary structure setup
- Automated payroll generation
- Payslip PDF generation

### 📢 Communication
- Digital notice board
- Push notifications
- Teacher-Parent remarks system
- Complaint management
- SMS integration (Twilio)

### 📊 Dashboard & Analytics
- Role-based dashboards
- Real-time statistics
- Revenue trends (6 months chart)
- Attendance visualization
- Quick action shortcuts

---

## 🛠️ Tech Stack

### Backend
- **Runtime:** Node.js
- **Framework:** Express.js
- **Database:** MongoDB + Mongoose
- **Authentication:** JWT (jsonwebtoken)
- **Security:** Helmet, bcryptjs
- **File Upload:** Multer
- **PDF Generation:** jsPDF
- **SMS:** Twilio

### Frontend
- **Library:** React 19
- **Routing:** React Router v7
- **Styling:** Tailwind CSS
- **HTTP Client:** Axios
- **Charts:** Recharts
- **State:** Context API

### DevOps
- **Version Control:** Git
- **Package Manager:** npm
- **Database:** MongoDB

---

## 🚀 Quick Start

### Prerequisites
- Node.js (v16+)
- MongoDB (v6+)
- npm or yarn

### 1. Clone & Install

```bash
# Navigate to project
cd school-erp

# Install backend dependencies
cd server
npm install

# Install frontend dependencies
cd ../client
npm install
```

### 2. Configure Environment

**Backend (.env in server/):**
```env
PORT=5000
MONGODB_URI=mongodb://username:password@127.0.0.1:27017/school_erp?authSource=admin
JWT_SECRET=replace_with_a_unique_32_plus_character_secret
JWT_EXPIRES_IN=7d
SCHOOL_NAME=St. Xavier's School
NODE_ENV=development
SEED_SUPERADMIN_PASSWORD=replace_with_a_strong_admin_password
```

**Frontend (.env in client/):**
```env
REACT_APP_API_URL=http://localhost:5000/api
REACT_APP_SCHOOL_NAME=St. Xavier's School
```

### 3. Seed Database

```bash
cd server
node seed.js
```

This creates the admin account:
- **Email:** admin@school.com
- **Password:** value of `SEED_SUPERADMIN_PASSWORD` or an auto-generated secure password printed by `seed.js`

### 4. Start Application

**Terminal 1 - Backend:**
```bash
cd server
npm run dev
```
Server runs on http://localhost:5000

**Terminal 2 - Frontend:**
```bash
cd client
npm start
```
Frontend runs on http://localhost:3000

### 5. Login

Open http://localhost:3000 and login with:
- **Email:** admin@school.com
- **Password:** the secure password generated or configured during seeding

---

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [SETUP_GUIDE.md](./SETUP_GUIDE.md) | Detailed installation and setup instructions |
| [IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md) | Complete implementation roadmap |
| [PROJECT_SUMMARY.md](./PROJECT_SUMMARY.md) | Project completion summary |
| [README.md](./README.md) | This file - project overview |

---

## 📸 Screenshots

### Dashboard
- Role-based dashboard with statistics
- Revenue trends chart
- Attendance visualization
- Quick action cards

### Student Management
- Student admission form
- Student list with filters
- Student profile view
- Document upload

### Attendance
- Class-wise attendance sheet
- Quick marking interface
- Attendance reports
- Defaulters list

### Fee Management
- Fee structure creation
- Fee collection with receipt
- Payment history
- Collection reports

---

## 👥 User Roles

| Role | Access Level | Key Features |
|------|-------------|--------------|
| **Super Admin** | Full Access | All modules, user management, system configuration |
| **Teacher** | Academic | Attendance, homework, exams, routine, remarks |
| **Student** | Read-only (own data) | View attendance, results, fees, homework |
| **Parent** | Read-only (children) | Monitor children's progress, pay fees |
| **Accounts** | Financial | Fee collection, payroll, reports |
| **HR** | Staff Management | Employee management, leave approval |
| **Canteen** | POS | Canteen billing, wallet management |
| **Conductor** | Transport | Student attendance on buses |
| **Driver** | View-only | View assigned routes |

---

## 🔌 API Endpoints

### Authentication
```
POST   /api/auth/login          - User login
POST   /api/auth/register       - Register user
GET    /api/auth/users          - Get all users
PUT    /api/auth/users/:id      - Update user
DELETE /api/auth/users/:id      - Delete user
```

### Students
```
POST   /api/students/admit          - Admit student
GET    /api/students                - Get all students
GET    /api/students/:id            - Get student by ID
PUT    /api/students/:id            - Update student
DELETE /api/students/:id            - Discharge student
POST   /api/students/bulk-import    - Bulk import
```

### Attendance
```
POST   /api/attendance/bulk          - Mark bulk attendance
POST   /api/attendance/mark          - Mark individual attendance
GET    /api/attendance/class/:classId/:date - Get class attendance
GET    /api/attendance/student/:id/stats - Get student stats
GET    /api/attendance/report/monthly - Monthly report
```

### Fees
```
POST   /api/fee/structure           - Create fee structure
POST   /api/fee/collect             - Collect fee
GET    /api/fee/receipt/:id         - Download receipt PDF
GET    /api/fee/defaulters          - Get defaulters
GET    /api/fee/collection-report   - Collection report
```

### Exams
```
POST   /api/exams/schedule          - Schedule exam
POST   /api/exams/results/bulk      - Save bulk results
GET    /api/exams/report-card/:studentId - Generate report card
GET    /api/exams/analytics         - Exam analytics
```

### Library
```
POST   /api/library/scan            - Scan ISBN
POST   /api/library/issue           - Issue book
PATCH  /api/library/transactions/:id/return - Return book
GET    /api/library/transactions    - All transactions
```

### Dashboard
```
GET    /api/dashboard/stats         - Dashboard statistics
GET    /api/dashboard/quick-actions - Quick actions
GET    /api/dashboard/notifications - Notifications
```

*For complete API documentation, see [API_REFERENCE.md](./docs/API_REFERENCE.md)*

---

## 🏗️ Project Structure

```
school-erp/
├── server/                    # Backend
│   ├── config/               # Database configuration
│   ├── middleware/           # Auth, upload, role check
│   ├── models/               # 27 Mongoose models
│   ├── routes/               # 22 API route files
│   ├── services/             # SMS, email services
│   ├── uploads/              # File uploads
│   ├── server.js             # Main application
│   └── seed.js               # Database seeding
│
├── client/                    # Frontend
│   ├── public/               # Static assets
│   ├── src/
│   │   ├── api/              # API functions
│   │   ├── components/       # Reusable components
│   │   ├── contexts/         # React contexts
│   │   ├── pages/            # 19 page components
│   │   ├── App.jsx           # Main app
│   │   └── index.js          # Entry point
│   └── package.json
│
├── SETUP_GUIDE.md
├── IMPLEMENTATION_PLAN.md
├── PROJECT_SUMMARY.md
└── README.md
```

---

## 🧪 Testing

### Run Tests

```bash
# Backend tests (coming soon)
cd server
npm test

# Frontend tests
cd client
npm test
```

### Manual Testing Checklist
- [ ] Login/Logout
- [ ] Student admission
- [ ] Attendance marking
- [ ] Fee collection
- [ ] Exam scheduling
- [ ] Result entry
- [ ] Library issue/return
- [ ] Report generation

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Style
- Use ESLint configuration
- Follow existing code conventions
- Write meaningful commit messages
- Add comments for complex logic

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- MongoDB for the database
- React team for the amazing library
- Tailwind CSS for the utility-first CSS framework
- All contributors to this project

---

## 📞 Support

For issues and questions:
- Create an issue on GitHub
- Check existing documentation
- Review troubleshooting guide in SETUP_GUIDE.md

---

## 🎯 Roadmap

### Phase 1 (Completed) ✅
- Core modules implementation
- User authentication
- All major features

### Phase 2 (Planned)
- Online payment gateway integration
- Email notifications
- Mobile app (React Native)
- Advanced analytics

### Phase 3 (Future)
- AI-powered insights
- Video conferencing integration
- Learning Management System (LMS)
- Parent-Teacher meeting scheduler

---

**Made with ❤️ by the EduGlass Team**

**Version:** 1.0.0  
**Last Updated:** March 27, 2026
