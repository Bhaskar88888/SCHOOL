# 📥 Bulk Import, Archive & Tally Integration Guide

**Date:** March 27, 2026  
**Status:** ✅ Fully Functional

---

## 🎯 Overview

The School ERP now includes three powerful features:

1. **Bulk Data Import** - Import previous school data from Excel/CSV
2. **Archive System** - View and export historical records
3. **Tally Integration** - Export bills/fees directly to Tally accounting software
4. **Bus Route Management** - Complete route planning with stops

---

## 📥 Part 1: Bulk Data Import

### What Can Be Imported?

| Data Type | Format | Template Available | Fields |
|-----------|--------|-------------------|---------|
| **Students** | Excel/CSV | ✅ Yes | Name, Admission No, Class, Section, DOB, Gender, Parent Info, etc. |
| **Staff** | Excel/CSV | ✅ Yes | Name, Email, Role, Department, Designation, Salary, etc. |
| **Fee Payments** | Excel/CSV | ✅ Yes | Admission No, Amount, Date, Receipt No, Fee Type, etc. |

### How to Import Data

#### Step 1: Navigate to Import Page
- Login as Super Admin
- Go to **Import Data** from sidebar (or `/import-data`)

#### Step 2: Select Data Type
Choose what you want to import:
- 🎓 Students
- 👥 Staff
- 💰 Fee Payments

#### Step 3: Download Template
- Click **"Download Template"** button
- Open the Excel file
- Fill in your data following the column headers
- Save the file

#### Step 4: Upload File
- Click **"Choose File"**
- Select your filled Excel/CSV file
- File uploads and shows preview (first 5 rows)

#### Step 5: Preview Data
- Review the preview
- Ensure data looks correct
- Check column mapping

#### Step 6: Import
- Set default password (for students/staff)
- Click **"Start Import"**
- Wait for processing
- View results (Success/Failed counts)

### Import Results

After import, you'll see:
- ✅ **Successful** - Records imported successfully
- ❌ **Failed** - Records that failed (with error reasons)
- 📊 **Total** - Total rows processed

**Example:**
```
✅ Successful: 95
❌ Failed: 5
📊 Total: 100
```

### Sample Data Format

#### Students Template
```excel
| Student Name | Admission No | Class | Section | Roll No | Parent Phone | DOB        | Gender |
|--------------|--------------|-------|---------|---------|--------------|------------|--------|
| Rajesh Kumar | ADM2024001   | 10    | A       | 1       | 9876543210   | 2010-05-15 | male   |
| Priya Sharma | ADM2024002   | 10    | A       | 2       | 9876543211   | 2010-06-20 | female |
```

#### Staff Template
```excel
| Name          | Email              | Role    | Phone      | Employee ID | Department | Designation     |
|---------------|--------------------|---------|------------|-------------|------------|-----------------|
| John Teacher  | john@school.com    | teacher | 9876543212 | EMP001      | Science    | Senior Teacher  |
| Sarah Accounts| sarah@school.com   | accounts| 9876543213 | EMP002      | Finance    | Accountant      |
```

#### Fee Payments Template
```excel
| Admission No | Amount | Payment Mode | Date       | Receipt No | Fee Type    | Academic Year |
|--------------|--------|--------------|------------|------------|-------------|---------------|
| ADM2024001   | 5000   | cash         | 2024-04-01 | REC001     | Tuition Fee | 2024-2025     |
| ADM2024002   | 5000   | upi          | 2024-04-02 | REC002     | Tuition Fee | 2024-2025     |
```

### Tips for Successful Import

1. ✅ **Use the template** - Don't create your own format
2. ✅ **Required fields** - Fill all mandatory columns
3. ✅ **Date format** - Use YYYY-MM-DD format
4. ✅ **Unique values** - Ensure Admission No, Email are unique
5. ✅ **Check preview** - Always review before importing
6. ✅ **Test first** - Import 5-10 records first as test

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| Missing required fields | Empty cells in required columns | Fill all required fields |
| Email already exists | Duplicate email in database | Use unique emails |
| Invalid date format | Wrong date format | Use YYYY-MM-DD |
| Class not found | Invalid class name | Use numeric class (1-12) |
| File too large | File > 10MB | Split into smaller files |

---

## 🗄️ Part 2: Archive System

### What is Archive?

The Archive module stores all historical data:
- Passed-out students (alumni)
- Former staff members
- Previous years' fee records
- Old exam results
- Historical attendance

### Accessing Archive

1. Go to **Archive** from sidebar (or `/archive`)
2. Select data type tab:
   - 🎓 Passed Out Students
   - 👥 Former Staff
   - 💰 Previous Fees
   - 📝 Past Exams
   - 📅 Old Attendance

### Features

#### Search & Filter
- **Search bar** - Search any field
- **Year filter** - Filter by academic year
- **Results count** - Shows total records found

#### View Details
- Click any record to view full details
- Modal popup with all fields
- Read-only view

#### Export Archive
- Click **"Export to Excel"** button
- Downloads CSV file
- Includes all filtered records
- Can export specific record from detail view

### Use Cases

**Scenario 1: TC Generation**
- Need to generate TC for passed-out student
- Go to Archive → Passed Out Students
- Search student by name/admission no
- View details and export data

**Scenario 2: Experience Certificate**
- Former staff needs experience certificate
- Go to Archive → Former Staff
- Find staff member
- View DOJ, designation, department

**Scenario 3: Fee Verification**
- Parent needs fee payment proof for old year
- Go to Archive → Previous Fees
- Search by admission no
- Export payment history

---

## 💰 Part 3: Tally Integration

### What is Tally Integration?

Directly export fee collections and payroll to Tally accounting software format. Eliminates manual data entry in Tally.

### Supported Formats

1. **XML** - Tally Import Format (Recommended)
2. **JSON** - For custom integrations
3. **CSV** - Universal spreadsheet format

### How to Export to Tally

#### Step 1: Navigate to Export
- Go to **Fee Page** or **Payroll Page**
- Look for **"Export to Tally"** button

#### Step 2: Select Date Range
- Choose start date
- Choose end date
- System shows transaction count

#### Step 3: Choose Format
- **XML** - Best for Tally (direct import)
- **CSV** - Good for Excel/other software
- **JSON** - For developers

#### Step 4: Download
- Click **"Export"**
- File downloads automatically
- Import into Tally

### Tally Import Steps

#### For Fee Collection:

1. **Open Tally**
2. Go to **Gateway of Tally**
3. Press **Alt+I** (Import)
4. Select **Vouchers**
5. Choose downloaded XML file
6. Tally shows preview
7. Press **Y** to import

#### For Payroll:

1. **Open Tally**
2. Go to **Payroll Vouchers**
3. Press **Alt+I** (Import)
4. Select payroll XML file
5. Review vouchers
6. Import

### Export Data Includes

#### Fee Export (XML)
```xml
<VOUCHER VCHTYPE="Receipt" ACTION="Create">
  <DATE>01-04-2024</DATE>
  <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>
  <VOUCHERNUMBER>REC001</VOUCHERNUMBER>
  <PARTYLEDGERNAME>Rajesh Kumar</PARTYLEDGERNAME>
  <AMOUNT>5000.00</AMOUNT>
  <NARRATION>Tuition Fee - Rajesh Kumar</NARRATION>
</VOUCHER>
```

#### Payroll Export (XML)
```xml
<VOUCHER VCHTYPE="Payment" ACTION="Create">
  <DATE>01-04-2024</DATE>
  <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>
  <VOUCHERNUMBER>SAL-2024-03</VOUCHERNUMBER>
  <PARTYLEDGERNAME>John Teacher</PARTYLEDGERNAME>
  <AMOUNT>-42000.00</AMOUNT>
  <NARRATION>Salary for 3/2024</NARRATION>
</VOUCHER>
```

### Benefits

✅ **Time Saving** - No manual entry in Tally  
✅ **Error Free** - Automated data transfer  
✅ **Audit Trail** - Complete transaction history  
✅ **Reconciliation** - Easy matching  
✅ **Compliance** - Proper accounting format  

---

## 🚌 Part 4: Bus Route Management

### What Can You Do?

- Create and manage bus routes
- Add multiple stops per route
- Set arrival/departure times
- Track distance and duration
- Assign vehicles and drivers
- View route on map (coming soon)

### Creating a Bus Route

#### Step 1: Navigate
- Go to **Bus Routes** (or `/bus-routes`)
- Click **"+ Add Bus Route"**

#### Step 2: Basic Info
- **Route Name** - e.g., "North Route"
- **Route Code** - e.g., "NR01"
- **Route Number** - e.g., "1"

#### Step 3: Vehicle & Schedule
- Select **Vehicle Type** (AC/Non-AC Bus, Van)
- Set **Departure Time** - First departure
- Set **Return Time** - Return to school

#### Step 4: Add Stops
Click **"+ Add Stop"** and fill:
- **Stop Name** - e.g., "Main Market"
- **Arrival Time** - When bus arrives
- **Departure Time** - When bus leaves
- **Distance** - From school (km)
- **Landmark** - Nearby landmark

#### Step 5: Save
- Review all stops
- Click **"Create Route"**
- Route appears in list

### Managing Routes

#### View Routes
- **List View** - Card layout with details
- **Map View** - Interactive map (coming soon)

#### Edit Route
- Click **"Edit Route"** button
- Modify any field
- Add/remove stops
- Save changes

#### Route Details
- Route name and code
- Vehicle assignment
- Driver/conductor info
- All stops with timings
- Total distance
- Active students

### Route Stops Management

**Adding Stops:**
1. Click "Add Stop"
2. Fill stop details
3. Stop auto-numbered by sequence
4. Add more stops as needed

**Editing Stops:**
1. Find stop in list
2. Modify any field
3. Changes save with route

**Removing Stops:**
1. Click "Remove" on stop
2. Stop deleted
3. Sequence auto-adjusted

### Example Route

```
Route: North Route (NR01)
Vehicle: MH01AB1234 (AC Bus)
Departure: 07:30 AM
Return: 04:00 PM
Distance: 25 km

Stops:
1. School (07:30 AM) - 0 km
2. Main Market (07:40 AM) - 2 km - Landmark: City Mall
3. Bus Stand (07:50 AM) - 5 km - Landmark: Railway Station
4. Hospital (08:00 AM) - 8 km - Landmark: City Hospital
5. Park Colony (08:15 AM) - 12 km - Landmark: Central Park
6. Final Stop (08:30 AM) - 15 km - Landmark: North Gate
```

---

## 📊 API Reference

### Import Endpoints

```javascript
// Upload file
POST /api/import/upload
Content-Type: multipart/form-data
Body: { file: Excel/CSV file }

// Import students
POST /api/import/students
Body: { filepath, defaultPassword, academicYear }

// Import staff
POST /api/import/staff
Body: { filepath, defaultPassword }

// Import fees
POST /api/import/fees
Body: { filepath }

// Download template
GET /api/import/templates/:type
// type: students, staff, fees
```

### Archive Endpoints

```javascript
// Get archive data
GET /api/archive/:type
// type: students, staff, fees, exams, attendance

// Export archive
GET /api/archive/export/:type?year=2024
```

### Tally Endpoints

```javascript
// Export fees to Tally
POST /api/tally/export-fees
Body: { startDate, endDate, format }
// format: xml, json, csv

// Export payroll to Tally
POST /api/tally/export-payroll
Body: { month, year, format }

// Get voucher list
GET /api/tally/vouchers?startDate=2024-04-01&endDate=2024-04-30
```

### Bus Route Endpoints

```javascript
// Create route
POST /api/transport/routes
Body: { routeName, routeCode, stops, ... }

// Get all routes
GET /api/transport/routes

// Get single route
GET /api/transport/routes/:id

// Update route
PUT /api/transport/routes/:id

// Delete route
DELETE /api/transport/routes/:id

// Add stops
POST /api/transport/routes/:id/stops

// Update stop
PUT /api/transport/routes/:id/stops/:stopIndex

// Delete stop
DELETE /api/transport/routes/:id/stops/:stopIndex

// Get route statistics
GET /api/transport/routes/stats/summary

// Get map data
GET /api/transport/routes/map/:id
```

---

## 🎯 Best Practices

### For Import
1. **Always backup** before bulk import
2. **Test with small data** first (5-10 records)
3. **Use templates** - Don't create custom format
4. **Clean data** - Remove duplicates before import
5. **Review results** - Check failed records

### For Archive
1. **Regular exports** - Export old data yearly
2. **Backup archives** - Keep multiple copies
3. **Document TC** - Note why student left
4. **Tag properly** - Use academic year tags

### For Tally Export
1. **Daily export** - Export fees daily to Tally
2. **Monthly payroll** - Export payroll monthly
3. **Reconcile** - Match Tally with ERP monthly
4. **Backup** - Keep exported files
5. **Audit** - Regular audit of both systems

### For Bus Routes
1. **Optimize routes** - Minimize distance
2. **Update timely** - Change stops as needed
3. **Track attendance** - Monitor route usage
4. **Parent communication** - Share route details
5. **Review fees** - Adjust based on distance

---

## 🐛 Troubleshooting

### Import Issues

**Problem:** File upload fails
- Check file size (< 10MB)
- Ensure .xlsx, .xls, or .csv format
- Try converting to CSV

**Problem:** All records fail
- Check template format
- Verify required fields
- Check date formats

**Problem:** Duplicate errors
- Clear existing data first
- Or use unique admission numbers

### Archive Issues

**Problem:** No data showing
- Check year filter
- Verify data is actually archived
- Try different tab

**Problem:** Export fails
- Check if any records exist
- Try smaller date range

### Tally Issues

**Problem:** XML not importing
- Check Tally version (should be Tally Prime or ERP 9)
- Verify XML format
- Check date format in XML

**Problem:** Amount mismatch
- Verify amounts in ERP
- Check export date range
- Review exported file

### Bus Route Issues

**Problem:** Can't add stops
- Ensure route is created first
- Check all required fields
- Verify stop sequence

**Problem:** Route not saving
- Check all required fields
- Ensure route code is unique
- Verify vehicle exists

---

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review error messages
3. Check browser console
4. Verify server logs

---

**Version:** 1.0  
**Last Updated:** March 27, 2026  
**Status:** Production Ready ✅
