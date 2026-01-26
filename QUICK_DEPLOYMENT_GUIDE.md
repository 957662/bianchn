# 快速部署指南

> 包含 CORS 和速率限制安全配置的完整部署流程

---

## ⚡ 快速开始 (5 分钟)

### 前置检查

```bash
# 1. 进入项目目录
cd /workspaces/bianchn

# 2. 检查环境依赖
bash check-deployment.sh

# 3. 复制和编辑环境文件
cp .env.example .env.local
nano .env.local  # ⚠️ 请填写实际的配置值
```

### 启动服务

```bash
# 4. 启动所有服务
docker-compose up -d

# 5. 等待 30 秒后检查服务状态
docker-compose ps

# 6. 查看日志 (可选)
docker-compose logs -f
```

### 验证部署

```bash
# 7. 运行 CORS 测试
docker-compose exec php php /var/www/html/wp-content/plugins/xiaowu-base/test-cors.php

# 8. 运行速率限制测试
docker-compose exec php php /var/www/html/wp-content/plugins/xiaowu-base/test-rate-limiter.php

# 9. 访问管理后台
# 浏览器打开: http://localhost/wp-admin
# 登录用户名: admin (从 .env 配置)
```

---

## 📋 完整部署清单

### 1. 环境配置 (必需)

- [ ] `.env.local` 文件已创建
- [ ] `DB_PASSWORD` 已设置为强密码
- [ ] `WP_HOME` 和 `WP_SITEURL` 指向正确域名
- [ ] `CORS_ALLOWED_ORIGINS` 包含所有前端域名
- [ ] `REDIS_PASSWORD` 已设置
- [ ] `MYSQL_ROOT_PASSWORD` 已设置
- [ ] 邮件配置已填写 (可选但建议)

**配置示例:**
```bash
# 生产环境
WP_HOME=https://yourdomain.com
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# 开发环境
WP_HOME=http://localhost
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173
```

### 2. Docker 检查

- [ ] Docker 已安装且可用
- [ ] Docker Compose 已安装且版本 ≥ 1.29
- [ ] 足够的磁盘空间 (最少 10GB)
- [ ] 所需端口未被占用 (80, 443, 3306, 6379)

**检查命令:**
```bash
docker --version
docker-compose --version
df -h | grep /
netstat -tlnp 2>/dev/null | grep -E ':(80|443|3306|6379)'
```

### 3. 代码完整性

- [ ] `xiaowu-base` 插件完整
- [ ] CORS 管理器已安装
- [ ] 速率限制器已安装
- [ ] 前端 axios 已配置
- [ ] Nginx CORS 配置已存在

**检查命令:**
```bash
ls -la wordpress/wp-content/plugins/xiaowu-base/includes/
ls -la docker/nginx/conf.d/cors.conf
grep -q "withCredentials" admin-panel/src/api/axios.js && echo "✅ Axios CORS 配置已存在"
```

### 4. 权限检查

- [ ] 当前用户有 Docker 权限 (或使用 sudo)
- [ ] 项目目录可写
- [ ] 日志目录可写

**检查命令:**
```bash
docker ps  # 检查 Docker 权限
ls -ld . && test -w . && echo "✅ 目录可写"
touch /tmp/test-write && rm /tmp/test-write && echo "✅ 可以写入临时文件"
```

### 5. 网络配置 (仅生产环境)

- [ ] DNS 已指向服务器
- [ ] 防火墙已开放 80 和 443 端口
- [ ] SSL 证书已准备好 (可选但强烈建议)
- [ ] CDN 已配置 (如果使用)

---

## 🚀 部署步骤详解

### Step 1: 准备阶段

```bash
# 进入项目目录
cd /workspaces/bianchn

# 创建必要的目录
mkdir -p logs/nginx logs/php
mkdir -p mysql/data redis/data

# 设置正确的权限
chmod 755 logs mysql redis
chmod 755 check-deployment.sh
chmod 755 deploy.sh
```

### Step 2: 环境配置

```bash
# 复制环境模板
cp .env.example .env.local

# 编辑配置 (使用你的编辑器)
vim .env.local
# 或
nano .env.local

# 验证必需的配置项
grep -E "DB_PASSWORD|WP_HOME|CORS_ALLOWED|REDIS_PASSWORD" .env.local
```

**关键配置项:**

```bash
# 生产环境
APP_ENV=production
WP_HOME=https://yourdomain.com
DB_PASSWORD=YourVerySecurePassword123!@#
REDIS_PASSWORD=YourRedisPassword123!@#
MYSQL_ROOT_PASSWORD=YourMySQLRoot123!@#
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# 开发环境
APP_ENV=development
WP_HOME=http://localhost
DB_PASSWORD=dev_password
REDIS_PASSWORD=dev_redis_pass
MYSQL_ROOT_PASSWORD=dev_mysql_pass
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173
```

### Step 3: 验证配置

```bash
# 运行部署前检查
bash check-deployment.sh

# 检查应该输出:
# ✅ WordPress 目录存在
# ✅ Admin Panel 目录存在
# ✅ Docker 已安装
# ✅ 环境配置文件存在
# ...
# ✅ 所有关键检查通过！系统已准备好部署。
```

### Step 4: 启动服务

```bash
# 启动所有容器
docker-compose up -d

# 等待 30-60 秒让容器完全启动

# 检查服务状态
docker-compose ps

# 预期输出:
# NAME                COMMAND                  SERVICE      STATUS      PORTS
# xiaowu-nginx        "nginx -g daemon off"   nginx        Up 30s      0.0.0.0:80->80/tcp
# xiaowu-php          "php-fpm"               php          Up 30s      9000/tcp
# xiaowu-mysql        "docker-entrypoint.sh"  mysql        Up 30s      3306/tcp
# xiaowu-redis        "redis-server"          redis        Up 30s      6379/tcp
```

### Step 5: 初始化数据库

```bash
# 检查 MySQL 是否已启动
docker-compose exec mysql mysqladmin ping -uroot -p${MYSQL_ROOT_PASSWORD}

# 初始化数据库 (如果需要)
# 这通常由 docker-compose 自动处理
```

### Step 6: 验证插件

```bash
# 进入 PHP 容器
docker-compose exec php bash

# 验证 PHP 语法
php -l /var/www/html/wp-content/plugins/xiaowu-base/xiaowu-base.php
php -l /var/www/html/wp-content/plugins/xiaowu-base/includes/class-cors-manager.php
php -l /var/www/html/wp-content/plugins/xiaowu-base/includes/class-rate-limiter.php

# 验证 WordPress 配置
php -r "require '/var/www/html/wp-load.php'; echo 'WordPress loaded successfully';"

# 退出容器
exit
```

### Step 7: 运行集成测试

```bash
# 运行 CORS 测试
docker-compose exec php php /var/www/html/wp-content/plugins/xiaowu-base/test-cors.php

# 运行速率限制测试
docker-compose exec php php /var/www/html/wp-content/plugins/xiaowu-base/test-rate-limiter.php

# 预期输出:
# ========== 测试开始 ==========
# 测试 1: ... ✅ PASS
# ...
# ========== 测试报告 ==========
# ✅ 所有测试通过！
```

### Step 8: 验证 CORS 配置

```bash
# 测试 OPTIONS 预检请求
curl -X OPTIONS http://localhost/wp-json/xiaowu/v1/articles \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  -v 2>&1 | grep "Access-Control"

# 预期响应头:
# < Access-Control-Allow-Origin: http://localhost:3000
# < Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS
# < Access-Control-Allow-Headers: Content-Type, Authorization, X-RateLimit-*
# < Access-Control-Allow-Credentials: true
```

### Step 9: 验证速率限制

```bash
# 向 API 发送多个请求测试限流
for i in {1..110}; do
  curl -s -H "Authorization: Bearer test" \
    http://localhost/wp-json/xiaowu/v1/articles \
    -o /dev/null -w "Request $i: HTTP %{http_code}\n"
done

# 第 101 个请求应该返回 429 (Too Many Requests)
```

### Step 10: 访问管理后台

```bash
# 打开浏览器
# 开发: http://localhost/wp-admin
# 生产: https://yourdomain.com/wp-admin

# 使用 .env.local 中配置的用户名和密码登录
# 用户名: admin (或 WP_ADMIN_USER)
# 密码: (WP_ADMIN_PASSWORD)
```

---

## 🔍 常见问题和解决方案

### 问题 1: Docker 容器无法启动

**症状:** `docker-compose up -d` 后 `docker-compose ps` 显示容器状态为 "Exited"

**解决方案:**
```bash
# 查看错误日志
docker-compose logs php
docker-compose logs mysql
docker-compose logs nginx

# 检查配置
cat .env.local | grep DB_PASSWORD
docker-compose config | grep -A 5 "mysql:"

# 重启服务
docker-compose down
docker-compose up -d --force-recreate
```

### 问题 2: CORS 错误 - "Access to XMLHttpRequest has been blocked"

**症状:** 浏览器控制台显示 CORS 错误

**解决方案:**
```bash
# 1. 检查前端 Origin 是否在 CORS 白名单中
grep CORS_ALLOWED_ORIGINS .env.local

# 2. 测试 CORS 头
curl -X OPTIONS http://localhost/wp-json/xiaowu/v1/articles \
  -H "Origin: http://localhost:3000" \
  -v | grep -i "access-control"

# 3. 检查 Nginx 配置
docker-compose exec nginx cat /etc/nginx/conf.d/cors.conf

# 4. 重启 Nginx
docker-compose restart nginx
```

### 问题 3: 速率限制问题 - "请求过于频繁"

**症状:** 正常请求返回 429 错误

**解决方案:**
```bash
# 1. 检查限流配置
grep RATE_LIMIT .env.local

# 2. 重置限流计数
docker-compose exec redis redis-cli FLUSHDB

# 3. 检查 Redis 连接
docker-compose exec php redis-cli ping

# 4. 检查日志
docker-compose logs php | grep -i rate
```

### 问题 4: 数据库连接失败

**症状:** WordPress 显示"Error establishing a database connection"

**解决方案:**
```bash
# 1. 检查 MySQL 是否运行
docker-compose exec mysql mysqladmin ping -uroot -p${MYSQL_ROOT_PASSWORD}

# 2. 验证凭证
grep "^DB_" .env.local

# 3. 检查 MySQL 日志
docker-compose logs mysql

# 4. 重启 MySQL
docker-compose restart mysql
```

### 问题 5: 权限拒绝 - "Permission denied"

**症状:** `docker-compose: command not found` 或权限错误

**解决方案:**
```bash
# 方案 A: 使用 sudo
sudo docker-compose ps
sudo docker-compose up -d

# 方案 B: 将用户添加到 docker 组
sudo usermod -aG docker $USER
# 注销并重新登录后生效

# 方案 C: 修复文件权限
chmod +x check-deployment.sh deploy.sh
chmod -R 755 logs mysql redis
```

---

## 📊 验证清单 - 完整部署验证

运行以下命令完成完整验证:

```bash
# 保存为 verify-deployment.sh
#!/bin/bash

echo "========== 部署验证 =========="
echo ""

# 1. Docker 检查
echo "1️⃣  Docker 服务..."
docker-compose ps | grep -E "(nginx|php|mysql|redis)" && echo "✅ 所有服务运行" || echo "❌ 某些服务未运行"

# 2. 数据库检查
echo ""
echo "2️⃣  MySQL 数据库..."
docker-compose exec -T mysql mysqladmin ping -uroot -proot 2>/dev/null && echo "✅ 数据库连接正常" || echo "❌ 数据库连接失败"

# 3. Redis 检查
echo ""
echo "3️⃣  Redis 缓存..."
docker-compose exec -T redis redis-cli ping 2>/dev/null && echo "✅ 缓存连接正常" || echo "❌ 缓存连接失败"

# 4. CORS 测试
echo ""
echo "4️⃣  CORS 配置..."
curl -s -X OPTIONS http://localhost/wp-json/xiaowu/v1/articles \
  -H "Origin: http://localhost:3000" | grep -q "Access-Control" && echo "✅ CORS 配置正常" || echo "❌ CORS 配置异常"

# 5. 速率限制测试
echo ""
echo "5️⃣  速率限制..."
docker-compose exec -T php php /var/www/html/wp-content/plugins/xiaowu-base/test-rate-limiter.php 2>/dev/null | grep -q "PASS" && echo "✅ 速率限制正常" || echo "❌ 速率限制异常"

# 6. CORS 测试
echo ""
echo "6️⃣  CORS 功能..."
docker-compose exec -T php php /var/www/html/wp-content/plugins/xiaowu-base/test-cors.php 2>/dev/null | grep -q "通过" && echo "✅ CORS 功能正常" || echo "❌ CORS 功能异常"

# 7. 文件权限
echo ""
echo "7️⃣  文件权限..."
test -w logs && test -w mysql && test -w redis && echo "✅ 文件权限正确" || echo "❌ 文件权限异常"

echo ""
echo "========== 验证完成 =========="
```

保存并运行:
```bash
chmod +x verify-deployment.sh
./verify-deployment.sh
```

---

## 🛡️ 安全建议

### 生产环境必做项

- [ ] **启用 HTTPS**
  ```bash
  # 使用 Let's Encrypt
  certbot certonly --standalone -d yourdomain.com
  # 更新 docker-compose.yml 中的 SSL 路径
  ```

- [ ] **更改默认密码**
  ```bash
  # WordPress 管理员密码
  docker-compose exec php wp user update admin --prompt=user_pass
  
  # MySQL root 密码
  docker-compose exec mysql mysqladmin -uroot password 'NewPassword123!@#'
  ```

- [ ] **启用防火墙**
  ```bash
  # 仅允许必要的端口
  sudo ufw allow 22/tcp   # SSH
  sudo ufw allow 80/tcp   # HTTP
  sudo ufw allow 443/tcp  # HTTPS
  ```

- [ ] **定期备份**
  ```bash
  # 每日备份数据库和上传文件
  0 2 * * * /workspaces/bianchn/backup-db.sh
  ```

- [ ] **监控日志**
  ```bash
  # 实时监控错误
  docker-compose logs -f --tail=100 php nginx mysql
  ```

### CORS 相关安全

- [ ] 定期审计 `CORS_ALLOWED_ORIGINS` 列表
- [ ] 不要在生产环境中允许 `localhost:*`
- [ ] 使用具体域名而非通配符
- [ ] 定期更新源白名单

---

## 📞 支持和文档

更详细的信息请参考:

- [CORS 配置文档](./CORS_CONFIGURATION.md)
- [速率限制文档](./IMPLEMENTATION_GUIDE.md#task-11-api-rate-limiting)
- [Task 1.2 完成总结](./TASK_1_2_COMPLETION_SUMMARY.md)
- [Week 1 进度报告](./WEEK_1_PROGRESS_REPORT.md)

---

**版本:** 1.0  
**最后更新:** 2024-01-15  
**状态:** 生产就绪 ✅
