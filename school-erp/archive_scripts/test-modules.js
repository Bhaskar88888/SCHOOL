/**
 * Mock Data Test Script for School ERP Modules
 * Tests: Transport, Bus Routes, and Budget-related modules (Fee & Payroll)
 * 
 * Run with: node test-modules.js
 */

const axios = require('axios');

const BASE_URL = process.env.BASE_URL || 'http://localhost:5000/api';
const TEST_EMAIL = 'testadmin@school.edu';
const TEST_PASSWORD = 'test123';

let authToken = '';
let testUser = null;
let testStudents = [];
let testBuses = [];
let testRoutes = [];
let testDrivers = [];
let testConductors = [];

// Color codes for console output
const colors = {
  reset: '\x1b[0m',
  green: '\x1b[32m',
  red: '\x1b[31m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m'
};

const log = {
  success: (msg) => console.log(`${colors.green}✓${colors.reset} ${msg}`),
  error: (msg) => console.log(`${colors.red}✗${colors.reset} ${msg}`),
  info: (msg) => console.log(`${colors.blue}ℹ${colors.reset} ${msg}`),
  section: (msg) => console.log(`\n${colors.cyan}${'='.repeat(60)}${colors.reset}\n${colors.yellow}${msg}${colors.reset}\n${colors.cyan}${'='.repeat(60)}${colors.reset}\n`)
};

// Helper function for API calls
const api = {
  post: async (url, data) => {
    try {
      const res = await axios.post(`${BASE_URL}${url}`, data, {
        headers: { Authorization: `Bearer ${authToken}`, 'Content-Type': 'application/json' }
      });
      return res.data;
    } catch (err) {
      throw err.response?.data || err.message;
    }
  },
  get: async (url) => {
    try {
      const res = await axios.get(`${BASE_URL}${url}`, {
        headers: { Authorization: `Bearer ${authToken}` }
      });
      return res.data;
    } catch (err) {
      throw err.response?.data || err.message;
    }
  },
  put: async (url, data) => {
    try {
      const res = await axios.put(`${BASE_URL}${url}`, data, {
        headers: { Authorization: `Bearer ${authToken}`, 'Content-Type': 'application/json' }
      });
      return res.data;
    } catch (err) {
      throw err.response?.data || err.message;
    }
  },
  delete: async (url) => {
    try {
      const res = await axios.delete(`${BASE_URL}${url}`, {
        headers: { Authorization: `Bearer ${authToken}` }
      });
      return res.data;
    } catch (err) {
      throw err.response?.data || err.message;
    }
  }
};

// ============================================================================
// AUTHENTICATION
// ============================================================================
async function authenticate() {
  log.section('AUTHENTICATION');
  try {
    const res = await axios.post(`${BASE_URL}/auth/login`, {
      email: TEST_EMAIL,
      password: TEST_PASSWORD
    });
    authToken = res.data.token;
    testUser = res.data.user;
    log.success(`Logged in as: ${testUser.name} (${testUser.role})`);
    return true;
  } catch (err) {
    log.error(`Login failed: ${err.msg || err}`);
    log.info('Create a test admin account first or check credentials');
    return false;
  }
}

// ============================================================================
// MOCK DATA GENERATORS
// ============================================================================

function generateMockDrivers() {
  return [
    { name: 'Rajesh Kumar', email: `driver1_${Date.now()}@test.com`, phone: '9876543210', role: 'driver', password: 'driver123' },
    { name: 'Suresh Yadav', email: `driver2_${Date.now()}@test.com`, phone: '9876543211', role: 'driver', password: 'driver123' }
  ];
}

function generateMockConductors() {
  return [
    { name: 'Mohan Singh', email: `conductor1_${Date.now()}@test.com`, phone: '9876543212', role: 'conductor', password: 'conductor123' },
    { name: 'Ramesh Gupta', email: `conductor2_${Date.now()}@test.com`, phone: '9876543213', role: 'conductor', password: 'conductor123' }
  ];
}

function generateMockBuses() {
  return [
    { busNumber: 'BUS-001', numberPlate: 'DL-1AB-1234', route: 'Route A - North Delhi', capacity: 50 },
    { busNumber: 'BUS-002', numberPlate: 'DL-2CD-5678', route: 'Route B - South Delhi', capacity: 45 },
    { busNumber: 'BUS-003', numberPlate: 'DL-3EF-9012', route: 'Route C - East Delhi', capacity: 40 }
  ];
}

function generateMockRoutes() {
  return [
    {
      routeName: 'North Delhi Route',
      routeCode: 'NDR-01',
      routeNumber: '1',
      departureTime: '07:00',
      returnTime: '15:30',
      vehicleType: 'AC Bus',
      capacity: 50,
      feePerStudent: 1500,
      totalDistance: 25,
      description: 'Covers North Delhi areas',
      stops: [
        { stopName: 'Model Town', sequence: 1, arrivalTime: '07:15', departureTime: '07:17', distance: 5, landmark: 'Metro Station' },
        { stopName: 'Civil Lines', sequence: 2, arrivalTime: '07:30', departureTime: '07:32', distance: 8, landmark: 'Red Fort' },
        { stopName: 'Kashmere Gate', sequence: 3, arrivalTime: '07:45', departureTime: '07:47', distance: 12, landmark: 'ISBT' }
      ]
    },
    {
      routeName: 'South Delhi Route',
      routeCode: 'SDR-02',
      routeNumber: '2',
      departureTime: '07:30',
      returnTime: '16:00',
      vehicleType: 'Non-AC Bus',
      capacity: 45,
      feePerStudent: 1200,
      totalDistance: 30,
      description: 'Covers South Delhi areas',
      stops: [
        { stopName: 'Saket', sequence: 1, arrivalTime: '07:45', departureTime: '07:47', distance: 7, landmark: 'Select Citywalk' },
        { stopName: 'Hauz Khas', sequence: 2, arrivalTime: '08:00', departureTime: '08:02', distance: 12, landmark: 'IIT' },
        { stopName: 'RK Puram', sequence: 3, arrivalTime: '08:15', departureTime: '08:17', distance: 18, landmark: 'Sector 1 Market' }
      ]
    },
    {
      routeName: 'East Delhi Route',
      routeCode: 'EDR-03',
      routeNumber: '3',
      departureTime: '06:45',
      returnTime: '15:15',
      vehicleType: 'Mini Bus',
      capacity: 35,
      feePerStudent: 1000,
      totalDistance: 20,
      description: 'Covers East Delhi areas',
      stops: [
        { stopName: 'Preet Vihar', sequence: 1, arrivalTime: '07:00', departureTime: '07:02', distance: 4, landmark: 'Community Center' },
        { stopName: 'Laxmi Nagar', sequence: 2, arrivalTime: '07:15', departureTime: '07:17', distance: 8, landmark: 'Metro Station' }
      ]
    }
  ];
}

function generateMockStudents(count = 5) {
  const students = [];
  for (let i = 0; i < count; i++) {
    students.push({
      name: `Test Student ${i + 1}`,
      admissionNo: `ADM${Date.now()}${i}`,
      dateOfBirth: '2010-05-15',
      gender: i % 2 === 0 ? 'Male' : 'Female',
      bloodGroup: 'O+',
      parentName: `Parent ${i + 1}`,
      parentPhone: `9999999${10 + i}`,
      parentEmail: `parent${i}@test.com`,
      structuredAddress: {
        line1: `${i + 1} Test Street`,
        city: i < 3 ? 'North Delhi' : 'South Delhi',
        state: 'Delhi',
        pincode: '110001',
        country: 'India'
      },
      transportRequired: i < 4 // First 4 students need transport
    });
  }
  return students;
}

// ============================================================================
// TEST MODULES
// ============================================================================

async function testDriverConductorCreation() {
  log.section('TEST 1: Creating Drivers & Conductors');

  const drivers = generateMockDrivers();
  const conductors = generateMockConductors();

  // Create drivers
  for (const driver of drivers) {
    try {
      const result = await api.post('/auth/register', driver);
      testDrivers.push(result.user || result);
      log.success(`Driver created: ${driver.name}`);
    } catch (err) {
      log.error(`Failed to create driver: ${err.msg || err}`);
    }
  }

  // Create conductors
  for (const conductor of conductors) {
    try {
      const result = await api.post('/auth/register', conductor);
      testConductors.push(result.user || result);
      log.success(`Conductor created: ${conductor.name}`);
    } catch (err) {
      log.error(`Failed to create conductor: ${err.msg || err}`);
    }
  }

  log.info(`Total: ${testDrivers.length} drivers, ${testConductors.length} conductors`);
}

async function testBusCreation() {
  log.section('TEST 2: Creating Buses (Transport Vehicles)');

  const buses = generateMockBuses();

  for (let i = 0; i < buses.length; i++) {
    const bus = buses[i];
    // Assign driver and conductor if available
    if (testDrivers[i]) bus.driverId = testDrivers[i]._id;
    if (testConductors[i]) bus.conductorId = testConductors[i]._id;

    try {
      const result = await api.post('/transport', bus);
      testBuses.push(result);
      log.success(`Bus created: ${bus.busNumber} (${bus.numberPlate})`);
    } catch (err) {
      log.error(`Failed to create bus: ${err.msg || err}`);
    }
  }

  log.info(`Total buses created: ${testBuses.length}`);
}

async function testRouteCreation() {
  log.section('TEST 3: Creating Bus Routes');

  const routes = generateMockRoutes();

  for (let i = 0; i < routes.length; i++) {
    const route = routes[i];
    // Assign vehicle if available
    if (testBuses[i]) route.vehicleId = testBuses[i]._id;
    // Assign driver and conductor
    if (testDrivers[i]) route.driverId = testDrivers[i]._id;
    if (testConductors[i]) route.conductorId = testConductors[i]._id;

    try {
      const result = await api.post('/transport/routes', route);
      testRoutes.push(result.route || result);
      log.success(`Route created: ${route.routeName} (${route.routeCode})`);
    } catch (err) {
      log.error(`Failed to create route: ${err.msg || err}`);
    }
  }

  log.info(`Total routes created: ${testRoutes.length}`);
}

async function testStudentCreation() {
  log.section('TEST 4: Creating Students');

  const students = generateMockStudents(6);

  for (const student of students) {
    try {
      const result = await api.post('/students', student);
      testStudents.push(result.student || result);
      log.success(`Student created: ${student.name} (${student.admissionNo})`);
    } catch (err) {
      log.error(`Failed to create student: ${err.msg || err}`);
    }
  }

  log.info(`Total students created: ${testStudents.length}`);
}

async function testStudentBusAssignment() {
  log.section('TEST 5: Assigning Students to Buses');

  if (testBuses.length === 0 || testStudents.length === 0) {
    log.error('No buses or students available for assignment');
    return;
  }

  // Assign first 4 students to first bus
  const studentIds = testStudents.slice(0, 4).map(s => s._id);

  try {
    const result = await api.put(`/transport/${testBuses[0]._id}/students`, {
      students: studentIds
    });
    log.success(`Assigned ${studentIds.length} students to ${testBuses[0].busNumber}`);
  } catch (err) {
    log.error(`Failed to assign students: ${err.msg || err}`);
  }
}

async function testTransportAttendance() {
  log.section('TEST 6: Testing Transport Attendance');

  if (testBuses.length === 0 || testStudents.length === 0) {
    log.error('No buses or students available');
    return;
  }

  const busId = testBuses[0]._id;
  const studentId = testStudents[0]._id;

  // Mark attendance
  try {
    const result = await api.post(`/transport/${busId}/attendance`, {
      studentId,
      status: 'boarded'
    });
    log.success(`Attendance marked: Student boarded bus ${testBuses[0].busNumber}`);
  } catch (err) {
    log.error(`Failed to mark attendance: ${err.msg || err}`);
  }

  // Get attendance
  try {
    const attendance = await api.get(`/transport/${busId}/attendance`);
    log.success(`Retrieved ${attendance.length} attendance record(s)`);
  } catch (err) {
    log.error(`Failed to get attendance: ${err.msg || err}`);
  }
}

async function testRouteRetrieval() {
  log.section('TEST 7: Testing Route Retrieval');

  try {
    const routes = await api.get('/transport/routes');
    log.success(`Retrieved ${routes.length} route(s)`);

    routes.forEach(route => {
      log.info(`  - ${route.routeName}: ${route.stops?.length || 0} stops, ${route.activeStudents || 0} students`);
    });
  } catch (err) {
    log.error(`Failed to get routes: ${err.msg || err}`);
  }
}

async function testFeeStructure() {
  log.section('TEST 8: Testing Fee Structure (Budget Module)');

  // Create fee structures for different classes
  const feeStructures = [
    { classId: null, feeType: 'Tuition Fee', amount: 25000, academicYear: '2024-25', term: 'Annual' },
    { classId: null, feeType: 'Transport Fee', amount: 1500, academicYear: '2024-25', term: 'Monthly' },
    { classId: null, feeType: 'Library Fee', amount: 500, academicYear: '2024-25', term: 'Annual' }
  ];

  for (const fee of feeStructures) {
    try {
      const result = await api.post('/fee/structure', fee);
      log.success(`Fee structure created: ${fee.feeType} - Rs. ${fee.amount}`);
    } catch (err) {
      log.error(`Failed to create fee structure: ${err.msg || err}`);
    }
  }

  // Get all fee structures
  try {
    const structures = await api.get('/fee/structures');
    log.info(`Total fee structures: ${structures.length}`);
  } catch (err) {
    log.error(`Failed to get fee structures: ${err.msg || err}`);
  }
}

async function testFeeCollection() {
  log.section('TEST 9: Testing Fee Collection (Budget Module)');

  if (testStudents.length === 0) {
    log.error('No students available');
    return;
  }

  const studentId = testStudents[0]._id;

  // Collect fee payment
  try {
    const result = await api.post('/fee/collect', {
      studentId,
      amountPaid: 25000,
      paymentMode: 'online',
      paymentDate: new Date().toISOString(),
      remarks: 'Test payment'
    });
    log.success(`Fee collected: Rs. ${result.payment?.amountPaid} from ${testStudents[0].name}`);
  } catch (err) {
    log.error(`Failed to collect fee: ${err.msg || err}`);
  }

  // Get payment history
  try {
    const payments = await api.get(`/fee/student/${studentId}`);
    log.info(`Payment history: ${payments.payments?.length || 0} payment(s), Total paid: Rs. ${payments.summary?.totalPaid || 0}`);
  } catch (err) {
    log.error(`Failed to get payment history: ${err.msg || err}`);
  }
}

async function testPayroll() {
  log.section('TEST 10: Testing Payroll (Budget Module)');

  // First, create salary structure for a driver
  if (testDrivers.length === 0) {
    log.error('No drivers available for payroll test');
    return;
  }

  const staffId = testDrivers[0]._id;

  // Create salary structure
  try {
    const salaryData = {
      staffId,
      basicSalary: 20000,
      hra: 5000,
      da: 3000,
      conveyance: 2000,
      medicalAllowance: 1500,
      specialAllowance: 2500,
      pfDeduction: 2400,
      taxDeduction: 1000,
      otherDeductions: 500,
      effectiveFrom: new Date().toISOString()
    };

    const result = await api.post('/salary-setup', salaryData);
    log.success(`Salary structure created for ${testDrivers[0].name}`);
  } catch (err) {
    log.error(`Failed to create salary structure: ${err.msg || err}`);
  }

  // Generate payroll for current month
  try {
    const now = new Date();
    const result = await api.post('/payroll/generate-batch', {
      monthNumber: now.getMonth() + 1,
      year: now.getFullYear(),
      targetStaffId: staffId
    });
    log.success(`Payroll generated: ${result.msg}`);
  } catch (err) {
    log.error(`Failed to generate payroll: ${err.msg || err}`);
  }

  // Get payroll records
  try {
    const payrolls = await api.get('/payroll');
    log.info(`Total payroll records: ${payrolls.length}`);
  } catch (err) {
    log.error(`Failed to get payroll records: ${err.msg || err}`);
  }
}

async function testRouteStatistics() {
  log.section('TEST 11: Testing Route Statistics');

  try {
    const stats = await api.get('/transport/routes/stats/summary');
    log.success('Route Statistics:');
    log.info(`  - Total Routes: ${stats.totalRoutes}`);
    log.info(`  - Active Routes: ${stats.activeRoutes}`);
    log.info(`  - Total Stops: ${stats.totalStops}`);
    log.info(`  - Total Distance: ${stats.totalDistance} km`);
  } catch (err) {
    log.error(`Failed to get statistics: ${err.msg || err}`);
  }
}

async function testCollectionReport() {
  log.section('TEST 12: Testing Fee Collection Report');

  try {
    const report = await api.get('/fee/collection-report');
    log.success('Fee Collection Report:');
    log.info(`  - Total Collected: Rs. ${report.summary?.totalCollected || 0}`);
    log.info(`  - Total Discount: Rs. ${report.summary?.totalDiscount || 0}`);
    log.info(`  - Transactions: ${report.summary?.totalTransactions || 0}`);
  } catch (err) {
    log.error(`Failed to get collection report: ${err.msg || err}`);
  }
}

// ============================================================================
// MAIN TEST RUNNER
// ============================================================================

async function runAllTests() {
  console.clear();
  log.section('SCHOOL ERP MODULE TESTING');
  log.info('Testing Transport, Route, and Budget Modules with Mock Data');
  log.info(`Base URL: ${BASE_URL}`);

  // Authenticate
  const authenticated = await authenticate();
  if (!authenticated) {
    log.error('Authentication failed. Exiting tests.');
    return;
  }

  // Run tests sequentially
  await testDriverConductorCreation();
  await testBusCreation();
  await testRouteCreation();
  await testStudentCreation();
  await testStudentBusAssignment();
  await testTransportAttendance();
  await testRouteRetrieval();
  await testFeeStructure();
  await testFeeCollection();
  await testPayroll();
  await testRouteStatistics();
  await testCollectionReport();

  // Summary
  log.section('TEST SUMMARY');
  log.success('All module tests completed!');
  log.info(`Created: ${testDrivers.length} drivers, ${testConductors.length} conductors`);
  log.info(`Created: ${testBuses.length} buses, ${testRoutes.length} routes`);
  log.info(`Created: ${testStudents.length} students`);
  log.info('\nModules Tested:');
  log.info('  ✓ Transport Module (Buses, Attendance)');
  log.info('  ✓ Route Module (Bus Routes, Stops, Statistics)');
  log.info('  ✓ Budget Module (Fee Structure, Collection, Payroll)');
}

// Run tests
runAllTests().catch(err => {
  log.error(`Test suite error: ${err.message || err}`);
  console.error(err);
});
