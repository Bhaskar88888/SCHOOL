const { verifyJwt } = require('../utils/jwt');

module.exports = (req, res, next) => {
  const token = req.cookies?.token || req.headers.authorization?.split(' ')[1];
  if (!token) return res.status(401).json({ msg: 'No token' });

  try {
    req.user = verifyJwt(token);
    next();
  } catch (err) {
    console.error('JWT Verification Error:', err.message);
    res.status(401).json({ msg: 'Invalid token' });
  }
};
