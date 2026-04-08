const prisma = require('./config/prisma');
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
require('dotenv').config();

async function seed() {
  try {
    await prisma.$connect();
    console.log('Prisma Connected');
    
    const existing = await prisma.user.findUnique({ where: { email: 'admin@school.com' } });
    if (existing) {
      console.log('Superadmin already exists');
      process.exit(0);
    }

    const seededPassword = process.env.SEED_SUPERADMIN_PASSWORD || crypto.randomBytes(18).toString('base64url');
    const password = await bcrypt.hash(seededPassword, 10);
    await prisma.user.create({
      data: {
        name: 'Super Admin',
        email: 'admin@school.com',
        password,
        role: 'superadmin',
        phone: '0000000000',
        isActive: true,
      },
    });
    console.log(`Superadmin seeded: admin@school.com / ${seededPassword}`);
    await prisma.$disconnect();
    process.exit(0);
  } catch (err) {
    if (err.name === 'ValidationError') {
      console.error('Validation Errors:', Object.keys(err.errors).map(key => `${key}: ${err.errors[key].message}`).join(', '));
    } else {
      console.error('Error seeding database:', err.message);
    }
    process.exit(1);
  }
}

seed();
