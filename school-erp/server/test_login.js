const http = require('http');

const data = JSON.stringify({ email: 'superadmin@eduglass.com', password: 'admin123' });

const options = {
  hostname: 'localhost',
  port: 5000,
  path: '/api/auth/login',
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Content-Length': Buffer.byteLength(data),
  },
};

const req = http.request(options, (res) => {
  let body = '';
  res.on('data', (chunk) => { body += chunk; });
  res.on('end', () => {
    console.log('STATUS:', res.statusCode);
    console.log('BODY:', body.substring(0, 500));
  });
});

req.on('error', (e) => {
  console.error('ERROR:', e.message, e.code);
});

req.write(data);
req.end();
