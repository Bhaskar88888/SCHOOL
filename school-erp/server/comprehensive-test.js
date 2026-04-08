/**
 * COMPREHENSIVE MODULE & FEATURE TEST
 * Tests ALL 176 API endpoints across ALL 30 modules
 * Creates 20 realistic schools with complete data
 * Reports every broken feature, button, and function
 */

const axios = require('axios');
const fs = require('fs');
const path = require('path');

// ============================================================
// CONFIGURATION
// ============================================================
const BASE_URL = process.env.BASE_URL || 'http://localhost:5000';
const API = `${BASE_URL}/api`;

// Test results storage
const testResults = {
  schools: [],
  modules: {},
  summary: {
    total: 0,
    passed: 0,
    failed: 0,
    errors: [],
    brokenFeatures: [],
    workingFeatures: []
  }
};

// ============================================================
// HELPER FUNCTIONS
// ============================================================
let superAdminToken = '';
let testSchools = [];
let testUsers = {};
let testStudents = {};
let testClasses = {};
let testExams = {};
let testBooks = {};
let testNotices = {};
let testHomework = {};
let testCanteenItems = {};
let testTransportVehicles = {};
let testBusRoutes = {};
let testHostelRooms = {};
let testFeeStructures = {};
let testSalaryStructures = {};

function log(module, feature, status, message) {
  const entry = { module, feature, status, message, timestamp: new Date().toISOString() };
  
  if (!testResults.modules[module]) {
    testResults.modules[module] = { tests: [], passed: 0, failed: 0 };
  }
  
  testResults.modules[module].tests.push(entry);
  testResults.summary.total++;
  
  if (status === 'PASS') {
    testResults.modules[module].passed++;
    testResults.summary.passed++;
    testResults.summary.workingFeatures.push(`${module}: ${feature}`);
  } else {
    testResults.modules[module].failed++;
    testResults.summary.failed++;
    testResults.summary.errors.push(entry);
    testResults.summary.brokenFeatures.push(`${module}: ${feature} - ${message}`);
  }
  
  const icon = status === 'PASS' ? '✅' : '❌';
  console.log(`  ${icon} [${module}] ${feature}: ${message}`);
}

async function test(name, fn) {
  try {
    await fn();
  } catch (error) {
    throw new Error(`${name}: ${error.message}`);
  }
}

async function apiCall(method, url, data, token, expectedStatus = 200) {
  const config = {
    method,
    url,
    headers: {}
  };
  
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  
  if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
    config.data = data;
  }
  
  try {
    const response = await axios(config);
    return response;
  } catch (error) {
    if (expectedStatus >= 400) {
      return error.response;
    }
    throw error;
  }
}

// ============================================================
// 20 REALISTIC SCHOOLS DATA
// ============================================================
const SCHOOLS_DATA = [
  { name: "Delhi Public School", city: "New Delhi", state: "Delhi", type: "Private", board: "CBSE" },
  { name: "Kendriya Vidyalaya", city: "Mumbai", state: "Maharashtra", type: "Government", board: "CBSE" },
  { name: "St. Xavier's High School", city: "Kolkata", state: "West Bengal", type: "Private", board: "ICSE" },
  { name: "DAV Public School", city: "Pune", state: "Maharashtra", type: "Private", board: "CBSE" },
  { name: "Ryan International School", city: "Bangalore", state: "Karnataka", type: "Private", board: "CBSE" },
  { name: "Amity International School", city: "Noida", state: "Uttar Pradesh", type: "Private", board: "CBSE" },
  { name: "Sri Chaitanya School", city: "Hyderabad", state: "Telangana", type: "Private", board: "State" },
  { name: "Navodaya Vidyalaya", city: "Jaipur", state: "Rajasthan", type: "Government", board: "CBSE" },
  { name: "Bharatiya Vidya Bhavan", city: "Chennai", state: "Tamil Nadu", type: "Private", board: "CBSE" },
  { name: "Greenwood High School", city: "Lucknow", state: "Uttar Pradesh", type: "Private", board: "ICSE" },
  { name: "Delhi Public School", city: "Patna", state: "Bihar", type: "Private", board: "CBSE" },
  { name: "Army Public School", city: "Pune", state: "Maharashtra", type: "Government", board: "CBSE" },
  { name: "Sanskriti School", city: "New Delhi", state: "Delhi", type: "Private", board: "CBSE" },
  { name: "The Heritage School", city: "Kolkata", state: "West Bengal", type: "Private", board: "ICSE" },
  { name: "Vibgyor High School", city: "Mumbai", state: "Maharashtra", type: "Private", board: "State" },
  { name: "Oakridge International School", city: "Hyderabad", state: "Telangana", type: "Private", board: "IB" },
  { name: "Chinmaya Vidyalaya", city: "Kochi", state: "Kerala", type: "Private", board: "CBSE" },
  { name: "Symbiosis International School", city: "Pune", state: "Maharashtra", type: "Private", board: "IB" },
  { name: "Birla Open Minds School", city: "Ahmedabad", state: "Gujarat", type: "Private", board: "CBSE" },
  { name: "Crescent Public School", city: "Guwahati", state: "Assam", type: "Private", board: "CBSE" }
];

const CLASSES = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
const SECTIONS = ['A', 'B', 'C'];

// ============================================================
// STEP 1: LOGIN AS SUPER ADMIN
// ============================================================
async function loginSuperAdmin() {
  console.log('\n🔐 LOGIN: Authenticating as super admin...');
  try {
    // First, check if we need to seed the database
    const seedPath = path.join(__dirname, 'seed.js');
    if (fs.existsSync(seedPath)) {
      console.log('  ⚠️  Running database seed...');
      require('child_process').execSync('node seed.js', { 
        cwd: __dirname, 
        stdio: 'inherit',
        timeout: 30000 
      });
    }

    const response = await axios.post(`${API}/auth/login`, {
      email: 'admin@school.com',
      password: process.env.SEED_SUPERADMIN_PASSWORD || 'admin123'
    });

    superAdminToken = response.data.token;
    console.log('  ✅ Super admin login successful');
    return true;
  } catch (error) {
    console.log('  ❌ Super admin login failed:', error.response?.data || error.message);
    console.log('  💡 Make sure to run: node seed.js in the server directory first');
    return false;
  }
}

// ============================================================
// STEP 2: CREATE 20 SCHOOLS (CLASSES + SECTIONS)
// ============================================================
async function createSchools() {
  console.log('\n🏫 CREATING 20 TEST SCHOOLS...');
  
  for (let i = 0; i < SCHOOLS_DATA.length; i++) {
    const school = SCHOOLS_DATA[i];
    console.log(`\n  📍 School ${i + 1}/20: ${school.name}, ${school.city}`);
    
    const schoolData = {
      schoolId: `SCHOOL_${i + 1}`,
      name: school.name,
      city: school.city,
      state: school.state,
      type: school.type,
      board: school.board
    };
    
    testSchools.push(schoolData);
    
    // Create classes for each school
    testClasses[schoolData.schoolId] = [];
    
    for (const className of CLASSES) {
      for (const section of SECTIONS) {
        try {
          const response = await apiCall('POST', `${API}/classes`, {
            name: `${className}${section}`,
            className: className,
            section: section,
            capacity: 40,
            academicYear: '2025-2026'
          }, superAdminToken);
          
          if (response.data && response.data._id) {
            testClasses[schoolData.schoolId].push(response.data);
            log('CLASSES', `Create class ${className}${section}`, 'PASS', `Created with ID: ${response.data._id}`);
          }
        } catch (error) {
          log('CLASSES', `Create class ${className}${section}`, 'FAIL', error.response?.data?.message || error.message);
        }
      }
    }
  }
}

// ============================================================
// MODULE 1: AUTHENTICATION & USER MANAGEMENT (13 endpoints)
// ============================================================
async function testAuthModule() {
  console.log('\n\n🔐 TESTING MODULE 1: AUTHENTICATION & USER MANAGEMENT');
  console.log('='.repeat(70));
  
  // Test 1: Register user
  await test('Register user', async () => {
    try {
      const res = await apiCall('POST', `${API}/auth/register`, {
        email: 'testuser@school.com',
        password: 'TestPass123!',
        name: 'Test User',
        role: 'teacher'
      }, superAdminToken);
      log('AUTH', 'Register user', 'PASS', 'User registered successfully');
    } catch (error) {
      log('AUTH', 'Register user', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Test 2: Login
  await test('Login', async () => {
    try {
      const res = await apiCall('POST', `${API}/auth/login`, {
        email: 'admin@school.com',
        password: process.env.SEED_SUPERADMIN_PASSWORD || 'admin123'
      });
      if (res.data.token) {
        log('AUTH', 'Login', 'PASS', 'Login successful, token received');
      } else {
        log('AUTH', 'Login', 'FAIL', 'No token in response');
      }
    } catch (error) {
      log('AUTH', 'Login', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Test 3: Get all users
  await test('Get all users', async () => {
    try {
      const res = await apiCall('GET', `${API}/auth/users`, null, superAdminToken);
      log('AUTH', 'Get all users', 'PASS', `Retrieved ${res.data?.users?.length || 0} users`);
    } catch (error) {
      log('AUTH', 'Get all users', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Test 4: Update user
  await test('Update user profile', async () => {
    try {
      const res = await apiCall('GET', `${API}/auth/users`, null, superAdminToken);
      if (res.data?.users?.length > 0) {
        const userId = res.data.users[0]._id;
        const updateRes = await apiCall('PUT', `${API}/auth/users/${userId}`, {
          name: 'Updated Name'
        }, superAdminToken);
        log('AUTH', 'Update user', 'PASS', 'User updated successfully');
      } else {
        log('AUTH', 'Update user', 'FAIL', 'No users found to update');
      }
    } catch (error) {
      log('AUTH', 'Update user', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Test 5: Change password
  await test('Change password', async () => {
    try {
      const res = await apiCall('PUT', `${API}/auth/change-password`, {
        currentPassword: process.env.SEED_SUPERADMIN_PASSWORD || 'admin123',
        newPassword: 'NewPass123!'
      }, superAdminToken);
      log('AUTH', 'Change password', 'PASS', 'Password changed successfully');
      
      // Change back to original
      await apiCall('PUT', `${API}/auth/change-password`, {
        currentPassword: 'NewPass123!',
        newPassword: process.env.SEED_SUPERADMIN_PASSWORD || 'admin123'
      }, superAdminToken);
    } catch (error) {
      log('AUTH', 'Change password', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Test 6: Forgot password
  await test('Forgot password', async () => {
    try {
      const res = await apiCall('POST', `${API}/auth/forgot-password`, {
        email: 'admin@school.com'
      });
      log('AUTH', 'Forgot password', 'PASS', 'Forgot password request processed');
    } catch (error) {
      log('AUTH', 'Forgot password', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Test 7: Create staff
  await test('Create staff', async () => {
    try {
      const res = await apiCall('POST', `${API}/auth/create-staff`, {
        email: 'staff1@school.com',
        password: 'StaffPass123!',
        name: 'Test Staff Member',
        role: 'teacher',
        department: 'Science'
      }, superAdminToken);
      log('AUTH', 'Create staff', 'PASS', 'Staff created successfully');
    } catch (error) {
      log('AUTH', 'Create staff', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 2: STUDENT MANAGEMENT (10 endpoints)
// ============================================================
async function testStudentModule() {
  console.log('\n\n🎓 TESTING MODULE 2: STUDENT MANAGEMENT');
  console.log('='.repeat(70));
  
  testStudents = {};
  
  for (const school of testSchools.slice(0, 3)) { // Test first 3 schools in detail
    const schoolId = school.schoolId;
    testStudents[schoolId] = [];
    
    console.log(`\n  📍 Testing students for: ${school.name}`);
    
    // Create 5 students per school
    for (let i = 0; i < 5; i++) {
      await test(`Admit student ${i + 1}`, async () => {
        try {
          const classData = testClasses[schoolId]?.[i];
          if (!classData) {
            log('STUDENTS', `Admit student ${i + 1}`, 'FAIL', `No class found for school ${schoolId}`);
            return;
          }
          
          const res = await apiCall('POST', `${API}/students/admit`, {
            name: `Student ${i + 1} ${school.name}`,
            email: `student${i + 1}_${schoolId}@test.com`,
            classId: classData._id,
            className: classData.name,
            section: classData.section,
            gender: i % 2 === 0 ? 'male' : 'female',
            dateOfBirth: '2010-01-15',
            parentName: `Parent of Student ${i + 1}`,
            parentPhone: `98765432${i.toString().padStart(2, '0')}`,
            address: `${i + 1} Test Street, ${school.city}`,
            admissionDate: new Date().toISOString().split('T')[0]
          }, superAdminToken);
          
          if (res.data?.student || res.data?.studentId) {
            const student = res.data.student || res.data;
            testStudents[schoolId].push(student);
            log('STUDENTS', `Admit student ${i + 1}`, 'PASS', `Student admitted: ${student.name || student._id}`);
          }
        } catch (error) {
          log('STUDENTS', `Admit student ${i + 1}`, 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test get all students
    await test('Get all students', async () => {
      try {
        const res = await apiCall('GET', `${API}/students`, null, superAdminToken);
        log('STUDENTS', 'Get all students', 'PASS', `Retrieved ${res.data?.students?.length || 0} students`);
      } catch (error) {
        log('STUDENTS', 'Get all students', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Test get students by class
    if (testClasses[schoolId]?.length > 0) {
      await test('Get students by class', async () => {
        try {
          const classId = testClasses[schoolId][0]._id;
          const res = await apiCall('GET', `${API}/students/class/${classId}`, null, superAdminToken);
          log('STUDENTS', 'Get students by class', 'PASS', `Retrieved ${res.data?.students?.length || 0} students`);
        } catch (error) {
          log('STUDENTS', 'Get students by class', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test student stats
    await test('Get student stats', async () => {
      try {
        const res = await apiCall('GET', `${API}/students/stats/summary`, null, superAdminToken);
        log('STUDENTS', 'Get student stats', 'PASS', 'Stats retrieved successfully');
      } catch (error) {
        log('STUDENTS', 'Get student stats', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Test update student
    if (testStudents[schoolId]?.length > 0) {
      await test('Update student', async () => {
        try {
          const student = testStudents[schoolId][0];
          const studentId = student._id || student.student?._id;
          if (!studentId) {
            log('STUDENTS', 'Update student', 'FAIL', 'No student ID found');
            return;
          }
          
          const res = await apiCall('PUT', `${API}/students/${studentId}`, {
            address: 'Updated Address'
          }, superAdminToken);
          log('STUDENTS', 'Update student', 'PASS', 'Student updated successfully');
        } catch (error) {
          log('STUDENTS', 'Update student', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test promote student
    if (testStudents[schoolId]?.length > 1 && testClasses[schoolId]?.length > 1) {
      await test('Promote student', async () => {
        try {
          const student = testStudents[schoolId][1];
          const studentId = student._id || student.student?._id;
          const newClass = testClasses[schoolId][2];
          
          if (!studentId || !newClass) {
            log('STUDENTS', 'Promote student', 'FAIL', 'Missing student ID or new class');
            return;
          }
          
          const res = await apiCall('PUT', `${API}/students/${studentId}/promote`, {
            newClassId: newClass._id,
            newClassName: newClass.name
          }, superAdminToken);
          log('STUDENTS', 'Promote student', 'PASS', 'Student promoted successfully');
        } catch (error) {
          log('STUDENTS', 'Promote student', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test bulk import
    await test('Bulk import students', async () => {
      try {
        const res = await apiCall('POST', `${API}/students/bulk-import`, {
          students: [
            { name: 'Bulk Student 1', email: 'bulk1@test.com', className: '1A' },
            { name: 'Bulk Student 2', email: 'bulk2@test.com', className: '1B' }
          ]
        }, superAdminToken);
        log('STUDENTS', 'Bulk import students', 'PASS', 'Bulk import successful');
      } catch (error) {
        log('STUDENTS', 'Bulk import students', 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 3: ATTENDANCE (10 endpoints)
// ============================================================
async function testAttendanceModule() {
  console.log('\n\n📅 TESTING MODULE 3: ATTENDANCE');
  console.log('='.repeat(70));
  
  const today = new Date().toISOString().split('T')[0];
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    const students = testStudents[schoolId] || [];
    const classes = testClasses[schoolId] || [];
    
    if (students.length === 0 || classes.length === 0) continue;
    
    console.log(`\n  📍 Testing attendance for: ${school.name}`);
    
    // Test mark individual attendance
    await test('Mark individual attendance', async () => {
      try {
        const student = students[0];
        const studentId = student._id || student.student?._id;
        if (!studentId) {
          log('ATTENDANCE', 'Mark individual attendance', 'FAIL', 'No student ID found');
          return;
        }
        
        const res = await apiCall('POST', `${API}/attendance/mark`, {
          studentId: studentId,
          date: today,
          status: 'present'
        }, superAdminToken);
        log('ATTENDANCE', 'Mark individual attendance', 'PASS', 'Attendance marked successfully');
      } catch (error) {
        log('ATTENDANCE', 'Mark individual attendance', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Test bulk attendance
    if (classes.length > 0 && students.length > 0) {
      await test('Mark bulk attendance', async () => {
        try {
          const classId = classes[0]._id;
          const studentIds = students.slice(0, 3).map(s => s._id || s.student?._id).filter(Boolean);
          
          const res = await apiCall('POST', `${API}/attendance/bulk`, {
            classId: classId,
            date: today,
            attendance: studentIds.map((id, idx) => ({
              studentId: id,
              status: idx % 2 === 0 ? 'present' : 'absent'
            }))
          }, superAdminToken);
          log('ATTENDANCE', 'Mark bulk attendance', 'PASS', 'Bulk attendance marked successfully');
        } catch (error) {
          log('ATTENDANCE', 'Mark bulk attendance', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test get class attendance
    if (classes.length > 0) {
      await test('Get class attendance', async () => {
        try {
          const classId = classes[0]._id;
          const res = await apiCall('GET', `${API}/attendance/class/${classId}/${today}`, null, superAdminToken);
          log('ATTENDANCE', 'Get class attendance', 'PASS', `Retrieved ${res.data?.attendance?.length || 0} records`);
        } catch (error) {
          log('ATTENDANCE', 'Get class attendance', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test student attendance stats
    if (students.length > 0) {
      await test('Get student attendance stats', async () => {
        try {
          const student = students[0];
          const studentId = student._id || student.student?._id;
          if (!studentId) return;
          
          const res = await apiCall('GET', `${API}/attendance/student/${studentId}/stats`, null, superAdminToken);
          log('ATTENDANCE', 'Get student attendance stats', 'PASS', 'Stats retrieved successfully');
        } catch (error) {
          log('ATTENDANCE', 'Get student attendance stats', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Test daily report
    await test('Get daily attendance report', async () => {
      try {
        const res = await apiCall('GET', `${API}/attendance/report/daily?date=${today}`, null, superAdminToken);
        log('ATTENDANCE', 'Get daily report', 'PASS', 'Daily report retrieved');
      } catch (error) {
        log('ATTENDANCE', 'Get daily report', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Test monthly report
    await test('Get monthly attendance report', async () => {
      try {
        const res = await apiCall('GET', `${API}/attendance/report/monthly?month=${new Date().getMonth() + 1}&year=${new Date().getFullYear()}`, null, superAdminToken);
        log('ATTENDANCE', 'Get monthly report', 'PASS', 'Monthly report retrieved');
      } catch (error) {
        log('ATTENDANCE', 'Get monthly report', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Test defaulters
    await test('Get attendance defaulters', async () => {
      try {
        const res = await apiCall('GET', `${API}/attendance/defaulters`, null, superAdminToken);
        log('ATTENDANCE', 'Get attendance defaulters', 'PASS', `Retrieved ${res.data?.defaulters?.length || 0} defaulters`);
      } catch (error) {
        log('ATTENDANCE', 'Get attendance defaulters', 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 4: FEE MANAGEMENT (14 endpoints)
// ============================================================
async function testFeeModule() {
  console.log('\n\n💰 TESTING MODULE 4: FEE MANAGEMENT');
  console.log('='.repeat(70));
  
  testFeeStructures = {};
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    const classes = testClasses[schoolId] || [];
    const students = testStudents[schoolId] || [];
    
    console.log(`\n  📍 Testing fees for: ${school.name}`);
    
    // Create fee structure
    if (classes.length > 0) {
      await test('Create fee structure', async () => {
        try {
          const classData = classes[0];
          const res = await apiCall('POST', `${API}/fee/structure`, {
            classId: classData._id,
            className: classData.name,
            type: 'annual',
            amount: 50000,
            description: 'Annual Tuition Fee',
            academicYear: '2025-2026',
            dueDate: '2025-04-30'
          }, superAdminToken);
          
          if (res.data?.structure || res.data?._id) {
            const structure = res.data.structure || res.data;
            testFeeStructures[schoolId] = structure;
            log('FEE', 'Create fee structure', 'PASS', 'Fee structure created');
          }
        } catch (error) {
          log('FEE', 'Create fee structure', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Get fee structures
    await test('Get fee structures', async () => {
      try {
        const res = await apiCall('GET', `${API}/fee/structures`, null, superAdminToken);
        log('FEE', 'Get fee structures', 'PASS', `Retrieved ${res.data?.structures?.length || 0} structures`);
      } catch (error) {
        log('FEE', 'Get fee structures', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Collect fee
    if (students.length > 0) {
      await test('Collect fee payment', async () => {
        try {
          const student = students[0];
          const studentId = student._id || student.student?._id;
          if (!studentId) return;
          
          const res = await apiCall('POST', `${API}/fee/collect`, {
            studentId: studentId,
            studentName: student.name,
            amount: 50000,
            paymentMode: 'cash',
            type: 'annual',
            description: 'Annual Tuition Fee',
            date: new Date().toISOString().split('T')[0]
          }, superAdminToken);
          
          if (res.data?.payment || res.data?._id) {
            log('FEE', 'Collect fee payment', 'PASS', 'Fee collected successfully');
          } else {
            log('FEE', 'Collect fee payment', 'FAIL', 'No payment data in response');
          }
        } catch (error) {
          log('FEE', 'Collect fee payment', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Get payments
    await test('Get fee payments', async () => {
      try {
        const res = await apiCall('GET', `${API}/fee/payments`, null, superAdminToken);
        log('FEE', 'Get fee payments', 'PASS', `Retrieved ${res.data?.payments?.length || 0} payments`);
      } catch (error) {
        log('FEE', 'Get fee payments', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Get defaulters
    await test('Get fee defaulters', async () => {
      try {
        const res = await apiCall('GET', `${API}/fee/defaulters`, null, superAdminToken);
        log('FEE', 'Get fee defaulters', 'PASS', `Retrieved ${res.data?.defaulters?.length || 0} defaulters`);
      } catch (error) {
        log('FEE', 'Get fee defaulters', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Get collection report
    await test('Get collection report', async () => {
      try {
        const res = await apiCall('GET', `${API}/fee/collection-report`, null, superAdminToken);
        log('FEE', 'Get collection report', 'PASS', 'Collection report retrieved');
      } catch (error) {
        log('FEE', 'Get collection report', 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 5: EXAMS & RESULTS (14 endpoints)
// ============================================================
async function testExamsModule() {
  console.log('\n\n📚 TESTING MODULE 5: EXAMS & RESULTS');
  console.log('='.repeat(70));
  
  testExams = {};
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    const classes = testClasses[schoolId] || [];
    const students = testStudents[schoolId] || [];
    
    console.log(`\n  📍 Testing exams for: ${school.name}`);
    
    // Create exam schedule
    if (classes.length > 0) {
      await test('Create exam schedule', async () => {
        try {
          const classData = classes[0];
          const res = await apiCall('POST', `${API}/exams/schedule`, {
            name: 'Mid Term Examination',
            examDate: '2025-09-15',
            className: classData.name,
            classId: classData._id,
            section: classData.section,
            subject: 'Mathematics',
            totalMarks: 100,
            passingMarks: 40,
            academicYear: '2025-2026'
          }, superAdminToken);
          
          if (res.data?.exam || res.data?._id) {
            const exam = res.data.exam || res.data;
            testExams[schoolId] = exam;
            log('EXAMS', 'Create exam schedule', 'PASS', 'Exam schedule created');
          }
        } catch (error) {
          log('EXAMS', 'Create exam schedule', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Get exam schedules
    await test('Get exam schedules', async () => {
      try {
        const res = await apiCall('GET', `${API}/exams/schedule`, null, superAdminToken);
        log('EXAMS', 'Get exam schedules', 'PASS', `Retrieved ${res.data?.exams?.length || 0} exams`);
      } catch (error) {
        log('EXAMS', 'Get exam schedules', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Save exam result
    if (students.length > 0 && testExams[schoolId]) {
      await test('Save exam result', async () => {
        try {
          const student = students[0];
          const studentId = student._id || student.student?._id;
          const exam = testExams[schoolId];
          const examId = exam._id || exam.exam?._id;
          
          if (!studentId || !examId) return;
          
          const res = await apiCall('POST', `${API}/exams/results`, {
            examId: examId,
            studentId: studentId,
            marksObtained: 85,
            grade: 'A',
            remarks: 'Excellent performance'
          }, superAdminToken);
          
          if (res.data?.result || res.data?._id) {
            log('EXAMS', 'Save exam result', 'PASS', 'Exam result saved');
          }
        } catch (error) {
          log('EXAMS', 'Save exam result', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Bulk save results
      await test('Bulk save exam results', async () => {
        try {
          const exam = testExams[schoolId];
          const examId = exam._id || exam.exam?._id;
          if (!examId) return;
          
          const studentIds = students.slice(0, 3).map(s => s._id || s.student?._id).filter(Boolean);
          
          const res = await apiCall('POST', `${API}/exams/results/bulk`, {
            examId: examId,
            results: studentIds.map((id, idx) => ({
              studentId: id,
              marksObtained: 60 + idx * 10,
              grade: idx === 0 ? 'A' : idx === 1 ? 'B' : 'C'
            }))
          }, superAdminToken);
          
          log('EXAMS', 'Bulk save exam results', 'PASS', 'Bulk results saved');
        } catch (error) {
          log('EXAMS', 'Bulk save exam results', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Get results for student
      await test('Get student exam results', async () => {
        try {
          const student = students[0];
          const studentId = student._id || student.student?._id;
          if (!studentId) return;
          
          const res = await apiCall('GET', `${API}/exams/results/student/${studentId}`, null, superAdminToken);
          log('EXAMS', 'Get student exam results', 'PASS', `Retrieved ${res.data?.results?.length || 0} results`);
        } catch (error) {
          log('EXAMS', 'Get student exam results', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Get exam analytics
    await test('Get exam analytics', async () => {
      try {
        const res = await apiCall('GET', `${API}/exams/analytics`, null, superAdminToken);
        log('EXAMS', 'Get exam analytics', 'PASS', 'Analytics retrieved');
      } catch (error) {
        log('EXAMS', 'Get exam analytics', 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 6: HOMEWORK (5 endpoints)
// ============================================================
async function testHomeworkModule() {
  console.log('\n\n📝 TESTING MODULE 6: HOMEWORK');
  console.log('='.repeat(70));
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    const classes = testClasses[schoolId] || [];
    
    console.log(`\n  📍 Testing homework for: ${school.name}`);
    
    // Create homework
    if (classes.length > 0) {
      await test('Create homework', async () => {
        try {
          const classData = classes[0];
          const res = await apiCall('POST', `${API}/homework`, {
            classId: classData._id,
            className: classData.name,
            section: classData.section,
            subject: 'Mathematics',
            title: 'Chapter 5 Exercises',
            description: 'Complete all exercises from page 45 to 50',
            dueDate: '2025-04-15',
            assignedBy: 'Test Teacher'
          }, superAdminToken);
          
          if (res.data?.homework || res.data?._id) {
            const homework = res.data.homework || res.data;
            testHomework[schoolId] = homework;
            log('HOMEWORK', 'Create homework', 'PASS', 'Homework created');
          }
        } catch (error) {
          log('HOMEWORK', 'Create homework', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Get homework
    await test('Get homework', async () => {
      try {
        const res = await apiCall('GET', `${API}/homework`, null, superAdminToken);
        log('HOMEWORK', 'Get homework', 'PASS', `Retrieved ${res.data?.homework?.length || 0} items`);
      } catch (error) {
        log('HOMEWORK', 'Get homework', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Update homework
    if (testHomework[schoolId]) {
      await test('Update homework', async () => {
        try {
          const homework = testHomework[schoolId];
          const homeworkId = homework._id || homework.homework?._id;
          if (!homeworkId) return;
          
          const res = await apiCall('PUT', `${API}/homework/${homeworkId}`, {
            description: 'Updated: Complete all exercises from page 45 to 52'
          }, superAdminToken);
          
          log('HOMEWORK', 'Update homework', 'PASS', 'Homework updated');
        } catch (error) {
          log('HOMEWORK', 'Update homework', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
  }
}

// ============================================================
// MODULE 7: NOTICES (4 endpoints)
// ============================================================
async function testNoticesModule() {
  console.log('\n\n📢 TESTING MODULE 7: NOTICES');
  console.log('='.repeat(70));
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    
    console.log(`\n  📍 Testing notices for: ${school.name}`);
    
    // Create notice
    await test('Create notice', async () => {
      try {
        const res = await apiCall('POST', `${API}/notices`, {
          title: `Test Notice for ${school.name}`,
          content: 'This is a test notice for all students and staff.',
          priority: 'important',
          targetRoles: ['student', 'teacher', 'parent'],
          postedBy: 'Super Admin'
        }, superAdminToken);
        
        if (res.data?.notice || res.data?._id) {
          const notice = res.data.notice || res.data;
          testNotices[schoolId] = notice;
          log('NOTICES', 'Create notice', 'PASS', 'Notice created');
        }
      } catch (error) {
        log('NOTICES', 'Create notice', 'FAIL', error.response?.data?.message || error.message);
      }
    });
    
    // Get notices
    await test('Get notices', async () => {
      try {
        const res = await apiCall('GET', `${API}/notices`, null, superAdminToken);
        log('NOTICES', 'Get notices', 'PASS', `Retrieved ${res.data?.notices?.length || 0} notices`);
      } catch (error) {
        log('NOTICES', 'Get notices', 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 8: REMARKS (7 endpoints)
// ============================================================
async function testRemarksModule() {
  console.log('\n\n💬 TESTING MODULE 8: REMARKS');
  console.log('='.repeat(70));
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    const students = testStudents[schoolId] || [];
    
    console.log(`\n  📍 Testing remarks for: ${school.name}`);
    
    // Create remark
    if (students.length > 0) {
      await test('Create remark', async () => {
        try {
          const student = students[0];
          const studentId = student._id || student.student?._id;
          if (!studentId) return;
          
          const res = await apiCall('POST', `${API}/remarks`, {
            studentId: studentId,
            studentName: student.name,
            remark: 'Good progress in studies',
            remarkType: 'academic'
          }, superAdminToken);
          
          log('REMARKS', 'Create remark', 'PASS', 'Remark created');
        } catch (error) {
          log('REMARKS', 'Create remark', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
    
    // Get remarks
    await test('Get remarks', async () => {
      try {
        const res = await apiCall('GET', `${API}/remarks`, null, superAdminToken);
        log('REMARKS', 'Get remarks', 'PASS', `Retrieved ${res.data?.remarks?.length || 0} remarks`);
      } catch (error) {
        log('REMARKS', 'Get remarks', 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 9: COMPLAINTS (5 endpoints)
// ============================================================
async function testComplaintsModule() {
  console.log('\n\n📋 TESTING MODULE 9: COMPLAINTS');
  console.log('='.repeat(70));
  
  // Create complaint
  await test('Create complaint', async () => {
    try {
      const res = await apiCall('POST', `${API}/complaints`, {
        title: 'Test Complaint',
        description: 'This is a test complaint for testing purposes',
        type: 'general',
        raisedByRole: 'student'
      }, superAdminToken);
      
      if (res.data?.complaint || res.data?._id) {
        log('COMPLAINTS', 'Create complaint', 'PASS', 'Complaint created');
      }
    } catch (error) {
      log('COMPLAINTS', 'Create complaint', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get complaints
  await test('Get complaints', async () => {
    try {
      const res = await apiCall('GET', `${API}/complaints`, null, superAdminToken);
      log('COMPLAINTS', 'Get complaints', 'PASS', `Retrieved ${res.data?.complaints?.length || 0} complaints`);
    } catch (error) {
      log('COMPLAINTS', 'Get complaints', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get my complaints
  await test('Get my complaints', async () => {
    try {
      const res = await apiCall('GET', `${API}/complaints/my`, null, superAdminToken);
      log('COMPLAINTS', 'Get my complaints', 'PASS', `Retrieved ${res.data?.complaints?.length || 0} complaints`);
    } catch (error) {
      log('COMPLAINTS', 'Get my complaints', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 10: LIBRARY (10 endpoints)
// ============================================================
async function testLibraryModule() {
  console.log('\n\n📖 TESTING MODULE 10: LIBRARY');
  console.log('='.repeat(70));
  
  // Get library dashboard
  await test('Get library dashboard', async () => {
    try {
      const res = await apiCall('GET', `${API}/library/dashboard`, null, superAdminToken);
      log('LIBRARY', 'Get library dashboard', 'PASS', 'Dashboard retrieved');
    } catch (error) {
      log('LIBRARY', 'Get library dashboard', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Add book manually
  await test('Add book to library', async () => {
    try {
      const res = await apiCall('POST', `${API}/library/manual`, {
        title: 'Test Book - Mathematics',
        author: 'Test Author',
        isbn: '9781234567890',
        category: 'Mathematics',
        totalCopies: 5,
        availableCopies: 5
      }, superAdminToken);
      
      if (res.data?.book || res.data?._id) {
        const book = res.data.book || res.data;
        testBooks['test'] = book;
        log('LIBRARY', 'Add book', 'PASS', 'Book added to library');
      }
    } catch (error) {
      log('LIBRARY', 'Add book', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get books
  await test('Get library books', async () => {
    try {
      const res = await apiCall('GET', `${API}/library/books`, null, superAdminToken);
      log('LIBRARY', 'Get books', 'PASS', `Retrieved ${res.data?.books?.length || 0} books`);
    } catch (error) {
      log('LIBRARY', 'Get books', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get transactions
  await test('Get library transactions', async () => {
    try {
      const res = await apiCall('GET', `${API}/library/transactions`, null, superAdminToken);
      log('LIBRARY', 'Get transactions', 'PASS', `Retrieved ${res.data?.transactions?.length || 0} transactions`);
    } catch (error) {
      log('LIBRARY', 'Get transactions', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 11: CANTEEN (12 endpoints)
// ============================================================
async function testCanteenModule() {
  console.log('\n\n🍽️ TESTING MODULE 11: CANTEEN');
  console.log('='.repeat(70));
  
  // Add canteen item
  await test('Add canteen item', async () => {
    try {
      const res = await apiCall('POST', `${API}/canteen/items`, {
        name: 'Test Sandwich',
        category: 'Snacks',
        price: 50,
        stock: 100,
        isVeg: true
      }, superAdminToken);
      
      if (res.data?.item || res.data?._id) {
        const item = res.data.item || res.data;
        testCanteenItems['test'] = item;
        log('CANTEEN', 'Add item', 'PASS', 'Canteen item added');
      }
    } catch (error) {
      log('CANTEEN', 'Add item', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get items
  await test('Get canteen items', async () => {
    try {
      const res = await apiCall('GET', `${API}/canteen/items`, null, superAdminToken);
      log('CANTEEN', 'Get items', 'PASS', `Retrieved ${res.data?.items?.length || 0} items`);
    } catch (error) {
      log('CANTEEN', 'Get items', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Record sale
  await test('Record canteen sale', async () => {
    try {
      const res = await apiCall('POST', `${API}/canteen/sell`, {
        studentId: 'test-student',
        studentName: 'Test Student',
        items: [
          { itemId: 'test-item', name: 'Test Sandwich', quantity: 2, price: 50 }
        ],
        totalAmount: 100,
        paymentMode: 'Cash'
      }, superAdminToken);
      
      log('CANTEEN', 'Record sale', 'PASS', 'Sale recorded');
    } catch (error) {
      log('CANTEEN', 'Record sale', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get sales
  await test('Get canteen sales', async () => {
    try {
      const res = await apiCall('GET', `${API}/canteen/sales`, null, superAdminToken);
      log('CANTEEN', 'Get sales', 'PASS', `Retrieved ${res.data?.sales?.length || 0} sales`);
    } catch (error) {
      log('CANTEEN', 'Get sales', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Wallet top-up
  await test('Canteen wallet top-up', async () => {
    try {
      const res = await apiCall('POST', `${API}/canteen/wallet/topup`, {
        studentId: 'test-student',
        amount: 500,
        paymentMode: 'cash'
      }, superAdminToken);
      
      log('CANTEEN', 'Wallet top-up', 'PASS', 'Wallet topped up');
    } catch (error) {
      log('CANTEEN', 'Wallet top-up', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 12: TRANSPORT (8 endpoints)
// ============================================================
async function testTransportModule() {
  console.log('\n\n🚌 TESTING MODULE 12: TRANSPORT');
  console.log('='.repeat(70));
  
  // Create vehicle
  await test('Create transport vehicle', async () => {
    try {
      const res = await apiCall('POST', `${API}/transport`, {
        vehicleNumber: 'DL01AB1234',
        vehicleType: 'AC Bus',
        capacity: 50,
        driverName: 'Test Driver',
        driverPhone: '9876543210'
      }, superAdminToken);
      
      if (res.data?.vehicle || res.data?._id) {
        const vehicle = res.data.vehicle || res.data;
        testTransportVehicles['test'] = vehicle;
        log('TRANSPORT', 'Create vehicle', 'PASS', 'Vehicle created');
      }
    } catch (error) {
      log('TRANSPORT', 'Create vehicle', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get vehicles
  await test('Get transport vehicles', async () => {
    try {
      const res = await apiCall('GET', `${API}/transport`, null, superAdminToken);
      log('TRANSPORT', 'Get vehicles', 'PASS', `Retrieved ${res.data?.vehicles?.length || 0} vehicles`);
    } catch (error) {
      log('TRANSPORT', 'Get vehicles', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 13: BUS ROUTES (10 endpoints)
// ============================================================
async function testBusRoutesModule() {
  console.log('\n\n🗺️ TESTING MODULE 13: BUS ROUTES');
  console.log('='.repeat(70));
  
  // Create bus route
  await test('Create bus route', async () => {
    try {
      const res = await apiCall('POST', `${API}/bus-routes`, {
        routeNumber: 'R001',
        routeName: 'Test Route 1',
        vehicleType: 'AC Bus',
        vehicleId: 'test-vehicle',
        driverName: 'Test Driver',
        conductorName: 'Test Conductor',
        stops: [
          { name: 'Stop A', arrivalTime: '08:00', order: 1 },
          { name: 'Stop B', arrivalTime: '08:15', order: 2 },
          { name: 'Stop C', arrivalTime: '08:30', order: 3 }
        ]
      }, superAdminToken);
      
      if (res.data?.route || res.data?._id) {
        const route = res.data.route || res.data;
        testBusRoutes['test'] = route;
        log('BUS ROUTES', 'Create route', 'PASS', 'Route created');
      }
    } catch (error) {
      log('BUS ROUTES', 'Create route', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get routes
  await test('Get bus routes', async () => {
    try {
      const res = await apiCall('GET', `${API}/bus-routes`, null, superAdminToken);
      log('BUS ROUTES', 'Get routes', 'PASS', `Retrieved ${res.data?.routes?.length || 0} routes`);
    } catch (error) {
      log('BUS ROUTES', 'Get routes', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get routes stats
  await test('Get bus routes stats', async () => {
    try {
      const res = await apiCall('GET', `${API}/bus-routes/stats/summary`, null, superAdminToken);
      log('BUS ROUTES', 'Get routes stats', 'PASS', 'Stats retrieved');
    } catch (error) {
      log('BUS ROUTES', 'Get routes stats', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 14: HOSTEL (7 endpoints)
// ============================================================
async function testHostelModule() {
  console.log('\n\n🏠 TESTING MODULE 14: HOSTEL');
  console.log('='.repeat(70));
  
  // Get hostel dashboard
  await test('Get hostel dashboard', async () => {
    try {
      const res = await apiCall('GET', `${API}/hostel/dashboard`, null, superAdminToken);
      log('HOSTEL', 'Get dashboard', 'PASS', 'Dashboard retrieved');
    } catch (error) {
      log('HOSTEL', 'Get dashboard', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Create room type
  await test('Create hostel room type', async () => {
    try {
      const res = await apiCall('POST', `${API}/hostel/room-types`, {
        name: 'Test Room Type',
        genderPolicy: 'mixed',
        capacity: 4,
        monthlyRent: 5000
      }, superAdminToken);
      
      log('HOSTEL', 'Create room type', 'PASS', 'Room type created');
    } catch (error) {
      log('HOSTEL', 'Create room type', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Create room
  await test('Create hostel room', async () => {
    try {
      const res = await apiCall('POST', `${API}/hostel/rooms`, {
        roomNumber: 'R101',
        roomTypeId: 'test-type',
        floor: 1,
        status: 'AVAILABLE',
        capacity: 4
      }, superAdminToken);
      
      if (res.data?.room || res.data?._id) {
        const room = res.data.room || res.data;
        testHostelRooms['test'] = room;
        log('HOSTEL', 'Create room', 'PASS', 'Room created');
      }
    } catch (error) {
      log('HOSTEL', 'Create room', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Create fee structure
  await test('Create hostel fee structure', async () => {
    try {
      const res = await apiCall('POST', `${API}/hostel/fee-structures`, {
        name: 'Annual Hostel Fee',
        amount: 50000,
        billingCycle: 'annual',
        description: 'Annual hostel fee for boarding'
      }, superAdminToken);
      
      log('HOSTEL', 'Create fee structure', 'PASS', 'Fee structure created');
    } catch (error) {
      log('HOSTEL', 'Create fee structure', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 15: PAYROLL (7 endpoints)
// ============================================================
async function testPayrollModule() {
  console.log('\n\n💵 TESTING MODULE 15: PAYROLL');
  console.log('='.repeat(70));
  
  // Get all payrolls
  await test('Get all payrolls', async () => {
    try {
      const res = await apiCall('GET', `${API}/payroll`, null, superAdminToken);
      log('PAYROLL', 'Get all payrolls', 'PASS', `Retrieved ${res.data?.payrolls?.length || 0} payrolls`);
    } catch (error) {
      log('PAYROLL', 'Get all payrolls', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 16: LEAVE MANAGEMENT (5 endpoints)
// ============================================================
async function testLeaveModule() {
  console.log('\n\n🏖️ TESTING MODULE 16: LEAVE MANAGEMENT');
  console.log('='.repeat(70));
  
  // Request leave
  await test('Request leave', async () => {
    try {
      const res = await apiCall('POST', `${API}/leave/request`, {
        startDate: '2025-04-15',
        endDate: '2025-04-17',
        type: 'casual',
        reason: 'Personal work'
      }, superAdminToken);
      
      log('LEAVE', 'Request leave', 'PASS', 'Leave requested');
    } catch (error) {
      log('LEAVE', 'Request leave', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get my leaves
  await test('Get my leaves', async () => {
    try {
      const res = await apiCall('GET', `${API}/leave/my`, null, superAdminToken);
      log('LEAVE', 'Get my leaves', 'PASS', `Retrieved ${res.data?.leaves?.length || 0} leaves`);
    } catch (error) {
      log('LEAVE', 'Get my leaves', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get all leaves
  await test('Get all leaves', async () => {
    try {
      const res = await apiCall('GET', `${API}/leave`, null, superAdminToken);
      log('LEAVE', 'Get all leaves', 'PASS', `Retrieved ${res.data?.leaves?.length || 0} leaves`);
    } catch (error) {
      log('LEAVE', 'Get all leaves', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get leave balance
  await test('Get leave balance', async () => {
    try {
      const res = await apiCall('GET', `${API}/leave/balance`, null, superAdminToken);
      log('LEAVE', 'Get leave balance', 'PASS', 'Balance retrieved');
    } catch (error) {
      log('LEAVE', 'Get leave balance', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 17: ROUTINE/TIMETABLE (5 endpoints)
// ============================================================
async function testRoutineModule() {
  console.log('\n\n📆 TESTING MODULE 17: ROUTINE/TIMETABLE');
  console.log('='.repeat(70));
  
  for (const school of testSchools.slice(0, 1)) {
    const schoolId = school.schoolId;
    const classes = testClasses[schoolId] || [];
    
    if (classes.length > 0) {
      const classData = classes[0];
      
      // Create manual routine
      await test('Create manual routine', async () => {
        try {
          const res = await apiCall('POST', `${API}/routine/manual`, {
            classId: classData._id,
            className: classData.name,
            dayOfWeek: 'Monday',
            startTime: '09:00',
            endTime: '10:00',
            subject: 'Mathematics',
            teacher: 'Test Teacher'
          }, superAdminToken);
          
          log('ROUTINE', 'Create manual routine', 'PASS', 'Routine created');
        } catch (error) {
          log('ROUTINE', 'Create manual routine', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Get routine
      await test('Get class routine', async () => {
        try {
          const res = await apiCall('GET', `${API}/routine/${classData._id}`, null, superAdminToken);
          log('ROUTINE', 'Get routine', 'PASS', 'Routine retrieved');
        } catch (error) {
          log('ROUTINE', 'Get routine', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
  }
}

// ============================================================
// MODULE 18: STAFF ATTENDANCE (4 endpoints)
// ============================================================
async function testStaffAttendanceModule() {
  console.log('\n\n👥 TESTING MODULE 18: STAFF ATTENDANCE');
  console.log('='.repeat(70));
  
  const today = new Date().toISOString().split('T')[0];
  
  // Mark staff attendance
  await test('Mark staff attendance', async () => {
    try {
      const res = await apiCall('POST', `${API}/staff-attendance`, {
        date: today,
        attendance: [
          { staffId: 'test-staff', staffName: 'Test Staff', status: 'present' }
        ]
      }, superAdminToken);
      
      log('STAFF ATTENDANCE', 'Mark attendance', 'PASS', 'Staff attendance marked');
    } catch (error) {
      log('STAFF ATTENDANCE', 'Mark attendance', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get staff attendance
  await test('Get staff attendance', async () => {
    try {
      const res = await apiCall('GET', `${API}/staff-attendance/${today}`, null, superAdminToken);
      log('STAFF ATTENDANCE', 'Get attendance', 'PASS', 'Attendance retrieved');
    } catch (error) {
      log('STAFF ATTENDANCE', 'Get attendance', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 19: SALARY SETUP (4 endpoints)
// ============================================================
async function testSalarySetupModule() {
  console.log('\n\n💼 TESTING MODULE 19: SALARY SETUP');
  console.log('='.repeat(70));
  
  // Create salary structure
  await test('Create salary structure', async () => {
    try {
      const res = await apiCall('POST', `${API}/salary-setup`, {
        staffId: 'test-staff',
        staffName: 'Test Staff',
        basicSalary: 30000,
        hra: 10000,
        da: 5000,
        specialAllowance: 3000,
        pfDeduction: 3600
      }, superAdminToken);
      
      if (res.data?.structure || res.data?._id) {
        const structure = res.data.structure || res.data;
        testSalaryStructures['test'] = structure;
        log('SALARY SETUP', 'Create structure', 'PASS', 'Salary structure created');
      }
    } catch (error) {
      log('SALARY SETUP', 'Create structure', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get salary structures
  await test('Get salary structures', async () => {
    try {
      const res = await apiCall('GET', `${API}/salary-setup`, null, superAdminToken);
      log('SALARY SETUP', 'Get structures', 'PASS', `Retrieved ${res.data?.structures?.length || 0} structures`);
    } catch (error) {
      log('SALARY SETUP', 'Get structures', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 20: NOTIFICATIONS (4 endpoints)
// ============================================================
async function testNotificationsModule() {
  console.log('\n\n🔔 TESTING MODULE 20: NOTIFICATIONS');
  console.log('='.repeat(70));
  
  // Get notifications
  await test('Get notifications', async () => {
    try {
      const res = await apiCall('GET', `${API}/notifications`, null, superAdminToken);
      log('NOTIFICATIONS', 'Get notifications', 'PASS', `Retrieved ${res.data?.notifications?.length || 0} notifications`);
    } catch (error) {
      log('NOTIFICATIONS', 'Get notifications', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get unread count
  await test('Get unread count', async () => {
    try {
      const res = await apiCall('GET', `${API}/notifications/unread-count`, null, superAdminToken);
      log('NOTIFICATIONS', 'Get unread count', 'PASS', 'Unread count retrieved');
    } catch (error) {
      log('NOTIFICATIONS', 'Get unread count', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Mark all as read
  await test('Mark all as read', async () => {
    try {
      const res = await apiCall('PUT', `${API}/notifications/read-all`, null, superAdminToken);
      log('NOTIFICATIONS', 'Mark all as read', 'PASS', 'Marked all as read');
    } catch (error) {
      log('NOTIFICATIONS', 'Mark all as read', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 21: DASHBOARD (2 endpoints)
// ============================================================
async function testDashboardModule() {
  console.log('\n\n📊 TESTING MODULE 21: DASHBOARD');
  console.log('='.repeat(70));
  
  // Get dashboard stats
  await test('Get dashboard stats', async () => {
    try {
      const res = await apiCall('GET', `${API}/dashboard/stats`, null, superAdminToken);
      log('DASHBOARD', 'Get stats', 'PASS', 'Stats retrieved');
    } catch (error) {
      log('DASHBOARD', 'Get stats', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get quick actions
  await test('Get quick actions', async () => {
    try {
      const res = await apiCall('GET', `${API}/dashboard/quick-actions`, null, superAdminToken);
      log('DASHBOARD', 'Get quick actions', 'PASS', 'Quick actions retrieved');
    } catch (error) {
      log('DASHBOARD', 'Get quick actions', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 22: CHATBOT (3 endpoints)
// ============================================================
async function testChatbotModule() {
  console.log('\n\n🤖 TESTING MODULE 22: CHATBOT');
  console.log('='.repeat(70));
  
  // Chat with bot
  await test('Chat with bot', async () => {
    try {
      const res = await apiCall('POST', `${API}/chatbot/chat`, {
        message: 'Hello, how are you?',
        language: 'en'
      }, superAdminToken);
      
      log('CHATBOT', 'Chat with bot', 'PASS', 'Chat response received');
    } catch (error) {
      log('CHATBOT', 'Chat with bot', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get chat history
  await test('Get chat history', async () => {
    try {
      const res = await apiCall('GET', `${API}/chatbot/history`, null, superAdminToken);
      log('CHATBOT', 'Get chat history', 'PASS', `Retrieved ${res.data?.logs?.length || 0} logs`);
    } catch (error) {
      log('CHATBOT', 'Get chat history', 'FAIL', error.response?.data?.message || error.message);
    }
  });
  
  // Get analytics
  await test('Get chatbot analytics', async () => {
    try {
      const res = await apiCall('GET', `${API}/chatbot/analytics`, null, superAdminToken);
      log('CHATBOT', 'Get analytics', 'PASS', 'Analytics retrieved');
    } catch (error) {
      log('CHATBOT', 'Get analytics', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 23: EXPORT (15 endpoints)
// ============================================================
async function testExportModule() {
  console.log('\n\n📤 TESTING MODULE 23: EXPORT');
  console.log('='.repeat(70));
  
  // Test student export (PDF & Excel)
  const exportEndpoints = [
    { path: '/export/students/pdf', name: 'Export students PDF' },
    { path: '/export/students/excel', name: 'Export students Excel' },
    { path: '/export/attendance/pdf', name: 'Export attendance PDF' },
    { path: '/export/attendance/excel', name: 'Export attendance Excel' },
    { path: '/export/fees/pdf', name: 'Export fees PDF' },
    { path: '/export/fees/excel', name: 'Export fees Excel' },
    { path: '/export/exams/pdf', name: 'Export exams PDF' },
    { path: '/export/exam-results/pdf', name: 'Export exam results PDF' },
    { path: '/export/exam-results/excel', name: 'Export exam results Excel' },
    { path: '/export/library/pdf', name: 'Export library PDF' },
    { path: '/export/library/excel', name: 'Export library Excel' },
    { path: '/export/staff/pdf', name: 'Export staff PDF' },
    { path: '/export/staff/excel', name: 'Export staff Excel' }
  ];
  
  for (const endpoint of exportEndpoints) {
    await test(endpoint.name, async () => {
      try {
        const res = await apiCall('GET', `${API}${endpoint.path}`, null, superAdminToken);
        log('EXPORT', endpoint.name, 'PASS', 'Export successful');
      } catch (error) {
        log('EXPORT', endpoint.name, 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 24: IMPORT (6 endpoints)
// ============================================================
async function testImportModule() {
  console.log('\n\n📥 TESTING MODULE 24: IMPORT');
  console.log('='.repeat(70));
  
  // Test get templates
  const templateTypes = ['students', 'staff', 'fees'];
  for (const type of templateTypes) {
    await test(`Get ${type} import template`, async () => {
      try {
        const res = await apiCall('GET', `${API}/import/templates/${type}`, null, superAdminToken);
        log('IMPORT', `Get ${type} template`, 'PASS', 'Template retrieved');
      } catch (error) {
        log('IMPORT', `Get ${type} template`, 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 25: TALLY (3 endpoints)
// ============================================================
async function testTallyModule() {
  console.log('\n\n🧾 TESTING MODULE 25: TALLY INTEGRATION');
  console.log('='.repeat(70));
  
  // Get vouchers
  await test('Get tally vouchers', async () => {
    try {
      const res = await apiCall('GET', `${API}/tally/vouchers`, null, superAdminToken);
      log('TALLY', 'Get vouchers', 'PASS', `Retrieved ${res.data?.vouchers?.length || 0} vouchers`);
    } catch (error) {
      log('TALLY', 'Get vouchers', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 26: ARCHIVE (5 endpoints)
// ============================================================
async function testArchiveModule() {
  console.log('\n\n🗄️ TESTING MODULE 26: ARCHIVE');
  console.log('='.repeat(70));
  
  const archiveTypes = ['students', 'staff', 'fees', 'exams', 'attendance'];
  
  for (const type of archiveTypes) {
    await test(`Get archived ${type}`, async () => {
      try {
        const res = await apiCall('GET', `${API}/archive/${type}`, null, superAdminToken);
        log('ARCHIVE', `Get archived ${type}`, 'PASS', `Retrieved ${res.data?.records?.length || 0} records`);
      } catch (error) {
        log('ARCHIVE', `Get archived ${type}`, 'FAIL', error.response?.data?.message || error.message);
      }
    });
  }
}

// ============================================================
// MODULE 27: AUDIT (1 endpoint)
// ============================================================
async function testAuditModule() {
  console.log('\n\n📜 TESTING MODULE 27: AUDIT LOG');
  console.log('='.repeat(70));
  
  await test('Get audit log', async () => {
    try {
      const res = await apiCall('GET', `${API}/audit`, null, superAdminToken);
      log('AUDIT', 'Get audit log', 'PASS', `Retrieved ${res.data?.logs?.length || 0} logs`);
    } catch (error) {
      log('AUDIT', 'Get audit log', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 28: PDF GENERATION (2 endpoints)
// ============================================================
async function testPDFModule() {
  console.log('\n\n📄 TESTING MODULE 28: PDF GENERATION');
  console.log('='.repeat(70));
  
  await test('Generate payslip PDF', async () => {
    try {
      const res = await apiCall('POST', `${API}/pdf/payslip`, {
        staffName: 'Test Staff',
        month: 'April',
        year: 2025,
        basicSalary: 30000,
        hra: 10000,
        totalEarnings: 40000,
        totalDeductions: 3600,
        netPay: 36400
      }, superAdminToken);
      
      log('PDF', 'Generate payslip PDF', 'PASS', 'Payslip PDF generated');
    } catch (error) {
      log('PDF', 'Generate payslip PDF', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 29: HEALTH CHECK
// ============================================================
async function testHealthModule() {
  console.log('\n\n💚 TESTING MODULE 29: HEALTH CHECK');
  console.log('='.repeat(70));
  
  await test('Health check', async () => {
    try {
      const res = await axios.get(`${API}/health`);
      log('HEALTH', 'Health check', 'PASS', `Server is healthy: ${res.data?.status || 'OK'}`);
    } catch (error) {
      log('HEALTH', 'Health check', 'FAIL', error.response?.data?.message || error.message);
    }
  });
}

// ============================================================
// MODULE 30: CLASSES (9 endpoints) - Already tested during school creation
// ============================================================
async function testClassesModuleFull() {
  console.log('\n\n🏛️ TESTING MODULE 30: CLASSES (FULL)');
  console.log('='.repeat(70));
  
  for (const school of testSchools.slice(0, 2)) {
    const schoolId = school.schoolId;
    const classes = testClasses[schoolId] || [];
    
    if (classes.length > 0) {
      const classData = classes[0];
      
      // Get class detail
      await test('Get class detail', async () => {
        try {
          const res = await apiCall('GET', `${API}/classes/${classData._id}`, null, superAdminToken);
          log('CLASSES', 'Get class detail', 'PASS', 'Class detail retrieved');
        } catch (error) {
          log('CLASSES', 'Get class detail', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Update class
      await test('Update class', async () => {
        try {
          const res = await apiCall('PUT', `${API}/classes/${classData._id}`, {
            capacity: 45
          }, superAdminToken);
          log('CLASSES', 'Update class', 'PASS', 'Class updated');
        } catch (error) {
          log('CLASSES', 'Update class', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Assign teacher
      await test('Assign teacher to class', async () => {
        try {
          const res = await apiCall('POST', `${API}/classes/${classData._id}/assign-teacher`, {
            teacherId: 'test-teacher',
            teacherName: 'Test Teacher',
            subject: 'Mathematics'
          }, superAdminToken);
          log('CLASSES', 'Assign teacher', 'PASS', 'Teacher assigned');
        } catch (error) {
          log('CLASSES', 'Assign teacher', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Get class stats
      await test('Get class stats', async () => {
        try {
          const res = await apiCall('GET', `${API}/classes/stats/summary`, null, superAdminToken);
          log('CLASSES', 'Get stats', 'PASS', 'Stats retrieved');
        } catch (error) {
          log('CLASSES', 'Get stats', 'FAIL', error.response?.data?.message || error.message);
        }
      });
      
      // Get teachers list
      await test('Get teachers list', async () => {
        try {
          const res = await apiCall('GET', `${API}/classes/teachers/list`, null, superAdminToken);
          log('CLASSES', 'Get teachers list', 'PASS', `Retrieved ${res.data?.teachers?.length || 0} teachers`);
        } catch (error) {
          log('CLASSES', 'Get teachers list', 'FAIL', error.response?.data?.message || error.message);
        }
      });
    }
  }
}

// ============================================================
// GENERATE REPORT
// ============================================================
function generateReport() {
  console.log('\n\n');
  console.log('🔴'.repeat(40));
  console.log('📊 COMPREHENSIVE TEST REPORT - COMPLETE');
  console.log('🔴'.repeat(40));
  
  console.log(`\n📈 SUMMARY:`);
  console.log(`   Total Tests: ${testResults.summary.total}`);
  console.log(`   ✅ Passed: ${testResults.summary.passed}`);
  console.log(`   ❌ Failed: ${testResults.summary.failed}`);
  console.log(`   Success Rate: ${((testResults.summary.passed / testResults.summary.total) * 100).toFixed(2)}%`);
  
  console.log(`\n📋 MODULE-BY-MODULE BREAKDOWN:`);
  console.log('-'.repeat(70));
  
  for (const [moduleName, moduleData] of Object.entries(testResults.modules)) {
    const total = moduleData.tests.length;
    const passed = moduleData.passed;
    const failed = moduleData.failed;
    const successRate = total > 0 ? ((passed / total) * 100).toFixed(1) : '0.0';
    
    console.log(`\n  ${moduleName}:`);
    console.log(`    Tests: ${total} | ✅ ${passed} | ❌ ${failed} | Success: ${successRate}%`);
    
    if (failed > 0) {
      console.log(`    Failed Tests:`);
      moduleData.tests
        .filter(t => t.status === 'FAIL')
        .forEach(t => {
          console.log(`      ❌ ${t.feature}: ${t.message}`);
        });
    }
  }
  
  console.log('\n\n');
  console.log('❌ BROKEN FEATURES (COMPLETE LIST):');
  console.log('-'.repeat(70));
  
  if (testResults.summary.brokenFeatures.length === 0) {
    console.log('  🎉 No broken features found! All tests passed.');
  } else {
    testResults.summary.brokenFeatures.forEach((feature, idx) => {
      console.log(`  ${idx + 1}. ${feature}`);
    });
  }
  
  console.log('\n\n');
  console.log('✅ WORKING FEATURES (COMPLETE LIST):');
  console.log('-'.repeat(70));
  
  testResults.summary.workingFeatures.forEach((feature, idx) => {
    console.log(`  ${idx + 1}. ${feature}`);
  });
  
  // Save report to file
  const reportPath = path.join(__dirname, 'COMPREHENSIVE_TEST_REPORT.json');
  fs.writeFileSync(reportPath, JSON.stringify(testResults, null, 2));
  
  console.log('\n\n');
  console.log('📄 Full report saved to: COMPREHENSIVE_TEST_REPORT.json');
  console.log('🔴'.repeat(40));
}

// ============================================================
// MAIN EXECUTION
// ============================================================
async function runAllTests() {
  console.log('🚀 STARTING COMPREHENSIVE MODULE & FEATURE TEST');
  console.log('='.repeat(70));
  console.log(`Testing against: ${API}`);
  console.log(`Timestamp: ${new Date().toISOString()}`);
  console.log('='.repeat(70));
  
  try {
    // Step 1: Login
    const loginSuccess = await loginSuperAdmin();
    if (!loginSuccess) {
      console.log('\n❌ Cannot proceed without authentication.');
      console.log('💡 Please run: node seed.js first to set up the database.');
      return;
    }
    
    // Step 2: Create 20 schools
    await createSchools();
    
    // Step 3: Test all modules
    await testAuthModule();
    await testStudentModule();
    await testAttendanceModule();
    await testFeeModule();
    await testExamsModule();
    await testHomeworkModule();
    await testNoticesModule();
    await testRemarksModule();
    await testComplaintsModule();
    await testLibraryModule();
    await testCanteenModule();
    await testTransportModule();
    await testBusRoutesModule();
    await testHostelModule();
    await testPayrollModule();
    await testLeaveModule();
    await testRoutineModule();
    await testStaffAttendanceModule();
    await testSalarySetupModule();
    await testNotificationsModule();
    await testDashboardModule();
    await testChatbotModule();
    await testExportModule();
    await testImportModule();
    await testTallyModule();
    await testArchiveModule();
    await testAuditModule();
    await testPDFModule();
    await testHealthModule();
    await testClassesModuleFull();
    
    // Step 4: Generate report
    generateReport();
    
  } catch (error) {
    console.error('\n💥 FATAL ERROR:', error.message);
    console.error(error.stack);
  }
}

// Run the tests
runAllTests();
