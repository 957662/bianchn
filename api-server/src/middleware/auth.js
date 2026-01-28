/**
 * JWT 认证中间件
 */

const jwt = require('jsonwebtoken');
const config = require('../config/config');

/**
 * 验证 JWT token
 */
function authMiddleware(req, res, next) {
  // 获取 token
  const authHeader = req.headers.authorization;
  const token = authHeader && authHeader.startsWith('Bearer ')
    ? authHeader.substring(7)
    : null;

  if (!token) {
    return res.status(401).json({
      success: false,
      message: '未授权，请先登录',
    });
  }

  try {
    // 验证 token
    const decoded = jwt.verify(token, config.jwt.secret);
    req.user = decoded;
    next();
  } catch (error) {
    return res.status(401).json({
      success: false,
      message: 'Token 无效或已过期',
    });
  }
}

/**
 * 可选的认证中间件（允许未登录访问）
 */
function optionalAuth(req, res, next) {
  const authHeader = req.headers.authorization;
  const token = authHeader && authHeader.startsWith('Bearer ')
    ? authHeader.substring(7)
    : null;

  if (token) {
    try {
      const decoded = jwt.verify(token, config.jwt.secret);
      req.user = decoded;
    } catch (error) {
      // token 无效，继续但不设置 req.user
    }
  }
  next();
}

module.exports = {
  authMiddleware,
  optionalAuth,
};
