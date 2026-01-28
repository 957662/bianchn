/**
 * 主应用文件
 */

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const path = require('path');
const config = require('./config/config');

// 导入路由
const authRoutes = require('./routes/auth');
const postRoutes = require('./routes/posts');
const commentRoutes = require('./routes/comments');
const userRoutes = require('./routes/users');
const galleryRoutes = require('./routes/gallery');
const searchRoutes = require('./routes/search');
const aiRoutes = require('./routes/ai');
const settingsRoutes = require('./routes/settings');
const statsRoutes = require('./routes/stats');
const deploymentRoutes = require('./routes/deployment');

// 导入数据库
const db = require('./models/database');

// 导入中间件
const authMiddleware = require('./middleware/auth');
const errorHandler = require('./middleware/errorHandler');

// 创建 Express 应用
const app = express();

// 安全中间件 - 禁用某些可能干扰 CORS 的 helmet 选项
app.use(helmet({
  contentSecurityPolicy: false, // 禁用 CSP 以避免冲突
}));

// CORS 配置 - 动态检查 origin
const allowedOrigins = [
  'http://localhost:3000',
  'http://localhost:5173',
  'http://127.0.0.1:3000',
];

const corsOptions = {
  origin: (origin, callback) => {
    // 允许没有 origin 的请求（如移动应用、Postman等）
    if (!origin) {
      return callback(null, true);
    }
    // 允许所有 monkeycode-ai.online 子域名
    if (origin.endsWith('.monkeycode-ai.online')) {
      return callback(null, true);
    }
    // 检查白名单
    if (allowedOrigins.includes(origin)) {
      return callback(null, true);
    }
    callback(new Error('不允许的跨域请求'));
  },
  credentials: true,
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization', 'X-WP-Nonce'],
};

app.use(cors(corsOptions));

// 手动处理预检请求（防止 Express 默认处理覆盖 CORS 头）
app.options('*', cors(corsOptions));

// 解析请求体
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// 静态文件服务
app.use('/uploads', express.static(path.join(__dirname, '../public/uploads')));

// 速率限制
const limiter = rateLimit({
  windowMs: config.rateLimit.windowMs,
  max: config.rateLimit.maxRequests,
  message: {
    success: false,
    message: '请求过于频繁，请稍后再试',
  },
});
app.use('/api/', limiter);

// 请求日志
app.use((req, res, next) => {
  console.log(`${req.method} ${req.path} - ${new Date().toISOString()}`);
  next();
});

// 创建一个组合的路由用于 xiaowu/v1
const xiaowuRouter = express.Router();

xiaowuRouter.use('/settings', settingsRoutes);
xiaowuRouter.use('/stats', statsRoutes);
xiaowuRouter.use('/deployment', deploymentRoutes);

// API 路由
app.use('/wp-json/jwt-auth/v1', authRoutes);
app.use('/wp-json/wp/v2', postRoutes);
app.use('/wp-json/xiaowu-comments/v1', commentRoutes);
app.use('/wp-json/xiaowu-user/v1', userRoutes);
app.use('/wp-json/xiaowu-3d-gallery/v1', galleryRoutes);
app.use('/wp-json/xiaowu-search/v1', searchRoutes);
app.use('/wp-json/xiaowu-ai/v1', aiRoutes);
app.use('/wp-json/xiaowu/v1', xiaowuRouter);

// 健康检查
app.get('/wp-json', (req, res) => {
  res.json({
    name: 'Xiaowu Blog API',
    version: '1.0.0',
    status: 'healthy',
  });
});

// 404 处理
app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: '请求的资源不存在',
  });
});

// 错误处理
app.use(errorHandler);

// 启动服务器
const PORT = config.server.port;
const HOST = config.server.host;

// 先初始化数据库，再启动服务器
db.initDatabase().then(() => {
  app.listen(PORT, HOST, () => {
    console.log(`
╔══════════════════════════════════════╗
║                                              ║
║   小伍博客 API 服务已启动                      ║
║                                              ║
║   地址: http://${HOST}:${PORT}                   ║
║   环境: ${process.env.NODE_ENV || 'development'}        ║
║                                              ║
╚══════════════════════════════════════╝
  `);
  });
}).catch(error => {
  console.error('Failed to initialize database:', error);
  process.exit(1);
});
