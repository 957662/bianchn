# 小伍博客后端 API 服务

## 项目说明

这是一个独立的 Node.js 后端服务，替代原本基于 WordPress 的后端，提供完整的博客 API。

## 技术栈

- **运行时**: Node.js
- **框架**: Express.js
- **数据库**: MySQL 8.0+
- **认证**: JWT (jsonwebtoken)
- **安全**: bcryptjs, helmet, CORS

## 目录结构

```
api-server/
├── src/
│   ├── app.js              # 主应用入口
│   ├── config/
│   │   └── database.js     # 数据库配置
│   ├── models/
│   │   └── database.js     # 数据库操作
│   ├── middleware/
│   │   ├── auth.js          # JWT 认证中间件
│   │   └── errorHandler.js # 错误处理中间件
│   └── routes/
│       ├── auth.js          # 登录/认证路由
│       ├── posts.js         # 文章管理路由
│       ├── comments.js      # 评论管理路由
│       ├── users.js         # 用户管理路由
│       ├── gallery.js       # 3D图库路由
│       ├── search.js        # 搜索路由
│       ├── ai.js            # AI 功能路由
│       ├── settings.js      # 系统设置路由
│       ├── stats.js         # 统计数据路由
│       └── deployment.js    # 部署向导路由
└── public/
    └── uploads/             # 文件上传目录
```

## 快速开始

### 1. 配置环境变量

创建 `.env` 文件：

```bash
# 数据库配置
DB_HOST=localhost
DB_PORT=3306
DB_USER=xiaowu_user
DB_PASSWORD=xiaowu_pass
DB_NAME=xiaowu_blog

# JWT 密钥
JWT_SECRET=your-secret-key-change-this

# 服务器配置
SERVER_PORT=8080
SERVER_HOST=0.0.0.0

# CORS 配置（多个域名用逗号分隔）
CORS_ORIGIN=http://localhost:3000,http://localhost:5173

# AI 服务配置（可选）
AI_PROVIDER=openai
AI_API_KEY=your-openai-api-key
AI_MODEL=gpt-4
```

### 2. 初始化数据库

首次运行需要初始化数据库表：

```bash
node src/init-db.js
```

### 3. 启动服务

```bash
# 开发模式（支持热重载）
npm run dev

# 生产模式
npm start
```

服务将运行在 `http://localhost:8080`

## API 端点

### 认证
- `POST /wp-json/jwt-auth/v1/token` - 用户登录
- `POST /wp-json/jwt-auth/v1/token/validate` - 验证 token

### 文章
- `GET /wp-json/wp/v2/posts` - 获取文章列表
- `GET /wp-json/wp/v2/posts/:id` - 获取单篇文章
- `POST /wp-json/wp/v2/posts` - 创建文章（需认证）
- `PUT /wp-json/wp/v2/posts/:id` - 更新文章（需认证）
- `DELETE /wp-json/wp/v2/posts/:id` - 删除文章（需认证）
- `GET /wp-json/wp/v2/posts/categories` - 获取分类列表
- `POST /wp-json/wp/v2/posts/:id/optimize` - AI 优化文章（需认证）

### 评论
- `GET /wp-json/xiaowu-comments/v1/comments` - 获取评论列表
- `GET /wp-json/xiaowu-comments/v1/comments/stats` - 获取评论统计
- `PUT /wp-json/xiaowu-comments/v1/comments/:id` - 更新评论状态（需认证）
- `DELETE /wp-json/xiaowu-comments/v1/comments/:id` - 删除评论（需认证）
- `POST /wp-json/xiaowu-comments/v1/comments/bulk` - 批量操作评论（需认证）

### 用户
- `GET /wp-json/xiaowu-user/v1/users` - 获取用户列表（需认证）
- `GET /wp-json/xiaowu-user/v1/users/:id` - 获取单个用户（需认证）
- `GET /wp-json/wp/v2/users/me` - 获取当前用户信息（需认证）
- `POST /wp-json/xiaowu-user/v1/users` - 创建用户（需管理员权限）
- `PUT /wp-json/xiaowu-user/v1/users/:id` - 更新用户（需认证）
- `DELETE /wp-json/xiaowu-user/v1/users/:id` - 删除用户（需认证）
- `POST /wp-json/xiaowu-user/v1/users/:id/reset-password` - 重置密码（需管理员权限）

### 3D 图库
- `GET /wp-json/xiaowu-3d-gallery/v1/models` - 获取 3D 模型列表
- `POST /wp-json/xiaowu-3d-gallery/v1/models/upload` - 上传 3D 模型（需认证）
- `GET /wp-json/xiaowu-3d-gallery/v1/categories` - 获取分类列表

### 搜索
- `GET /wp-json/xiaowu-search/v1/search` - 搜索文章和模型
- `GET /wp-json/xiaowu-search/v1/analytics/stats` - 获取搜索统计
- `GET /wp-json/xiaowu-search/v1/popular` - 获取热门搜索
- `GET /wp-json/xiaowu-search/v1/recent` - 获取最近搜索

### AI 功能
- `GET /wp-json/xiaowu-ai/v1/settings` - 获取 AI 设置（需认证）
- `POST /wp-json/xiaowu-ai/v1/settings` - 保存 AI 设置（需认证）
- `POST /wp-json/xiaowu-ai/v1/test-connection` - 测试 AI 连接（需认证）
- `POST /wp-json/xiaowu-ai/v1/generate-outline` - 生成文章大纲（需认证）
- `POST /wp-json/xiaowu-ai/v1/optimize-article` - 优化文章内容（需认证）

### 系统设置
- `GET /wp-json/xiaowu/v1/settings` - 获取系统设置（需认证）
- `POST /wp-json/xiaowu/v1/settings/basic` - 保存基本设置（需认证）
- `POST /wp-json/xiaowu/v1/settings/comments` - 保存评论设置（需认证）
- `POST /wp-json/xiaowu/v1/settings/cache/clear` - 清除缓存（需认证）

### 统计数据
- `GET /wp-json/xiaowu/v1/stats/dashboard` - 获取仪表盘统计数据
- `GET /wp-json/xiaowu/v1/stats/visits` - 获取访问量数据
- `GET /wp-json/xiaowu/v1/stats/content` - 获取内容统计

### 部署向导
- `GET /wp-json/xiaowu/v1/deployment/environment` - 环境检测
- `POST /wp-json/xiaowu/v1/deployment/test-db` - 测试数据库连接
- `POST /wp-json/xiaowu/v1/deployment/test-ai` - 测试 AI 连接
- `POST /wp-json/xiaowu/v1/deployment/test-email` - 测试邮件发送

## 默认管理员账号

首次运行后会自动创建默认管理员账号：

- 用户名: `admin`
- 密码: `admin123`

请登录后立即修改密码！

## 数据库表结构

### users
用户表，存储用户信息

### posts
文章表，存储博客文章

### categories
分类表，存储文章分类

### post_categories
文章分类关联表

### comments
评论表，存储文章评论

### models
3D 模型表，存储 3D 模型信息

### media
媒体文件表，存储上传的媒体文件

### settings
设置表，存储系统配置

### analytics
统计表，存储访问统计数据

## 安全特性

- JWT 令牌认证
- 密码 bcrypt 加密
- CORS 跨域支持
- 速率限制
- Helmet 安全头
- SQL 注入防护
- XSS 防护

## 开发

```bash
# 安装依赖
npm install

# 启动开发服务器（支持热重载）
npm run dev
```

## 生产

```bash
# 启动生产服务器
npm start

# 或使用 PM2 进程管理
pm2 start src/app.js --name xiaowu-api
```

## 注意事项

1. 确保 MySQL 8.0+ 已安装并运行
2. 首次启动前需要运行数据库初始化脚本
3. 生产环境请修改 `.env` 文件中的敏感配置
4. 建议使用 PM2 或 systemd 管理进程
