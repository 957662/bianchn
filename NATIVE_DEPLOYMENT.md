# 小伍博客 - WordPress 原生部署指南

## 部署概述

本项目已完成 Docker 到原生部署的迁移，使用以下技术栈：
- **WordPress 6.0+** - 内容管理系统
- **Nginx** - Web 服务器
- **PHP 7.4-FPM** - PHP 处理器
- **MySQL 8.0+** - 数据库
- **Redis** - 缓存和会话存储

## 快速开始

### 1. 安装完成状态

所有服务已配置并运行：
- Nginx (端口 80)
- PHP-FPM (Unix socket)
- MySQL (端口 3306)
- Redis (端口 6379)

### 2. 访问博客

打开浏览器访问：
- 前台: `http://localhost` 或 `http://your-server-ip`
- 后台: `http://localhost/wp-admin`

### 3. 完成 WordPress 安装

首次访问会看到 WordPress 安装页面：

**选择语言**: 中文（简体中文）

**填写站点信息**:
- 站点标题: 小伍同学博客
- 用户名: admin
- 密码: `查看 INSTALL_INFO.txt 获取`
- 您的电子邮件: admin@localhost.local
- 搜索引擎可见性: 根据需要选择

### 4. 安装完成后激活插件

登录 WordPress 后台，进入 **插件 > 已安装的插件**，激活以下插件：

#### 必须激活的插件
- **xiaowu-base** - 基础服务（速率限制、安全认证、CORS）
- **xiaowu-ai** - AI 服务（文章优化、智能搜索、内容推荐）
- **xiaowu-3d-gallery** - 3D 图库
- **xiaowu-comments** - 评论管理
- **xiaowu-user** - 用户管理
- **xiaowu-search** - 智能搜索

#### 推荐安装
- **Redis Object Cache** - Redis 缓存插件（从插件市场搜索安装）

### 5. 配置 AI 服务（可选）

在 WordPress 后台导航到：
**设置 > AI 设置**

填写 AI 服务配置：
- AI 提供商: OpenAI / 通义千问 / 文心一言
- API 密钥: 您的 API 密钥
- 模型: gpt-4 或其他模型

## 服务管理

### 基本命令

```bash
# 启动所有服务
./start.sh

# 停止服务
./stop.sh

# 重启服务
./restart.sh

# 查看服务状态
./status.sh
```

### 手动服务管理

```bash
# Nginx
sudo systemctl start|stop|restart|status nginx

# PHP-FPM
sudo systemctl start|stop|restart|status php7.4-fpm

# MySQL
sudo systemctl start|stop|restart|status mysql

# Redis
sudo systemctl start|stop|restart|status redis-server
```

### 查看日志

```bash
# Nginx 访问日志
sudo tail -f /var/log/nginx/xiaowu-blog-access.log

# Nginx 错误日志
sudo tail -f /var/log/nginx/xiaowu-blog-error.log

# PHP-FPM 日志
sudo tail -f /var/log/php7.4-fpm-xiaowu-error.log

# MySQL 日志
sudo tail -f /var/log/mysql/error.log
```

## 配置文件位置

| 配置 | 路径 |
|------|------|
| WordPress | `/root/bianchn/wordpress/wp-config.php` |
| 环境变量 | `/root/bianchn/.env` |
| Nginx 站点配置 | `/etc/nginx/sites-available/xiaowu-blog` |
| PHP-FPM 配置 | `/etc/php/7.4/fpm/pool.d/xiaowu.conf` |
| PHP 自定义配置 | `/etc/php/7.4/fpm/conf.d/99-xiaowu-custom.ini` |
| Redis 配置 | `/etc/redis/redis.conf` |

## 数据库信息

```
数据库: xiaowu_blog
用户: wordpress
密码: 查看 .env 文件或 INSTALL_INFO.txt
主机: localhost
端口: 3306
字符集: utf8mb4
```

## Redis 信息

```
主机: 127.0.0.1
端口: 6379
密码: 查看 .env 文件或 INSTALL_INFO.txt
数据库: 0
```

## 插件位置

所有自定义插件位于:
`/root/bianchn/wordpress/wp-content/plugins/`

- `xiaowu-base/` - 基础服务
- `xiaowu-ai/` - AI 服务
- `xiaowu-3d-gallery/` - 3D 图库
- `xiaowu-comments/` - 评论管理
- `xiaowu-user/` - 用户管理
- `xiaowu-search/` - 智能搜索

## 前端管理面板

Vue3 后台管理面板位于:
`/root/bianchn/admin-panel/`

### 开发模式运行

```bash
cd /root/bianchn/admin-panel
npm install
npm run dev
```

访问: `http://localhost:3000`

### 生产构建

```bash
cd /root/bianchn/admin-panel
npm run build
```

构建文件位于: `/root/bianchn/admin-panel/dist/`

## 故障排除

### 问题: 无法访问 WordPress

**检查服务状态**:
```bash
./status.sh
```

**检查 Nginx 配置**:
```bash
sudo nginx -t
```

**查看错误日志**:
```bash
sudo tail -f /var/log/nginx/xiaowu-blog-error.log
```

### 问题: PHP-FPM 错误

**检查 PHP-FPM 状态**:
```bash
sudo systemctl status php7.4-fpm
```

**测试 PHP-FPM 配置**:
```bash
sudo php-fpm7.4 -t
```

### 问题: 数据库连接失败

**测试 MySQL 连接**:
```bash
mysql -u wordpress -p xiaowu_blog
```

**检查 MySQL 状态**:
```bash
sudo systemctl status mysql
```

### 问题: Redis 连接失败

**测试 Redis 连接**:
```bash
redis-cli -a YOUR_REDIS_PASSWORD ping
```

**检查 Redis 状态**:
```bash
sudo systemctl status redis-server
```

## 安全建议

1. **修改默认密码**
   - 定期修改数据库密码
   - 修改 WordPress 管理员密码

2. **启用 HTTPS**
   - 安装 Let's Encrypt SSL 证书
   - 配置 Nginx SSL

3. **配置防火墙**
   ```bash
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   ```

4. **定期备份**
   - 备份数据库
   - 备份 WordPress 文件
   - 备份 .env 配置文件

5. **更新系统**
   ```bash
   sudo apt update && sudo apt upgrade
   ```

## 性能优化

### 启用 OpCache

OpCache 已在 PHP 配置中启用，配置文件：
`/etc/php/7.4/fpm/conf.d/99-xiaowu-custom.ini`

### 配置 Redis 缓存

安装并激活 Redis Object Cache 插件：
1. 在 WordPress 后台搜索 "Redis Object Cache"
2. 安装并激活
3. 进入 设置 > Redis
4. 点击 "Enable Object Cache"

### 启用 CDN（可选）

在 .env 文件中配置 CDN：
```
CDN_ENABLED=true
CDN_URL=https://cdn.yourdomain.com
```

## 文件权限

WordPress 目录权限已正确设置：
- 目录: 755
- 文件: 644
- 所有者: www-data:www-data

如需修复权限：
```bash
sudo chown -R www-data:www-data /root/bianchn/wordpress
sudo find /root/bianchn/wordpress -type d -exec chmod 755 {} \;
sudo find /root/bianchn/wordpress -type f -exec chmod 644 {} \;
```

## 卸载

如需完全卸载：

```bash
# 停止服务
sudo systemctl stop nginx php7.4-fpm mysql redis-server

# 禁用服务
sudo systemctl disable nginx php7.4-fpm mysql redis-server

# 删除配置文件
sudo rm -f /etc/nginx/sites-available/xiaowu-blog
sudo rm -f /etc/nginx/sites-enabled/xiaowu-blog
sudo rm -f /etc/php/7.4/fpm/pool.d/xiaowu.conf
sudo rm -f /etc/php/7.4/fpm/conf.d/99-xiaowu-custom.ini

# 删除项目目录（可选）
# sudo rm -rf /root/bianchn
```

## 支持

如有问题，请查看：
- 项目文档: `/root/bianchn/README.md`
- 架构文档: `/root/bianchn/ARCHITECTURE.md`
- 部署手册: `/root/bianchn/DEPLOYMENT_MANUAL.md`

## 更新日志

### v1.0.0 - 原生部署版本
- 移除 Docker 依赖
- 实现原生 Nginx + PHP-FPM + MySQL + Redis 部署
- 创建自动化安装脚本
- 配置速率限制和安全防护
- 完善服务管理脚本
