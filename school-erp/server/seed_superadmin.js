const prisma = require('./config/prisma');
const bcrypt = require('bcryptjs');

async function seedSuperAdmin() {
  try {
    await prisma.$connect();
    console.log('Prisma Connected');
    
    let admin = await prisma.user.findUnique({ where: { email: 'superadmin@eduglass.com' } });
    if (!admin) {
      const password = await bcrypt.hash('admin123', 10);
      admin = await prisma.user.create({
        data: {
          name: 'Super Admin',
          email: 'superadmin@eduglass.com',
          password,
          role: 'superadmin',
          phone: '9999999999',
          isActive: true,
        },
      });
      console.log('Created superadmin@eduglass.com / admin123');
    } else {
      console.log('superadmin@eduglass.com already exists');
    }
    
    await prisma.$disconnect();
    process.exit(0);
  } catch (err) {
    console.error('Error:', err);
    process.exit(1);
  }
}

seedSuperAdmin();
