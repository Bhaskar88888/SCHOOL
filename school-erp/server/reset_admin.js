const prisma = require('./config/prisma');
const bcrypt = require('bcryptjs');
require('dotenv').config();

async function resetAdmin() {
  try {
    await prisma.$connect();
    console.log('Prisma Connected');

    const password = await bcrypt.hash('admin123', 10);
    await prisma.user.upsert({
      where: { email: 'superadmin@eduglass.com' },
      update: {
        password,
        role: 'superadmin',
      },
      create: {
        name: 'Super Admin',
        email: 'superadmin@eduglass.com',
        password,
        role: 'superadmin',
        phone: '0000000000',
        isActive: true,
      },
    });
    console.log('Superadmin reset. Email: superadmin@eduglass.com, Password: admin123');
    
    await prisma.$disconnect();
    process.exit(0);
  } catch (err) {
    console.error('Error:', err.message);
    process.exit(1);
  }
}

resetAdmin();
