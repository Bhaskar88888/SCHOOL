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
    
    // First let's get a class ID
    http.request({ hostname: 'localhost', port: 5000, path: '/api/classes', method: 'GET', headers: { 'Authorization': 'Bearer ' + token } }, (res_) => {
       let b2 = ''; res_.on('data', c=>b2+=c); res_.on('end', () => {
           const classId = JSON.parse(b2).data[0]._id;
           
           // Get students for this class
           http.request({ hostname: 'localhost', port: 5000, path: `/api/students/class/${classId}`, method: 'GET', headers: { 'Authorization': 'Bearer ' + token } }, (res3) => {
               let b3 = ''; res3.on('data', c=>b3+=c); res3.on('end', () => {
                   const students = JSON.parse(b3).data;
                   if (!students || students.length === 0) {
                      console.log('No students found in class');
                      return;
                   }
                   const records = students.map(s => ({ studentId: s._id, status: 'present' }));
                   
                   // Mark bulk attendance
                   const bulkData = JSON.stringify({ classId, date: '2026-04-08', records });
                   const r = http.request({
                       hostname: 'localhost', port: 5000, path: '/api/attendance/bulk', method: 'POST',
                       headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(bulkData) }
                   }, (res_b) => {
                       let bodyB = ''; res_b.on('data', c=>bodyB+=c); res_b.on('end', () => {
                           console.log('BULK RESPONSE CODE:', res_b.statusCode);
                           console.log('BULK RESPONSE BODY:', bodyB);
                       });
                   });
                   r.write(bulkData);
                   r.end();
               });
           }).end();
       });
    }).end();
  });
});

req.write(data);
req.end();
