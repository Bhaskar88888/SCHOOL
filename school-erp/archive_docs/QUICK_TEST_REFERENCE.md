# 🚀 Quick Test Reference Card

## Start Testing (3 Steps)

```bash
# 1. Start MongoDB (if not running)
mongod

# 2. Start Backend (in one terminal)
cd c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp\server
npm run dev

# 3. Run Tests (in another terminal)
cd c:\Users\Bhaskar Tiwari\Desktop\SCHOOL\school-erp
node test-modules.js
```

---

## Test Credentials (Default)

Edit in `test-modules.js`:
```javascript
TEST_EMAIL = 'testadmin@school.edu'
TEST_PASSWORD = 'test123'
```

---

## Quick API Tests (Using curl/Postman)

### 1. Create Bus
```bash
curl -X POST http://localhost:5000/api/transport \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "busNumber": "BUS-TEST-1",
    "numberPlate": "DL-TEST-1234",
    "route": "Test Route",
    "capacity": 40
  }'
```

### 2. Create Route
```bash
curl -X POST http://localhost:5000/api/transport/routes \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "routeName": "Test Route",
    "routeCode": "TEST-01",
    "routeNumber": "1",
    "departureTime": "07:00",
    "returnTime": "15:00",
    "vehicleType": "AC Bus",
    "capacity": 50,
    "feePerStudent": 1500,
    "stops": [
      {
        "stopName": "Stop 1",
        "sequence": 1,
        "arrivalTime": "07:15",
        "distance": 5
      }
    ]
  }'
```

### 3. Create Fee Structure
```bash
curl -X POST http://localhost:5000/api/fee/structure \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "classId": "CLASS_ID_HERE",
    "feeType": "Tuition Fee",
    "amount": 25000,
    "academicYear": "2024-25"
  }'
```

### 4. Collect Fee
```bash
curl -X POST http://localhost:5000/api/fee/collect \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "studentId": "STUDENT_ID_HERE",
    "amountPaid": 25000,
    "paymentMode": "online"
  }'
```

### 5. Create Salary Structure
```bash
curl -X POST http://localhost:5000/api/salary-setup \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "staffId": "STAFF_ID_HERE",
    "basicSalary": 20000,
    "hra": 5000,
    "da": 3000,
    "conveyance": 2000,
    "medicalAllowance": 1500,
    "specialAllowance": 2500,
    "pfDeduction": 2400,
    "taxDeduction": 1000,
    "effectiveFrom": "2024-01-01"
  }'
```

### 6. Generate Payroll
```bash
curl -X POST http://localhost:5000/api/payroll/generate-batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "monthNumber": 3,
    "year": 2024,
    "targetStaffId": "STAFF_ID_HERE"
  }'
```

---

## Frontend Testing URLs

```
Dashboard:     http://localhost:3000/dashboard
Transport:     http://localhost:3000/transport
Bus Routes:    http://localhost:3000/bus-routes
Fee:           http://localhost:3000/fee
Payroll:       http://localhost:3000/payroll
```

---

## Expected Results

### Transport Module
```
✓ Bus created: BUS-001 (DL-1AB-1234)
✓ 4 students assigned to BUS-001
✓ Attendance marked: Student boarded
✓ Retrieved 1 attendance record(s)
```

### Route Module
```
✓ Route created: North Delhi Route (NDR-01)
✓ Retrieved 3 route(s)
✓ Route Statistics:
  - Total Routes: 3
  - Total Stops: 8
  - Total Distance: 75 km
```

### Budget Module (Fee)
```
✓ Fee structure created: Tuition Fee - Rs. 25000
✓ Fee collected: Rs. 25000 from Test Student
✓ Payment history: 1 payment(s), Total paid: Rs. 25000
```

### Budget Module (Payroll)
```
✓ Salary structure created for Rajesh Kumar
✓ Payroll generated: Payroll generated successfully
✓ Total payroll records: 1
```

---

## Common Errors & Fixes

| Error | Solution |
|-------|----------|
| `Authentication failed` | Check TEST_EMAIL/TEST_PASSWORD, create admin user first |
| `Cannot connect to MongoDB` | Start MongoDB: `mongod` |
| `Port 5000 already in use` | Kill process or use different port |
| `ClassId required` | Create a class first via `/api/classes` |
| `Duplicate key error` | Use unique bus numbers/plates |
| `Staff not found` | Create driver/conductor users first |

---

## Module Status

| Module | Status | Frontend | Backend |
|--------|--------|----------|---------|
| Transport | ✅ Working | ✓ | ✓ |
| Routes | ✅ Working | ✓ | ✓ |
| Fee Management | ✅ Working | ✓ | ✓ |
| Payroll | ✅ Working | ✓ | ✓ |

---

## Files Created for Testing

```
school-erp/
├── test-modules.js           # Automated test script
├── TESTING_GUIDE.md          # Detailed testing guide
├── VERIFICATION_SUMMARY.md   # Module verification summary
└── QUICK_TEST_REFERENCE.md   # This file
```

---

**Need Help?** Check `TESTING_GUIDE.md` for detailed documentation.
