/**
 * 全局错误处理中间件
 */

/**
 * 错误处理中间件
 */
function errorHandler(err, req, res, next) {
  // 记录错误
  console.error('Error:', err);

  // 数据库错误
  if (err.code === 'ER_DUP_ENTRY') {
    return res.status(409).json({
      success: false,
      message: '数据已存在',
    });
  }

  // JWT 错误
  if (err.name === 'JsonWebTokenError') {
    return res.status(401).json({
      success: false,
      message: 'Token 无效',
    });
  }

  // 数据库连接错误
  if (err.code && err.code.startsWith('ER_')) {
    return res.status(500).json({
      success: false,
      message: '数据库错误',
      error: process.env.NODE_ENV === 'development' ? err.message : undefined,
    });
  }

  // 文件上传错误
  if (err.code === 'LIMIT_FILE_SIZE') {
    return res.status(413).json({
      success: false,
      message: '文件大小超过限制',
    });
  }

  // 默认错误
  const statusCode = err.statusCode || 500;
  const message = err.message || '服务器内部错误';

  res.status(statusCode).json({
    success: false,
    message,
    error: process.env.NODE_ENV === 'development' ? err.stack : undefined,
  });
}

module.exports = errorHandler;
