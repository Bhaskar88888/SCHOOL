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
    const token = JSON.parse(body).token;
    
    // Now get leave
    const opt2 = {
      hostname: 'localhost',
      port: 5000,
      path: '/api/leave',
      method: 'GET',
      headers: {
        'Authorization': 'Bearer ' + token
      }
    };
    http.request(opt2, (res2) => {
      let b2 = '';
      res2.on('data', c2 => { b2 += c2; });
      res2.on('end', () => {
         console.log('STATUS:', res2.statusCode);
         console.log('BODY:', b2);
      });
    }).end();
  });
});

req.write(data);
req.end();
