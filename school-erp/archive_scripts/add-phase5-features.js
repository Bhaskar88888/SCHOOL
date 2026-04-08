const fs = require('fs');
const path = require('path');

const nlpPath = path.join(__dirname, 'server', 'ai', 'nlpEngine.js');
let content = fs.readFileSync(nlpPath, 'utf8');

// Check if Phase 5 features are already added
if (content.includes('PHASE 5: SMART FEATURES')) {
  console.log('Phase 5 smart features already present. Skipping.');
  process.exit(0);
}

// Find the position to insert the new code - after cleanupContexts function
const insertMarker = '// Run cleanup every 15 minutes';
const insertPos = content.indexOf(insertMarker);

if (insertPos === -1) {
  console.error('ERROR: Could not find insertion point in nlpEngine.js');
  process.exit(1);
}

const phase5Code = `
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
    corrected = corrected.replace(new RegExp('\\\\b' + wrong + '\\\\b', 'gi'), right);
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
  'teacher': ['sir', 'ma\\\\'am', 'guru', 'shikshak'],
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

// Feature 86: Natural language date parsing
function parseNaturalDate(text) {
  const now = new Date();
  const lower = text.toLowerCase();
  if (lower === 'today') return now;
  if (lower === 'tomorrow') { const d = new Date(now); d.setDate(d.getDate() + 1); return d; }
  if (lower === 'yesterday') { const d = new Date(now); d.setDate(d.getDate() - 1); return d; }
  if (lower.includes('next monday')) { const d = new Date(now); d.setDate(d.getDate() + ((1 - d.getDay() + 7) % 7 || 7)); return d; }
  if (lower.includes('last week')) { const d = new Date(now); d.setDate(d.getDate() - 7); return d; }
  if (lower.includes('this week')) { const d = new Date(now); d.setDate(d.getDate() - d.getDay()); return d; }
  if (lower.includes('last month')) { const d = new Date(now); d.setMonth(d.getMonth() - 1); return d; }
  if (lower.includes('this month')) { const d = new Date(now); d.setDate(1); return d; }
  const match = text.match(/(\\d{1,2})[\\/\\s-](\\d{1,2})[\\/\\s-](\\d{4})/);
  if (match) return new Date(match[3], match[2] - 1, match[1]);
  return null;
}

// Feature 87: Amount parsing
function parseAmount(text) {
  const match = text.match(/[₹Rs.]*\\s*(\\d+)/);
  if (match) return parseInt(match[1]);
  const wordNums = { 'hundred': 100, 'thousand': 1000, 'five': 5, 'ten': 10, 'twenty': 20, 'fifty': 50 };
  const words = text.toLowerCase().split(' ');
  let total = 0;
  for (const w of words) { if (wordNums[w]) total = total ? total * wordNums[w] : wordNums[w]; }
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

// Feature 92: Proactive alerts
async function getProactiveAlerts(userId) {
  const alerts = [];
  try {
    const Attendance = getModelOrNull('Attendance');
    if (Attendance) {
      const total = await Attendance.countDocuments();
      const present = await Attendance.countDocuments({ status: 'present' });
      const pct = total > 0 ? Math.round((present / total) * 100) : 100;
      if (pct < 75) alerts.push('⚠️ Your attendance is below 75% (' + pct + '%)!');
    }
    const LibraryTransaction = getModelOrNull('LibraryTransaction');
    if (LibraryTransaction) {
      const overdue = await LibraryTransaction.countDocuments({ status: 'BORROWED', dueDate: { $lt: new Date() } });
      if (overdue > 0) alerts.push('📖 You have ' + overdue + ' overdue book(s)!');
    }
  } catch (e) { /* ignore */ }
  return alerts.length > 0 ? alerts.join('\\n\\n') : null;
}

`;

// Insert the Phase 5 code
content = content.slice(0, insertPos) + phase5Code + content.slice(insertPos);

// Now modify processMessage to use spell correction and synonym expansion
// Find the line: const response = await manager.process(language || 'en', message);
const processLine = "const response = await manager.process(language || 'en', message);";
const replacementLine = `// Apply spell correction and synonym expansion (Phase 5)
    const correctedMessage = correctSpelling(message);
    const expandedMessage = expandSynonyms(correctedMessage);
    const response = await manager.process(language || 'en', expandedMessage);`;

if (content.includes(processLine)) {
  content = content.replace(processLine, replacementLine);
  console.log('✓ Modified processMessage to use spell correction and synonym expansion');
} else {
  console.log('⚠ Could not find processMessage line to modify');
}

fs.writeFileSync(nlpPath, content);
console.log('\n✓ Phase 5 smart features added to nlpEngine.js');
console.log('  - Feature 88: Spell correction (30+ common typos)');
console.log('  - Feature 89: Synonym expansion (16 word groups)');
console.log('  - Feature 86: Natural language date parsing');
console.log('  - Feature 87: Amount parsing');
console.log('  - Feature 90: Multi-intent detection');
console.log('  - Feature 92: Proactive alerts');
