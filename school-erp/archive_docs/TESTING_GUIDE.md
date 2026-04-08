# School ERP Module Testing Guide

## Overview

This document explains how to test the **Transport**, **Route**, and **Budget** modules using mock data.

## Modules Tested

### 1. Transport Module
- **Files**: `server/routes/transport.js`, `server/models/TransportVehicle.js`, `server/models/TransportAttendance.js`
- **Features**:
  - Create/Update/Delete buses
  - Assign drivers and conductors to buses
  - Assign students to buses
  - Mark student attendance (boarded, dropped_off, absent)
  - Real-time attendance tracking with parent notifications

### 2. Route Module
- **Files**: `server/routes/busRoutes.js`, `server/models/BusRoute.js`
- **Features**:
  - Create bus routes with multiple stops
  - Assign vehicles, drivers, and conductors to routes
  - Track route distance and duration
  - Set fee per student for each route
  - View route statistics and summaries
  - Map route stops with timing and landmarks

### 3. Budget Module (Fee + Payroll)
- **Files**: 
  - `server/routes/fee.js`, `server/models/FeeStructure.js`, `server/models/FeePayment.js`
  - `server/routes/payroll.js`, `server/models/Payroll.js`, `server/models/SalaryStructure.js`
- **Features**:
  - Create fee structures per class
  - Collect fee payments with receipts
  - Generate payment reports
  - Track defaulters
  - Generate staff payroll with salary structures
  - Prorate salary based on attendance

## Quick Start

### Prerequisites

1. Ensure MongoDB is running
2. Start the backend server:
   ```bash
   cd server
   npm start
   ```
3. Have an admin account created

### Running the Test Script

```bash
cd school-erp
node test-modules.js
```

### Environment Variables

Create a `.env` file or modify the script to set:

```env
BASE_URL=http://localhost:5000/api
TEST_EMAIL=your-admin-email@school.edu
TEST_PASSWORD=your-admin-password
```

## Manual Testing with Mock Data

### 1. Transport Module Testing

#### Create a Bus
```javascript
POST /api/transport
Headers: Authorization: Bearer <token>
Body:
{
  "busNumber": "BUS-001",
  "numberPlate": "DL-1AB-1234",
  "route": "North Delhi Route",
  "capacity": 50,
  "driverId": "<driver-user-id>",
  "conductorId": "<conductor-user-id>"
}
```

#### Assign Students to Bus
```javascript
PUT /api/transport/:id/students
Body:
{
  "students": ["<student-id-1>", "<student-id-2>"]
}
```

#### Mark Attendance
```javascript
POST /api/transport/:id/attendance
Body:
{
  "studentId": "<student-id>",
  "status": "boarded"  // or "dropped_off" or "absent"
}
```

#### Get Attendance
```javascript
GET /api/transport/:id/attendance?date=2024-03-30
```

### 2. Route Module Testing

#### Create a Route
```javascript
POST /api/transport/routes
Body:
{
  "routeName": "North Delhi Route",
  "routeCode": "NDR-01",
  "routeNumber": "1",
  "vehicleId": "<bus-id>",
  "driverId": "<driver-id>",
  "conductorId": "<conductor-id>",
  "departureTime": "07:00",
  "returnTime": "15:30",
  "vehicleType": "AC Bus",
  "capacity": 50,
  "feePerStudent": 1500,
  "totalDistance": 25,
  "stops": [
    {
      "stopName": "Model Town",
      "sequence": 1,
      "arrivalTime": "07:15",
      "departureTime": "07:17",
      "distance": 5,
      "landmark": "Metro Station"
    },
    {
      "stopName": "Civil Lines",
      "sequence": 2,
      "arrivalTime": "07:30",
      "departureTime": "07:32",
      "distance": 8,
      "landmark": "Red Fort"
    }
  ]
}
```

#### Get All Routes
```javascript
GET /api/transport/routes
```

#### Get Route Statistics
```javascript
GET /api/transport/routes/stats/summary
```

Response:
```json
{
  "totalRoutes": 3,
  "activeRoutes": 3,
  "inactiveRoutes": 0,
  "totalStops": 8,
  "totalDistance": 75,
  "routesByType": [
    { "_id": "AC Bus", "count": 1 },
    { "_id": "Non-AC Bus", "count": 2 }
  ]
}
```

### 3. Budget Module Testing

#### Create Fee Structure
```javascript
POST /api/fee/structure
Body:
{
  "classId": "<class-id>",
  "feeType": "Tuition Fee",
  "amount": 25000,
  "academicYear": "2024-25",
  "term": "Annual",
  "dueDate": "2024-04-30"
}
```

#### Collect Fee Payment
```javascript
POST /api/fee/collect
Body:
{
  "studentId": "<student-id>",
  "feeStructureId": "<fee-structure-id>",
  "amountPaid": 25000,
  "paymentMode": "online",  // cash, online, cheque
  "paymentDate": "2024-03-30",
  "remarks": "Annual fee payment"
}
```

#### Get Collection Report
```javascript
GET /api/fee/collection-report?startDate=2024-03-01&endDate=2024-03-31
```

#### Create Salary Structure
```javascript
POST /api/salary-setup/structure
Body:
{
  "staffId": "<staff-id>",
  "basicSalary": 20000,
  "hra": 5000,
  "da": 3000,
  "conveyance": 2000,
  "medicalAllowance": 1500,
  "specialAllowance": 2500,
  "pfDeduction": 2400,
  "taxDeduction": 1000,
  "otherDeductions": 500,
  "effectiveFrom": "2024-01-01"
}
```

#### Generate Payroll
```javascript
POST /api/payroll/generate-batch
Body:
{
  "monthNumber": 3,
  "year": 2024,
  "targetStaffId": "<staff-id>"
}
```

#### Mark Payroll as Paid
```javascript
PUT /api/payroll/:payrollId/pay
```

## Test Data Examples

### Mock Bus Data
```json
{
  "busNumber": "BUS-001",
  "numberPlate": "DL-1AB-1234",
  "route": "North Delhi - Model Town to Civil Lines",
  "capacity": 50,
  "driver": { "name": "Rajesh Kumar", "phone": "9876543210" },
  "conductor": { "name": "Mohan Singh", "phone": "9876543211" }
}
```

### Mock Route Data
```json
{
  "routeName": "North Delhi Express",
  "routeCode": "NDR-01",
  "totalStops": 5,
  "totalDistance": "25 km",
  "totalDuration": "45 mins",
  "departureTime": "07:00 AM",
  "returnTime": "03:30 PM",
  "feePerStudent": "₹1,500/month"
}
```

### Mock Fee Structure
```json
{
  "class": "Class 10",
  "feeTypes": [
    { "type": "Tuition Fee", "amount": "₹25,000", "frequency": "Annual" },
    { "type": "Transport Fee", "amount": "₹1,500", "frequency": "Monthly" },
    { "type": "Library Fee", "amount": "₹500", "frequency": "Annual" },
    { "type": "Sports Fee", "amount": "₹1,000", "frequency": "Annual" }
  ]
}
```

### Mock Payroll Data
```json
{
  "staff": "Rajesh Kumar (Driver)",
  "month": "March 2024",
  "earnings": {
    "basicSalary": "₹20,000",
    "hra": "₹5,000",
    "da": "₹3,000",
    "conveyance": "₹2,000",
    "medical": "₹1,500",
    "special": "₹2,500",
    "totalEarnings": "₹34,000"
  },
  "deductions": {
    "pf": "₹2,400",
    "tax": "₹1,000",
    "other": "₹500",
    "totalDeductions": "₹3,900"
  },
  "netPay": "₹30,100"
}
```

## Verification Checklist

### Transport Module
- [ ] Can create buses with unique numbers and plates
- [ ] Can assign drivers and conductors
- [ ] Can assign students to buses
- [ ] Driver/Conductor can see only their assigned bus
- [ ] Can mark attendance (boarded, dropped, absent)
- [ ] Parents receive notifications on attendance change
- [ ] Attendance auto-refreshes every 30 seconds
- [ ] Status counts (boarded, dropped, absent, pending) are accurate

### Route Module
- [ ] Can create routes with multiple stops
- [ ] Each stop has sequence, timing, distance, landmark
- [ ] Can assign vehicle, driver, conductor to route
- [ ] Can update and delete routes
- [ ] Route statistics show correct totals
- [ ] Can filter routes by active status and vehicle type
- [ ] Fee per student is configurable

### Budget Module (Fee)
- [ ] Can create fee structures per class
- [ ] Can collect payments with receipt generation
- [ ] Payment history is accurate
- [ ] Can generate collection reports
- [ ] Can identify defaulters
- [ ] SMS notifications sent to parents on payment

### Budget Module (Payroll)
- [ ] Can create salary structures for staff
- [ ] Payroll auto-calculates earnings and deductions
- [ ] Payroll prorates based on attendance
- [ ] Can generate batch payroll for all staff
- [ ] Can mark payroll as paid
- [ ] Staff can view their own payslips

## Common Issues & Solutions

### Issue: "Cannot create bus - duplicate key"
**Solution**: Bus numbers and number plates must be unique. Use different values.

### Issue: "Attendance marking fails"
**Solution**: Ensure the student is assigned to the bus first using `/students` endpoint.

### Issue: "Route creation fails"
**Solution**: Make sure vehicleId, driverId, and conductorId reference valid users/vehicles.

### Issue: "Fee collection fails - classId required"
**Solution**: Create a class first using `/api/classes` endpoint, then use its ID.

### Issue: "Payroll shows zero amount"
**Solution**: Create a salary structure first using `/api/salary-setup/structure` before generating payroll.

## API Endpoints Summary

### Transport
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/transport` | Create bus |
| GET | `/api/transport` | Get all buses (role-filtered) |
| PUT | `/api/transport/:id` | Update bus |
| PUT | `/api/transport/:id/students` | Assign students |
| POST | `/api/transport/:id/attendance` | Mark attendance |
| GET | `/api/transport/:id/attendance` | Get attendance |

### Routes
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/transport/routes` | Create route |
| GET | `/api/transport/routes` | Get all routes |
| GET | `/api/transport/routes/:id` | Get single route |
| PUT | `/api/transport/routes/:id` | Update route |
| DELETE | `/api/transport/routes/:id` | Delete route |
| GET | `/api/transport/routes/stats/summary` | Get statistics |

### Fee
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/fee/structure` | Create fee structure |
| GET | `/api/fee/structures` | Get all structures |
| POST | `/api/fee/collect` | Collect payment |
| GET | `/api/fee/payments` | Get all payments |
| GET | `/api/fee/student/:id` | Get student payment history |
| GET | `/api/fee/collection-report` | Get collection report |

### Payroll
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/salary-setup/structure` | Create salary structure |
| POST | `/api/payroll/generate-batch` | Generate payroll |
| GET | `/api/payroll` | Get all payroll records |
| PUT | `/api/payroll/:id/pay` | Mark as paid |

## Next Steps

1. Run the automated test script: `node test-modules.js`
2. Verify all modules in the frontend UI
3. Test role-based access (driver, conductor, parent, student views)
4. Test notification system (SMS/push notifications)
5. Test PDF receipt generation
6. Test data export features
