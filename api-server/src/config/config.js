/**
 * 数据库配置
 */

require('dotenv').config();

const mysqlConfig = {
  host: process.env.DB_HOST || '',
  port: process.env.DB_PORT || 3306,
  user: process.env.DB_USER || 'xiaowu_user',
  password: process.env.DB_PASSWORD || 'xiaowu_pass',
  database: process.env.DB_NAME || 'xiaowu_blog',
  socketPath: process.env.DB_SOCKET || '/run/mysqld/mysqld.sock',
  waitForConnections: true,
  connectionLimit: 10,
};

const jwtConfig = {
  secret: process.env.JWT_SECRET || 'xiaowu-secret-key-2024-change-this-in-production',
  expiresIn: '7d',
};

const serverConfig = {
  port: parseInt(process.env.SERVER_PORT) || 8080,
  host: process.env.SERVER_HOST || '0.0.0.0',
};

const corsConfig = {
  origin: process.env.CORS_ORIGIN?.split(',') || [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://127.0.0.1:3000',
    'https://*.monkeycode-ai.online',
  ],
  credentials: true,
};

const uploadConfig = {
  maxSize: 100 * 1024 * 1024, // 100MB
  allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
  uploadDir: process.env.UPLOAD_DIR || './public/uploads',
};

const rateLimitConfig = {
  windowMs: 60 * 1000, // 1分钟
  maxRequests: 100, // 每分钟最多100个请求
};

const aiConfig = {
  provider: process.env.AI_PROVIDER || 'openai',
  apiKey: process.env.AI_API_KEY || '',
  model: process.env.AI_MODEL || 'gpt-4',
};

module.exports = {
  mysql: mysqlConfig,
  jwt: jwtConfig,
  server: serverConfig,
  cors: corsConfig,
  upload: uploadConfig,
  rateLimit: rateLimitConfig,
  ai: aiConfig,
};
