require('dotenv').config();
const prisma = require('./config/prisma');
const bcrypt = require('bcryptjs');

async function main() {
  await prisma.$connect();
  console.log('✅ Connected to MongoDB:', MONGO_URI);

  // Raw schema — bypass the pre-save hook

  const user = await prisma.user.findUnique({ where: { email: 'bhaskar@eduglass.com' } });

  if (!user) {
    console.log('❌ User NOT FOUND. Creating fresh superadmin...');
    const hash = await bcrypt.hash('12345678', 10);
    await prisma.user.create({
      data: {
        name: 'Bhaskar Tiwari',
        email: 'bhaskar@eduglass.com',
        password: hash,
        role: 'superadmin',
        phone: '9999999999',
        isActive: true,
      },
    });
    console.log('✅ Created! Email: bhaskar@eduglass.com | Password: 12345678');
  } else {
    console.log(`✅ User found: ${user.name} | role: ${user.role} | active: ${user.isActive}`);
    const matchOk = await bcrypt.compare('12345678', user.password);
    console.log(`   bcrypt.compare("12345678") => ${matchOk}`);

    if (!matchOk) {
      console.log('🔧 Password mismatch — resetting via updateOne (bypasses pre-save hook)...');
      const newHash = await bcrypt.hash('12345678', 10);
      await prisma.user.update({
        where: { id: user.id },
        data: { password: newHash, isActive: true },
      });
      console.log('✅ Password FIXED. Use: bhaskar@eduglass.com / 12345678');
    } else {
      console.log('✅ Password is already correct! Use: bhaskar@eduglass.com / 12345678');
    }
  }

  await prisma.$disconnect();
  console.log('Done.');
}

main().catch(console.error);
