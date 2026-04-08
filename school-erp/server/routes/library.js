const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { getStudentRecordsForUser } = require('../utils/accessScope');
const { isValidIsbn } = require('../utils/security');
const { withLegacyId, toLegacyClass, toLegacyStudent } = require('../utils/prismaCompat');

function sanitizeIsbn(value) {
  return (value || '').toString().replace(/[^0-9Xx]/g, '').trim();
}

function toLegacyLibraryBook(record) {
  return record ? withLegacyId(record) : null;
}

function toLegacyLibraryTransaction(record) {
  if (!record) return null;
  return withLegacyId({
    ...record,
    studentId: record.student ? toLegacyStudent(record.student) : record.studentId,
    bookId: record.book ? withLegacyId(record.book) : record.bookId,
  });
}

async function paginatedBooks(query, page = 1, limit = 50) {
  const skip = (page - 1) * limit;
  const [data, total] = await Promise.all([
    prisma.libraryBook.findMany({
      where: query,
      orderBy: { createdAt: 'desc' },
      skip,
      take: limit,
    }),
    prisma.libraryBook.count({ where: query }),
  ]);
  const totalPages = Math.ceil(total / limit) || 1;
  return {
    data: data.map(toLegacyLibraryBook),
    books: data.map(toLegacyLibraryBook),
    pagination: {
      total,
      totalPages,
      currentPage: page,
      hasNextPage: page < totalPages,
      hasPrevPage: page > 1,
    },
  };
}

router.get('/dashboard', auth, async (req, res) => {
  try {
    let transactionsQuery = {};
    if (['student', 'parent'].includes(req.user.role)) {
      const linkedStudents = await getStudentRecordsForUser(req.user);
      const studentIds = linkedStudents.map(item => item._id || item.id);
      transactionsQuery = { studentId: { in: studentIds } };
    }

    const [booksCount, transactionsCount, activeLoansCount] = await Promise.all([
      prisma.libraryBook.count(),
      prisma.libraryTransaction.count({ where: transactionsQuery }),
      prisma.libraryTransaction.count({
        where: {
          ...transactionsQuery,
          status: { in: ['BORROWED', 'issued'] },
        },
      }),
    ]);

    res.json({ booksCount, transactionsCount, activeLoansCount });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/', auth, async (req, res) => {
  try {
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(100, Math.max(1, parseInt(req.query.limit) || 50));
    const search = req.query.search ? req.query.search.trim() : '';
    const query = search
      ? {
        OR: [
          { title: { contains: search, mode: 'insensitive' } },
          { author: { contains: search, mode: 'insensitive' } },
          { isbn: { contains: search, mode: 'insensitive' } },
        ],
      }
      : {};
    res.json(await paginatedBooks(query, page, limit));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/books', auth, async (req, res) => {
  try {
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(100, Math.max(1, parseInt(req.query.limit) || 50));
    const search = req.query.search ? req.query.search.trim() : '';
    const query = search
      ? {
        OR: [
          { title: { contains: search, mode: 'insensitive' } },
          { author: { contains: search, mode: 'insensitive' } },
          { isbn: { contains: search, mode: 'insensitive' } },
        ],
      }
      : {};
    res.json(await paginatedBooks(query, page, limit));
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.get('/transactions', auth, async (req, res) => {
  try {
    let query = {};

    if (['student', 'parent'].includes(req.user.role)) {
      const linkedStudents = await getStudentRecordsForUser(req.user);
      const studentIds = linkedStudents.map(item => item._id || item.id);
      query = { studentId: { in: studentIds } };
    } else if (!['superadmin', 'teacher', 'staff', 'hr'].includes(req.user.role)) {
      return res.status(403).json({ msg: 'Access denied' });
    }

    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(100, Math.max(1, parseInt(req.query.limit) || 50));
    const skip = (page - 1) * limit;

    const [total, transactions] = await Promise.all([
      prisma.libraryTransaction.count({ where: query }),
      prisma.libraryTransaction.findMany({
        where: query,
        include: {
          student: {
            include: { class: { select: { id: true, name: true, section: true } } },
          },
          book: { select: { id: true, title: true, author: true, isbn: true } },
        },
        orderBy: { createdAt: 'desc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: transactions.map(toLegacyLibraryTransaction),
      transactions: transactions.map(toLegacyLibraryTransaction),
      pagination: {
        total,
        totalPages: Math.ceil(total / limit) || 1,
        currentPage: page,
        hasNextPage: page * limit < total,
        hasPrevPage: page > 1,
      },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/scan', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (req, res) => {
  try {
    const isbn = sanitizeIsbn(req.body.isbn);
    if (!isbn) {
      return res.status(400).json({ msg: 'ISBN is required.' });
    }
    if (!isValidIsbn(isbn)) {
      return res.status(400).json({ msg: 'Provide a valid ISBN-10 or ISBN-13.' });
    }

    const response = await fetch(
      `https://openlibrary.org/api/books?bibkeys=ISBN:${encodeURIComponent(isbn)}&format=json&jscmd=data`,
      { signal: AbortSignal.timeout(5000) }
    );
    const data = await response.json();
    const bookData = data[`ISBN:${isbn}`];

    if (!bookData) {
      return res.status(404).json({ msg: 'Book not found in public registry. Add it manually.' });
    }

    const title = bookData.title;
    const author = bookData.authors?.[0]?.name || 'Unknown Author';
    const coverImageUrl = bookData.cover?.large || bookData.cover?.medium || null;

    const existing = await prisma.libraryBook.findUnique({ where: { isbn } });
    if (existing) {
      const updated = await prisma.libraryBook.update({
        where: { id: existing.id },
        data: {
          totalCopies: existing.totalCopies + 1,
          availableCopies: existing.availableCopies + 1,
          coverImageUrl: existing.coverImageUrl || coverImageUrl,
        },
      });
      return res.json({ msg: 'Added another copy to the catalog.', book: toLegacyLibraryBook(updated) });
    }

    const book = await prisma.libraryBook.create({
      data: {
        isbn,
        title,
        author,
        coverImageUrl,
        totalCopies: 1,
        availableCopies: 1,
      },
    });

    res.status(201).json({ msg: 'New book added to catalog.', book: toLegacyLibraryBook(book) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error during library scan.' });
  }
});

router.post('/manual', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (req, res) => {
  try {
    const isbn = sanitizeIsbn(req.body.isbn) || null;
    const title = (req.body.title || '').trim();
    const author = (req.body.author || '').trim() || 'Unknown Author';
    const coverImageUrl = (req.body.coverImageUrl || '').trim() || null;
    const copies = Math.max(1, Number(req.body.copies || req.body.defaultStock || 1));

    if (!title) {
      return res.status(400).json({ msg: 'Title is required.' });
    }

    if (isbn) {
      const existing = await prisma.libraryBook.findUnique({ where: { isbn } });
      if (existing) {
        const updated = await prisma.libraryBook.update({
          where: { id: existing.id },
          data: {
            totalCopies: existing.totalCopies + copies,
            availableCopies: existing.availableCopies + copies,
          },
        });
        return res.json({ msg: 'Existing catalog entry updated with more copies.', book: toLegacyLibraryBook(updated) });
      }
    }

    const book = await prisma.libraryBook.create({
      data: {
        isbn,
        title,
        author,
        coverImageUrl,
        totalCopies: copies,
        availableCopies: copies,
      },
    });

    res.status(201).json({ msg: 'Book added manually.', book: toLegacyLibraryBook(book) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/books', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (req, res) => {
  try {
    const isbn = sanitizeIsbn(req.body.isbn) || null;
    const title = (req.body.title || '').trim();
    const author = (req.body.author || '').trim() || 'Unknown Author';
    const coverImageUrl = (req.body.coverImageUrl || '').trim() || null;
    const copies = Math.max(1, Number(req.body.copies || req.body.defaultStock || req.body.totalCopies || 1));

    if (!title) {
      return res.status(400).json({ msg: 'Title is required.' });
    }

    if (isbn) {
      const existing = await prisma.libraryBook.findUnique({ where: { isbn } });
      if (existing) {
        const updated = await prisma.libraryBook.update({
          where: { id: existing.id },
          data: {
            totalCopies: existing.totalCopies + copies,
            availableCopies: existing.availableCopies + copies,
          },
        });
        return res.json({ msg: 'Existing catalog entry updated with more copies.', book: toLegacyLibraryBook(updated) });
      }
    }

    const book = await prisma.libraryBook.create({
      data: {
        isbn,
        title,
        author,
        coverImageUrl,
        totalCopies: copies,
        availableCopies: copies,
      },
    });

    res.status(201).json({ msg: 'Book added manually.', book: toLegacyLibraryBook(book) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/issue', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (req, res) => {
  try {
    const { studentId, bookId, dueDate, remarks = '' } = req.body;

    if (!studentId || !bookId || !dueDate) {
      return res.status(400).json({ msg: 'Student, book, and due date are required.' });
    }

    const [student, book] = await Promise.all([
      prisma.student.findUnique({ where: { id: studentId } }),
      prisma.libraryBook.findUnique({ where: { id: bookId } }),
    ]);

    if (!student) {
      return res.status(404).json({ msg: 'Student not found.' });
    }

    if (!book) {
      return res.status(404).json({ msg: 'Book not found.' });
    }

    if (book.availableCopies <= 0) {
      return res.status(400).json({ msg: 'No copy is currently available for issue.' });
    }

    const activeLoan = await prisma.libraryTransaction.findFirst({
      where: { studentId, bookId, status: 'BORROWED' },
    });

    if (activeLoan) {
      return res.status(400).json({ msg: 'This student already has an active copy of the same book.' });
    }

    const transaction = await prisma.$transaction(async (tx) => {
      const created = await tx.libraryTransaction.create({
        data: {
          studentId,
          bookId,
          dueDate: new Date(dueDate),
          remarks,
          status: 'BORROWED',
        },
      });

      await tx.libraryBook.update({
        where: { id: bookId },
        data: { availableCopies: book.availableCopies - 1 },
      });

      return created;
    });

    const populated = await prisma.libraryTransaction.findUnique({
      where: { id: transaction.id },
      include: {
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
        book: { select: { id: true, title: true, author: true, isbn: true } },
      },
    });

    res.status(201).json({ msg: 'Book issued successfully.', transaction: toLegacyLibraryTransaction(populated) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.patch('/transactions/:id/return', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (req, res) => {
  try {
    const transaction = await prisma.libraryTransaction.findUnique({ where: { id: req.params.id } });
    if (!transaction) {
      return res.status(404).json({ msg: 'Loan record not found.' });
    }

    if (transaction.status !== 'BORROWED') {
      return res.status(400).json({ msg: 'This book has already been settled.' });
    }

    const book = await prisma.libraryBook.findUnique({ where: { id: transaction.bookId } });
    if (!book) {
      return res.status(404).json({ msg: 'Book record not found.' });
    }

    const updated = await prisma.$transaction(async (tx) => {
      const next = await tx.libraryTransaction.update({
        where: { id: req.params.id },
        data: {
          status: 'RETURNED',
          returnDate: new Date(),
          fineAmount: Math.max(0, Number(req.body.fineAmount || 0)),
          remarks: req.body.remarks || transaction.remarks,
        },
      });

      await tx.libraryBook.update({
        where: { id: transaction.bookId },
        data: { availableCopies: Math.min(book.totalCopies, book.availableCopies + 1) },
      });

      return next;
    });

    const populated = await prisma.libraryTransaction.findUnique({
      where: { id: updated.id },
      include: {
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
        book: { select: { id: true, title: true, author: true, isbn: true } },
      },
    });

    res.json({ msg: 'Book returned successfully.', transaction: toLegacyLibraryTransaction(populated) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.post('/return', auth, roleCheck('superadmin', 'teacher', 'staff', 'hr'), async (req, res) => {
  try {
    const transactionId = req.body.transactionId || req.body.id;
    if (!transactionId) {
      return res.status(400).json({ msg: 'transactionId is required.' });
    }

    const transaction = await prisma.libraryTransaction.findUnique({ where: { id: transactionId } });
    if (!transaction) {
      return res.status(404).json({ msg: 'Loan record not found.' });
    }

    if (transaction.status !== 'BORROWED') {
      return res.status(400).json({ msg: 'This book has already been settled.' });
    }

    const book = await prisma.libraryBook.findUnique({ where: { id: transaction.bookId } });
    if (!book) {
      return res.status(404).json({ msg: 'Book record not found.' });
    }

    const updated = await prisma.$transaction(async (tx) => {
      const next = await tx.libraryTransaction.update({
        where: { id: transactionId },
        data: {
          status: 'RETURNED',
          returnDate: new Date(),
          fineAmount: Math.max(0, Number(req.body.fineAmount || 0)),
          remarks: req.body.remarks || transaction.remarks,
        },
      });

      await tx.libraryBook.update({
        where: { id: transaction.bookId },
        data: { availableCopies: Math.min(book.totalCopies, book.availableCopies + 1) },
      });

      return next;
    });

    const populated = await prisma.libraryTransaction.findUnique({
      where: { id: updated.id },
      include: {
        student: { include: { class: { select: { id: true, name: true, section: true } } } },
        book: { select: { id: true, title: true, author: true, isbn: true } },
      },
    });

    res.json({ msg: 'Book returned successfully.', transaction: toLegacyLibraryTransaction(populated) });
  } catch (err) {
    console.error(err);
    res.status(500).json({ msg: 'Server Error.' });
  }
});

router.delete('/books/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const activeLoan = await prisma.libraryTransaction.findFirst({
      where: { bookId: req.params.id, status: 'BORROWED' },
    });

    if (activeLoan) {
      return res.status(400).json({ msg: 'Cannot delete a book with an active issue record.' });
    }

    await prisma.$transaction([
      prisma.libraryTransaction.deleteMany({ where: { bookId: req.params.id } }),
      prisma.libraryBook.delete({ where: { id: req.params.id } }),
    ]);

    res.json({ msg: 'Book removed from catalog.' });
  } catch (err) {
    res.status(500).json({ msg: 'Server Error.' });
  }
});

module.exports = router;
