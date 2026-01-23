# 小伍同学博客 - 部署维护手册

## 项目概述

小伍同学的个人博客是一个基于WordPress开源框架构建的现代化博客系统，集成大模型AI能力，提供智能化的内容创作、管理和阅读体验。

**GitHub仓库**: https://github.com/957662/bianchn

## 完成记录

以下记录了在本次开发中完成的所有任务：

### 1. 前端Views组件
- [x] 创建 Users/Index.vue - 用户管理界面
- [x] 创建 Gallery/Index.vue - 3D图库管理界面
- [x] 创建 Search/Index.vue - 搜索管理界面
- [x] 创建 AI/Index.vue - AI设置界面
- [x] 创建 Settings/Index.vue - 系统设置界面
- [x] 创建 NotFound.vue - 404页面

### 2. API接口完善
- [x] 修复 api/index.js 中的拼写错误
- [x] 添加 postsAPI 缺失方法 (getCategories, getTags, getAuthors)
- [x] 添加 commentsAPI 缺失方法 (getStats, analyzeSpam, updateComment)
- [x] 添加 galleryAPI 缺失方法 (getCategories)
- [x] 添加 searchAPI 缺失方法 (getRecentSearches, saveConfig)
- [x] 添加 aiAPI 缺失方法 (optimizeContent, getUsage, getUsageTrend, getTasks)
- [x] 新增 settingsAPI - 系统设置接口

### 3. WordPress插件
- [x] xiaowu-ai - AI服务插件
- [x] xiaowu-3d-gallery - 3D图库插件
- [x] xiaowu-comments - 评论管理插件
- [x] xiaowu-search - 搜索插件
- [x] xiaowu-user - 用户管理插件

### 4. 配置文件
- [x] 配置GitHub推送密钥
- [x] Vite配置添加 allowedHosts
- [x] 创建 .env 环境变量文件

## 系统架构

```
bianchn/
├── wordpress/                  # WordPress核心及插件目录
│   ├── wp-config.php          # WordPress配置文件
│   ├── wp-content/
│   │   ├── plugins/
│   │   │   ├── xiaowu-ai/         # AI服务插件
│   │   │   ├── xiaowu-3d-gallery/ # 3D图库插件
│   │   │   ├── xiaowu-comments/    # 评论管理插件
│   │   │   ├── xiaowu-search/      # 搜索插件
│   │   │   └── xiaowu-user/       # 用户管理插件
│   │   ├── themes/
│   │   └── uploads/
│   └── index.php
├── admin-panel/                # Vue3后台管理面板
│   ├── src/
│   │   ├── views/
│   │   │   ├── Dashboard.vue
│   │   │   ├── Posts/Index.vue
│   │   │   ├── Posts/Edit.vue
│   │   │   ├── Comments/Index.vue
│   │   │   ├── Users/Index.vue
│   │   │   ├── Gallery/Index.vue
│   │   │   ├── Search/Index.vue
│   │   │   ├── AI/Index.vue
│   │   │   ├── Settings/Index.vue
│   │   │   ├── Layout.vue
│   │   │   ├── Login.vue
│   │   │   └── NotFound.vue
│   │   ├── api/index.js
│   │   ├── router/index.js
│   │   └── stores/
│   ├── vite.config.js
│   └── package.json
├── docker/                    # Docker配置目录
├── .env                       # 环境变量配置
└── docs/                      # 文档目录
```

## 环境要求

### 服务器要求
- **操作系统**: Linux (推荐 Ubuntu 20.04+ 或 CentOS 8+)
- **Web服务器**: Nginx 1.20+
- **PHP**: 8.1+
- **数据库**: MySQL 8.0+ 或 MariaDB 10.5+
- **缓存**: Redis 6.0+
- **Node.js**: 18+ (用于构建前端)

### 软件依赖
- Composer 2+
- Git 2+
- PHP扩展: `php-curl`, `php-mbstring`, `php-mysql`, `php-redis`

## 部署步骤

### 方式一: Docker部署 (推荐)

#### 1. 克隆项目

```bash
git clone https://github.com/957662/bianchn.git
cd bianchn
```

#### 2. 配置环境变量

```bash
cp .env.example .env
# 编辑 .env 文件，填入实际配置
```

#### 3. 构建前端

```bash
cd admin-panel
npm install
npm run build
cd ..
```

#### 4. 启动服务

```bash
docker-compose up -d
```

#### 5. 初始化WordPress

访问 http://localhost/wp-admin/install.php 进行WordPress安装

### 方式二: 传统部署

#### 1. 安装基础环境

```bash
# 安装Nginx
sudo apt update
sudo apt install nginx

# 安装PHP 8.1
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.1 php8.1-fpm php8.1-mysql php8.1-curl php8.1-mbstring php8.1-redis

# 安装MySQL
sudo apt install mysql-server

# 安装Redis
sudo apt install redis-server

# 安装Node.js (用于构建)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# 安装Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### 2. 配置Nginx

```bash
# 创建Nginx配置文件
sudo nano /etc/nginx/sites-available/bianchn

# 内容如下:
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/bianchn/wordpress;
    index index.php index.html;

    # WordPress配置
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP处理
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # 上传文件大小限制
    client_max_body_size 100M;
}
```

```bash
# 启用站点
sudo ln -s /etc/nginx/sites-available/bianchn /etc/nginx/sites-enabled/

# 测试配置
sudo nginx -t

# 重启Nginx
sudo systemctl restart nginx
```

#### 3. 部署WordPress

```bash
# 创建网站目录
sudo mkdir -p /var/www/bianchn
sudo chown -R $USER:$USER /var/www/bianchn

# 克隆或上传项目文件
cd /var/www/bianchn
# 上传项目文件...

# 配置数据库
sudo mysql -u root -p
```

```sql
CREATE DATABASE xiaowu_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'xiaowu_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON xiaowu_blog.* TO 'xiaowu_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# 复制WordPress配置
cp wp-config.sample.php wp-config.php
# 编辑 wp-config.php 填入数据库信息
```

#### 4. 构建并部署前端

```bash
cd admin-panel
npm install
npm run build

# 复制构建产物到WordPress主题目录
cp -r dist/* ../wordpress/wp-content/themes/admin-panel/
```

#### 5. 配置权限

```bash
# 设置WordPress目录权限
sudo chown -R www-data:www-data /var/www/bianchn/wordpress
sudo find /var/www/bianchn/wordpress -type d -exec chmod 755 {} \;
sudo find /var/www/bianchn/wordpress -type f -exec chmod 644 {} \;

# 设置上传目录权限
sudo chmod 777 /var/www/bianchn/wordpress/wp-content/uploads
```

#### 6. 启动服务

```bash
# 启动PHP-FPM
sudo systemctl start php8.1-fpm
sudo systemctl enable php8.1-fpm

# 启动MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# 启动Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# 启动Nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

## 环境变量配置

### WordPress配置

| 变量 | 说明 | 示例 |
|------|------|------|
| DB_NAME | 数据库名称 | xiaowu_blog |
| DB_USER | 数据库用户名 | xiaowu_user |
| DB_PASSWORD | 数据库密码 | your_secure_password |
| DB_HOST | 数据库主机 | localhost |
| DB_CHARSET | 数据库字符集 | utf8mb4 |

### AI服务配置

| 变量 | 说明 | 示例 |
|------|------|------|
| AI_PROVIDER | AI服务提供商 | openai |
| AI_API_KEY | API密钥 | sk-xxx |
| AI_MODEL | AI模型名称 | gpt-4 |
| AI_MAX_TOKENS | 最大Token数 | 4000 |
| AI_TEMPERATURE | 温度参数 | 0.7 |

### CDN配置

| 变量 | 说明 | 示例 |
|------|------|------|
| CDN_PROVIDER | CDN提供商 | tencent |
| CDN_SECRET_ID | 密钥ID | xxx |
| CDN_SECRET_KEY | 密钥 | xxx |
| CDN_BUCKET | 存储桶名称 | xiaowu-blog |
| CDN_REGION | 区域 | ap-shanghai |

## 插件激活

WordPress安装后，需要激活以下插件：

1. 登录WordPress后台 `/wp-admin`
2. 进入 `插件` -> `已安装的插件`
3. 依次激活以下插件：
   - 小伍AI服务 (xiaowu-ai)
   - 小伍3D图库 (xiaowu-3d-gallery)
   - 小伍评论管理 (xiaowu-comments)
   - 小伍搜索 (xiaowu-search)
   - 小伍用户管理 (xiaowu-user)
4. JWT Authentication - 需要额外安装

## 常见问题

### WordPress白屏

1. 检查PHP错误日志
```bash
tail -f /var/log/php-fpm/error.log
```

2. 启用WordPress调试模式
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

3. 检查文件权限
```bash
ls -la /var/www/bianchn/wordpress
```

### 数据库连接失败

1. 检查MySQL服务状态
```bash
sudo systemctl status mysql
```

2. 测试数据库连接
```bash
mysql -u xiaowu_user -p xiaowu_blog
```

3. 检查wp-config.php配置

### 静态资源404

1. 检查Nginx配置中的root路径
2. 确认文件权限正确
3. 检查SELinux设置 (如果使用CentOS)

### Redis缓存不工作

1. 检查Redis服务状态
```bash
sudo systemctl status redis
```

2. 测试Redis连接
```bash
redis-cli ping
```

3. 检查WordPress Redis配置

### AI API调用失败

1. 验证API密钥是否正确
2. 检查网络连接
3. 查看AI服务日志
```bash
tail -f /var/www/bianchn/wordpress/wp-content/uploads/xiaowu-ai.log
```

## 维护操作

### 定期备份

```bash
# 数据库备份
mysqldump -u xiaowu_user -p xiaowu_blog > backup_$(date +%Y%m%d).sql

# 文件备份
tar -czf files_backup_$(date +%Y%m%d).tar.gz /var/www/bianchn/wordpress/wp-content
```

### 缓存清理

```bash
# Redis缓存
redis-cli FLUSHDB

# WordPress缓存
wp cache flush

# Nginx缓存
sudo rm -rf /var/cache/nginx/*
```

### 日志管理

```bash
# 查看Nginx访问日志
tail -f /var/log/nginx/access.log

# 查看Nginx错误日志
tail -f /var/log/nginx/error.log

# 查看PHP错误日志
tail -f /var/log/php-fpm/error.log
```

### 性能监控

1. 监控服务器资源使用
```bash
htop
```

2. 监控MySQL慢查询
```bash
# 开启慢查询日志
# 查看慢查询日志
tail -f /var/log/mysql/slow.log
```

3. 监控WordPress性能
- 安装 Query Monitor 插件
- 安装 WP Rocket 缓存插件

## 安全加固

### 1. 文件权限

```bash
# 严格设置权限
find /var/www/bianchn/wordpress -type d -exec chmod 755 {} \;
find /var/www/bianchn/wordpress -type f -exec chmod 644 {} \;

# 保护wp-config.php
chmod 600 /var/www/bianchn/wordpress/wp-config.php
```

### 2. 禁用文件编辑

在wp-config.php中添加:
```php
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
```

### 3. 限制登录尝试

使用Fail2ban或登录限制插件。

### 4. 启用HTTPS

```bash
# 安装Let's Encrypt证书
sudo apt install certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d your-domain.com
```

### 5. 配置防火墙

```bash
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

## 更新流程

### WordPress更新

1. 备份数据库和文件
2. 登录WordPress后台
3. 进入 `仪表盘` -> `更新`
4. 执行自动更新或手动更新

### 插件更新

1. 进入 `插件` -> `已安装的插件`
2. 查看可用更新
3. 点击更新按钮

### 前端更新

```bash
# 拉取最新代码
git pull origin main

# 安装依赖
cd admin-panel
npm install

# 构建生产版本
npm run build

# 部署到服务器
# 使用rsync或其他方式上传
```

## 监控告警

### 推荐监控工具

1. **服务器监控**: htop, glances
2. **应用监控**: New Relic, Datadog
3. **日志监控**: ELK Stack, Loki
4. **错误追踪**: Sentry, Bugsnag

### 关键指标

- CPU使用率
- 内存使用率
- 磁盘使用率
- 响应时间
- 错误率

## 回滚方案

### 数据库回滚

```bash
# 恢复数据库备份
mysql -u root -p xiaowu_blog < backup_20240101.sql
```

### 代码回滚

```bash
# 回退到指定版本
git checkout <commit-hash>

# 回退到上一个版本
git reset --hard HEAD~1

# 回退到指定标签
git checkout v1.0.0
```

## 技术支持

### 联系方式

- GitHub Issues: https://github.com/957662/bianchn/issues
- 项目地址: https://github.com/957662/bianchn

### 文档链接

- 项目概述: /workspace/INDEX.md
- 架构设计: /workspace/ARCHITECTURE.md
- 开发指南: /workspace/DEVELOPER_GUIDE.md
- 接口定义: /workspace/INTERFACES.md

## 许可证

GPL v2 or later

## 更新日志

### v1.0.0 (2024-01-23)

初始版本，包含以下功能：

- 完整的WordPress博客系统
- Vue3 + Element Plus后台管理面板
- AI辅助文章创作和优化
- 3D图库展示和管理
- 智能搜索功能
- 多用户管理系统
- 评论审核和管理
- 系统设置和配置
