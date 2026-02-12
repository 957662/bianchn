# 小伍同学的个人博客

集成大模型AI能力的现代化博客系统，提供智能化的内容创作、管理和阅读体验。

## 项目特点

- **AI辅助创作**: 集成大模型API，提供文章优化、智能排版、代码生成等能力
- **3D图库展示**: 基于Three.js的3D模型展示功能
- **智能搜索**: 集成AI增强的全文搜索，支持语义搜索
- **多用户管理**: 支持管理员、编辑、作者、订阅用户等角色权限
- **现代化界面**: Vue3 + Element Plus 构建的后台管理界面
- **原生部署**: 无需Docker，直接使用LNMP栈部署

## 技术栈

### 后端
- **CMS**: WordPress 6.0+
- **语言**: PHP 7.4+
- **数据库**: MySQL 8.0+
- **缓存**: Redis 6.0+
- **Web服务器**: Nginx

### 前端
- **框架**: Vue 3 (Composition API)
- **UI库**: Element Plus
- **状态管理**: Pinia
- **路由**: Vue Router 4
- **构建工具**: Vite 5

## 快速开始

### 前置要求

- Ubuntu 20.04+ / Debian 10+ 或兼容系统
- 至少 2GB 内存
- 至少 10GB 可用磁盘空间
- Root 或 sudo 权限

### 一键安装

```bash
# 克隆项目
git clone https://github.com/957662/bianchn.git
cd bianchn

# 运行安装脚本
sudo ./install.sh
```

安装脚本会自动完成以下操作：
1. 检查系统环境
2. 安装 Nginx, PHP-FPM, MySQL, Redis
3. 配置数据库和缓存
4. 创建 WordPress 配置文件
5. 配置 Nginx 虚拟主机
6. 设置文件权限
7. 启动所有服务

安装完成后：
- 访问 http://localhost 完成 WordPress 安装
- 查看 `INSTALL_INFO.txt` 获取登录凭据
- 在 WordPress 后台激活小伍博客插件

详细说明请参考 [原生部署指南](./NATIVE_DEPLOYMENT.md)

### 服务管理

```bash
# 启动服务
./start.sh

# 停止服务
./stop.sh

# 重启服务
./restart.sh

# 查看状态
./status.sh
```

## 配置文件

### 环境变量

项目根目录的 `.env` 文件包含主要配置：

```bash
# 数据库配置
DB_NAME=xiaowu_blog
DB_USER=wordpress
DB_PASSWORD=your_secure_password
DB_HOST=localhost

# AI服务配置
AI_PROVIDER=openai
AI_API_KEY=your_api_key_here
AI_MODEL=gpt-4

# Redis配置
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# 邮件服务配置
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
```

## 目录结构

```
bianchn/
├── wordpress/                 # WordPress 核心
│   ├── wp-content/
│   │   ├── plugins/        # 自定义插件
│   │   ├── themes/         # 主题文件
│   │   └── uploads/        # 上传文件
│   └── wp-config.php       # WordPress 配置
├── admin-panel/             # Vue3 后台管理面板
│   ├── src/
│   │   ├── views/          # 页面组件
│   │   ├── components/     # 公共组件
│   │   ├── api/            # API 接口
│   │   ├── stores/         # Pinia 状态管理
│   │   └── router/         # 路由配置
│   ├── package.json
│   └── vite.config.js
├── nginx-production.conf    # Nginx 生产环境配置
├── install.sh               # 安装脚本
├── start.sh                 # 启动脚本
├── stop.sh                  # 停止脚本
├── restart.sh               # 重启脚本
├── status.sh                # 状态检查
├── .env                     # 环境变量
├── .env.example             # 环境变量示例
├── INSTALL_INFO.txt         # 安装信息（自动生成）
└── README.md
```

## WordPress 插件

| 插件名称 | 功能描述 |
|---------|---------|
| xiaowu-base | 基础服务（速率限制、安全认证、CORS、输入验证） |
| xiaowu-ai | AI 服务（文章优化、智能搜索、内容推荐、代码生成） |
| xiaowu-3d-gallery | 3D 图库（模型上传、展示、预览） |
| xiaowu-comments | 评论管理（审核、垃圾检测、敏感词过滤） |
| xiaowu-user | 用户管理（资料、社交登录、等级、私信） |
| xiaowu-search | 智能搜索（全文搜索、语义搜索、联想推荐） |

## 前端管理面板

### 安装依赖

```bash
cd admin-panel
npm install
```

### 开发模式

```bash
npm run dev
```

访问 http://localhost:3000

### 生产构建

```bash
npm run build
```

## 故障排除

### 查看服务状态

```bash
./status.sh
```

### 常见问题

**无法访问 WordPress**

```bash
# 检查服务状态
sudo systemctl status nginx php7.4-fpm mysql

# 查看错误日志
sudo tail -f /var/log/nginx/xiaowu-blog-error.log
```

**数据库连接失败**

```bash
# 检查 MySQL 状态
sudo systemctl status mysql

# 测试连接
mysql -u wordpress -p xiaowu_blog
```

**Redis 连接失败**

```bash
# 检查 Redis 状态
sudo systemctl status redis-server

# 测试连接
redis-cli -a YOUR_PASSWORD ping
```

## 备份与恢复

### 备份数据库

```bash
mysqldump -u wordpress -p xiaowu_blog > backup_$(date +%Y%m%d).sql
```

### 恢复数据库

```bash
mysql -u wordpress -p xiaowu_blog < backup_20240212.sql
```

### 完整备份

```bash
# 停止服务
./stop.sh

# 备份文件
tar -czf xiaowu_blog_backup_$(date +%Y%m%d).tar.gz \
  wordpress/wp-content \
  .env \
  /etc/nginx/sites-available/xiaowu-blog

# 重启服务
./start.sh
```

## 安全建议

1. 修改默认数据库密码
2. 配置防火墙规则
3. 启用 HTTPS（使用 Let's Encrypt）
4. 定期更新 WordPress 和插件
5. 定期备份数据

## 性能优化

### 启用 OpCache

已默认启用，配置文件：`/etc/php/7.4/fpm/conf.d/99-xiaowu-custom.ini`

### 配置 Redis 缓存

安装 Redis Object Cache 插件并启用。

### 配置 CDN

在 .env 文件中配置 CDN_URL。

## 项目文档

- [原生部署指南](./NATIVE_DEPLOYMENT.md) - 完整的部署和维护手册
- [架构设计](./ARCHITECTURE.md) - 系统架构和技术设计
- [开发指南](./DEVELOPER_GUIDE.md) - 开发环境和规范
- [接口定义](./INTERFACES.md) - 接口和交互规范
- [快速参考](./QUICK_REFERENCE.md) - 常用操作速查

## 许可证

GPL v2 or later

## 联系方式

- GitHub: https://github.com/957662/bianchn
- Issues: https://github.com/957662/bianchn/issues
