process.env.NODE_ENV = 'test';

const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

if (process.env.DATABASE_URL_TEST) {
  process.env.DATABASE_URL = process.env.DATABASE_URL_TEST;
} else {
  throw new Error('DATABASE_URL_TEST is required for chatbot smoke tests.');
}

const assert = require('node:assert/strict');
const bcrypt = require('bcryptjs');
const prisma = require('../config/prisma');
const connectDB = require('../config/db');
const { startServer } = require('../server');

let baseUrl = '';
let server;

async function request(pathname, { method = 'GET', token, body } = {}) {
  const response = await fetch(`${baseUrl}${pathname}`, {
    method,
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const text = await response.text();
  let data = null;

  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text;
  }

  return { status: response.status, data, text };
}

async function hashPassword(password) {
  const salt = await bcrypt.genSalt(10);
  return bcrypt.hash(password, salt);
}

async function setupUser() {
  const email = 'chatbot.smoke@test.local';
  await prisma.user.deleteMany({ where: { email } });
  await prisma.user.create({
    data: {
      name: 'Chatbot Smoke User',
      email,
      password: await hashPassword('test12345'),
      role: 'superadmin',
      phone: '9000000999',
    },
  });
  return email;
}

async function login(email, password) {
  const result = await request('/api/auth/login', {
    method: 'POST',
    body: { email, password },
  });

  assert.equal(result.status, 200, `login failed: ${result.text}`);
  assert.ok(result.data.token, 'missing token');
  return result.data.token;
}

async function run() {
  await connectDB();
  const email = await setupUser();

  const started = await startServer({
    port: 0,
    skipAiBootstrap: true,
    skipScheduler: true,
  });
  server = started.server;
  baseUrl = `http://127.0.0.1:${server.address().port}`;

  const token = await login(email, 'test12345');

  const languages = ['en', 'hi', 'as'];
  for (const language of languages) {
    const result = await request('/api/chatbot/chat', {
      method: 'POST',
      token,
      body: { message: 'hello', language },
    });

    assert.equal(result.status, 200, `chatbot ${language} failed: ${result.text}`);
    assert.ok(result.data, `missing response body for ${language}`);
    assert.ok(result.data.message || result.data.response || result.data.reply, `no message field for ${language}`);
  }
}

async function cleanup() {
  if (server) {
    await new Promise((resolve, reject) => {
      server.close((error) => (error ? reject(error) : resolve()));
    });
  }
  await prisma.$disconnect();
}

run()
  .then(() => {
    process.stdout.write('Chatbot language smoke test: PASS\n');
  })
  .catch((error) => {
    process.stderr.write(`Chatbot language smoke test: FAIL\n${error.stack || error.message}\n`);
    process.exitCode = 1;
  })
  .finally(async () => {
    try {
      await cleanup();
    } catch (error) {
      process.stderr.write(`Cleanup failed: ${error.stack || error.message}\n`);
      process.exitCode = 1;
    }
  });
