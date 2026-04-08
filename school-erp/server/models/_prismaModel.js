const prisma = require('../config/prisma');
const { withLegacyId, toLegacyStudent } = require('../utils/prismaCompat');

const prismaModelMap = {
  User: prisma.user,
  Student: prisma.student,
  Class: prisma.class,
  Attendance: prisma.attendance,
  StaffAttendance: prisma.staffAttendance,
  FeeStructure: prisma.feeStructure,
  FeePayment: prisma.feePayment,
  Exam: prisma.exam,
  ExamResult: prisma.examResult,
  LibraryBook: prisma.libraryBook,
  LibraryTransaction: prisma.libraryTransaction,
  CanteenItem: prisma.canteenItem,
  CanteenSale: prisma.canteenSale,
  HostelRoomType: prisma.hostelRoomType,
  HostelRoom: prisma.hostelRoom,
  HostelFeeStructure: prisma.hostelFeeStructure,
  HostelAllocation: prisma.hostelAllocation,
  TransportVehicle: prisma.transportVehicle,
  TransportAttendance: prisma.transportAttendance,
  BusRoute: prisma.busRoute,
  SalaryStructure: prisma.salaryStructure,
  Payroll: prisma.payroll,
  Notice: prisma.notice,
  Notification: prisma.notification,
  Complaint: prisma.complaint,
  Remark: prisma.remark,
  Homework: prisma.homework,
  Routine: prisma.routine,
  Leave: prisma.leave,
  Counter: prisma.counter,
  AuditLog: prisma.auditLog,
  ChatbotLog: prisma.chatbotLog,
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

const fieldAliases = {
  Notice: {
    relatedClassId: 'classId',
    relatedStudentId: 'studentId',
  },
  Class: {
    classTeacher: 'classTeacherId',
  },
};

function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value) && !(value instanceof Date);
}

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

  if (isPlainObject(value)) {
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
  if (!sort || typeof sort !== 'object') return undefined;
  return Object.entries(sort).map(([field, direction]) => ({
    [field === '_id' ? 'id' : field]: direction === -1 ? 'desc' : 'asc',
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
    const mapped = field === '_id' ? 'id' : field;
    acc[mapped] = true;
    return acc;
  }, {});
}

function resolveRelation(modelName, path) {
  return relationMap[modelName]?.[path] || null;
}

function buildInclude(populateMap) {
  if (!populateMap) return undefined;
  const include = {};
  Object.entries(populateMap).forEach(([_, relation]) => {
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

function normalizeRecord(modelName, record, populateMap) {
  if (!record) return record;
  const normalized = applyPopulateAliases(record, populateMap);
  if (modelName === 'Student') {
    return toLegacyStudent(normalized);
  }
  return withLegacyId(normalized);
}

function sanitizeUpdate(data) {
  if (!data || typeof data !== 'object') return data;
  const copy = Array.isArray(data) ? data.map(item => sanitizeUpdate(item)) : { ...data };
  if (!Array.isArray(copy)) {
    delete copy._id;
    delete copy.id;
    delete copy.createdAt;
    delete copy.updatedAt;
    delete copy.canteenWallet;
    Object.entries(copy).forEach(([key, value]) => {
      if (key.endsWith('Id') && value && typeof value === 'object') {
        copy[key] = value._id || value.id || value;
      }
    });
  }
  return copy;
}

function applyUpdateOperators(update = {}) {
  if (!update || typeof update !== 'object') return update;
  if ('$set' in update || '$unset' in update || '$inc' in update || '$addToSet' in update) {
    const data = {};
    if (update.$set && typeof update.$set === 'object') {
      Object.assign(data, update.$set);
    }
    if (update.$unset && typeof update.$unset === 'object') {
      Object.keys(update.$unset).forEach(key => {
        data[key] = null;
      });
    }
    if (update.$inc && typeof update.$inc === 'object') {
      Object.entries(update.$inc).forEach(([key, value]) => {
        data[key] = { increment: value };
      });
    }
    if (update.$addToSet && typeof update.$addToSet === 'object') {
      Object.entries(update.$addToSet).forEach(([key, value]) => {
        data[key] = { push: Array.isArray(value) ? value : [value] };
      });
    }
    return data;
  }
  return update;
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
    const query = { where: this.where };
    if (this.orderBy && this.orderBy.length) query.orderBy = this.orderBy;
    if (typeof this.take === 'number') query.take = this.take;

    if (this.selectFields) {
      query.select = this.selectFields;
    } else if (this.populateMap) {
      query.include = buildInclude(this.populateMap);
    }

    const result = this.single ? await this.model.findFirst(query) : await this.model.findMany(query);
    if (Array.isArray(result)) {
      return result.map(item => normalizeRecord(this.modelName, item, this.populateMap));
    }
    return normalizeRecord(this.modelName, result, this.populateMap);
  }

  then(resolve, reject) {
    return this.exec().then(resolve, reject);
  }
}

function createModelAdapter(modelName) {
  const model = prismaModelMap[modelName];
  if (!model) {
    throw new Error(`Unknown Prisma model: ${modelName}`);
  }

  class ModelAdapter {
    constructor(data = {}) {
      Object.assign(this, data);
    }

    async save() {
      const saved = await ModelAdapter.save(this);
      Object.assign(this, saved);
      return this;
    }

    toObject() {
      return { ...this };
    }
  }

  ModelAdapter.find = (where = {}) => new PrismaQueryBuilder(modelName, model, where, false);
  ModelAdapter.findOne = (where = {}) => new PrismaQueryBuilder(modelName, model, where, true);
  ModelAdapter.findById = (id) => new PrismaQueryBuilder(modelName, model, { id }, true);
  ModelAdapter.countDocuments = (where = {}) => model.count({ where: mapWhere(where, modelName) });
  ModelAdapter.distinct = async (field, where = {}) => {
      const data = await model.findMany({
        where: mapWhere(where, modelName),
        select: { [field]: true },
        distinct: [field],
      });
      return data.map(item => item[field]).filter(value => value !== null && value !== undefined);
    };
  ModelAdapter.aggregate = async (pipeline = []) => {
      const groupStage = pipeline.find(step => step.$group);
      if (!groupStage) return [];

      const groupField = String(groupStage.$group._id || '').replace('$', '');
      if (!groupField) return [];

      const grouped = await model.groupBy({
        by: [groupField],
        _count: { _all: true },
      });

      let result = grouped.map(item => ({
        _id: item[groupField],
        count: item._count._all,
      }));

      const sortStage = pipeline.find(step => step.$sort);
      if (sortStage && sortStage.$sort) {
        const [[field, direction]] = Object.entries(sortStage.$sort);
        if (field === 'count') {
          result.sort((a, b) => direction === -1 ? b.count - a.count : a.count - b.count);
        }
      }

      return result;
    };
  ModelAdapter.create = async (data) => normalizeRecord(modelName, await model.create({ data: sanitizeUpdate(data) }), null);
  ModelAdapter.insertMany = async (items, options = {}) => {
      if (!Array.isArray(items) || items.length === 0) return [];
      if (options?.ordered === false) {
        await model.createMany({ data: items.map(sanitizeUpdate), skipDuplicates: true });
        return items.map(item => normalizeRecord(modelName, item, null));
      }
      const created = [];
      for (const item of items) {
        created.push(await model.create({ data: sanitizeUpdate(item) }));
      }
      return created.map(item => normalizeRecord(modelName, item, null));
    };
  ModelAdapter.updateOne = async (where, update) => {
      const data = applyUpdateOperators(update);
      const record = await model.findFirst({ where: mapWhere(where, modelName) });
      if (!record) return { matchedCount: 0, modifiedCount: 0 };
      await model.update({ where: { id: record.id }, data: sanitizeUpdate(data) });
      return { matchedCount: 1, modifiedCount: 1 };
    };
  ModelAdapter.findOneAndUpdate = async (where, update, options = {}) => {
      const data = applyUpdateOperators(update);
      const existing = await model.findFirst({ where: mapWhere(where, modelName) });
      if (existing) {
        const updated = await model.update({ where: { id: existing.id }, data: sanitizeUpdate(data) });
        return normalizeRecord(modelName, updated, null);
      }
      if (options?.upsert) {
        const created = await model.create({ data: sanitizeUpdate({ ...(where || {}), ...(data || {}) }) });
        return normalizeRecord(modelName, created, null);
      }
      return null;
    };
  ModelAdapter.deleteMany = (args = {}) => {
      if (args.where) {
        return model.deleteMany({ where: mapWhere(args.where, modelName) });
      }
      return model.deleteMany({ where: mapWhere(args, modelName) });
    };
  ModelAdapter.updateMany = (args = {}) => {
      if (args.where || args.data) {
        return model.updateMany({
          where: mapWhere(args.where || {}, modelName),
          data: sanitizeUpdate(args.data || {}),
        });
      }
      const data = applyUpdateOperators(args);
      return model.updateMany({ data: sanitizeUpdate(data) });
    };
  ModelAdapter.save = async (record) => {
    if (!record) return record;
    const id = record._id || record.id;
    const data = sanitizeUpdate(record);
    if (id) {
      const updated = await model.update({ where: { id }, data });
      return normalizeRecord(modelName, updated, null);
    }
    const created = await model.create({ data });
    return normalizeRecord(modelName, created, null);
  };

  return ModelAdapter;
}

module.exports = createModelAdapter;
