const { NlpManager } = require('node-nlp');
const actions = require('./actions');
const path = require('path');
const fs = require('fs');
const prisma = require('../config/prisma');
const { parseNaturalDate } = require('./dateParser');
const { getChatbotBootstrap, pickLanguage } = require('./chatbotUi');

// Create manager with multilingual support (let so it can be recreated to clear entity duplicates)
let manager = new NlpManager({
  languages: ['en', 'hi', 'as'],
  forceNER: true,
  useNoneFeature: true,
  threshold: 0.35
});

const modelPath = path.join(__dirname, 'model.nlp');
const modelVersionPath = path.join(__dirname, 'model.version.json');
const MODEL_SCHEMA_VERSION = 3;

// Dynamic entity cache
let entityCache = {
  students: [],
  staff: [],
  classes: [],
  vehicles: [],
  books: [],
  lastUpdated: null
};
let lastDynamicEntitySignature = '';
let modelInitialized = false;
let initializationPromise = null;

// Cache entities for 5 minutes
const CACHE_DURATION = 5 * 60 * 1000;

function isCacheValid() {
  if (!entityCache.lastUpdated) return false;
  return Date.now() - entityCache.lastUpdated < CACHE_DURATION;
}

function getEntitySignature() {
  return JSON.stringify({
    students: entityCache.students,
    staff: entityCache.staff,
    classes: entityCache.classes,
    vehicles: entityCache.vehicles,
    books: entityCache.books,
  });
}

// Load entities from database dynamically
async function loadEntitiesFromDatabase(options = {}) {
  const { force = false } = options;

  if (!force && isCacheValid()) {
    console.log('[NLP] Using cached entities');
    return entityCache;
  }

  try {
    console.log('[NLP] Loading entities from database...');

    // Load students (top 100 most recent)
    try {
      const students = await prisma.student.findMany({
        select: { name: true },
        orderBy: [{ updatedAt: 'desc' }, { createdAt: 'desc' }],
        take: 250,
      });
      entityCache.students = students.map(s => s.name).filter(Boolean);
    } catch (e) {
      console.log('[NLP] Student model not available:', e.message);
    }

    // Load staff (teachers + hr + staff users)
    try {
      const staff = await prisma.user.findMany({
        where: { role: { in: ['teacher', 'hr', 'staff', 'accounts'] } },
        select: { name: true },
        orderBy: [{ updatedAt: 'desc' }, { createdAt: 'desc' }],
        take: 250,
      });
      entityCache.staff = staff.map(s => s.name).filter(Boolean);
    } catch (e) {
      console.log('[NLP] Staff model not available:', e.message);
    }

    // Load classes
    try {
      const classes = await prisma.class.findMany({
        select: { name: true, section: true },
        orderBy: [{ updatedAt: 'desc' }, { createdAt: 'desc' }],
        take: 120,
      });
      entityCache.classes = classes
        .map(c => [c.name, c.section ? `${c.name} ${c.section}` : null])
        .flat()
        .filter(Boolean);
    } catch (e) {
      console.log('[NLP] Class model not available:', e.message);
    }

    // Load vehicles
    try {
      const vehicles = await prisma.transportVehicle.findMany({
        select: { busNumber: true, numberPlate: true, route: true },
        orderBy: [{ updatedAt: 'desc' }, { createdAt: 'desc' }],
        take: 120,
      });
      entityCache.vehicles = vehicles
        .map(v => [v.busNumber, v.numberPlate, v.route])
        .flat()
        .filter(Boolean);
    } catch (e) {
      console.log('[NLP] Vehicle model not available:', e.message);
    }

    // Load books
    try {
      const books = await prisma.libraryBook.findMany({
        select: { title: true },
        orderBy: [{ updatedAt: 'desc' }, { createdAt: 'desc' }],
        take: 250,
      });
      entityCache.books = books.map(b => b.title).filter(Boolean);
    } catch (e) {
      console.log('[NLP] Book model not available:', e.message);
    }

    entityCache.lastUpdated = Date.now();
    console.log(`[NLP] Loaded ${entityCache.students.length} students, ${entityCache.staff.length} staff, ${entityCache.classes.length} classes`);

    return entityCache;
  } catch (err) {
    console.error('[NLP] Error loading entities:', err.message);
    return entityCache;
  }
}

// Add dynamic entities to the NLP manager
function addDynamicEntities() {
  const signature = getEntitySignature();
  if (signature === lastDynamicEntitySignature) {
    return;
  }

  // Note: node-nlp doesn't have removeNamedEntity, so we just add new entities
  // The training process will handle duplicates

  // Add student names
  if (entityCache.students.length > 0) {
    entityCache.students.forEach((name, index) => {
      manager.addNamedEntityText('studentName', `student_${index}`, ['en', 'hi', 'as'], [name]);
    });
  }

  // Add staff names
  if (entityCache.staff.length > 0) {
    entityCache.staff.forEach((name, index) => {
      manager.addNamedEntityText('staffName', `staff_${index}`, ['en', 'hi', 'as'], [name]);
    });
  }

  // Add class names
  if (entityCache.classes.length > 0) {
    entityCache.classes.forEach((name, index) => {
      manager.addNamedEntityText('className', `class_${index}`, ['en', 'hi', 'as'], [name]);
    });
  }

  // Add vehicle numbers
  if (entityCache.vehicles.length > 0) {
    entityCache.vehicles.forEach((name, index) => {
      manager.addNamedEntityText('vehicleNumber', `vehicle_${index}`, ['en', 'hi', 'as'], [name]);
    });
  }

  // Add book titles
  if (entityCache.books.length > 0) {
    entityCache.books.forEach((name, index) => {
      manager.addNamedEntityText('bookName', `book_${index}`, ['en', 'hi', 'as'], [name]);
    });
  }

  lastDynamicEntitySignature = signature;
}

// Get model version info
function getModelVersion() {
  try {
    if (fs.existsSync(modelVersionPath)) {
      return JSON.parse(fs.readFileSync(modelVersionPath, 'utf8'));
    }
  } catch (err) {
    console.error('[NLP] Error reading model version:', err.message);
  }
  return null;
}

// Save model version info
function saveModelVersion(version, metadata = {}) {
  try {
    const versionInfo = {
      version,
      schemaVersion: MODEL_SCHEMA_VERSION,
      timestamp: new Date().toISOString(),
      languages: ['en', 'hi', 'as'],
      entityCount: {
        students: entityCache.students.length,
        staff: entityCache.staff.length,
        classes: entityCache.classes.length,
        vehicles: entityCache.vehicles.length,
        books: entityCache.books.length
      },
      ...metadata
    };
    fs.writeFileSync(modelVersionPath, JSON.stringify(versionInfo, null, 2));
    console.log(`[NLP] Model version ${version} saved`);
  } catch (err) {
    console.error('[NLP] Error saving model version:', err.message);
  }
}

async function ensureModelReady(options = {}) {
  const { forceEntityReload = false } = options;

  if (modelInitialized) {
    if (forceEntityReload || !isCacheValid()) {
      await loadEntitiesFromDatabase({ force: forceEntityReload });
      addDynamicEntities();
    }
    return;
  }

  if (initializationPromise) {
    await initializationPromise;
    return;
  }

  initializationPromise = (async () => {
    const currentVersion = getModelVersion();
    const modelExists = fs.existsSync(modelPath);
    const modelCompatible = currentVersion?.schemaVersion === MODEL_SCHEMA_VERSION;

    if (modelExists && currentVersion && modelCompatible) {
      try {
        console.log('[NLP] Loading NLP model on demand...');
        manager.load(modelPath);
        modelInitialized = true;
        await loadEntitiesFromDatabase({ force: true });
        addDynamicEntities();
        return;
      } catch (err) {
        console.error('[NLP] On-demand model load failed, retraining:', err.message);
      }
    }

    await trainChatbot({ forceRetrain: true, forceEntityReload: true });
    modelInitialized = true;
  })();

  try {
    await initializationPromise;
  } finally {
    initializationPromise = null;
  }
}

// Function to recreate the NLP manager — clears accumulated entity duplicates
function recreateManager() {
  try {
    const newManager = new NlpManager({
      languages: ['en', 'hi', 'as'],
      forceNER: true,
      useNoneFeature: true,
      threshold: 0.35
    });
    // Clear old entity signature so entities get re-added fresh
    lastDynamicEntitySignature = '';
    return newManager;
  } catch (err) {
    console.error('[NLP] Error recreating manager:', err.message);
    return manager; // fallback to old manager
  }
}

// Function to train the bot
async function trainChatbot(options = {}) {
  const { forceRetrain = false, forceEntityReload = false } = options;
  const currentVersion = getModelVersion();
  const modelExists = fs.existsSync(modelPath);
  const modelCompatible = currentVersion?.schemaVersion === MODEL_SCHEMA_VERSION;

  // Load existing model if available unless a full retrain was requested.
  if (!forceRetrain && modelExists && currentVersion && modelCompatible) {
    console.log('[NLP] Loading existing NLP model...');
    try {
      manager.load(modelPath);
      await loadEntitiesFromDatabase({ force: forceEntityReload });
      addDynamicEntities();
      modelInitialized = true;
      return;
    } catch (err) {
      console.error('[NLP] Error loading model, retraining:', err.message);
    }
  }

  // Re-create manager to clear accumulated entity duplicates (memory leak fix)
  manager = recreateManager();

  console.log('[NLP] Training NLP model with multilingual support...');
  console.time('[NLP] Training time');

  // ============= ENGLISH DOCUMENTS =============

  // 1. Admission Intents
  manager.addDocument('en', 'hello', 'greeting.welcome');
  manager.addDocument('en', 'hi', 'greeting.welcome');
  manager.addDocument('en', 'hey', 'greeting.welcome');
  manager.addDocument('en', 'good morning', 'greeting.welcome');
  manager.addDocument('en', 'good afternoon', 'greeting.welcome');

  manager.addDocument('en', 'admit a new student', 'admission.create');
  manager.addDocument('en', 'how to add student', 'admission.create');
  manager.addDocument('en', 'student admission process', 'admission.create');
  manager.addDocument('en', 'new student registration', 'admission.create');
  manager.addDocument('en', 'how many students are there', 'student.getCount');
  manager.addDocument('en', 'student count', 'student.getCount');
  manager.addDocument('en', 'total students', 'student.getCount');

  manager.addDocument('en', 'details for student %studentName%', 'admission.get');
  manager.addDocument('en', 'show me info about student %studentName%', 'admission.get');
  manager.addDocument('en', 'student profile for %studentName%', 'admission.get');
  manager.addDocument('en', 'find student %studentName%', 'admission.get');

  // 2. Exam Intents
  manager.addDocument('en', 'when is the exam for %className%', 'exam.get');
  manager.addDocument('en', 'exam schedule for %className%', 'exam.get');
  manager.addDocument('en', 'show me the upcoming exams', 'exam.get');
  manager.addDocument('en', 'exam timetable', 'exam.get');
  manager.addDocument('en', 'show me exam schedule', 'exam.get');

  // 3. Complaint Intents
  manager.addDocument('en', 'submit a complaint', 'complaint.create');
  manager.addDocument('en', 'raise a ticket', 'complaint.create');
  manager.addDocument('en', 'file an issue', 'complaint.create');
  manager.addDocument('en', 'I have a complaint', 'complaint.create');

  // 4. Library Intents
  manager.addDocument('en', 'is %bookName% available', 'library.checkBook');
  manager.addDocument('en', 'check library for %bookName%', 'library.checkBook');
  manager.addDocument('en', 'search book %bookName%', 'library.checkBook');
  manager.addDocument('en', 'issue book %bookName% to %studentName%', 'library.issueBook');
  manager.addDocument('en', 'give book %bookName% to student %studentName%', 'library.issueBook');
  manager.addDocument('en', 'library books available', 'library.checkBook');
  manager.addDocument('en', 'available library books', 'library.checkBook');

  // 5. Canteen Intents
  manager.addDocument('en', 'what is the lunch menu today', 'canteen.getMenu');
  manager.addDocument('en', 'canteen menu', 'canteen.getMenu');
  manager.addDocument('en', 'what food is available', 'canteen.getMenu');
  manager.addDocument('en', 'check canteen balance for %studentName%', 'canteen.getWallet');
  manager.addDocument('en', 'canteen wallet for %studentName%', 'canteen.getWallet');

  // 6. HR & Staff Intents
  manager.addDocument('en', 'staff profile for %staffName%', 'hr.getStaff');
  manager.addDocument('en', 'who is %staffName%', 'hr.getStaff');
  manager.addDocument('en', 'details for teacher %staffName%', 'hr.getStaff');
  manager.addDocument('en', 'who is absent today', 'hr.getAbsent');
  manager.addDocument('en', 'show absent staff', 'hr.getAbsent');
  manager.addDocument('en', 'how many teachers', 'staff.getCount');
  manager.addDocument('en', 'teacher count', 'staff.getCount');
  manager.addDocument('en', 'total teachers', 'staff.getCount');

  // 7. Payroll Intents
  manager.addDocument('en', 'salary for %staffName%', 'payroll.getSalary');
  manager.addDocument('en', 'how much was %staffName% paid', 'payroll.getSalary');
  manager.addDocument('en', 'total payroll expense for this month', 'payroll.getTotal');
  manager.addDocument('en', 'total salary paid this month', 'payroll.getTotal');
  manager.addDocument('en', 'payroll information', 'payroll.getTotal');
  manager.addDocument('en', 'payroll summary', 'payroll.getTotal');

  // 8. Financial/Fee Intents
  manager.addDocument('en', 'fee defaults for %className%', 'fee.getDefaults');
  manager.addDocument('en', 'who hasn\'t paid fees in %className%', 'fee.getDefaults');
  manager.addDocument('en', 'pending fees', 'fee.getDefaults');
  manager.addDocument('en', 'fee payment status', 'fee.getStatus');

  // 9. Transport Intents
  manager.addDocument('en', 'details for bus %vehicleNumber%', 'transport.getVehicle');
  manager.addDocument('en', 'where is bus %vehicleNumber%', 'transport.getVehicle');
  manager.addDocument('en', 'who is driving bus %vehicleNumber%', 'transport.getDriver');
  manager.addDocument('en', 'driver contact for %vehicleNumber%', 'transport.getDriver');
  manager.addDocument('en', 'transport routes', 'transport.getRoutes');
  manager.addDocument('en', 'show transport routes', 'transport.getRoutes');
  manager.addDocument('en', 'bus routes', 'transport.getRoutes');

  // 10. Attendance Intents
  manager.addDocument('en', 'mark attendance', 'attendance.mark');
  manager.addDocument('en', 'attendance report', 'attendance.report');
  manager.addDocument('en', 'who is absent', 'attendance.absent');

  // ============= HINDI DOCUMENTS =============

  manager.addDocument('hi', 'नए छात्र को भर्ती करें', 'admission.create');
  manager.addDocument('hi', 'छात्र प्रवेश प्रक्रिया', 'admission.create');
  manager.addDocument('hi', 'नया छात्र पंजीकरण', 'admission.create');

  manager.addDocument('hi', 'छात्र %studentName% का विवरण', 'admission.get');
  manager.addDocument('hi', 'छात्र %studentName% के बारे में जानकारी', 'admission.get');

  manager.addDocument('hi', 'परीक्षा कब है', 'exam.get');
  manager.addDocument('hi', 'परीक्षा कार्यक्रम', 'exam.get');
  manager.addDocument('hi', 'आगामी परीक्षाएं', 'exam.get');

  manager.addDocument('hi', 'शिकायत दर्ज करें', 'complaint.create');
  manager.addDocument('hi', 'समस्या रिपोर्ट करें', 'complaint.create');

  manager.addDocument('hi', 'पुस्तकालय में %bookName% उपलब्ध है', 'library.checkBook');
  manager.addDocument('hi', '%bookName% खोजें', 'library.checkBook');

  manager.addDocument('hi', 'कैंटीन मेनू क्या है', 'canteen.getMenu');
  manager.addDocument('hi', 'आज का भोजन', 'canteen.getMenu');

  manager.addDocument('hi', 'कर्मचारी %staffName% का प्रोफाइल', 'hr.getStaff');
  manager.addDocument('hi', 'आज कौन अनुपस्थित है', 'hr.getAbsent');

  manager.addDocument('hi', '%staffName% का वेतन', 'payroll.getSalary');
  manager.addDocument('hi', 'कुल वेतन खर्च', 'payroll.getTotal');

  manager.addDocument('hi', 'फीस भुगतान स्थिति', 'fee.getStatus');
  manager.addDocument('hi', 'बकाया फीस', 'fee.getDefaults');

  manager.addDocument('hi', 'बस %vehicleNumber% का विवरण', 'transport.getVehicle');
  manager.addDocument('hi', 'ड्राइवर का संपर्क', 'transport.getDriver');

  manager.addDocument('hi', 'उपस्थिति चिह्नित करें', 'attendance.mark');
  manager.addDocument('hi', 'उपस्थिति रिपोर्ट', 'attendance.report');

  // ============= ASSAMESE DOCUMENTS =============

  manager.addDocument('as', 'নতুন ছাত্ৰক ভৰ্তি কৰক', 'admission.create');
  manager.addDocument('as', 'ছাত্ৰ প্ৰৱেশ প্ৰক্ৰিয়া', 'admission.create');

  manager.addDocument('as', 'ছাত্ৰ %studentName% ৰ বিৱৰণ', 'admission.get');
  manager.addDocument('as', 'ছাত্ৰ %studentName% ৰ তথ্য', 'admission.get');

  manager.addDocument('as', 'পৰীক্ষা কেতিয়া', 'exam.get');
  manager.addDocument('as', 'পৰীক্ষাৰ সময়সূচী', 'exam.get');

  manager.addDocument('as', 'অভিযোগ দাখিল কৰক', 'complaint.create');

  manager.addDocument('as', 'পুস্তকালয়ত %bookName% আছেনে', 'library.checkBook');
  manager.addDocument('as', '%bookName% বিচাৰক', 'library.checkBook');

  manager.addDocument('as', 'কেণ্টিন মেনু', 'canteen.getMenu');
  manager.addDocument('as', 'আজিৰ আহাৰ', 'canteen.getMenu');

  manager.addDocument('as', 'কৰ্মচাৰী %staffName% ৰ তথ্য', 'hr.getStaff');
  manager.addDocument('as', 'আজি কোন অনুপস্থিত', 'hr.getAbsent');

  manager.addDocument('as', '%staffName% ৰ দৰমহা', 'payroll.getSalary');
  manager.addDocument('as', 'মুঠ দৰমহা খৰচ', 'payroll.getTotal');

  manager.addDocument('as', 'মাচুল ভুগবান স্থিতি', 'fee.getStatus');
  manager.addDocument('as', 'বকেয়া মাচুল', 'fee.getDefaults');

  manager.addDocument('as', 'বাস %vehicleNumber% ৰ বিৱৰণ', 'transport.getVehicle');
  manager.addDocument('as', 'চালকৰ যোগাযোগ', 'transport.getDriver');

  manager.addDocument('as', 'উপস্থিতি চিহ্নিত কৰক', 'attendance.mark');
  manager.addDocument('as', 'উপস্থিতি প্ৰতিবেদন', 'attendance.report');

  // ============= NEW INTENTS (Phase 2: 27 intents) =============
  manager.addDocument('en', 'show my homework', 'homework.list');
  manager.addDocument('en', 'pending assignments', 'homework.list');
  manager.addDocument('en', 'overdue homework', 'homework.pending');
  manager.addDocument('en', 'show timetable', 'routine.view');
  manager.addDocument('en', 'today schedule', 'routine.view');
  manager.addDocument('en', 'show notices', 'notice.list');
  manager.addDocument('en', 'recent notices', 'notice.list');
  manager.addDocument('en', 'complaint status', 'complaint.status');
  manager.addDocument('en', 'file complaint', 'complaint.new');
  manager.addDocument('en', 'my attendance', 'attendance.my');
  manager.addDocument('en', 'attendance percentage', 'attendance.my');
  manager.addDocument('en', 'attendance history', 'attendance.history');
  manager.addDocument('en', 'my fee status', 'fee.my');
  manager.addDocument('en', 'fee balance', 'fee.my');
  manager.addDocument('en', 'my exams', 'exam.my');
  manager.addDocument('en', 'upcoming exams', 'exam.my');
  manager.addDocument('en', 'my results', 'exam.results');
  manager.addDocument('en', 'grade card', 'exam.results');
  manager.addDocument('en', 'my books', 'library.my');
  manager.addDocument('en', 'overdue books', 'library.overdue');
  manager.addDocument('en', 'library fine', 'library.overdue');
  manager.addDocument('en', 'recharge wallet', 'canteen.recharge');
  manager.addDocument('en', 'my hostel', 'hostel.my');
  manager.addDocument('en', 'my bus', 'transport.my');
  manager.addDocument('en', 'leave balance', 'leave.balance');
  manager.addDocument('en', 'apply for leave', 'leave.apply');
  manager.addDocument('en', 'my salary', 'payroll.my');
  manager.addDocument('en', 'my payslip', 'payroll.my');
  manager.addDocument('en', 'show dashboard', 'dashboard.stats');
  manager.addDocument('en', 'school overview', 'dashboard.stats');
  manager.addDocument('as', 'মোৰ গৃহকাম', 'homework.list');
  manager.addDocument('as', 'আজিৰ ৰুটিন', 'routine.view');
  manager.addDocument('as', 'জাননী দেখুৱাওক', 'notice.list');
  manager.addDocument('as', 'অভিযোগৰ স্থিতি', 'complaint.status');
  manager.addDocument('as', 'মোৰ উপস্থিতি', 'attendance.my');
  manager.addDocument('as', 'মোৰ মাচুল', 'fee.my');
  manager.addDocument('as', 'মোৰ পৰীক্ষা', 'exam.my');
  manager.addDocument('as', 'মোৰ ফলাফল', 'exam.results');
  manager.addDocument('as', 'মোৰ কিতাপ', 'library.my');
  manager.addDocument('as', 'মোৰ হোষ্টেল', 'hostel.my');
  manager.addDocument('as', 'মোৰ বাছ', 'transport.my');
  manager.addDocument('as', 'মোৰ দৰমহা', 'payroll.my');
  manager.addDocument('as', 'ডেশ্বব\'ৰ্ড', 'dashboard.stats');
  // ============= END NEW INTENTS =============

  // ============= PHASE 6: ADDITIONAL 50+ INTENTS (Added Apr 8, 2026) =============

  // Grade & Results queries
  manager.addDocument('en', 'what is my grade', 'exam.results');
  manager.addDocument('en', 'show my grade card', 'exam.results');
  manager.addDocument('en', 'how did I perform', 'exam.results');
  manager.addDocument('en', 'my academic progress', 'exam.results');

  // Fee payment queries
  manager.addDocument('en', 'show my fee receipt', 'fee.my');
  manager.addDocument('en', 'fee payment history', 'fee.my');
  manager.addDocument('en', 'how to pay fees online', 'fee.my');
  manager.addDocument('en', 'online fee payment', 'fee.my');
  manager.addDocument('en', 'pay my fees', 'fee.my');

  // Holiday & Calendar queries
  manager.addDocument('en', 'when is the next holiday', 'notice.list');
  manager.addDocument('en', 'school calendar', 'notice.list');
  manager.addDocument('en', 'upcoming holidays', 'notice.list');
  manager.addDocument('en', 'school events', 'notice.list');
  manager.addDocument('en', 'upcoming events', 'notice.list');

  // Contact & Communication
  manager.addDocument('en', 'contact my teacher', 'hr.getStaff');
  manager.addDocument('en', 'teacher contact info', 'hr.getStaff');
  manager.addDocument('en', 'send message to parent', 'notice.list');
  manager.addDocument('en', 'contact school office', 'notice.list');

  // ID Cards & Documents
  manager.addDocument('en', 'generate my ID card', 'admission.get');
  manager.addDocument('en', 'download ID card', 'admission.get');
  manager.addDocument('en', 'print attendance report', 'attendance.report');
  manager.addDocument('en', 'download report card', 'exam.results');

  // Child Progress (for parents)
  manager.addDocument('en', 'my child progress', 'exam.results');
  manager.addDocument('en', 'how is my child doing', 'exam.results');
  manager.addDocument('en', 'child attendance', 'attendance.my');
  manager.addDocument('en', 'child fee status', 'fee.my');
  manager.addDocument('en', 'child exam results', 'exam.results');

  // Admission help
  manager.addDocument('en', 'admission process', 'admission.create');
  manager.addDocument('en', 'how to admit student', 'admission.create');
  manager.addDocument('en', 'new admission steps', 'admission.create');
  manager.addDocument('en', 'admission requirements', 'admission.create');
  manager.addDocument('en', 'admission documents needed', 'admission.create');

  // Help & Support
  manager.addDocument('en', 'I need help', 'greeting.welcome');
  manager.addDocument('en', 'how to use chatbot', 'greeting.welcome');
  manager.addDocument('en', 'what can you do', 'greeting.welcome');
  manager.addDocument('en', 'show me options', 'greeting.welcome');
  manager.addDocument('en', 'help menu', 'greeting.welcome');

  // Password & Profile
  manager.addDocument('en', 'change my password', 'admission.get');
  manager.addDocument('en', 'update my profile', 'admission.get');
  manager.addDocument('en', 'edit my details', 'admission.get');

  // Transport specific
  manager.addDocument('en', 'bus timing', 'transport.my');
  manager.addDocument('en', 'bus schedule', 'transport.my');
  manager.addDocument('en', 'when does bus arrive', 'transport.my');
  manager.addDocument('en', 'bus stop location', 'transport.my');
  manager.addDocument('en', 'track my bus', 'transport.my');

  // Library specific
  manager.addDocument('en', 'renew my book', 'library.my');
  manager.addDocument('en', 'extend book issue', 'library.my');
  manager.addDocument('en', 'book due date', 'library.my');
  manager.addDocument('en', 'how many books can I borrow', 'library.checkBook');

  // Attendance specific
  manager.addDocument('en', 'why is my attendance low', 'attendance.my');
  manager.addDocument('en', 'attendance requirement', 'attendance.my');
  manager.addDocument('en', 'minimum attendance required', 'attendance.my');

  // Canteen specific
  manager.addDocument('en', 'today menu', 'canteen.getMenu');
  manager.addDocument('en', 'what is available today', 'canteen.getMenu');
  manager.addDocument('en', 'food options', 'canteen.getMenu');

  // Exam specific  
  manager.addDocument('en', 'exam preparation tips', 'exam.my');
  manager.addDocument('en', 'exam syllabus', 'exam.my');
  manager.addDocument('en', 'exam date sheet', 'exam.my');
  manager.addDocument('en', 'exam time table', 'exam.my');

  // Hindi additional
  manager.addDocument('hi', 'मेरी ग्रेड क्या है', 'exam.results');
  manager.addDocument('hi', 'फीस रसीद दिखाओ', 'fee.my');
  manager.addDocument('hi', 'अगली छुट्टी कब है', 'notice.list');
  manager.addDocument('hi', 'स्कूल कैलेंडर', 'notice.list');
  manager.addDocument('hi', 'बस का समय', 'transport.my');
  manager.addDocument('hi', 'मेरा बस ट्रैक करें', 'transport.my');
  manager.addDocument('hi', 'बच्चे की प्रगति', 'exam.results');
  manager.addDocument('hi', 'ऑनलाइन फीस कैसे भरें', 'fee.my');

  // Assamese additional
  manager.addDocument('as', 'মোৰ গ্ৰেড কিমান', 'exam.results');
  manager.addDocument('as', 'মাচুল ৰচিদ', 'fee.my');
  manager.addDocument('as', 'বিদ্যালয়ৰ বন্ধ', 'notice.list');
  manager.addDocument('as', 'বাছৰ সময়', 'transport.my');
  manager.addDocument('as', 'সন্তানৰ উন্নতি', 'exam.results');
  // ============= END PHASE 6 INTENTS =============

  // Load dynamic entities from database
  await loadEntitiesFromDatabase({ force: true });
  addDynamicEntities();

  // Train the model
  await manager.train();

  // Save the model
  manager.save(modelPath);

  // Save version info
  const version = `1.${Date.now()}`;
  saveModelVersion(version, { trainingCompleted: true });

  console.timeEnd('[NLP] Training time');
  console.log('[NLP] Model trained and saved with multilingual support.');
  modelInitialized = true;
}

// Conversation context store
const conversationContext = new Map();

// Get context for a user
function getUserContext(userId) {
  if (!conversationContext.has(userId)) {
    conversationContext.set(userId, {
      messages: [],
      lastIntent: null,
      entities: {},
      activeForm: null,
      formData: {},
      timestamp: Date.now()
    });
  }
  return conversationContext.get(userId);
}

// Update context
function updateContext(userId, intent, entities, message) {
  const context = getUserContext(userId);
  context.messages.push({ role: 'user', message, timestamp: Date.now() });
  context.lastIntent = intent;

  // Merge entities
  if (entities && typeof entities === 'object') {
    Object.assign(context.entities, entities);
  }

  // Keep only last 10 messages
  if (context.messages.length > 10) {
    context.messages = context.messages.slice(-10);
  }

  context.timestamp = Date.now();
}

function addAssistantMessage(userId, message) {
  const context = getUserContext(userId);
  context.messages.push({ role: 'assistant', message, timestamp: Date.now() });

  if (context.messages.length > 10) {
    context.messages = context.messages.slice(-10);
  }

  context.timestamp = Date.now();
}

function getNormalizedUser(user) {
  if (!user) return null;
  if (typeof user === 'string') {
    return { id: user, role: 'unknown' };
  }
  return user;
}

function localizeCopy(language, copy) {
  return pickLanguage(language, copy);
}

function getLocalizedFallback(language, role) {
  const bootstrap = getChatbotBootstrap({ language, role });
  return {
    message: localizeCopy(language, {
      en: "I didn't quite understand that. Please try rephrasing or ask me about admissions, exams, attendance, fees, library, transport, homework, or notices.",
      hi: 'Main aapki baat poori tarah samajh nahin paaya. Kripya sawaal ko doosre tareeke se poochhiye ya mujhse admission, exam, attendance, fees, library, transport, homework ya notices ke baare mein poochhiye.',
      as: 'Moi apunar proshnotu purapuri bujhi napalu. Anugraha kore proshnotu olop bhinnobhabe punorai sodhkok ba admission, exam, attendance, fees, library, transport, homework ba notice bisoye sodhok.',
    }),
    suggestions: bootstrap.suggestions || [],
  };
}

function getLocalizedError(language) {
  return localizeCopy(language, {
    en: 'I encountered an error processing your request. Please try again.',
    hi: 'Aapke request ko process karte waqt error aaya. Kripya dobara koshish kijiye.',
    as: 'Apunar request process korar somoy asubidha hol. Anugraha kore punorai chesta korok.',
  });
}

function getLocalizedActionError(language) {
  return localizeCopy(language, {
    en: 'I understood your request, but an error occurred while executing the action. Please try again or use the main module screen.',
    hi: 'Main aapka request samajh gaya tha, lekin action chalate waqt error aaya. Kripya dobara koshish kijiye ya main module screen ka upyog kijiye.',
    as: 'Moi apunar request bujhiparu, kintu action choluar somoy asubidha hol. Anugraha kore punorai chesta korok ba mul module screen byabohar korok.',
  });
}

function resolveDirectIntent(message, language = 'en') {
  const normalized = String(message || '').trim().toLowerCase();
  if (!normalized) return null;

  const intentKeywords = [
    {
      intent: 'greeting.welcome',
      keywords: ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'namaste', 'namaskar', 'नमस्ते', 'नमस्कार', 'নমস্কাৰ', 'নমস্কার'],
    },
    {
      intent: 'admission.create',
      keywords: ['admission', 'admit student', 'student admission', 'प्रवेश', 'भर्ती', 'ভৰ্তি', 'ছাত্ৰ ভৰ্তি'],
    },
    {
      intent: 'complaint.create',
      keywords: ['complaint', 'file complaint', 'raise complaint', 'शिकायत', 'অভিযোগ'],
    },
    {
      intent: 'canteen.getMenu',
      keywords: ['canteen menu', 'today menu', 'lunch menu', 'मेनू', 'आज का भोजन', 'মেনু', 'আজিৰ মেনু'],
    },
    {
      intent: 'notice.list',
      keywords: ['show notices', 'notice list', 'notice board', 'सूचना', 'নোটিছ'],
    },
    {
      intent: 'homework.list',
      keywords: ['homework', 'pending homework', 'गृहकार्य', 'होमवर्क', 'গৃহকাৰ্য', 'হোমৱৰ্ক'],
    },
  ];

  const preferred = language === 'hi'
    ? ['नमस्ते', 'नमस्कार', 'प्रवेश', 'भर्ती', 'शिकायत', 'मेनू', 'सूचना', 'गृहकार्य', 'होमवर्क']
    : language === 'as'
      ? ['নমস্কাৰ', 'নমস্কার', 'ভৰ্তি', 'ছাত্ৰ ভৰ্তি', 'অভিযোগ', 'মেনু', 'নোটিছ', 'গৃহকাৰ্য', 'হোমৱৰ্ক']
      : [];

  let bestIntent = null;
  let bestScore = 0;

  intentKeywords.forEach(({ intent, keywords }) => {
    let score = 0;
    keywords.forEach((keyword) => {
      if (normalized.includes(keyword.toLowerCase())) {
        score += preferred.includes(keyword) ? 3 : 1;
      }
    });

    if (score > bestScore) {
      bestScore = score;
      bestIntent = intent;
    }
  });

  return bestScore > 0 ? bestIntent : null;
}

// Clear old contexts (older than 1 hour)
function cleanupContexts() {
  const now = Date.now();
  const ONE_HOUR = 60 * 60 * 1000;

  for (const [userId, context] of conversationContext.entries()) {
    if (now - context.timestamp > ONE_HOUR) {
      conversationContext.delete(userId);
    }
  }
}


// ==================== PHASE 5: SMART FEATURES (17 features) ====================

// Feature 88: Spell correction dictionary
const SPELL_CORRECTIONS = {
  'atendance': 'attendance', 'attandance': 'attendance', 'attendence': 'attendance',
  'libary': 'library', 'librery': 'library', 'libray': 'library',
  'examm': 'exam', 'exame': 'exam', 'exm': 'exam',
  'hostal': 'hostel', 'hostl': 'hostel', 'hostle': 'hostel',
  'trasport': 'transport', 'transpor': 'transport', 'transportaion': 'transportation',
  'payrole': 'payroll', 'payrol': 'payroll', 'payrolee': 'payroll',
  'recipt': 'receipt', 'receit': 'receipt', 'reciept': 'receipt',
  'complant': 'complaint', 'complaints': 'complaint',
  'timetabel': 'timetable', 'scedule': 'schedule', 'shedule': 'schedule',
  'notic': 'notice', 'paymant': 'payment', 'salry': 'salary',
  'stident': 'student', 'studnet': 'student', 'stduent': 'student',
  'tehcer': 'teacher', 'techer': 'teacher', 'teachr': 'teacher',
  'fees': 'fee', 'dues': 'fee', 'fess': 'fee',
  'mark': 'marks', 'narks': 'marks', 'marrks': 'marks',
  'canteem': 'canteen', 'rout': 'route', 'rute': 'route',
};
function correctSpelling(text) {
  let corrected = text;
  for (const [wrong, right] of Object.entries(SPELL_CORRECTIONS)) {
    corrected = corrected.replace(new RegExp('\\b' + wrong + '\\b', 'gi'), right);
  }
  return corrected;
}

// Feature 89: Synonym expansion
const SYNONYMS = {
  'fee': ['dues', 'charges', 'payment', 'maasul', 'shulk'],
  'exam': ['test', 'paper', 'assessment', 'pariksha'],
  'attendance': ['present', 'absent', 'hajiri', 'upasthiti'],
  'library': ['books', 'kitab', 'pustakalay'],
  'marks': ['score', 'grade', 'result', 'number', 'nambar'],
  'holiday': ['vacation', 'break', 'chutti', 'chhutti'],
  'teacher': ['sir', 'maam', 'guru', 'shikshak'],
  'student': ['pupil', 'child', 'baccha', 'chatra'],
  'canteen': ['food', 'mess', 'lunch', 'khana', 'ahar'],
  'hostel': ['room', 'dormitory', 'boarding', 'awashan'],
  'transport': ['bus', 'vehicle', 'vahan', 'paribahan'],
  'payroll': ['salary', 'dadarma', 'tanakha'],
  'homework': ['assignment', 'home work', 'griha kaam'],
  'notice': ['announcement', 'janani', 'suchana'],
  'complaint': ['issue', 'problem', 'shikayat', 'abhiyog'],
  'timetable': ['routine', 'schedule', 'samay suchi'],
};
function expandSynonyms(text) {
  let expanded = text.toLowerCase();
  for (const [canonical, synonyms] of Object.entries(SYNONYMS)) {
    for (const syn of synonyms) {
      if (expanded.includes(syn.toLowerCase())) {
        expanded = expanded.replace(new RegExp(syn, 'gi'), canonical);
      }
    }
  }
  return expanded;
}

// Feature 87: Amount parsing
function parseAmount(text) {
  // Fix: Use proper regex to match currency symbols correctly
  const match = text.match(/(?:₹|Rs\.?|INR)?\s*(\d+)/i);
  if (match) return parseInt(match[1]);

  // Fix: Handle compound numbers correctly (e.g., "twenty five" = 25, not 20*5)
  const wordNums = {
    'hundred': 100, 'thousand': 1000,
    'one': 1, 'two': 2, 'three': 3, 'four': 4, 'five': 5,
    'six': 6, 'seven': 7, 'eight': 8, 'nine': 9, 'ten': 10,
    'eleven': 11, 'twelve': 12, 'thirteen': 13, 'fourteen': 14, 'fifteen': 15,
    'sixteen': 16, 'seventeen': 17, 'eighteen': 18, 'nineteen': 19,
    'twenty': 20, 'thirty': 30, 'forty': 40, 'fifty': 50,
    'sixty': 60, 'seventy': 70, 'eighty': 80, 'ninety': 90
  };
  const words = text.toLowerCase().split(/\s+/);
  let total = 0;
  let currentTotal = 0;

  for (const w of words) {
    const num = wordNums[w];
    if (!num) continue;

    if (num >= 100) {
      // Handle multipliers like hundred, thousand
      currentTotal = currentTotal || 1;
      currentTotal *= num;
    } else if (num < 100) {
      // Add small numbers (handles "twenty five" = 20 + 5)
      currentTotal += num;
    }

    // If we hit hundred or thousand, multiply the accumulated value
    if (num >= 100) {
      total = Math.max(total, currentTotal);
      currentTotal = 0;
    }
  }

  total += currentTotal;
  return total || 0;
}

// Feature 90: Multi-intent detection
function detectMultipleIntents(text) {
  const lower = text.toLowerCase();
  const detected = [];
  const intentKeywords = {
    'attendance.my': ['attendance', 'present', 'absent', 'hajiri'],
    'fee.my': ['fee', 'payment', 'due', 'balance', 'dues'],
    'exam.my': ['exam', 'test', 'result', 'marks', 'pariksha'],
    'homework.list': ['homework', 'assignment', 'griha'],
    'notice.list': ['notice', 'announcement', 'janani'],
    'library.my': ['book', 'library', 'kitab', 'borrow'],
    'transport.my': ['bus', 'transport', 'route', 'vahan'],
  };
  for (const [intent, keywords] of Object.entries(intentKeywords)) {
    if (keywords.some(kw => lower.includes(kw))) detected.push(intent);
  }
  return detected.length > 1 ? detected : null;
}

// Feature 92: Proactive alerts - now properly scoped to user
async function getProactiveAlerts(userId) {
  const alerts = [];
  if (!userId) return null;

  try {
    // Get student IDs linked to this user (for students/parents)
    const studentRecords = await prisma.student.findMany({
      where: { OR: [{ userId }, { parentUserId: userId }] },
      select: { id: true },
    });
    const studentIds = studentRecords.map(s => s.id);

    if (studentIds.length > 0) {
      // Check attendance for linked students only
      const [total, present] = await Promise.all([
        prisma.attendance.count({ where: { studentId: { in: studentIds } } }),
        prisma.attendance.count({ where: { studentId: { in: studentIds }, status: 'present' } }),
      ]);
      const pct = total > 0 ? Math.round((present / total) * 100) : 100;
      if (pct < 75) alerts.push('⚠️ Your attendance is below 75% (' + pct + '%)!');

      // Check overdue books for linked students only
      const overdue = await prisma.libraryTransaction.count({
        where: { studentId: { in: studentIds }, status: 'BORROWED', dueDate: { lt: new Date() } },
      });
      if (overdue > 0) alerts.push('📖 You have ' + overdue + ' overdue book(s)!');
    }
  } catch (e) { /* ignore */ }
  return alerts.length > 0 ? alerts.join('\n\n') : null;
}

// Run cleanup every 15 minutes
setInterval(cleanupContexts, 15 * 60 * 1000);

// Function to process user message with context support
async function processMessage(message, language = 'en', user = null) {
  const startTime = Date.now();
  const normalizedUser = getNormalizedUser(user);
  const userId = normalizedUser ? String(normalizedUser.id || normalizedUser._id) : 'anonymous';
  const supportedLanguages = ['en', 'hi', 'as'];
  const safeLanguage = supportedLanguages.includes(language) ? language : 'en';

  try {
    await ensureModelReady();

    // Refresh entities if cache is stale
    if (!isCacheValid()) {
      await loadEntitiesFromDatabase();
      addDynamicEntities();
    }

    // Get user context for conversation memory and rigidly assign the authenticated user to block arbitrary escalations
    const context = getUserContext(userId);
    context.user = normalizedUser;

    // Phase 5: Form State Machine Interception
    if (context.activeForm && actions[`${context.activeForm}.step`]) {
      try {
        const actionResult = await actions[`${context.activeForm}.step`](message, context, normalizedUser, safeLanguage);
        const responseTime = Date.now() - startTime;
        addAssistantMessage(userId, actionResult.message);
        return {
          intent: context.activeForm,
          message: actionResult.message,
          data: actionResult.data || null,
          context: context.activeForm,
          suggestions: actionResult.suggestions || null,
          responseTime,
          source: 'live'
        };
      } catch (err) {
        console.error(`Form step error for ${context.activeForm}:`, err);
        context.activeForm = null; // Reset on crash
        context.formData = {};
      }
    }

    // Pass the text through the NLP engine
    // Apply spell correction and synonym expansion (Phase 5)
    const correctedMessage = correctSpelling(message);
    const expandedMessage = expandSynonyms(correctedMessage);

    const directIntent = resolveDirectIntent(expandedMessage, safeLanguage);
    const response = directIntent
      ? { intent: directIntent, entities: [] }
      : await manager.process(safeLanguage, expandedMessage);

    // If an intent is matched and has a connected action, execute it
    if (response.intent && response.intent !== 'None') {
      // Extract entities from response
      const entities = response.entities || [];
      const entityMap = {};

      entities.forEach(entity => {
        if (entity.entity) {
          // Prefer utteranceText (actual matched text) over option (internal ID)
          const value = entity.utteranceText || entity.sourceText || entity.option || entity.entity;
          entityMap[entity.entity] = value;
        }
      });

      // Update context
      updateContext(userId, response.intent, entityMap, message);

      if (actions[response.intent]) {
        try {
          const actionResult = await actions[response.intent](entities, context, normalizedUser, safeLanguage);
          const responseTime = Date.now() - startTime;
          addAssistantMessage(userId, actionResult.message || `Action executed for ${response.intent}`);

          // Phase 4.4 Intent Chaining - Smart Context Follow-ups
          let smartSuggestions = actionResult.suggestions || null;
          if (!smartSuggestions) {
            if (response.intent === 'attendance.my') smartSuggestions = ['Attendance history', 'My fee status', 'Attendance percentage'];
            else if (response.intent === 'fee.my') smartSuggestions = ['Fee defaults', 'My attendance', 'Fee receipt'];
            else if (response.intent === 'library.my') smartSuggestions = ['Overdue books', 'Check book availability', 'Renew book'];
            else if (response.intent === 'exam.my') smartSuggestions = ['My results', 'Show timetable', 'Exam tips'];
            else if (response.intent === 'complaint.new') smartSuggestions = ['Complaint status'];
            else if (response.intent === 'leave.balance') smartSuggestions = ['Apply for leave'];
            else if (response.intent === 'transport.my') smartSuggestions = ['Bus timing', 'Track my bus', 'Driver contact'];
            else if (response.intent === 'canteen.recharge') smartSuggestions = ['Today menu', 'Canteen wallet'];
          }

          // NEW: Add proactive alerts to response
          let proactiveAlerts = '';
          try {
            const alerts = await getProactiveAlerts(normalizedUser?.id);
            if (alerts) {
              proactiveAlerts = alerts + '\n\n';
            }
          } catch (e) {
            // Silently ignore alert errors
          }

          return {
            intent: response.intent,
            message: proactiveAlerts + (actionResult.message || `Action executed for ${response.intent}`),
            data: actionResult.data || null,
            context: context.lastIntent,
            suggestions: smartSuggestions,
            responseTime,
            source: 'live'
          };
        } catch (err) {
          const responseTime = Date.now() - startTime;
          console.error(`Action error for intent ${response.intent}:`, err);
          return {
            intent: response.intent,
            message: getLocalizedActionError(safeLanguage),
            data: null,
            error: true,
            responseTime,
            source: 'live'
          };
        }
      } else {
        const fallbackMessage = localizeCopy(safeLanguage, {
          en: `I recognized your request (${response.intent}). This feature is still being completed.`,
          hi: `Maine aapka request (${response.intent}) pehchana, lekin yeh feature abhi poori tarah tayyar nahin hai.`,
          as: `Moi apunar request (${response.intent}) chinilu, kintu ei feature etiyao sampurno bhabe prostut nohoi.`,
        });
        addAssistantMessage(userId, fallbackMessage);
        return {
          intent: response.intent,
          message: fallbackMessage,
          data: null,
          responseTime: Date.now() - startTime,
          source: 'live'
        };
      }
    }

    // Check context for follow-up questions
    if (context.lastIntent) {
      // Try to understand as follow-up to last conversation
      const followUpResponse = handleFollowUp(context, message, safeLanguage);
      if (followUpResponse) {
        addAssistantMessage(userId, followUpResponse);
        return {
          intent: 'FOLLOW_UP',
          message: followUpResponse,
          context: context.lastIntent,
          responseTime: Date.now() - startTime,
          source: 'live'
        };
      }
    }

    // Fallback if no intent matches
    // Phase 4.1 Did You Mean? Feature
    const alternatives = detectMultipleIntents(expandedMessage);
    if (alternatives && alternatives.length > 0) {
      const suggestText = alternatives.map(a => a.split('.')[0]).join(' or ');
      const suggestionChips = alternatives.map(a => `Show my ${a.split('.')[0]}`);
      const dymMessage = localizeCopy(safeLanguage, {
        en: `I'm not completely sure what you mean. Did you mean to ask about **${suggestText}**?`,
        hi: `Main poori tarah nishchit nahin hoon. Kya aap **${suggestText}** ke baare mein poochna chahte the?`,
        as: `Moi etia purapuri nischit nohoi. Apuni **${suggestText}** bisoye sodhib bisarise ne?`,
      });
      addAssistantMessage(userId, dymMessage);
      return {
        intent: 'None',
        message: dymMessage,
        data: null,
        suggestions: suggestionChips,
        responseTime: Date.now() - startTime,
        source: 'live'
      };
    }

    const fallback = getLocalizedFallback(safeLanguage, normalizedUser?.role);
    const fallbackMessage = fallback.message;
    addAssistantMessage(userId, fallbackMessage);
    return {
      intent: 'None',
      message: fallbackMessage,
      data: null,
      suggestions: fallback.suggestions,
      responseTime: Date.now() - startTime,
      source: 'live'
    };
  } catch (err) {
    console.error('[NLP] Process message error:', err);
    return {
      intent: 'ERROR',
      message: getLocalizedError(safeLanguage),
      data: null,
      error: true,
      responseTime: Date.now() - startTime,
      source: 'live'
    };
  }
}

// Handle follow-up questions based on context
function handleFollowUp(context, message, language) {
  const lowerMessage = message.toLowerCase();

  // If last intent was about attendance and user asks "what about students?"
  if (context.lastIntent === 'hr.getAbsent' && lowerMessage.includes('student')) {
    return localizeCopy(language, {
      en: 'For student attendance, please use the Attendance module. Would you like help with marking student attendance?',
      hi: 'Student attendance ke liye Attendance module ka upyog kijiye. Kya aapko student attendance mark karne mein madad chahiye?',
      as: 'Chatrar attendance-r babe Attendance module byabohar korok. Chatrar attendance mark korat sahay lagibo ne?',
    });
  }

  // If last intent was about fees and user asks about payment methods
  if (context.lastIntent === 'fee.getDefaults' && (lowerMessage.includes('pay') || lowerMessage.includes('method'))) {
    return localizeCopy(language, {
      en: 'Fee payments can be made by Cash, Card, UPI, Bank Transfer, or Cheque. Would you like help with fee collection?',
      hi: 'Fee payment Cash, Card, UPI, Bank Transfer ya Cheque se kiya ja sakta hai. Kya aapko fee collection mein madad chahiye?',
      as: 'Fee payment Cash, Card, UPI, Bank Transfer ba Cheque-e kori paribo. Fee collection-r babe sahay lagibo ne?',
    });
  }

  if (context.lastIntent === 'notice.list' && (lowerMessage.includes('important') || lowerMessage.includes('urgent'))) {
    return localizeCopy(language, {
      en: 'Recent urgent notices appear at the top of the notices list. You can also open the Notices page and filter by priority.',
      hi: 'Recent urgent notices list ke top par dikhte hain. Aap Notices page kholkar priority ke hisaab se filter bhi kar sakte hain.',
      as: 'Recent urgent notices-talika-r uparot dekha jai. Apuni Notices page khuli priority anusare filter koribo paribo.',
    });
  }

  if ((context.lastIntent === 'homework.list' || context.lastIntent === 'homework.pending') && (lowerMessage.includes('due') || lowerMessage.includes('when'))) {
    return localizeCopy(language, {
      en: 'Homework entries include their due date. If you want only overdue work, ask for pending or overdue homework.',
      hi: 'Homework entries mein due date hoti hai. Agar aapko sirf overdue work chahiye to pending ya overdue homework poochhiye.',
      as: 'Homework entries-t due date thake. Jodi kebol overdue work lage, pending ba overdue homework buli sodhok.',
    });
  }

  if (context.lastIntent === 'exam.results' && (lowerMessage.includes('report') || lowerMessage.includes('card'))) {
    return localizeCopy(language, {
      en: 'For a full report card, open the Exams or Results page and generate the student report card PDF.',
      hi: 'Full report card ke liye Exams ya Results page kholiye aur student report card PDF generate kijiye.',
      as: 'Full report card-r babe Exams ba Results page khuli student report card PDF generate korok.',
    });
  }

  if (context.lastIntent === 'transport.my' && (lowerMessage.includes('driver') || lowerMessage.includes('contact'))) {
    return localizeCopy(language, {
      en: 'You can ask for the driver contact or bus details directly by bus number if transport data is configured.',
      hi: 'Agar transport data configured hai to aap bus number dekar driver contact ya bus details pooch sakte hain.',
      as: 'Jodi transport data configured thake, bus number diya driver contact ba bus details sodhi paribo.',
    });
  }

  // Generic follow-up for clarification
  if (lowerMessage.includes('what') || lowerMessage.includes('how') || lowerMessage.includes('why')) {
    return localizeCopy(language, {
      en: `Based on our conversation about ${context.lastIntent}, could you be more specific about what you'd like to know?`,
      hi: `${context.lastIntent} ke baare mein humari baat ko dekhte hue, kripya batayiye ki aapko kis cheez ki aur jankari chahiye?`,
      as: `${context.lastIntent} bisoye amader kotha-r upor bhitti kori, apunak ki bisoye aro spasto jani bole lagise?`,
    });
  }

  return null;
}

// Export context functions for external use
function getContext(userId) {
  return getUserContext(userId);
}

function clearContext(userId) {
  conversationContext.delete(userId);
}

module.exports = {
  trainChatbot,
  processMessage,
  getContext,
  clearContext,
  refreshEntities: loadEntitiesFromDatabase,
  getModelVersion,
  parseNaturalDate
};
