require('dotenv').config();
const axios = require('axios');
const jwt = require('jsonwebtoken');
const prisma = require('./config/prisma');
const { randomUUID } = require('crypto');

const BASE_URL = 'http://localhost:5000/api';
let superAdminToken, teacherToken, parentToken, conductorToken;
let teacherId, parentId, conductorId, classId, studentId, busId;

async function runTests() {
  try {
    console.log('--- STARTING PHASE 2 END-TO-END TESTS ---');
    await prisma.$connect();
    
    const User = require('./models/User');
    const ClassModel = require('./models/Class');
    const Student = require('./models/Student');
    const Notification = require('./models/Notification');
    
    // Clear previous test notifications & vehicles
    await Notification.deleteMany({});
    await require('./models/TransportVehicle').deleteMany({});
    
    // 1. Setup Users
    console.log('- Setting up test users...');
    const teacher = await User.findOneAndUpdate({ email: 'test_teacher@abc.com' }, { name: 'Test Teacher', password: 'hash', role: 'teacher' }, { upsert: true, new: true });
    const parent = await User.findOneAndUpdate({ email: 'test_parent@abc.com' }, { name: 'Test Parent', password: 'hash', role: 'parent' }, { upsert: true, new: true });
    const conductor = await User.findOneAndUpdate({ email: 'test_conductor@abc.com' }, { name: 'Test Conductor', password: 'hash', role: 'conductor' }, { upsert: true, new: true });
    
    teacherId = teacher._id; parentId = parent._id; conductorId = conductor._id;
    
    // Generate Tokens
    const signToken = (id, role) => jwt.sign({ id, role }, process.env.JWT_SECRET || 'secret123');
    superAdminToken = signToken(randomUUID(), 'superadmin');
    teacherToken = signToken(teacherId, 'teacher');
    parentToken = signToken(parentId, 'parent');
    conductorToken = signToken(conductorId, 'conductor');
    
    // 2. Setup Class & Student
    console.log('- Setting up class and student...');
    const testClass = await ClassModel.findOneAndUpdate({ name: 'Test Class' }, { section: 'A', classTeacher: teacherId }, { upsert: true, new: true });
    classId = testClass._id;
    
    const testStudent = await Student.findOneAndUpdate({ name: 'Test Student' }, { 
      admissionNo: 'TS100', classId: classId, parentUserId: parentId, parentPhone: '123',
      userId: randomUUID(), dob: new Date(), address: '123 Test St', gender: 'male'
    }, { upsert: true, new: true, runValidators: true });
    studentId = testStudent._id;
    console.log('Upserted Student:', testStudent);

    const auth = (token) => ({ headers: { 'Authorization': `Bearer ${token}` } });

    // ── TEST 1: HOMEWORK NOTIFICATION ──
    console.log('Test 1: Teacher Assigns Homework (Should notify Parent)');
    
    // Check students
    const checkStudents = await Student.find({ classId });
    console.log('Students in class:', checkStudents.length, 'Parent ID:', checkStudents[0]?.parentUserId);
    
    const hwRes = await axios.post(`${BASE_URL}/homework`, {
      classId, subject: 'Science', title: 'Chapter 1', description: 'Read it', dueDate: new Date()
    }, auth(teacherToken));
    console.log('Homework HTTP Status:', hwRes.status);
    
    let parentNotifs = await Notification.find({ recipientId: parentId, type: 'homework' });
    if (parentNotifs.length !== 1) {
      const allNotifs = await Notification.find();
      console.log('All inserted notifs:', allNotifs);
      throw new Error('Homework Notification Failed! Count: ' + parentNotifs.length);
    }
    console.log('✅ Homework Notif Success');

    // ── TEST 2: BULK ATTENDANCE NOTIFICATION ──
    console.log('Test 2: Teacher Marks Bulk Attendance (Absent should notify Parent)');
    await axios.post(`${BASE_URL}/attendance/bulk`, {
      classId, date: new Date().toISOString().split('T')[0], records: [{ studentId, status: 'absent' }]
    }, auth(teacherToken));
    
    parentNotifs = await Notification.find({ recipientId: parentId, type: 'attendance_alert' });
    if (parentNotifs.length !== 1) throw new Error('Attendance Notification Failed!');
    console.log('✅ Bulk Attendance Notif Success');

    // ── TEST 3: PARENT TO TEACHER COMPLAINT ──
    console.log('Test 3: Parent files Complaint to Class Teacher');
    await axios.post(`${BASE_URL}/complaints`, {
      subject: 'Issue', description: 'Help', type: 'parent_to_teacher', classId
    }, auth(parentToken));
    
    let teacherNotifs = await Notification.find({ recipientId: teacherId, type: 'complaint_to_teacher' });
    if (teacherNotifs.length !== 1) throw new Error('Parent->Teacher Complaint Notification Failed!');
    console.log('✅ Parent->Teacher Complaint Success');

    // ── TEST 4: TEACHER TO PARENT COMPLAINT ──
    console.log('Test 4: Teacher files Complaint to Parent');
    await axios.post(`${BASE_URL}/complaints`, {
      subject: 'Issue 2', description: 'Help 2', type: 'teacher_to_parent', studentId
    }, auth(teacherToken));
    
    parentNotifs = await Notification.find({ recipientId: parentId, type: 'complaint_to_parent' });
    if (parentNotifs.length !== 1) throw new Error('Teacher->Parent Complaint Notification Failed!');
    console.log('✅ Teacher->Parent Complaint Success');

    // ── TEST 5: TRANSPORT MODULE ──
    console.log('Test 5: Conductor Marks Student Boarded (Should notify Parent)');
    const busRes = await axios.post(`${BASE_URL}/transport`, {
      busNumber: 'T-01', numberPlate: 'AB123', route: 'Test Route', conductorId
    }, auth(superAdminToken));
    busId = busRes.data._id;
    
    // Assign student to bus directly in DB for test
    const TransportVehicle = require('./models/TransportVehicle');
    await TransportVehicle.findByIdAndUpdate(busId, { $push: { students: studentId } });

    await axios.post(`${BASE_URL}/transport/${busId}/attendance`, {
      studentId, status: 'boarded'
    }, auth(conductorToken));
    
    parentNotifs = await Notification.find({ recipientId: parentId, type: 'transport' });
    if (parentNotifs.length !== 1) throw new Error('Transport Notification Failed!');
    console.log('✅ Transport Attendance Notif Success');

    console.log('🎉 ALL INTEGRATION TESTS PASSED 🎉');
    process.exit(0);

  } catch (err) {
    console.error('❌ TEST FAILED');
    if (err.response) {
      console.error(err.response.data);
    } else {
      console.error(err.message);
    }
    process.exit(1);
  }
}

runTests();
