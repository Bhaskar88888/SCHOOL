const prisma = require('./config/prisma');
const bcrypt = require('bcryptjs');

async function createAllTestAccounts() {
  try {
    await prisma.$connect();
    console.log('✅ MySQL Connected\n');
    console.log('👥 Creating Test Accounts for All Roles...\n');
    
    const roles = ['superadmin', 'teacher', 'parent', 'student', 'accounts', 'hr', 'canteen', 'conductor', 'driver', 'staff'];
    const password = await bcrypt.hash('test123', 10);

    for (const role of roles) {
      const email = `${role}@test.com`;
      await prisma.user.upsert({
        where: { email },
        update: { password, isActive: true },
        create: {
          name: `${role.charAt(0).toUpperCase() + role.slice(1)} User`,
          email,
          password,
          role,
          phone: `99999999${roles.indexOf(role)}`,
          isActive: true
        }
      });
      console.log(`   ✅ ${email} / test123`);
    }

    console.log('\n📋 TEST ACCOUNTS CREATED USING PRISMA!');
    process.exit(0);
  } catch (err) {
    console.error('❌ Error:', err.message);
    process.exit(1);
  }
}

createAllTestAccounts();
