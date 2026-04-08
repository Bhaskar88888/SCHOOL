const prisma = require('./prisma');

let connectPromise = null;

const connectDB = async () => {
  if (connectPromise) {
    return connectPromise;
  }

  connectPromise = prisma.$connect();

  try {
    await connectPromise;
    return prisma;
  } catch (err) {
    connectPromise = null;
    console.error('Prisma DB Error:', err.message);
    if (process.env.NODE_ENV === 'production') {
      process.exit(1);
    }
    throw err;
  }
};

module.exports = connectDB;
