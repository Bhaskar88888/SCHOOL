const prisma = require('./config/prisma');
const bcrypt = require('bcryptjs');
const User = require('./models/User');
require('dotenv').config();

async function check() {
  await prisma.$connect();
  console.log('Connected to Prisma');
  
  const user = await User.findOne({ email: 'superadmin@eduglass.com' });
  if (!user) {
    console.log('User not found!');
    process.exit(1);
  }
  
  console.log('User found:', user.email, 'isActive:', user.isActive, 'role:', user.role);
  
  const isMatch = await bcrypt.compare('admin123', user.password);
  console.log('Password match:', isMatch);
  await prisma.$disconnect();
  process.exit(0);
}
check();
