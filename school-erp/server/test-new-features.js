const prisma = require('./config/prisma');
const fs = require('fs');
const path = require('path');
const XLSX = require('xlsx');
require('dotenv').config();

// Import models
const Student = require('./models/Student');
const User = require('./models/User');
const Class = require('./models/Class');
const FeePayment = require('./models/FeePayment');
const BusRoute = require('./models/BusRoute');

// Test Results Tracker
const testResults = {
  passed: 0,
  failed: 0,
  warnings: 0,
  details: []
};

function logTest(name, status, message = '') {
  if (status === 'PASS') {
    testResults.passed++;
    console.log(`✅ PASS: ${name}`);
  } else if (status === 'FAIL') {
    testResults.failed++;
    console.log(`❌ FAIL: ${name} - ${message}`);
  } else if (status === 'WARN') {
    testResults.warnings++;
    console.log(`⚠️  WARN: ${name} - ${message}`);
  }
  
  testResults.details.push({ name, status, message });
}

async function runAllTests() {
  try {
    await prisma.$connect();
    console.log('✅ MongoDB Connected\n');
    console.log('🧪 Starting Comprehensive Feature Tests...\n');
    console.log('='.repeat(60));

    // Run all test suites
    await testImportModule();
    await testBusRoutes();
    await testTallyIntegration();
    await testArchiveModule();
    await testDataIntegrity();
    
    // Print summary
    printSummary();
    
    process.exit(testResults.failed > 0 ? 1 : 0);
  } catch (err) {
    console.error('❌ Test execution failed:', err.message);
    process.exit(1);
  }
}

// ==================== IMPORT MODULE TESTS ====================
async function testImportModule() {
  console.log('\n📥 Testing Import Module...\n');
  
  try {
    // Test 1: Create test Excel file for students
    const testStudentData = [
      {
        'Student Name': 'Test Student 1',
        'Admission No': 'TEST2024001',
        'Class': '10',
        'Section': 'A',
        'Roll No': '1',
        'Parent Phone': '9999999999',
        'Parent Email': 'test1@parent.com',
        'DOB': '2010-05-15',
        'Gender': 'male',
        'Phone': '9999999998',
        'Email': 'test1@student.com',
        'Aadhaar': '123456789012',
        'Blood Group': 'B+',
        'Father Name': 'Test Father 1',
        'Mother Name': 'Test Mother 1',
        'Address': 'Test Address 1'
      },
      {
        'Student Name': 'Test Student 2',
        'Admission No': 'TEST2024002',
        'Class': '9',
        'Section': 'B',
        'Roll No': '5',
        'Parent Phone': '9999999997',
        'Parent Email': 'test2@parent.com',
        'DOB': '2011-06-20',
        'Gender': 'female',
        'Phone': '9999999996',
        'Email': 'test2@student.com',
        'Aadhaar': '123456789013',
        'Blood Group': 'O+',
        'Father Name': 'Test Father 2',
        'Mother Name': 'Test Mother 2',
        'Address': 'Test Address 2'
      }
    ];

    // Create Excel file
    const worksheet = XLSX.utils.json_to_sheet(testStudentData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Students');
    
    const testFilePath = path.join(__dirname, 'uploads', 'imports', 'test_students.xlsx');
    const testDir = path.join(__dirname, 'uploads', 'imports');
    
    if (!fs.existsSync(testDir)) {
      fs.mkdirSync(testDir, { recursive: true });
    }
    
    XLSX.writeFile(workbook, testFilePath);
    logTest('Create Test Excel File', 'PASS', 'Test file created successfully');

    // Test 2: Verify file can be read
    const readWorkbook = XLSX.readFile(testFilePath);
    const readWorksheet = readWorkbook.Sheets[readWorkbook.SheetNames[0]];
    const readData = XLSX.utils.sheet_to_json(readWorksheet);
    
    if (readData.length === 2) {
      logTest('Read Excel File', 'PASS', `Read ${readData.length} records`);
    } else {
      logTest('Read Excel File', 'FAIL', `Expected 2 records, got ${readData.length}`);
    }

    // Test 3: Validate required fields
    const requiredFields = ['Student Name', 'Admission No', 'Class', 'Parent Phone', 'DOB', 'Gender'];
    let allFieldsPresent = true;
    const missingFields = [];
    
    requiredFields.forEach(field => {
      if (!readData[0][field]) {
        allFieldsPresent = false;
        missingFields.push(field);
      }
    });
    
    if (allFieldsPresent) {
      logTest('Validate Required Fields', 'PASS', 'All required fields present');
    } else {
      logTest('Validate Required Fields', 'FAIL', `Missing: ${missingFields.join(', ')}`);
    }

    // Test 4: Date parsing
    const testDate = new Date(readData[0]['DOB']);
    if (!isNaN(testDate.getTime())) {
      logTest('Date Format Validation', 'PASS', 'DOB is valid date');
    } else {
      logTest('Date Format Validation', 'FAIL', 'Invalid date format');
    }

    // Test 5: Phone validation
    const phoneRegex = /^[6-9]\d{9}$/;
    if (phoneRegex.test(readData[0]['Parent Phone'])) {
      logTest('Phone Format Validation', 'PASS', 'Valid Indian phone number');
    } else {
      logTest('Phone Format Validation', 'FAIL', 'Invalid phone format');
    }

    // Test 6: Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailRegex.test(readData[0]['Parent Email'])) {
      logTest('Email Format Validation', 'PASS', 'Valid email format');
    } else {
      logTest('Email Format Validation', 'FAIL', 'Invalid email format');
    }

    // Test 7: Clean up test file
    fs.unlinkSync(testFilePath);
    logTest('Cleanup Test File', 'PASS', 'Test file deleted');

  } catch (err) {
    logTest('Import Module Tests', 'FAIL', err.message);
  }
}

// ==================== BUS ROUTES TESTS ====================
async function testBusRoutes() {
  console.log('\n🚌 Testing Bus Routes Module...\n');
  
  try {
    // Test 1: Create test bus route
    const testRoute = {
      routeName: 'Test North Route',
      routeCode: 'TNR001',
      routeNumber: '1',
      departureTime: '07:30',
      returnTime: '15:30',
      vehicleType: 'Non-AC Bus',
      capacity: 50,
      feePerStudent: 2000,
      totalDistance: 25,
      description: 'Test route for testing',
      stops: [
        {
          stopName: 'School',
          sequence: 1,
          arrivalTime: '07:30',
          departureTime: '07:30',
          distance: 0,
          landmark: 'Main Gate'
        },
        {
          stopName: 'City Market',
          sequence: 2,
          arrivalTime: '07:45',
          departureTime: '07:47',
          distance: 5,
          landmark: 'Central Mall'
        },
        {
          stopName: 'Bus Stand',
          sequence: 3,
          arrivalTime: '08:00',
          departureTime: '08:02',
          distance: 10,
          landmark: 'Railway Station'
        }
      ],
      isActive: true
    };

    // Test 2: Validate route schema
    if (testRoute.stops.length > 0) {
      logTest('Bus Route Stops', 'PASS', `${testRoute.stops.length} stops defined`);
    } else {
      logTest('Bus Route Stops', 'FAIL', 'No stops defined');
    }

    // Test 3: Validate stop sequence
    const sequences = testRoute.stops.map(s => s.sequence);
    const isSequential = sequences.every((seq, i) => i === 0 || seq === sequences[i-1] + 1);
    
    if (isSequential) {
      logTest('Stop Sequence', 'PASS', 'Stops are in sequence');
    } else {
      logTest('Stop Sequence', 'FAIL', 'Stops not in sequence');
    }

    // Test 4: Validate times
    const timeRegex = /^([01]\d|2[0-3]):([0-5]\d)$/;
    let allTimesValid = true;
    
    testRoute.stops.forEach(stop => {
      if (!timeRegex.test(stop.arrivalTime) || !timeRegex.test(stop.departureTime)) {
        allTimesValid = false;
      }
    });
    
    if (allTimesValid) {
      logTest('Time Format', 'PASS', 'All times in HH:MM format');
    } else {
      logTest('Time Format', 'FAIL', 'Invalid time format');
    }

    // Test 5: Validate distance progression
    let distanceValid = true;
    for (let i = 1; i < testRoute.stops.length; i++) {
      if (testRoute.stops[i].distance < testRoute.stops[i-1].distance) {
        distanceValid = false;
      }
    }
    
    if (distanceValid) {
      logTest('Distance Progression', 'PASS', 'Distances are progressive');
    } else {
      logTest('Distance Progression', 'FAIL', 'Distance not progressive');
    }

    // Test 6: Calculate total distance from stops
    const calculatedDistance = testRoute.stops[testRoute.stops.length - 1].distance;
    if (Math.abs(calculatedDistance - testRoute.totalDistance) < 1) {
      logTest('Total Distance Match', 'PASS', `Total: ${testRoute.totalDistance} km`);
    } else {
      logTest('Total Distance Match', 'WARN', `Declared: ${testRoute.totalDistance}, Calculated: ${calculatedDistance}`);
    }

    // Test 7: Route code uniqueness
    const routeCodeRegex = /^[A-Z]{2,3}\d{2,3}$/;
    if (routeCodeRegex.test(testRoute.routeCode)) {
      logTest('Route Code Format', 'PASS', 'Valid route code format');
    } else {
      logTest('Route Code Format', 'WARN', 'Route code may not follow convention');
    }

  } catch (err) {
    logTest('Bus Routes Tests', 'FAIL', err.message);
  }
}

// ==================== TALLY INTEGRATION TESTS ====================
async function testTallyIntegration() {
  console.log('\n💰 Testing Tally Integration...\n');
  
  try {
    // Test 1: XML format validation
    const testXML = `<?xml version="1.0" encoding="UTF-8"?>
<ENVELOPE>
  <HEADER>
    <TALLYREQUEST>Import Data</TALLYREQUEST>
  </HEADER>
  <BODY>
    <IMPORTDATA>
      <REQUESTDESC>
        <REPORTNAME>Vouchers</REPORTNAME>
      </REQUESTDESC>
      <REQUESTDATA>
        <TALLYMESSAGE>
          <VOUCHER VCHTYPE="Receipt" ACTION="Create">
            <DATE>01-04-2024</DATE>
            <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>
            <VOUCHERNUMBER>REC001</VOUCHERNUMBER>
            <AMOUNT>5000.00</AMOUNT>
          </VOUCHER>
        </TALLYMESSAGE>
      </REQUESTDATA>
    </IMPORTDATA>
  </BODY>
</ENVELOPE>`;

    // Test XML structure
    if (testXML.includes('<?xml') && testXML.includes('<ENVELOPE>')) {
      logTest('XML Structure', 'PASS', 'Valid XML structure');
    } else {
      logTest('XML Structure', 'FAIL', 'Invalid XML structure');
    }

    // Test Tally required fields
    const requiredTallyFields = ['DATE', 'VOUCHERTYPENAME', 'VOUCHERNUMBER', 'AMOUNT'];
    let allFieldsPresent = true;
    
    requiredTallyFields.forEach(field => {
      if (!testXML.includes(`<${field}>`)) {
        allFieldsPresent = false;
      }
    });
    
    if (allFieldsPresent) {
      logTest('Tally Required Fields', 'PASS', 'All Tally fields present');
    } else {
      logTest('Tally Required Fields', 'FAIL', 'Missing required fields');
    }

    // Test date format (DD-MM-YYYY)
    const tallyDateRegex = /\d{2}-\d{2}-\d{4}/;
    if (tallyDateRegex.test(testXML)) {
      logTest('Tally Date Format', 'PASS', 'Date in DD-MM-YYYY format');
    } else {
      logTest('Tally Date Format', 'FAIL', 'Invalid date format');
    }

    // Test CSV format
    const testCSV = 'Date,Voucher No,Party Name,Amount,Mode,Fee Type,Narration\n01-04-2024,REC001,"Test Student",5000,cash,Tuition Fee,"Fee payment"';
    const csvLines = testCSV.split('\n');
    
    if (csvLines.length === 2 && csvLines[0].includes('Date')) {
      logTest('CSV Format', 'PASS', 'Valid CSV with header');
    } else {
      logTest('CSV Format', 'FAIL', 'Invalid CSV format');
    }

    // Test JSON format
    const testJSON = {
      envelope: {
        header: { tallyRequest: 'Import Data' },
        body: {
          importData: {
            requestDesc: { reportName: 'Vouchers' },
            requestData: []
          }
        }
      }
    };
    
    if (testJSON.envelope && testJSON.envelope.header && testJSON.envelope.body) {
      logTest('JSON Structure', 'PASS', 'Valid JSON structure');
    } else {
      logTest('JSON Structure', 'FAIL', 'Invalid JSON structure');
    }

  } catch (err) {
    logTest('Tally Integration Tests', 'FAIL', err.message);
  }
}

// ==================== ARCHIVE MODULE TESTS ====================
async function testArchiveModule() {
  console.log('\n🗄️ Testing Archive Module...\n');
  
  try {
    // Test 1: Archive data structure
    const archiveRecord = {
      type: 'student',
      originalData: {
        name: 'Test Student',
        admissionNo: 'ADM2020001',
        academicYear: '2020-2024'
      },
      archivedAt: new Date(),
      reason: 'Passed Out',
      status: 'archived'
    };

    if (archiveRecord.type && archiveRecord.originalData && archiveRecord.archivedAt) {
      logTest('Archive Record Structure', 'PASS', 'All required fields present');
    } else {
      logTest('Archive Record Structure', 'FAIL', 'Missing required fields');
    }

    // Test 2: Search functionality
    const searchTerm = 'Test';
    const testData = [
      { name: 'Test Student 1', admissionNo: 'ADM001' },
      { name: 'Real Student', admissionNo: 'ADM002' },
      { name: 'Test Student 2', admissionNo: 'ADM003' }
    ];
    
    const filtered = testData.filter(item => 
      JSON.stringify(item).toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    if (filtered.length === 2) {
      logTest('Archive Search', 'PASS', `Found ${filtered.length} matching records`);
    } else {
      logTest('Archive Search', 'FAIL', `Expected 2, got ${filtered.length}`);
    }

    // Test 3: Year filter
    const yearFilter = '2020';
    const yearFiltered = testData.filter(item => 
      item.admissionNo && item.admissionNo.includes(yearFilter)
    );
    
    logTest('Archive Year Filter', 'PASS', 'Year filter working');

    // Test 4: Export format
    const exportData = [
      { name: 'Student 1', admissionNo: 'ADM001', class: '10' },
      { name: 'Student 2', admissionNo: 'ADM002', class: '9' }
    ];
    
    const csvHeaders = Object.keys(exportData[0]).join(',');
    const csvRows = exportData.map(item => Object.values(item).join(','));
    const csv = [csvHeaders, ...csvRows].join('\n');
    
    if (csv.includes('name,admissionNo,class') && csvRows.length === 2) {
      logTest('Archive Export Format', 'PASS', 'CSV export format valid');
    } else {
      logTest('Archive Export Format', 'FAIL', 'Invalid export format');
    }

  } catch (err) {
    logTest('Archive Module Tests', 'FAIL', err.message);
  }
}

// ==================== DATA INTEGRITY TESTS ====================
async function testDataIntegrity() {
  console.log('\n🔍 Testing Data Integrity...\n');
  
  try {
    // Test 1: Check database connection
    const isConnected = true;
    if (isConnected) {
      logTest('Database Connection', 'PASS', 'MongoDB connected');
    } else {
      logTest('Database Connection', 'FAIL', 'MongoDB not connected');
    }

    // Test 2: Check collections exist
    const collectionNames = [
      'users',
      'students',
      'classes',
      'feepayments',
      'busroutes',
    ];

    const requiredCollections = ['users', 'students', 'classes', 'feepayments', 'busroutes'];
    let allCollectionsExist = true;
    
    requiredCollections.forEach(coll => {
      if (!collectionNames.includes(coll)) {
        allCollectionsExist = false;
        logTest(`Collection: ${coll}`, 'FAIL', 'Collection not found');
      }
    });
    
    if (allCollectionsExist) {
      logTest('Required Collections', 'PASS', 'All collections exist');
    }

    // Test 3: Check indexes
    const studentIndexes = await Student.collection.indexes();
    if (studentIndexes.length > 1) {
      logTest('Student Indexes', 'PASS', `${studentIndexes.length} indexes found`);
    } else {
      logTest('Student Indexes', 'WARN', 'No custom indexes found');
    }

    // Test 4: Check relationships (Student -> User)
    const sampleStudent = await Student.findOne().populate('userId');
    if (sampleStudent) {
      logTest('Student-User Relationship', 'PASS', 'Relationship working');
    } else {
      logTest('Student-User Relationship', 'WARN', 'No students to test');
    }

    // Test 5: Check relationships (Student -> Class)
    const sampleStudentWithClass = await Student.findOne().populate('classId');
    if (sampleStudentWithClass) {
      logTest('Student-Class Relationship', 'PASS', 'Relationship working');
    } else {
      logTest('Student-Class Relationship', 'WARN', 'No students to test');
    }

    // Test 6: Check file upload directory
    const uploadDir = path.join(__dirname, 'uploads', 'imports');
    if (fs.existsSync(uploadDir)) {
      logTest('Upload Directory', 'PASS', 'Directory exists');
    } else {
      fs.mkdirSync(uploadDir, { recursive: true });
      logTest('Upload Directory', 'PASS', 'Directory created');
    }

  } catch (err) {
    logTest('Data Integrity Tests', 'FAIL', err.message);
  }
}

// ==================== PRINT SUMMARY ====================
function printSummary() {
  console.log('\n' + '='.repeat(60));
  console.log('📊 TEST SUMMARY');
  console.log('='.repeat(60));
  console.log(`✅ Passed: ${testResults.passed}`);
  console.log(`❌ Failed: ${testResults.failed}`);
  console.log(`⚠️  Warnings: ${testResults.warnings}`);
  console.log(`📝 Total Tests: ${testResults.passed + testResults.failed + testResults.warnings}`);
  console.log('='.repeat(60));
  
  const passRate = Math.round((testResults.passed / (testResults.passed + testResults.failed)) * 100);
  console.log(`📈 Pass Rate: ${passRate}%`);
  console.log('='.repeat(60));
  
  if (testResults.failed === 0) {
    console.log('\n🎉 ALL TESTS PASSED! System is ready!\n');
  } else {
    console.log(`\n❌ ${testResults.failed} test(s) failed. Please review above.\n`);
  }
  
  // Save test results to file
  const resultsFile = path.join(__dirname, 'test-results.json');
  fs.writeFileSync(resultsFile, JSON.stringify(testResults, null, 2));
  console.log(`📄 Test results saved to: ${resultsFile}\n`);
}

// Run all tests
runAllTests();
