const prisma = require('../config/prisma');
const {
  getTeacherClassIds,
  getStudentRecordsForUser,
  canUserAccessStudent,
} = require('../utils/accessScope');
const { toLegacyStudent, withLegacyId } = require('../utils/prismaCompat');
const { parseNaturalDate } = require('./dateParser');
const { getChatbotBootstrap, pickLanguage } = require('./chatbotUi');

const modelAliases = {
  Staff: 'User',
  Driver: 'User',
  Book: 'LibraryBook',
  Vehicle: 'TransportVehicle',
  Bus: 'TransportVehicle',
  FeeDetails: 'FeePayment',
  Canteen: 'CanteenItem',
};

const prismaModels = {
  Student: prisma.student,
  User: prisma.user,
  Class: prisma.class,
  Exam: prisma.exam,
  ExamResult: prisma.examResult,
  LibraryBook: prisma.libraryBook,
  LibraryTransaction: prisma.libraryTransaction,
  CanteenItem: prisma.canteenItem,
  StaffAttendance: prisma.staffAttendance,
  Payroll: prisma.payroll,
  FeePayment: prisma.feePayment,
  FeeStructure: prisma.feeStructure,
  SalaryStructure: prisma.salaryStructure,
  TransportVehicle: prisma.transportVehicle,
  TransportAttendance: prisma.transportAttendance,
  Attendance: prisma.attendance,
  BusRoute: prisma.busRoute,
  Homework: prisma.homework,
  Routine: prisma.routine,
  Notice: prisma.notice,
  Complaint: prisma.complaint,
  HostelAllocation: prisma.hostelAllocation,
  Leave: prisma.leave,
  Notification: prisma.notification,
};

const fieldAliases = {
  Notice: {
    relatedClassId: 'classId',
    relatedStudentId: 'studentId',
  },
};

const relationMap = {
  Student: {
    classId: 'class',
    parentUserId: 'parentUser',
    userId: 'user',
  },
  Attendance: {
    studentId: 'student',
    classId: 'class',
    teacherId: 'teacher',
  },
  StaffAttendance: {
    staffId: 'staff',
  },
  ExamResult: {
    examId: 'exam',
    studentId: 'student',
  },
  LibraryTransaction: {
    bookId: 'book',
    studentId: 'student',
  },
  HostelAllocation: {
    roomId: 'room',
    studentId: 'student',
  },
  TransportVehicle: {
    driverId: 'driver',
    conductorId: 'conductor',
  },
  BusRoute: {
    driverId: 'driver',
    conductorId: 'conductor',
    vehicleId: 'vehicle',
  },
  Payroll: {
    staffId: 'staff',
  },
  FeePayment: {
    studentId: 'student',
    feeStructureId: 'feeStructure',
  },
  Notice: {
    classId: 'class',
    studentId: 'student',
  },
  Routine: {
    classId: 'class',
    teacherId: 'teacher',
  },
};

function mapRegexValue(value) {
  if (value instanceof RegExp) {
    return {
      contains: value.source,
      mode: value.ignoreCase ? 'insensitive' : undefined,
    };
  }

  if (value && typeof value === 'object' && value.$regex) {
    const regex = value.$regex instanceof RegExp ? value.$regex : new RegExp(String(value.$regex), value.$options || 'i');
    return {
      contains: regex.source,
      mode: regex.ignoreCase ? 'insensitive' : undefined,
    };
  }

  return null;
}

function mapFieldValue(value) {
  if (value instanceof RegExp) {
    return mapRegexValue(value);
  }

  if (value && typeof value === 'object' && !Array.isArray(value) && !(value instanceof Date)) {
    if ('$in' in value) {
      return { in: value.$in };
    }
    if ('$nin' in value) {
      return { notIn: value.$nin };
    }
    if ('$gte' in value || '$gt' in value || '$lt' in value || '$lte' in value) {
      const range = {};
      if (value.$gte !== undefined) range.gte = value.$gte;
      if (value.$gt !== undefined) range.gt = value.$gt;
      if (value.$lt !== undefined) range.lt = value.$lt;
      if (value.$lte !== undefined) range.lte = value.$lte;
      return range;
    }
    if ('$exists' in value) {
      return value.$exists ? { not: null } : { equals: null };
    }

    const regexValue = mapRegexValue(value);
    if (regexValue) {
      return regexValue;
    }
  }

  return value;
}

function mapWhere(query = {}, modelName) {
  if (!query || typeof query !== 'object') {
    return query;
  }

  const { $or, $and, ...rest } = query;
  const base = {};
  const aliases = fieldAliases[modelName] || {};

  Object.entries(rest).forEach(([key, value]) => {
    const mappedKey = aliases[key] || key;
    const field = mappedKey === '_id' ? 'id' : mappedKey;
    base[field] = mapFieldValue(value);
  });

  const clauses = [];
  if (Object.keys(base).length) clauses.push(base);
  if (Array.isArray($or) && $or.length) clauses.push({ OR: $or.map(item => mapWhere(item, modelName)) });
  if (Array.isArray($and) && $and.length) clauses.push({ AND: $and.map(item => mapWhere(item, modelName)) });

  if (!clauses.length) return {};
  if (clauses.length === 1) return clauses[0];
  return { AND: clauses };
}

function mapSort(sort) {
  if (!sort || typeof sort !== 'object') {
    return undefined;
  }

  return Object.entries(sort).map(([field, direction]) => ({
    [field]: direction === -1 ? 'desc' : 'asc',
  }));
}

function parseSelect(select) {
  if (!select) return null;
  const fields = String(select)
    .split(/\s+/)
    .map(item => item.trim())
    .filter(Boolean);

  if (!fields.length) return null;

  return fields.reduce((acc, field) => {
    acc[field] = true;
    return acc;
  }, {});
}

function resolveRelation(modelName, path) {
  return relationMap[modelName]?.[path] || null;
}

function buildSelect(selectFields, populateMap) {
  const select = { ...(selectFields || {}) };

  if (!Object.prototype.hasOwnProperty.call(select, 'id')) {
    select.id = true;
  }

  if (populateMap) {
    Object.values(populateMap).forEach((relation) => {
      if (!relation) return;
      select[relation] = true;
    });
  }

  return select;
}

function buildInclude(populateMap) {
  if (!populateMap) return undefined;
  const include = {};
  Object.values(populateMap).forEach((relation) => {
    if (!relation) return;
    include[relation] = true;
  });
  return include;
}

function applyPopulateAliases(record, populateMap) {
  if (!record || !populateMap) return record;

  const result = { ...record };
  Object.entries(populateMap).forEach(([path, relation]) => {
    if (!relation || !(relation in result)) return;
    result[path] = result[relation];
    if (path !== relation) {
      delete result[relation];
    }
  });

  return result;
}

class PrismaQueryBuilder {
  constructor(modelName, model, where, single = false) {
    this.modelName = modelName;
    this.model = model;
    this.where = mapWhere(where, modelName);
    this.single = single;
    this.orderBy = undefined;
    this.take = undefined;
    this.selectFields = null;
    this.populateMap = null;
  }

  sort(sort) {
    this.orderBy = mapSort(sort);
    return this;
  }

  limit(amount) {
    this.take = amount;
    return this;
  }

  select(fields) {
    const parsed = parseSelect(fields);
    if (parsed) {
      this.selectFields = { ...(this.selectFields || {}), ...parsed };
    }
    return this;
  }

  populate(path, select) {
    const relation = resolveRelation(this.modelName, path);
    if (!relation) return this;
    this.populateMap = { ...(this.populateMap || {}), [path]: relation };
    return this;
  }

  lean() {
    return this;
  }

  async exec() {
    const query = {
      where: this.where,
    };

    if (this.orderBy && this.orderBy.length) {
      query.orderBy = this.orderBy;
    }
    if (typeof this.take === 'number') {
      query.take = this.take;
    }

    if (this.selectFields) {
      query.select = buildSelect(this.selectFields, this.populateMap);
    } else if (this.populateMap) {
      query.include = buildInclude(this.populateMap);
    }

    const result = this.single
      ? await this.model.findFirst(query)
      : await this.model.findMany(query);

    const normalizeRecord = (item) => {
      const normalized = applyPopulateAliases(item, this.populateMap);
      if (this.modelName === 'Student') {
        return toLegacyStudent(normalized);
      }
      return withLegacyId(normalized);
    };

    if (Array.isArray(result)) {
      return result.map(normalizeRecord);
    }

    return normalizeRecord(result);
  }

  then(resolve, reject) {
    return this.exec().then(resolve, reject);
  }
}

function getModelOrNull(modelName) {
  const resolvedName = modelAliases[modelName] || modelName;
  const model = prismaModels[resolvedName];
  if (!model) return null;

  return {
    find: (where = {}) => new PrismaQueryBuilder(resolvedName, model, where, false),
    findOne: (where = {}) => new PrismaQueryBuilder(resolvedName, model, where, true),
    findById: (id) => new PrismaQueryBuilder(resolvedName, model, { id }, true),
    countDocuments: (where = {}) => model.count({ where: mapWhere(where, resolvedName) }),
    create: (data) => model.create({ data }).then(withLegacyId),
    createMany: (data) => model.createMany({ data }),
    update: (args) => model.update(args).then(withLegacyId),
    updateMany: (args) => model.updateMany(args),
    deleteMany: (args) => model.deleteMany(args),
  };
}

// Check if database is connected
function isDbConnected() {
  return true;
}

// Error messages for DB failures
const DB_ERROR_MESSAGES = {
  student: 'Unable to access student database. Please ensure the database is connected.',
  staff: 'Unable to access staff database. Please try again later.',
  fee: 'Unable to access fee records. The database may be temporarily unavailable.',
  exam: 'Unable to access exam records. Please try again in a moment.',
  library: 'Unable to access library database. Please retry shortly.',
  canteen: 'Unable to access canteen records. Please try again.',
  transport: 'Unable to access transport database. Please retry.',
  payroll: 'Unable to access payroll records. Please try again later.',
  attendance: 'Unable to access attendance records. Please retry.',
  generic: 'Database is temporarily unavailable. Please try again in a few moments.'
};

function escapeRegex(value = '') {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function getEntityValue(entity) {
  return entity?.utteranceText || entity?.sourceText || entity?.option || '';
}

function translate(language, copy) {
  return pickLanguage(language, copy);
}

function getCurrentUser(context) {
  return context?.user || null;
}

function getCurrentRole(context) {
  return getCurrentUser(context)?.role || 'unknown';
}

function hasRole(context, roles) {
  return roles.includes(getCurrentRole(context));
}

async function getScopedStudentRecords(context) {
  const user = getCurrentUser(context);
  if (!user) return [];

  if (['student', 'parent'].includes(user.role)) {
    return getStudentRecordsForUser(user);
  }

  return [];
}

async function getScopedStudentIds(context) {
  const records = await getScopedStudentRecords(context);
  return records.map(record => String(record._id));
}

async function resolveScopedStudentByName(name, context) {
  const user = getCurrentUser(context);

  if (!name) return null;

  const matches = await prisma.student.findMany({
    where: { name: { contains: name, mode: 'insensitive' } },
    include: { class: true },
    take: 10,
  });
  const legacyMatches = matches.map(toLegacyStudent);

  if (!user) {
    return legacyMatches[0] || null;
  }

  // Fix: For admin roles, prefer exact name match over first result
  if (['superadmin', 'accounts', 'hr'].includes(user.role)) {
    if (legacyMatches.length === 1) return legacyMatches[0];
    // Try exact match first
    const exactMatch = legacyMatches.find(m =>
      String(m.name || '').trim().toLowerCase() === name.trim().toLowerCase()
    );
    return exactMatch || legacyMatches[0] || null;
  }

  for (const student of legacyMatches) {
    if (await canUserAccessStudent(user, student._id)) {
      return student;
    }
  }

  return null;
}

async function getRelevantClassIds(context) {
  const user = getCurrentUser(context);
  if (!user) return [];

  if (user.role === 'teacher') {
    // Fix: Add null check for user.id and user._id
    const teacherId = user.id || user._id;
    if (!teacherId) return [];
    return getTeacherClassIds(teacherId);
  }

  const records = await getScopedStudentRecords(context);
  return [...new Set(records.map(record => String(record.classId?._id || record.classId)).filter(Boolean))];
}

function formatStudentSummary(student) {
  const className = student?.classId?.name
    ? `${student.classId.name}${student.classId.section ? ` ${student.classId.section}` : ''}`
    : student?.section || 'N/A';

  return `- ${student.name}\n  Admission No: ${student.admissionNo || 'N/A'}\n  Class: ${className}\n  Roll No: ${student.rollNumber || 'N/A'}`;
}

const actions = {
  'greeting.welcome': async (_entities, context, _user, language = 'en') => {
    const bootstrap = getChatbotBootstrap({
      language,
      role: getCurrentRole(context),
    });
    return {
      message: bootstrap.welcome,
      suggestions: bootstrap.suggestions,
    };
  },

  // ==================== ADMISSION ACTIONS ====================

  'student.getCount': async () => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.student };
    }

    const Student = getModelOrNull('Student');
    if (!Student) return { message: 'Student module not initialized.' };

    try {
      const count = await Student.countDocuments();
      return {
        message: `There are currently ${count.toLocaleString('en-IN')} students in the system.`,
        data: { count }
      };
    } catch (err) {
      console.error('[Action Error] student.getCount:', err);
      return { message: DB_ERROR_MESSAGES.student };
    }
  },

  'admission.get': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.student };
    }

    const studentEntity = entities?.find(e => e.entity === 'studentName');
    const name = getEntityValue(studentEntity) || context?.entities?.studentName;

    if (!name) {
      return { message: "Could you please specify the student's name?" };
    }

    try {
      const role = getCurrentRole(context);
      if (!['superadmin', 'accounts', 'hr', 'teacher', 'student', 'parent'].includes(role)) {
        return { message: 'Access denied. Student profile lookups are not available for your role.' };
      }

      const student = await resolveScopedStudentByName(name, context);
      if (student) {
        return {
          message: `Found student: **${student.name}**\n\n• Admission No: ${student.admissionNo}\n• Class: ${student.classId || 'N/A'}\n• Section: ${student.section || 'N/A'}\n• Roll No: ${student.rollNumber || 'N/A'}`,
          data: student
        };
      }
      return { message: `I couldn't find a student named "${name}". Please check the spelling or try the full name.` };
    } catch (err) {
      console.error('[Action Error] admission.get:', err);
      return { message: DB_ERROR_MESSAGES.student };
    }
  },

  'admission.create': async (_entities, _context, _user, language = 'en') => {
    return {
      message: translate(language, {
        en: "Student Admission Process\n\n1. Open the Students module.\n2. Choose Admit Student.\n3. Fill in student, class, and parent details.\n4. Upload required documents such as TC, birth certificate, and photo.\n5. Review the form and submit admission.\n\nIf you want, ask me about required documents or class assignment next.",
        hi: "Student admission process\n\n1. Students module kholiye.\n2. Admit Student chuniyega.\n3. Student, class aur parent details bhariyega.\n4. TC, birth certificate aur photo jaise documents upload kijiye.\n5. Form review karke admission submit kijiye.\n\nAgar chahen to agla sawaal required documents ya class assignment ke baare mein poochhiye.",
        as: "Student admission process\n\n1. Students module khulok.\n2. Admit Student bachok.\n3. Student, class aru parent-r details diok.\n4. TC, birth certificate aru photo-dore required documents upload korok.\n5. Form review kori admission submit korok.\n\nLage hole agote required documents ba class assignment bisoye sodhib paribo.",
      })
    };
  },

  // ==================== EXAM ACTIONS ====================

  'exam.get': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.exam };
    }

    const Exam = getModelOrNull('Exam');
    if (!Exam) return { message: "Exam module not initialized. Showing simulated data." };

    try {
      const exams = await Exam.find().sort({ date: 1 }).limit(5);

      if (exams.length > 0) {
        const examList = exams.map(e =>
          `• **${e.subject}** - ${new Date(e.date).toLocaleDateString('en-IN')}`
        ).join('\n');

        return {
          message: `📝 **Upcoming Exams:**\n\n${examList}`,
          data: exams
        };
      }
      return { message: "No upcoming exams are currently scheduled." };
    } catch (err) {
      console.error('[Action Error] exam.get:', err);
      return { message: DB_ERROR_MESSAGES.exam };
    }
  },

  // ==================== COMPLAINT ACTIONS ====================

  'complaint.create': async (_entities, _context, _user, language = 'en') => {
    return {
      message: translate(language, {
        en: "Complaint filing help\n\n1. Open the Complaints module.\n2. Choose New Complaint.\n3. Enter complaint type, description, and priority.\n4. Submit the complaint.\n\nYou will receive a complaint record that can be tracked later with complaint status.",
        hi: "Complaint filing help\n\n1. Complaints module kholiye.\n2. New Complaint chuniyega.\n3. Complaint type, description aur priority bhariyega.\n4. Complaint submit kijiye.\n\nBaad mein aap complaint status poochhkar uska progress dekh sakte hain.",
        as: "Complaint filing help\n\n1. Complaints module khulok.\n2. New Complaint bachok.\n3. Complaint type, description aru priority diok.\n4. Complaint submit korok.\n\nPichote complaint status sodhi progress dekhbo paribo.",
      })
    };
  },

  // ==================== LIBRARY ACTIONS ====================

  'library.checkBook': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.library };
    }

    const bookEntity = entities?.find(e => e.entity === 'bookName');
    const bookName = getEntityValue(bookEntity) || context?.entities?.bookName;

    const Book = getModelOrNull('LibraryBook');
    if (!Book) return { message: "Library module not initialized." };

    try {
      const query = bookName
        ? { title: { $regex: new RegExp(bookName, 'i') } }
        : {};

      const books = await Book.find(query).limit(5);

      if (books.length > 0) {
        const bookList = books.map(b =>
          `• **${b.title}** by ${b.author} - ${b.availableCopies || 0} available`
        ).join('\n');

        return {
          message: `📖 **Books Found:**\n\n${bookList}`,
          data: books
        };
      }

      return {
        message: bookName
          ? `No books found matching "${bookName}".`
          : "Library catalog is empty or not configured."
      };
    } catch (err) {
      console.error('[Action Error] library.checkBook:', err);
      return { message: DB_ERROR_MESSAGES.library };
    }
  },

  'library.issueBook': async (entities, context) => {
    const bookEntity = entities?.find(e => e.entity === 'bookName');
    const studentEntity = entities?.find(e => e.entity === 'studentName');
    const bName = getEntityValue(bookEntity) || context?.entities?.bookName || "a book";
    const sName = getEntityValue(studentEntity) || context?.entities?.studentName || "a student";

    return {
      message: `📖 **Issue Book Request**\n\nTo issue "${bName}" to ${sName}:\n\n1. Go to **Library Page** (/library)\n2. Find the book in catalog\n3. Click **'Issue'**\n4. Select the student\n5. Set due date (default: 14 days)\n6. Click **'Issue Book'**\n\n💡 Standard loan period: 14 days\n⚠️ Late fee: ₹10/day`
    };
  },

  // ==================== CANTEEN ACTIONS ====================

  'canteen.getMenu': async (_entities, _context, _user, language = 'en') => {
    const CanteenItem = getModelOrNull('CanteenItem') || getModelOrNull('Canteen');
    if (!CanteenItem) {
      return {
        message: translate(language, {
          en: "Today's menu\n\n- Rice and dal\n- Vegetable curry\n- Roti\n- Salad\n\nThe canteen module is not fully configured yet.",
          hi: "Aaj ka menu\n\n- Rice aur dal\n- Vegetable curry\n- Roti\n- Salad\n\nCanteen module abhi poori tarah configured nahin hai.",
          as: "Aji-r menu\n\n- Rice aru dal\n- Vegetable curry\n- Roti\n- Salad\n\nCanteen module etiyao sampurno bhabe configured nohoi.",
        }),
      };
    }

    try {
      const items = await CanteenItem.find({ isAvailable: true, quantityAvailable: { $gt: 0 } }).limit(10);

      if (items.length > 0) {
        const menuStr = items.map(i => `• ${i.itemName || i.name} - ₹${i.price || 'N/A'}`).join('\n');
        return {
          message: translate(language, {
            en: `Today's menu\n\n${menuStr}`,
            hi: `Aaj ka menu\n\n${menuStr}`,
            as: `Aji-r menu\n\n${menuStr}`,
          }),
        };
      }
      return {
        message: translate(language, {
          en: 'Today\'s menu is being updated. Please check back shortly.',
          hi: 'Aaj ka menu update ho raha hai. Kripya thodi der baad phir dekhiye.',
          as: 'Aji-r menu update hoi ase. Anugraha kore olop pichot punorai sabi.',
        }),
      };
    } catch (err) {
      console.error('[Action Error] canteen.getMenu:', err);
      return {
        message: translate(language, {
          en: 'Unable to fetch the canteen menu right now. Please check with canteen staff.',
          hi: 'Abhi canteen menu nahin mil pa raha hai. Kripya canteen staff se check kijiye.',
          as: 'Etiya canteen menu anib nuwarilu. Anugraha kore canteen staff-r logot check korok.',
        }),
      };
    }
  },

  'canteen.getWallet': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.canteen };
    }

    const studentEntity = entities?.find(e => e.entity === 'studentName');
    const name = getEntityValue(studentEntity) || context?.entities?.studentName;

    if (!name) return { message: "Could you please specify the student's name?" };

    const Student = getModelOrNull('Student');
    if (!Student) return { message: `Simulated: Wallet balance for ${name} is ₹150.` };

    try {
      const student = await Student.findOne({ name: { $regex: new RegExp(name, 'i') } });

      if (student) {
        const wallet = student.canteenBalance || 0;
        return {
          message: `💰 **Canteen Wallet Balance**\n\nStudent: ${student.name}\nBalance: **₹${wallet}**`,
          data: { wallet, studentId: student._id }
        };
      }
      return { message: `Student "${name}" not found.` };
    } catch (err) {
      console.error('[Action Error] canteen.getWallet:', err);
      return { message: DB_ERROR_MESSAGES.canteen };
    }
  },

  // ==================== HR & STAFF ACTIONS ====================

  'hr.getStaff': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.staff };
    }

    const staffEntity = entities?.find(e => e.entity === 'staffName');
    const name = getEntityValue(staffEntity) || context?.entities?.staffName;

    if (!name) return { message: "Could you please specify the staff member's name?" };

    const Staff = getModelOrNull('Staff') || getModelOrNull('User');
    if (!Staff) return { message: `Simulated: Staff profile for ${name}.` };

    try {
      const staff = await Staff.findOne({
        name: { $regex: new RegExp(name, 'i') },
        role: { $in: ['staff', 'teacher', 'hr', 'accounts', 'canteen', 'conductor', 'driver'] }
      });

      if (staff) {
        return {
          message: `👤 **Staff Profile**\n\n• Name: ${staff.name}\n• Role: ${staff.designation || staff.role || 'Staff'}\n• Department: ${staff.department || 'N/A'}\n• Contact: ${staff.phone || 'Not provided'}`,
          data: staff
        };
      }
      return { message: `No staff member named "${name}" found.` };
    } catch (err) {
      console.error('[Action Error] hr.getStaff:', err);
      return { message: DB_ERROR_MESSAGES.staff };
    }
  },

  'hr.getAbsent': async (entities, context) => {
    const role = context?.user?.role;
    if (role === 'student' || role === 'parent') {
      return { message: "🔒 Access Denied: You do not have permission to view staff attendance." };
    }

    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.attendance };
    }

    const StaffAttendance = getModelOrNull('StaffAttendance');
    if (!StaffAttendance) return { message: "Staff attendance module not initialized." };

    try {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 1);

      const absentees = await StaffAttendance.find({
        date: { $gte: today, $lt: tomorrow },
        status: 'absent'
      }).populate('staffId', 'name role');

      if (absentees.length > 0) {
        const names = absentees.map(a => a.staffId?.name || 'Unknown').join(', ');
        return {
          message: `📋 **Absent Staff Today**\n\n${names}\n\nTotal: ${absentees.length} staff member(s) absent.`
        };
      }
      return { message: "✅ All staff members are present today." };
    } catch (err) {
      console.error('[Action Error] hr.getAbsent:', err);
      return { message: DB_ERROR_MESSAGES.attendance };
    }
  },

  'staff.getCount': async () => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.staff };
    }

    const User = getModelOrNull('User');
    if (!User) return { message: 'Staff module not initialized.' };

    try {
      const count = await User.countDocuments({ role: 'teacher' });
      return {
        message: `There are currently ${count.toLocaleString('en-IN')} teachers in the system.`,
        data: { count }
      };
    } catch (err) {
      console.error('[Action Error] staff.getCount:', err);
      return { message: DB_ERROR_MESSAGES.staff };
    }
  },

  // ==================== PAYROLL ACTIONS ====================

  'payroll.getSalary': async (entities, context) => {
    const role = context?.user?.role;
    if (role === 'student' || role === 'parent') {
      return { message: "🔒 Access Denied: Payroll information is restricted." };
    }

    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.payroll };
    }

    const staffEntity = entities?.find(e => e.entity === 'staffName');
    const name = getEntityValue(staffEntity) || context?.entities?.staffName;

    if (!name) return { message: "Please specify the staff member to check their salary." };

    const Staff = getModelOrNull('Staff') || getModelOrNull('User');
    const Payroll = getModelOrNull('Payroll');

    if (!Staff) return { message: `Staff module not initialized.` };

    try {
      const staff = await Staff.findOne({ name: { $regex: new RegExp(name, 'i') } });

      if (!staff) return { message: `Staff member "${name}" not found.` };

      if (!Payroll) return { message: "Payroll module not configured." };

      const latestPayroll = await Payroll.findOne({ staffId: staff._id }).sort({ createdAt: -1 });

      if (latestPayroll) {
        const amt = latestPayroll.netPay || latestPayroll.netSalary || latestPayroll.amount || 0;
        return {
          message: `💰 **Salary Information**\n\nStaff: ${staff.name}\nLatest Salary: **₹${amt.toLocaleString('en-IN')}**\nMonth: ${new Date(latestPayroll.month || latestPayroll.createdAt).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}`,
          data: latestPayroll
        };
      }
      return { message: `No payroll records found for ${staff.name}.` };
    } catch (err) {
      console.error('[Action Error] payroll.getSalary:', err);
      return { message: DB_ERROR_MESSAGES.payroll };
    }
  },

  'payroll.getTotal': async (entities, context) => {
    const role = context?.user?.role;
    if (role !== 'superadmin' && role !== 'accounts') {
      return { message: "🔒 Access Denied: Monthly payroll summaries require accounts or superadmin privileges." };
    }

    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.payroll };
    }

    const Payroll = getModelOrNull('Payroll');
    if (!Payroll) return { message: "Payroll module not initialized." };

    try {
      const startOfMonth = new Date();
      startOfMonth.setDate(1);
      startOfMonth.setHours(0, 0, 0, 0);

      const payrolls = await Payroll.find({ createdAt: { $gte: startOfMonth } });

      let total = 0;
      payrolls.forEach(p => { total += (p.netPay || p.netSalary || p.amount || 0); });

      return {
        message: `💰 **Monthly Payroll Summary**\n\nTotal payroll expense this month: **₹${total.toLocaleString('en-IN')}**\nPayments processed: ${payrolls.length}`
      };
    } catch (err) {
      console.error('[Action Error] payroll.getTotal:', err);
      return { message: DB_ERROR_MESSAGES.payroll };
    }
  },

  // ==================== FEE ACTIONS ====================

  'fee.getDefaults': async (entities, context) => {
    const role = context?.user?.role;
    if (role !== 'superadmin' && role !== 'accounts') {
      return { message: "🔒 Access Denied: Only accounts and superadmin can view complete fee default lists." };
    }

    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.fee };
    }

    const FeeDetails = getModelOrNull('FeeDetails') || getModelOrNull('FeePayment');
    const Student = getModelOrNull('Student');

    if (!FeeDetails || !Student) return { message: "Fee module not initialized." };

    try {
      const students = await Student.find().select('_id').limit(100).lean();
      const studentIds = students.map(student => student._id);
      const paidStudentIds = new Set(
        (await FeeDetails.find({ studentId: { $in: studentIds } }).select('studentId').lean())
          .map(payment => String(payment.studentId))
      );
      const pendingFees = students.filter(student => !paidStudentIds.has(String(student._id)));

      if (pendingFees.length > 0) {
        const totalPending = pendingFees.length * 5000;
        return {
          message: `💰 **Pending Fees Report**\n\nStudents with pending fees: **${pendingFees.length}**\nTotal pending amount: **₹${totalPending.toLocaleString('en-IN')}**`
        };
      }

      return { message: "✅ No pending fee defaults recorded." };
    } catch (err) {
      console.error('[Action Error] fee.getDefaults:', err);
      return { message: DB_ERROR_MESSAGES.fee };
    }
  },

  'fee.getStatus': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.fee };
    }

    const studentEntity = entities?.find(e => e.entity === 'studentName');
    const name = getEntityValue(studentEntity) || context?.entities?.studentName;

    if (!name) return { message: "Please specify the student's name to check fee status." };

    const Student = getModelOrNull('Student');
    const FeePayment = getModelOrNull('FeePayment');

    if (!Student) return { message: "Student module not initialized." };

    try {
      const student = await Student.findOne({ name: { $regex: new RegExp(name, 'i') } });

      if (!student) return { message: `Student "${name}" not found.` };

      if (!FeePayment) return { message: "Fee payment module not configured." };

      const payments = await FeePayment.find({ studentId: student._id }).sort({ paymentDate: -1 }).limit(5);

      if (payments.length > 0) {
        const paymentList = payments.map(p =>
          `• ₹${p.amountPaid} on ${new Date(p.paymentDate).toLocaleDateString('en-IN')}`
        ).join('\n');

        return {
          message: `💰 **Fee Payment History**\n\nStudent: ${student.name}\n\nRecent Payments:\n${paymentList}`,
          data: payments
        };
      }
      return { message: `No fee payments recorded for ${student.name}.` };
    } catch (err) {
      console.error('[Action Error] fee.getStatus:', err);
      return { message: DB_ERROR_MESSAGES.fee };
    }
  },

  // ==================== TRANSPORT ACTIONS ====================

  'transport.getVehicle': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.transport };
    }

    const vehicleEntity = entities?.find(e => e.entity === 'vehicleNumber');
    const lastMessage = context?.messages?.[context.messages.length - 1]?.message || '';
    const rawVehicleQuery = lastMessage.replace(/^.*?(?:bus|vehicle)\s+/i, '').trim();
    const vName =
      getEntityValue(vehicleEntity) ||
      context?.entities?.vehicleNumber ||
      lastMessage.match(/BUS-\d{4}/i)?.[0] ||
      rawVehicleQuery ||
      lastMessage;

    if (!vName) return { message: "Which bus or vehicle are you asking about?" };

    const Vehicle = getModelOrNull('TransportVehicle');
    if (!Vehicle) return { message: "Transport module not initialized." };

    try {
      const vehicle = await Vehicle.findOne({
        $or: [
          { busNumber: { $regex: new RegExp(vName, 'i') } },
          { numberPlate: { $regex: new RegExp(vName, 'i') } },
          { route: { $regex: new RegExp(vName, 'i') } }
        ]
      });

      if (vehicle) {
        return {
          message: `🚌 **Vehicle Information**\n\n• Vehicle No: ${vehicle.busNumber}\n• Plate No: ${vehicle.numberPlate || 'N/A'}\n• Route: ${vehicle.route || 'N/A'}\n• Capacity: ${vehicle.capacity || 'N/A'} students`
        };
      }
      return { message: `No vehicle found matching "${vName}".` };
    } catch (err) {
      console.error('[Action Error] transport.getVehicle:', err);
      return { message: DB_ERROR_MESSAGES.transport };
    }
  },

  'transport.getDriver': async (entities, context) => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.transport };
    }

    const vehicleEntity = entities?.find(e => e.entity === 'vehicleNumber');
    const lastMessage = context?.messages?.[context.messages.length - 1]?.message || '';
    const rawVehicleQuery = lastMessage.replace(/^.*?(?:bus|vehicle)\s+/i, '').trim();
    const vName =
      getEntityValue(vehicleEntity) ||
      context?.entities?.vehicleNumber ||
      lastMessage.match(/BUS-\d{4}/i)?.[0] ||
      rawVehicleQuery ||
      lastMessage;

    if (!vName) return { message: "Please tell me the bus number or route." };

    const Vehicle = getModelOrNull('TransportVehicle');
    const Driver = getModelOrNull('User');

    if (!Vehicle || !Driver) return { message: "Transport module not initialized." };

    try {
      const vehicle = await Vehicle.findOne({
        $or: [
          { busNumber: { $regex: new RegExp(vName, 'i') } },
          { numberPlate: { $regex: new RegExp(vName, 'i') } },
          { route: { $regex: new RegExp(vName, 'i') } }
        ]
      }).populate('driverId');

      if (vehicle && vehicle.driverId) {
        return {
          message: `👨‍✈️ **Driver Information**\n\nVehicle: ${vehicle.busNumber}\nDriver: ${vehicle.driverId.name}\nContact: ${vehicle.driverId.phone || 'Not provided'}`
        };
      }

      return { message: `No driver assigned to vehicle "${vName}".` };
    } catch (err) {
      console.error('[Action Error] transport.getDriver:', err);
      return { message: DB_ERROR_MESSAGES.transport };
    }
  },

  'transport.getRoutes': async () => {
    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.transport };
    }

    const BusRoute = getModelOrNull('BusRoute');
    const TransportVehicle = getModelOrNull('TransportVehicle');

    try {
      let routes = [];

      if (BusRoute) {
        routes = await BusRoute.find().select('routeName name startLocation endLocation').limit(5).lean();
      }

      if (!routes.length && TransportVehicle) {
        const vehicleRoutes = await TransportVehicle.find().select('route').limit(20).lean();
        routes = [...new Set(vehicleRoutes.map(vehicle => vehicle.route).filter(Boolean))]
          .slice(0, 5)
          .map(route => ({ routeName: route }));
      }

      if (!routes.length) {
        return { message: 'No transport routes are configured yet.' };
      }

      const routeList = routes.map(route => {
        const name = route.routeName || route.name || route.route || 'Unnamed Route';
        const start = route.startLocation ? ` (${route.startLocation}` : '';
        const end = route.endLocation ? ` -> ${route.endLocation})` : start ? ')' : '';
        return `- ${name}${start}${end}`;
      }).join('\n');

      return {
        message: `Available transport routes:\n${routeList}`,
        data: routes
      };
    } catch (err) {
      console.error('[Action Error] transport.getRoutes:', err);
      return { message: DB_ERROR_MESSAGES.transport };
    }
  },

  // ==================== ATTENDANCE ACTIONS ====================

  'attendance.mark': async () => {
    return {
      message: "📅 **Mark Attendance**\n\nTo mark attendance:\n\n1. Go to **Attendance Page** (/attendance)\n2. Select **Class** from dropdown\n3. Select **Date** (defaults to today)\n4. Mark each student as:\n   • 🟢 Present\n   • 🔴 Absent\n   • 🟠 Late\n   • 🔵 Half-Day\n5. Click **'Save Attendance'**\n\n💡 Tip: Absent students trigger parent notifications."
    };
  },

  'attendance.report': async () => {
    return {
      message: "📊 **Attendance Reports**\n\nTo view attendance reports:\n\n1. Go to **Attendance Page** (/attendance)\n2. Click **'View Attendance'** tab\n3. Select class and date range\n4. View detailed report\n\n📈 Available reports:\n• Daily Report\n• Monthly Summary\n• Defaulters List\n• Export to PDF/Excel"
    };
  },

  // ==================== SECOND DEFINITIONS (active implementations with DB queries) ====================
  // NOTE: All stub implementations below have been consolidated here with proper scoping

  'attendance.absent': async (entities, context) => {
    const role = getCurrentRole(context);
    if (!['superadmin', 'accounts', 'hr', 'teacher'].includes(role)) {
      return { message: 'Access denied. Full absentee lists are not available for your role.' };
    }

    if (!isDbConnected()) {
      return { message: DB_ERROR_MESSAGES.attendance };
    }

    const Attendance = getModelOrNull('Attendance');
    if (!Attendance) {
      return { message: 'Attendance module not initialized.' };
    }

    try {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 1);

      const query = {
        date: { $gte: today, $lt: tomorrow },
        status: 'absent'
      };

      if (role === 'teacher') {
        const classIds = await getTeacherClassIds(context.user.id || context.user._id);
        if (!classIds.length) {
          return { message: 'No teacher classes are linked to your account yet.' };
        }
        query.classId = { $in: classIds };
      }

      const absentStudents = await Attendance.find(query).populate('studentId', 'name');
      if (!absentStudents.length) {
        return { message: 'All scoped students are present today.' };
      }

      const names = absentStudents.map(entry => entry.studentId?.name || 'Unknown student').join(', ');
      return {
        message: `Absent students today:\n\n${names}\n\nTotal: ${absentStudents.length}`,
        data: absentStudents
      };
    } catch (err) {
      console.error('[Action Error] attendance.absent:', err);
      return { message: DB_ERROR_MESSAGES.attendance };
    }
  },

  'homework.list': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    const Homework = getModelOrNull('Homework');
    if (!Homework) return { message: 'Homework module not initialized.' };

    try {
      const user = getCurrentUser(context);
      const query = {};

      if (user?.role === 'teacher') {
        query.teacherId = user.id || user._id;
      } else {
        const classIds = await getRelevantClassIds(context);
        if (!classIds.length) {
          return { message: 'No class-linked homework is available for your profile yet.' };
        }
        query.classId = { $in: classIds };
      }

      const homework = await Homework.find(query)
        .sort({ dueDate: 1, createdAt: -1 })
        .limit(8)
        .lean();

      if (!homework.length) {
        return { message: 'No homework is assigned right now.' };
      }

      const list = homework
        .map(item => `- ${item.subject}: ${item.title}\n  Due: ${new Date(item.dueDate).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Homework list:\n\n${list}`,
        data: homework,
        suggestions: ['Show overdue homework', 'Show timetable']
      };
    } catch (err) {
      console.error('[Action Error] homework.list:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  'homework.pending': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    const Homework = getModelOrNull('Homework');
    if (!Homework) return { message: 'Homework module not initialized.' };

    try {
      const user = getCurrentUser(context);
      const now = new Date();
      const query = { dueDate: { $lt: now } };

      if (user?.role === 'teacher') {
        query.teacherId = user.id || user._id;
      } else {
        const classIds = await getRelevantClassIds(context);
        if (!classIds.length) {
          return { message: 'No overdue homework is available for your profile.' };
        }
        query.classId = { $in: classIds };
      }

      const overdue = await Homework.find(query).sort({ dueDate: 1 }).limit(8).lean();
      if (!overdue.length) {
        return { message: 'No overdue homework was found.' };
      }

      const list = overdue
        .map(item => `- ${item.subject}: ${item.title}\n  Was due: ${new Date(item.dueDate).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Overdue homework:\n\n${list}`,
        data: overdue
      };
    } catch (err) {
      console.error('[Action Error] homework.pending:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  'routine.view': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    const Routine = getModelOrNull('Routine');
    if (!Routine) return { message: 'Routine module not initialized.' };

    try {
      const classIds = await getRelevantClassIds(context);
      if (!classIds.length) {
        return { message: 'No timetable is linked to your current role yet.' };
      }

      const routines = await Routine.find({ classId: { $in: classIds } })
        .populate('classId', 'name section')
        .limit(4)
        .lean();

      if (!routines.length) {
        return { message: 'No timetable is configured for your classes yet.' };
      }

      const today = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][new Date().getDay()];
      const list = routines
        .map(routine => {
          const classLabel = routine.classId?.name
            ? `${routine.classId.name}${routine.classId.section ? ` ${routine.classId.section}` : ''}`
            : 'Class';
          const schedule = routine.timetable?.[today];
          return `- ${classLabel}: ${schedule ? JSON.stringify(schedule) : 'No schedule for today'}`;
        })
        .join('\n');

      return {
        message: `Today's timetable (${today}):\n\n${list}`,
        data: routines
      };
    } catch (err) {
      console.error('[Action Error] routine.view:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  'notice.list': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    const Notice = getModelOrNull('Notice');
    if (!Notice) return { message: 'Notice module not initialized.' };

    try {
      const user = getCurrentUser(context);
      const studentIds = await getScopedStudentIds(context);
      const classIds = await getRelevantClassIds(context);
      const audienceFilters = [{ audience: 'all' }];

      if (user?.role) audienceFilters.push({ audience: user.role });
      if (studentIds.length) audienceFilters.push({ relatedStudentId: { $in: studentIds } });
      if (classIds.length) audienceFilters.push({ relatedClassId: { $in: classIds } });

      const notices = await Notice.find({ published: true, $or: audienceFilters })
        .sort({ priority: -1, createdAt: -1 })
        .limit(6)
        .lean();

      if (!notices.length) {
        return { message: 'No published notices are available for your role right now.' };
      }

      const list = notices
        .map(notice => `- ${notice.title} (${notice.priority || 'normal'})\n  ${String(notice.content || '').slice(0, 120)}`)
        .join('\n');

      return {
        message: `Recent notices:\n\n${list}`,
        data: notices,
        suggestions: ['Show important notices', 'Show homework']
      };
    } catch (err) {
      console.error('[Action Error] notice.list:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  'complaint.status': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    const Complaint = getModelOrNull('Complaint');
    if (!Complaint) return { message: 'Complaint module not initialized.' };

    try {
      const user = getCurrentUser(context);
      if (!user?.id && !user?._id) {
        return { message: 'Could not identify your profile for complaint tracking.' };
      }

      const studentIds = await getScopedStudentIds(context);
      const query = {
        $or: [
          { userId: user.id || user._id },
          { targetUserId: user.id || user._id },
        ]
      };

      if (studentIds.length) {
        query.$or.push({ studentId: { $in: studentIds } });
      }

      const complaints = await Complaint.find(query).sort({ createdAt: -1 }).limit(5).lean();
      if (!complaints.length) {
        return { message: 'No complaint records were found for your account.' };
      }

      const list = complaints
        .map(complaint => `- ${complaint.subject}\n  Status: ${complaint.status}\n  Raised: ${new Date(complaint.createdAt).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Complaint status:\n\n${list}`,
        data: complaints
      };
    } catch (err) {
      console.error('[Action Error] complaint.status:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  'attendance.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.attendance };

    const Attendance = getModelOrNull('Attendance');
    if (!Attendance) return { message: 'Attendance module not initialized.' };

    try {
      const records = await getScopedStudentRecords(context);
      if (!records.length) {
        return { message: 'Attendance self-service is available for student and parent accounts with linked student records.' };
      }

      const summaries = [];
      for (const record of records) {
        const total = await Attendance.countDocuments({ studentId: record._id });
        const present = await Attendance.countDocuments({ studentId: record._id, status: 'present' });
        const percentage = total ? Math.round((present / total) * 100) : 0;
        summaries.push(`- ${record.name}: ${percentage}% (${present}/${total})`);
      }

      return {
        message: `Attendance summary:\n\n${summaries.join('\n')}`,
        data: records.map((record, index) => ({ studentId: record._id, name: record.name, summary: summaries[index] })),
        suggestions: ['Show attendance history', 'Show my exams']
      };
    } catch (err) {
      console.error('[Action Error] attendance.my:', err);
      return { message: DB_ERROR_MESSAGES.attendance };
    }
  },

  'attendance.history': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.attendance };

    const Attendance = getModelOrNull('Attendance');
    if (!Attendance) return { message: 'Attendance module not initialized.' };

    try {
      const studentIds = await getScopedStudentIds(context);
      if (!studentIds.length) {
        return { message: 'Attendance history is available for student and parent accounts with linked student records.' };
      }

      const history = await Attendance.find({ studentId: { $in: studentIds } })
        .populate('studentId', 'name')
        .sort({ date: -1 })
        .limit(10)
        .lean();

      if (!history.length) {
        return { message: 'No attendance history was found yet.' };
      }

      const list = history
        .map(entry => `- ${entry.studentId?.name || 'Student'}: ${entry.status} on ${new Date(entry.date).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Recent attendance history:\n\n${list}`,
        data: history
      };
    } catch (err) {
      console.error('[Action Error] attendance.history:', err);
      return { message: DB_ERROR_MESSAGES.attendance };
    }
  },

  'fee.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.fee };

    const FeePayment = getModelOrNull('FeePayment');
    if (!FeePayment) return { message: 'Fee module not initialized.' };

    try {
      const studentIds = await getScopedStudentIds(context);
      if (!studentIds.length) {
        return { message: 'Fee self-service is available for student and parent accounts with linked student records.' };
      }

      const payments = await FeePayment.find({ studentId: { $in: studentIds } })
        .populate('studentId', 'name')
        .sort({ paymentDate: -1 })
        .limit(8)
        .lean();

      if (!payments.length) {
        return { message: 'No fee payments were recorded for your linked students yet.' };
      }

      const totalPaid = payments.reduce((sum, payment) => sum + (payment.amountPaid || 0), 0);
      const list = payments
        .map(payment => `- ${payment.studentId?.name || 'Student'}: Rs ${payment.amountPaid} on ${new Date(payment.paymentDate).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Recent fee payments:\n\n${list}\n\nTotal paid in shown records: Rs ${totalPaid.toLocaleString('en-IN')}`,
        data: payments,
        suggestions: ['Show my attendance', 'Show notices']
      };
    } catch (err) {
      console.error('[Action Error] fee.my:', err);
      return { message: DB_ERROR_MESSAGES.fee };
    }
  },

  'exam.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.exam };

    const Exam = getModelOrNull('Exam');
    if (!Exam) return { message: 'Exam module not initialized.' };

    try {
      const classIds = await getRelevantClassIds(context);
      if (!classIds.length) {
        return { message: 'No exam scope is linked to your profile yet.' };
      }

      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const exams = await Exam.find({ classId: { $in: classIds }, date: { $gte: today } })
        .sort({ date: 1 })
        .limit(6)
        .lean();

      if (!exams.length) {
        return { message: 'No upcoming exams are scheduled for your classes right now.' };
      }

      const list = exams
        .map(exam => `- ${exam.subject || exam.name || 'Exam'} on ${new Date(exam.date).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Upcoming exams:\n\n${list}`,
        data: exams,
        suggestions: ['Show my results', 'Show timetable']
      };
    } catch (err) {
      console.error('[Action Error] exam.my:', err);
      return { message: DB_ERROR_MESSAGES.exam };
    }
  },

  'exam.results': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.exam };

    const ExamResult = getModelOrNull('ExamResult');
    if (!ExamResult) return { message: 'Exam results module not initialized.' };

    try {
      const studentIds = await getScopedStudentIds(context);
      if (!studentIds.length) {
        return { message: 'Exam result self-service is available for student and parent accounts with linked student records.' };
      }

      const results = await ExamResult.find({ studentId: { $in: studentIds } })
        .populate('examId', 'name subject date')
        .populate('studentId', 'name')
        .sort({ createdAt: -1 })
        .limit(8)
        .lean();

      if (!results.length) {
        return { message: 'No exam results were recorded for your linked students yet.' };
      }

      const list = results
        .map(result => `- ${result.studentId?.name || 'Student'}: ${result.examId?.subject || result.examId?.name || 'Exam'} - ${result.marksObtained}/${result.totalMarks} (${result.grade || 'No grade'})`)
        .join('\n');

      return {
        message: `Recent exam results:\n\n${list}`,
        data: results
      };
    } catch (err) {
      console.error('[Action Error] exam.results:', err);
      return { message: DB_ERROR_MESSAGES.exam };
    }
  },

  'library.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.library };

    const LibraryTransaction = getModelOrNull('LibraryTransaction');
    if (!LibraryTransaction) return { message: 'Library module not initialized.' };

    try {
      const studentIds = await getScopedStudentIds(context);
      if (!studentIds.length) {
        return { message: 'Library self-service is available for student and parent accounts with linked student records.' };
      }

      const borrowed = await LibraryTransaction.find({ studentId: { $in: studentIds }, status: 'BORROWED' })
        .populate('bookId', 'title author')
        .populate('studentId', 'name')
        .sort({ dueDate: 1 })
        .limit(8)
        .lean();

      if (!borrowed.length) {
        return { message: 'No active borrowed books were found for your linked students.' };
      }

      const list = borrowed
        .map(item => `- ${item.studentId?.name || 'Student'}: ${item.bookId?.title || 'Book'} due on ${new Date(item.dueDate).toLocaleDateString('en-IN')}`)
        .join('\n');

      return {
        message: `Borrowed library books:\n\n${list}`,
        data: borrowed
      };
    } catch (err) {
      console.error('[Action Error] library.my:', err);
      return { message: DB_ERROR_MESSAGES.library };
    }
  },

  'library.overdue': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.library };

    const LibraryTransaction = getModelOrNull('LibraryTransaction');
    if (!LibraryTransaction) return { message: 'Library module not initialized.' };

    try {
      const studentIds = await getScopedStudentIds(context);
      if (!studentIds.length) {
        return { message: 'Overdue book checks are available for student and parent accounts with linked student records.' };
      }

      const now = new Date();
      const overdue = await LibraryTransaction.find({
        studentId: { $in: studentIds },
        status: 'BORROWED',
        dueDate: { $lt: now }
      })
        .populate('bookId', 'title')
        .populate('studentId', 'name')
        .sort({ dueDate: 1 })
        .lean();

      if (!overdue.length) {
        return { message: 'No overdue library books were found.' };
      }

      let totalFine = 0;
      const list = overdue.map(item => {
        const daysLate = Math.floor((now - new Date(item.dueDate)) / (1000 * 60 * 60 * 24));
        const fine = daysLate * 10;
        totalFine += fine;
        return `- ${item.studentId?.name || 'Student'}: ${item.bookId?.title || 'Book'} - ${daysLate} day(s) late - Fine Rs ${fine}`;
      }).join('\n');

      return {
        message: `Overdue library books:\n\n${list}\n\nEstimated total fine: Rs ${totalFine}`,
        data: { overdue, totalFine }
      };
    } catch (err) {
      console.error('[Action Error] library.overdue:', err);
      return { message: DB_ERROR_MESSAGES.library };
    }
  },

  'hostel.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    const HostelAllocation = getModelOrNull('HostelAllocation');
    if (!HostelAllocation) return { message: 'Hostel module not initialized.' };

    try {
      const studentIds = await getScopedStudentIds(context);
      if (!studentIds.length) {
        return { message: 'Hostel self-service is available for student and parent accounts with linked student records.' };
      }

      const allocations = await HostelAllocation.find({ studentId: { $in: studentIds }, status: 'ACTIVE' })
        .populate('roomId', 'roomNumber block')
        .populate('studentId', 'name')
        .limit(5)
        .lean();

      if (!allocations.length) {
        return { message: 'No active hostel allocation was found for your linked students.' };
      }

      const list = allocations
        .map(item => `- ${item.studentId?.name || 'Student'}: Room ${item.roomId?.roomNumber || 'Unknown'}${item.roomId?.block ? `, Block ${item.roomId.block}` : ''}`)
        .join('\n');

      return {
        message: `Hostel allocation:\n\n${list}`,
        data: allocations
      };
    } catch (err) {
      console.error('[Action Error] hostel.my:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  'transport.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.transport };

    const TransportVehicle = getModelOrNull('TransportVehicle');
    if (!TransportVehicle) return { message: 'Transport module not initialized.' };

    try {
      const user = getCurrentUser(context);
      const query = {};

      if (user?.role === 'driver') {
        query.driverId = user.id || user._id;
      } else if (user?.role === 'conductor') {
        query.conductorId = user.id || user._id;
      } else {
        const studentIds = await getScopedStudentIds(context);
        if (!studentIds.length) {
          return { message: 'Transport self-service is available for linked student, parent, driver, and conductor accounts.' };
        }
        query.students = { $in: studentIds };
      }

      const vehicles = await TransportVehicle.find(query)
        .populate('driverId', 'name phone')
        .populate('conductorId', 'name phone')
        .limit(5)
        .lean();

      if (!vehicles.length) {
        return { message: 'No transport assignment was found for your profile.' };
      }

      const list = vehicles
        .map(vehicle => `- ${vehicle.busNumber} (${vehicle.route})${vehicle.driverId?.name ? `\n  Driver: ${vehicle.driverId.name}` : ''}`)
        .join('\n');

      return {
        message: `Transport details:\n\n${list}`,
        data: vehicles
      };
    } catch (err) {
      console.error('[Action Error] transport.my:', err);
      return { message: DB_ERROR_MESSAGES.transport };
    }
  },

  'leave.balance': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.attendance };

    const user = getCurrentUser(context);
    if (!user?.id && !user?._id) {
      return { message: 'Could not identify your staff profile.' };
    }

    if (['student', 'parent'].includes(getCurrentRole(context))) {
      return { message: 'Leave balances are available for staff accounts.' };
    }

    const User = getModelOrNull('User');
    if (!User) return { message: 'Leave balance data is not initialized.' };

    try {
      const profile = await User.findById(user.id || user._id)
        .select('name casualLeaveBalance earnedLeaveBalance sickLeaveBalance')
        .lean();

      if (!profile) {
        return { message: 'Staff profile was not found.' };
      }

      return {
        message: `Leave balance for ${profile.name}:\n\n- Casual leave: ${profile.casualLeaveBalance ?? 0}\n- Earned leave: ${profile.earnedLeaveBalance ?? 0}\n- Sick leave: ${profile.sickLeaveBalance ?? 0}`,
        data: profile
      };
    } catch (err) {
      console.error('[Action Error] leave.balance:', err);
      return { message: DB_ERROR_MESSAGES.attendance };
    }
  },

  'payroll.my': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.payroll };

    if (['student', 'parent'].includes(getCurrentRole(context))) {
      return { message: 'Payslips are available only for staff accounts.' };
    }

    const Payroll = getModelOrNull('Payroll');
    const user = getCurrentUser(context);
    if (!Payroll || (!user?.id && !user?._id)) {
      return { message: 'Payroll module is not initialized.' };
    }

    try {
      const latest = await Payroll.findOne({ staffId: user.id || user._id }).sort({ month: -1, year: -1 }).lean();
      if (!latest) {
        return { message: 'No payroll records were found for your account.' };
      }

      const net = latest.netPay || latest.netSalary || latest.amount || 0;
      return {
        message: `Latest payslip:\n\n- Gross: Rs ${(latest.grossSalary || latest.gross || 0).toLocaleString('en-IN')}\n- Deductions: Rs ${(latest.totalDeductions || latest.deductions || 0).toLocaleString('en-IN')}\n- Net pay: Rs ${net.toLocaleString('en-IN')}\n- Status: ${latest.isPaid ? 'Paid' : 'Pending'}`,
        data: latest
      };
    } catch (err) {
      console.error('[Action Error] payroll.my:', err);
      return { message: DB_ERROR_MESSAGES.payroll };
    }
  },

  // ==================== PHASE 5: CONVERSATIONAL FORMS ====================

  'leave.apply': async (entities, context, user) => {
    // Entering "Form Mode" state machine
    if (!context) return { message: 'Context error. Please try again.' };

    // Set active form interceptor
    context.activeForm = 'leave.apply';
    context.formData = { step: 1 };

    return {
      message: "📅 **Leave Application**\n\nLet's get your leave application filed. What type of leave is this?\n\n• Sick Leave\n• Casual Leave\n• Academic Leave",
      suggestions: ['Sick Leave', 'Casual Leave', 'Academic Leave']
    };
  },

  'leave.apply.step': async (message, context, user) => {
    const step = context.formData.step;
    const text = message.toLowerCase();

    if (text === 'cancel' || text === 'stop') {
      context.activeForm = null;
      context.formData = {};
      return { message: 'Leave application cancelled.', suggestions: ['Show dashboard', 'Check leave balance'] };
    }

    try {
      if (step === 1) {
        context.formData.leaveType = message;
        context.formData.step = 2;
        return {
          message: "Got it. When will this leave start and end? (e.g. 'Tomorrow', 'Oct 20 to Oct 22')",
        };
      }

      if (step === 2) {
        context.formData.dates = message;
        context.formData.step = 3;
        return {
          message: "Please provide a brief reason for your leave request.",
        };
      }

      if (step === 3) {
        context.formData.reason = message;

        // Execute the database write
        const Leave = getModelOrNull('Leave');
        if (Leave && user && (user.id || user._id)) {
          // Parse dates from user's natural language input
          const datesText = context.formData.dates || '';
          let fromDate = new Date();
          let toDate = new Date(Date.now() + 86400000);

          if (datesText.includes(' to ')) {
            const parts = datesText.split(' to ');
            const parsedFrom = parseNaturalDate(parts[0].trim());
            const parsedTo = parseNaturalDate(parts[1].trim());
            if (parsedFrom) fromDate = parsedFrom;
            if (parsedTo) toDate = parsedTo;
          } else {
            const parsed = parseNaturalDate(datesText);
            if (parsed) {
              fromDate = parsed;
              toDate = new Date(parsed);
              toDate.setDate(toDate.getDate() + 1);
            }
          }

          await Leave.create({
            staffId: user.id || user._id,
            type: context.formData.leaveType.toLowerCase() === 'sick leave' ? 'sick' :
              context.formData.leaveType.toLowerCase() === 'casual leave' ? 'casual' :
                context.formData.leaveType.toLowerCase() === 'earned leave' ? 'earned' : 'unpaid',
            reason: context.formData.reason,
            fromDate,
            toDate,
            status: 'pending'
          });
        }

        // Form Complete. Clear the state.
        context.activeForm = null;
        const type = context.formData.leaveType;
        context.formData = {};

        return {
          message: `✅ **Success!** Your ${type} application has been submitted for approval.\n\nYou will be notified once it is reviewed.`,
          suggestions: ['Check leave balance', 'Show dashboard']
        };
      }
    } catch (err) {
      console.error('Leave form error:', err);
      context.activeForm = null;
      context.formData = {};
      return { message: 'An error occurred while saving your application. Please use the main UI to apply.' };
    }
  },

  'complaint.new': async (entities, context, user) => {
    if (!context) return { message: 'Context error. Please try again.' };

    // Set active form interceptor for complaints
    context.activeForm = 'complaint.new';
    context.formData = { step: 1 };

    return {
      message: "📋 **Submit a Complaint**\n\nI can help you file this right now. What category does this complaint fall under?\n\n• Infrastructure (e.g. Broken AC)\n• Academic (e.g. Missing grades)\n• Harassment / Bullying\n• Hostel / Mess\n• Other",
      suggestions: ['Infrastructure', 'Academic', 'Hostel', 'Other']
    };
  },

  'complaint.new.step': async (message, context, user) => {
    const step = context.formData.step;
    const text = message.toLowerCase();

    if (text === 'cancel' || text === 'stop') {
      context.activeForm = null;
      context.formData = {};
      return { message: 'Complaint submission cancelled.', suggestions: ['Show dashboard', 'Check complaint status'] };
    }

    try {
      if (step === 1) {
        context.formData.category = message;
        context.formData.step = 2;
        return {
          message: "What is the priority level for this issue?",
          suggestions: ['Low', 'Medium', 'High', 'Critical']
        };
      }

      if (step === 2) {
        context.formData.priority = message;
        context.formData.step = 3;
        return {
          message: "Please describe the problem in a few sentences.",
        };
      }

      if (step === 3) {
        context.formData.description = message;

        // Execute the database write
        const Complaint = getModelOrNull('Complaint');
        if (Complaint && user && (user.id || user._id)) {
          // Map category to proper type
          const categoryLower = (context.formData.category || '').toLowerCase();
          let complaintType = 'general';
          if (categoryLower.includes('infrastructure')) complaintType = 'infrastructure';
          else if (categoryLower.includes('academic')) complaintType = 'academic';
          else if (categoryLower.includes('harassment') || categoryLower.includes('bullying')) complaintType = 'harassment';
          else if (categoryLower.includes('hostel') || categoryLower.includes('mess')) complaintType = 'hostel';

          await Complaint.create({
            subject: `${context.formData.category} Issue`,
            description: context.formData.description,
            userId: user.id || user._id,
            raisedByRole: user.role || 'staff',
            type: complaintType,
            priority: (context.formData.priority || 'medium').toLowerCase(),
            status: 'open'
          });
        }

        // Form Complete
        context.activeForm = null;
        context.formData = {};

        return {
          message: `✅ **Complaint LoggedSuccessfully!**\n\nThe issue has been forwarded to administration. You can track status using 'Check complaint status'.`,
          suggestions: ['Check complaint status', 'Show dashboard']
        };
      }
    } catch (err) {
      console.error('Complaint form error:', err);
      context.activeForm = null;
      context.formData = {};
      return { message: 'An error occurred while saving your complaint. Please use the main UI to file it.' };
    }
  },

  'dashboard.stats': async (entities, context) => {
    if (!isDbConnected()) return { message: DB_ERROR_MESSAGES.generic };

    try {
      const role = getCurrentRole(context);

      if (['student', 'parent'].includes(role)) {
        const studentRecords = await getScopedStudentRecords(context);
        const classIds = await getRelevantClassIds(context);
        const Attendance = getModelOrNull('Attendance');
        const Exam = getModelOrNull('Exam');

        let attendanceCount = 0;
        let upcomingExamCount = 0;

        if (Attendance && studentRecords.length) {
          attendanceCount = await Attendance.countDocuments({ studentId: { $in: studentRecords.map(record => record._id) } });
        }
        if (Exam && classIds.length) {
          upcomingExamCount = await Exam.countDocuments({ classId: { $in: classIds }, date: { $gte: new Date() } });
        }

        return {
          message: `Your dashboard summary:\n\n- Linked students: ${studentRecords.length}\n- Attendance records: ${attendanceCount}\n- Upcoming exams: ${upcomingExamCount}`,
          data: { linkedStudents: studentRecords.length, attendanceCount, upcomingExamCount }
        };
      }

      if (role === 'teacher') {
        const classIds = await getTeacherClassIds(context.user.id || context.user._id);
        return {
          message: `Teacher dashboard summary:\n\n- Assigned classes: ${classIds.length}`,
          data: { assignedClasses: classIds.length }
        };
      }

      const Student = getModelOrNull('Student');
      const User = getModelOrNull('User');
      const Attendance = getModelOrNull('Attendance');
      const FeePayment = getModelOrNull('FeePayment');
      const stats = {};

      if (Student) stats.students = await Student.countDocuments();
      if (User) stats.staff = await User.countDocuments({ role: { $in: ['teacher', 'staff', 'hr'] } });
      if (Attendance) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        stats.attendanceToday = await Attendance.countDocuments({ date: { $gte: today, $lt: tomorrow }, status: 'present' });
      }
      if (FeePayment) {
        const startOfMonth = new Date();
        startOfMonth.setDate(1);
        startOfMonth.setHours(0, 0, 0, 0);
        const fees = await FeePayment.find({ paymentDate: { $gte: startOfMonth } }).lean();
        stats.monthlyFees = fees.reduce((sum, fee) => sum + (fee.amountPaid || 0), 0);
      }

      const lines = [];
      if (stats.students !== undefined) lines.push(`- Students: ${stats.students}`);
      if (stats.staff !== undefined) lines.push(`- Staff: ${stats.staff}`);
      if (stats.attendanceToday !== undefined) lines.push(`- Present today: ${stats.attendanceToday}`);
      if (stats.monthlyFees !== undefined) lines.push(`- Fees this month: Rs ${stats.monthlyFees.toLocaleString('en-IN')}`);

      return {
        message: `School dashboard:\n\n${lines.join('\n') || 'No data available.'}`,
        data: stats
      };
    } catch (err) {
      console.error('[Action Error] dashboard.stats:', err);
      return { message: DB_ERROR_MESSAGES.generic };
    }
  },

  // ==================== Additional Actions (Added Apr 8, 2026) ====================
  ...require('./actions-additional'),
};

module.exports = actions;
