function isPlainObject(value) {
  return Boolean(value) && typeof value === 'object' && !Array.isArray(value) && !(value instanceof Date);
}

function withLegacyId(value) {
  if (Array.isArray(value)) {
    return value.map(withLegacyId);
  }

  if (!isPlainObject(value)) {
    return value;
  }

  const result = {};

  for (const [key, current] of Object.entries(value)) {
    result[key] = withLegacyId(current);
  }

  if (typeof value.id === 'string' && !Object.prototype.hasOwnProperty.call(result, '_id')) {
    result._id = value.id;
  }

  return result;
}

function toLegacyUser(user, { includePassword = false } = {}) {
  if (!user) {
    return null;
  }

  const base = { ...user };
  if (!includePassword) {
    delete base.password;
  }
  delete base.passwordResetTokenHash;
  delete base.passwordResetExpiresAt;

  return withLegacyId(base);
}

function toLegacyClassSubject(subject) {
  if (!subject) {
    return null;
  }

  return withLegacyId({
    ...subject,
    teacherId: subject.teacher ? toLegacyUser(subject.teacher) : subject.teacherId,
  });
}

function toLegacyClass(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    classTeacher: record.classTeacher ? toLegacyUser(record.classTeacher) : record.classTeacherId,
    subjects: Array.isArray(record.subjects) ? record.subjects.map(toLegacyClassSubject) : [],
  });
}

function toLegacyStudent(record) {
  if (!record) {
    return null;
  }

  const legacy = {
    ...record,
    userId: record.user ? toLegacyUser(record.user) : record.userId,
    classId: record.class ? toLegacyClass(record.class) : record.classId,
    parentUserId: record.parentUser ? toLegacyUser(record.parentUser) : record.parentUserId,
    canteenWallet: {
      balance: Number(record.canteenBalance || 0),
      rfidTagHex: record.rfidTagHex || null,
    },
  };

  delete legacy.user;
  delete legacy.class;
  delete legacy.parentUser;

  return withLegacyId(legacy);
}

function toLegacyNotification(record) {
  if (!record) {
    return null;
  }

  return withLegacyId({
    ...record,
    senderId: record.sender ? toLegacyUser(record.sender) : record.senderId,
    recipientId: record.recipient ? toLegacyUser(record.recipient) : record.recipientId,
  });
}

module.exports = {
  withLegacyId,
  toLegacyUser,
  toLegacyClass,
  toLegacyClassSubject,
  toLegacyStudent,
  toLegacyNotification,
};
