# Role Panel Implementation Plan

## Connected Panels

### Superadmin
- Full control over users, students, classes, attendance, homework, exams, fee, hostel, library, transport, notices, complaints, HR, payroll.

### Teacher
- Dashboard
- Attendance for assigned classes only
- Homework for assigned classes only
- Exams result entry for assigned classes only
- Library circulation
- Notices
- Complaints to parent or admin
- Scoped student view for assigned classes only

### Student
- Dashboard
- Own attendance
- Own exam results
- Own fee ledger
- Own library loans
- Own hostel allotment
- Own transport assignment
- Notices
- Complaints to admin

### Parent
- Dashboard
- Linked child attendance
- Linked child exam results
- Linked child fee ledger
- Linked child library loans
- Linked child hostel allotment
- Linked child transport assignment
- Notices
- Complaints to class teacher or admin

## Core Module Links

- `Notice`
  - Audience-based publishing
  - Optional class-level targeting
  - Optional student-level targeting

- `Complaint`
  - Teacher -> Parent
  - Teacher -> Admin
  - Parent -> Teacher
  - Parent -> Admin
  - Student -> Admin

- `Attendance`
  - Teacher marks only on assigned classes
  - Student and Parent view only

- `Exams`
  - Superadmin schedules
  - Teacher enters marks for allowed classes
  - Student and Parent view results only

- `Fee`
  - Accounts and Superadmin collect and define structure
  - Student and Parent view ledgers and receipts only

- `Library`
  - Staff-facing issue and return desk
  - Student and Parent see only linked transactions

- `Hostel`
  - Admin-facing room and fee control
  - Student and Parent see only linked allotments

- `Transport`
  - Admin manages fleet
  - Driver and Conductor mark transport attendance
  - Student and Parent view assigned vehicle only

## Required Data Relationships

- `User.role`
- `User.employeeId`
- `User.linkedStudentIds[]`
- `Student.userId`
- `Student.studentId`
- `Student.admissionNo`
- `Student.parentUserId`
- `Class.classTeacher`
- `Class.subjects[].teacherId`
- `Notice.audience[]`
- `Notice.relatedClassId`
- `Notice.relatedStudentId`
- `Complaint.userId`
- `Complaint.targetUserId`
- `Complaint.raisedByRole`
- `Complaint.assignedToRole`
- `HostelAllocation.studentId`
- `LibraryTransaction.studentId`
- `TransportVehicle.students[]`

## ID Generation Logic

- Student admission number: `ADM-YYYY-0001`
- Student ID: `STU-YYYY-0001`
- Staff employee ID: `EMP-YYYY-0001`

Generation uses atomic counters in the `Counter` collection so IDs stay unique under concurrent inserts.
