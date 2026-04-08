# 🧪 COMPREHENSIVE MANUAL TESTING CHECKLIST

**Test Date:** ___________  
**Tester:** ___________  
**Status:** ⏳ Ready for Testing

---

## 📋 How to Use This Checklist

1. **Login** with each test account (see credentials below)
2. **Navigate** through each module
3. **Click EVERY button** you see
4. **Test EVERY feature** listed
5. **Mark** ✓ if working, ✗ if broken, ⚠️ if partially working
6. **Note** any issues in the comments section

---

## 🔐 Test Account Credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | superadmin@test.com | test123 |
| Teacher | teacher@test.com | test123 |
| Student | student@test.com | test123 |
| Parent | parent@test.com | test123 |
| Accounts | accounts@test.com | test123 |
| HR | hr@test.com | test123 |
| Canteen | canteen@test.com | test123 |
| Conductor | conductor@test.com | test123 |
| Driver | driver@test.com | test123 |
| Staff | staff@test.com | test123 |

---

## 1️⃣ SUPERADMIN TESTING

### Dashboard
- [ ] Login successful
- [ ] Dashboard loads without errors
- [ ] Stats cards show (Total Students, Attendance, Fees, Complaints)
- [ ] Revenue chart displays
- [ ] Attendance chart displays
- [ ] Left sidebar shows ALL 20 modules
- [ ] "Your Modules" section highlighted
- [ ] Can click all modules
- [ ] Logout button works
- [ ] Session timeout warning appears after 55 min

### User Management (/users)
- [ ] Page loads
- [ ] Can view all users
- [ ] Can create new user
- [ ] Can edit user
- [ ] Can delete user
- [ ] Can search users
- [ ] Can filter by role
- [ ] All buttons work

### Student Admission (/students)
- [ ] Page loads
- [ ] Can view all students
- [ ] "+ Admit Student" button works
- [ ] Admission form opens
- [ ] Can fill all fields
- [ ] Can upload documents (TC, Birth Certificate)
- [ ] Can submit admission
- [ ] Student created successfully
- [ ] Can edit student
- [ ] Can delete/discharge student
- [ ] Search works
- [ ] Filter by class works
- [ ] Grid/List view toggle works

### Attendance (/attendance)
- [ ] Page loads
- [ ] Can select class
- [ ] Can select date
- [ ] Student list appears
- [ ] Can mark Present/Absent/Late/Half-day
- [ ] "Save Attendance" button works
- [ ] Can view existing attendance
- [ ] Daily report works
- [ ] Monthly report works
- [ ] Defaulters list works

### Fee Management (/fee)
- [ ] Page loads
- [ ] Can view fee structures
- [ ] "+ Fee Structure" button works
- [ ] Can create fee structure
- [ ] Can edit fee structure
- [ ] Can delete fee structure
- [ ] "+ Collect Fee" button works
- [ ] Can select student
- [ ] Can enter amount
- [ ] Can select payment mode
- [ ] "Collect & Print Receipt" works
- [ ] PDF receipt downloads
- [ ] Payment history shows
- [ ] Defaulters list works
- [ ] Collection report works

### Exams (/exams)
- [ ] Page loads
- [ ] Can view exam schedule
- [ ] "Schedule Exam" button works
- [ ] Can create exam
- [ ] Can edit exam
- [ ] Can delete exam
- [ ] "Enter Marks" button works
- [ ] Can enter marks for students
- [ ] Bulk marks entry works
- [ ] Grades auto-calculate
- [ ] Can view results
- [ ] Report card PDF downloads
- [ ] Exam analytics works

### Library (/library)
- [ ] Page loads
- [ ] Can view books
- [ ] "Scan ISBN" button works
- [ ] ISBN lookup works
- [ ] "Add Manual" button works
- [ ] Can add book manually
- [ ] "Issue Book" button works
- [ ] Can issue to student
- [ ] "Return Book" button works
- [ ] Can return book
- [ ] Fine calculates correctly
- [ ] Transaction history shows

### Canteen (/canteen)
- [ ] Page loads
- [ ] Can view items
- [ ] Can add new item
- [ ] Can edit item
- [ ] Can delete item
- [ ] Can make sale
- [ ] Wallet topup works
- [ ] RFID assignment works
- [ ] Sales report shows

### Hostel (/hostel)
- [ ] Page loads
- [ ] Can view room types
- [ ] Can create room type
- [ ] Can view rooms
- [ ] Can create room
- [ ] Can view fee structures
- [ ] Can create fee structure
- [ ] "Student Allotment" form works
- [ ] Can allot room
- [ ] "Vacate" button works
- [ ] Can vacate room
- [ ] Occupancy stats show

### Transport (/transport)
- [ ] Page loads
- [ ] Can view vehicles
- [ ] "Add Vehicle" button works
- [ ] Can create vehicle
- [ ] Can edit vehicle
- [ ] Can delete vehicle
- [ ] Can assign students to vehicle
- [ ] Bus routes page loads
- [ ] Can create route
- [ ] Can add stops
- [ ] Route statistics show

### HR & Leaves (/hr)
- [ ] Page loads
- [ ] Can view staff
- [ ] Can view leave applications
- [ ] Can approve leave
- [ ] Can reject leave
- [ ] Leave balance shows
- [ ] Staff attendance shows

### Payroll (/payroll)
- [ ] Page loads
- [ ] Can view payroll records
- [ ] "Generate Payroll" button works
- [ ] Can generate batch payroll
- [ ] Can view payslips
- [ ] Payslip PDF downloads
- [ ] Can mark as paid
- [ ] Salary structure shows

### Notices (/notices)
- [ ] Page loads
- [ ] Can view notices
- [ ] "Create Notice" button works
- [ ] Can create notice
- [ ] Can edit notice
- [ ] Can delete notice
- [ ] Category filter works

### Homework (/homework)
- [ ] Page loads
- [ ] Can view homework
- [ ] "Add Homework" button works
- [ ] Can assign homework
- [ ] Can edit homework
- [ ] Can delete homework
- [ ] Class filter works

### Routine (/routine)
- [ ] Page loads
- [ ] Can view routines
- [ ] "Create Routine" button works
- [ ] Can create routine
- [ ] Can edit routine
- [ ] Can delete routine
- [ ] Class/Teacher filter works

### Remarks (/remarks)
- [ ] Page loads
- [ ] Can view remarks
- [ ] "Add Remark" button works
- [ ] Can add remark
- [ ] Can reply to remark
- [ ] Student/Teacher filter works

### Complaints (/complaints)
- [ ] Page loads
- [ ] Can view complaints
- [ ] "File Complaint" button works
- [ ] Can file complaint
- [ ] Can update status
- [ ] Can resolve complaint
- [ ] Category filter works

### Import Data (/import-data)
- [ ] Page loads
- [ ] Can download templates
- [ ] Student template downloads
- [ ] Staff template downloads
- [ ] Fee template downloads
- [ ] Can upload file
- [ ] Preview shows
- [ ] Can import data
- [ ] Import results show

### Archive (/archive)
- [ ] Page loads
- [ ] Can switch tabs
- [ ] Can search archive
- [ ] Can filter by year
- [ ] Can view details
- [ ] "Export to Excel" button works
- [ ] CSV downloads

### Bus Routes (/bus-routes)
- [ ] Page loads
- [ ] Can view routes
- [ ] "Add Bus Route" button works
- [ ] Can create route
- [ ] Can add stops
- [ ] Can edit route
- [ ] Can delete route
- [ ] Route statistics show

---

## 2️⃣ TEACHER TESTING

### Should Access:
- [ ] Dashboard (with teacher stats)
- [ ] Attendance (mark for own classes)
- [ ] Routine (view own schedule)
- [ ] Exams (enter marks)
- [ ] Homework (assign)
- [ ] Students (view own classes)
- [ ] Remarks (add for students)
- [ ] Notices (view)
- [ ] Complaints (view/file)
- [ ] Library (view)
- [ ] HR & Leaves (apply for leave)

### Should NOT Access:
- [ ] Users page (should be blocked)
- [ ] Fee collection (should be blocked)
- [ ] Payroll (should be blocked)
- [ ] Import Data (should be blocked)
- [ ] Archive (should be blocked)

### Specific Tests:
- [ ] Dashboard shows: Classes taught, Homework count, Exams upcoming
- [ ] Can mark attendance for assigned classes
- [ ] Can enter exam marks
- [ ] Can assign homework
- [ ] Can apply for leave
- [ ] Leave balance updates

---

## 3️⃣ STUDENT TESTING

### Should Access:
- [ ] Dashboard (with own stats)
- [ ] Attendance (view own)
- [ ] Routine (view own)
- [ ] Exams (view schedule & results)
- [ ] Homework (view)
- [ ] Fee (view own, pay)
- [ ] Notices (view)
- [ ] Remarks (view/add)
- [ ] Complaints (file)
- [ ] Library (view)
- [ ] Canteen (use)

### Should NOT Access:
- [ ] Other students' data
- [ ] Mark attendance
- [ ] Enter marks
- [ ] Admin pages
- [ ] Users page
- [ ] Import Data
- [ ] Archive

### Specific Tests:
- [ ] Dashboard shows: Own attendance %, Fees paid, Library loans
- [ ] Can ONLY see own attendance
- [ ] Can ONLY see own marks
- [ ] Can ONLY see own fee status
- [ ] Cannot see other students
- [ ] Session times out after 60 min

---

## 4️⃣ PARENT TESTING

### Should Access:
- [ ] Dashboard (with children's stats)
- [ ] Attendance (view children's)
- [ ] Routine (view)
- [ ] Exams (view results)
- [ ] Homework (view)
- [ ] Fee (view/pay for children)
- [ ] Notices (view)
- [ ] Remarks (view/reply)
- [ ] Complaints (file)
- [ ] Transport (track)

### Should NOT Access:
- [ ] Other children's data
- [ ] Admin functions
- [ ] Modify data

### Specific Tests:
- [ ] Dashboard shows: Children count, Today's attendance
- [ ] Can see ALL own children's data
- [ ] Can pay fees for children
- [ ] Can view children's marks
- [ ] Cannot modify anything

---

## 5️⃣ ACCOUNTS TESTING

### Should Access:
- [ ] Dashboard (with financial stats)
- [ ] Fee (full access)
- [ ] Payroll (manage)
- [ ] Students (view for fees)
- [ ] Archive (financial records)
- [ ] Bus Routes (view for fee calculation)
- [ ] Library (view)
- [ ] Canteen (audit)

### Should NOT Access:
- [ ] User management
- [ ] Mark attendance
- [ ] Enter marks

### Specific Tests:
- [ ] Dashboard shows: Today's collection
- [ ] Can collect fees
- [ ] Can view fee reports
- [ ] Can manage payroll
- [ ] Can export to Tally
- [ ] Can access archive

---

## 6️⃣ HR TESTING

### Should Access:
- [ ] Dashboard (HR stats)
- [ ] Users (view staff only)
- [ ] HR/Leaves (approve/reject)
- [ ] Payroll (view)
- [ ] Staff Attendance (view)
- [ ] Notices (post)
- [ ] Complaints (manage)

### Should NOT Access:
- [ ] Student admission
- [ ] Fee collection
- [ ] Exam marks

### Specific Tests:
- [ ] Can view staff list
- [ ] Can approve leaves
- [ ] Can view payroll
- [ ] Leave balances update

---

## 7️⃣ CANTEEN TESTING

### Should Access:
- [ ] Dashboard (sales stats)
- [ ] Canteen (full POS)
- [ ] Canteen Items (manage)
- [ ] Wallet (topup)
- [ ] RFID (process)
- [ ] Sales Reports (view)
- [ ] Notices (view)

### Should NOT Access:
- [ ] Academic modules
- [ ] Fee collection
- [ ] Student data

### Specific Tests:
- [ ] Can make sales
- [ ] Can topup wallets
- [ ] Can process RFID payments
- [ ] Can view sales reports
- [ ] Cannot access other modules

---

## 8️⃣ CONDUCTOR TESTING

### Should Access:
- [ ] Dashboard (transport stats)
- [ ] Transport (own bus only)
- [ ] Transport Attendance (mark)
- [ ] Student List (own bus)
- [ ] Notices (view)

### Should NOT Access:
- [ ] Academic modules
- [ ] Other buses

### Specific Tests:
- [ ] Can see assigned bus
- [ ] Can mark transport attendance
- [ ] Can see bus students
- [ ] Cannot see other buses

---

## 9️⃣ DRIVER TESTING

### Should Access:
- [ ] Dashboard (minimal)
- [ ] Transport (view own route)
- [ ] Route Info (view)
- [ ] Notices (view)

### Should NOT Access:
- [ ] Mark attendance
- [ ] Modify anything

### Specific Tests:
- [ ] Can view route
- [ ] Can view schedule
- [ ] Cannot mark attendance
- [ ] Cannot modify anything

---

## 🔟 STAFF TESTING

### Should Access:
- [ ] Dashboard (general)
- [ ] Notices (view)
- [ ] Complaints (file)
- [ ] Library (use)
- [ ] Canteen (use)
- [ ] Transport (use)
- [ ] HR/Leaves (apply)

### Should NOT Access:
- [ ] Admin functions
- [ ] Student data
- [ ] Financial data

### Specific Tests:
- [ ] Can apply for leave
- [ ] Can file complaints
- [ ] Can use facilities
- [ ] Cannot access admin pages

---

## 📝 ISSUES FOUND

### Critical Issues (System doesn't work)
```
1. Module: ___________
   Issue: _____________
   Role: ______________
   
2. Module: ___________
   Issue: _____________
   Role: ______________
```

### High Priority (Feature broken)
```
1. Module: ___________
   Issue: _____________
   Role: ______________
```

### Medium Priority (Partially working)
```
1. Module: ___________
   Issue: _____________
   Role: ______________
```

---

## ✅ TEST SIGN-OFF

**Total Modules Tested:** ___/20  
**Total Buttons Tested:** ___ (estimate)  
**Total Features Tested:** ___ (estimate)

**Pass Rate:** ___%

**Status:** ✅ PASS / ❌ FAIL

**Tester Signature:** _______________  
**Date:** _______________

---

**Happy Testing! 🧪**
