# 部署脚本完整指南

## 📋 概述

小伍博客系统提供了两个部署脚本：

1. **deploy.sh** - 原始部署脚本（基础功能）
2. **deploy-enhanced.sh** - 增强部署脚本（完整安全检查）

---

## 🚀 快速开始

### 使用增强版部署脚本（推荐）

```bash
# 给脚本添加可执行权限
chmod +x deploy-enhanced.sh

# 运行部署脚本
./deploy-enhanced.sh

# 如果需要sudo权限
sudo ./deploy-enhanced.sh
```

### 使用原始部署脚本

```bash
chmod +x deploy.sh
./deploy.sh
```

---

## ✅ 增强版脚本的功能

### 1. 完整的输入验证

#### 支持的验证类型

| 验证类型 | 说明 | 示例 |
|---------|------|------|
| `validate_not_empty` | 检查输入不为空 | 数据库密码、API 密钥 |
| `validate_length` | 验证长度范围 | 密码长度 8-128 个字符 |
| `validate_numeric` | 检查是否为数字 | 端口号、数量 |
| `validate_url` | 验证 URL 格式 | API 端点地址 |
| `validate_ip` | 验证 IP 地址 | 数据库主机 |
| `validate_no_sql_injection` | 防止 SQL 注入 | 数据库用户名、密码 |
| `validate_no_xss` | 防止 XSS 攻击 | 配置参数 |
| `validate_file_path` | 验证文件路径 | 备份路径、配置路径 |

#### SQL 注入检测

脚本检测以下 SQL 关键字：
- DROP, DELETE, TRUNCATE
- INSERT, UPDATE, ALTER, CREATE
- EXEC, EXECUTE, UNION, SELECT

#### XSS 检测

脚本检测以下代码模式：
- `<script>`, `javascript:`
- `onerror=`, `onclick=`, `onload=`
- `<iframe>`, `<object>`, `eval(`

### 2. 全面的安全检查

#### 权限检查
```bash
检查是否以 root 身份运行
提示不安全的权限使用
```

#### 依赖检查
```bash
验证以下工具已安装：
- docker
- docker-compose
- curl
- jq
```

#### 网络验证
```bash
检查互联网连接
测试 DNS 解析
验证 Docker Hub 可访问
```

#### 磁盘空间检查
```bash
验证可用磁盘空间 >= 5GB
防止磁盘空间不足导致部署失败
```

#### Docker Compose 配置验证
```bash
验证 docker-compose.yml 文件存在
验证配置语法正确
```

### 3. 智能化的部署流程

```
前置检查
  ↓
环境配置
  ↓
Docker Compose 验证
  ↓
备份现有数据
  ↓
停止现有服务
  ↓
拉取镜像
  ↓
启动服务
  ↓
健康检查
  ↓
显示部署结果
```

### 4. 自动备份功能

```bash
在重新部署前自动备份数据库
备份文件: ./backups/mysql/backup_YYYYMMDD_HHMMSS.sql.gz
```

### 5. 完整的健康检查

```bash
检查 MySQL 连接
检查 Redis 连接
检查 PHP-FPM 状态
检查 Nginx 响应
```

### 6. 彩色日志输出

```bash
[INFO]  - 提供信息
[✓]    - 操作成功
[⚠]    - 警告信息
[✗]    - 错误信息
```

---

## 📝 环境配置

### 创建 .env 文件

脚本会自动创建 `.env` 文件，如果不存在的话。

```bash
# WordPress 配置
WORDPRESS_DB_HOST=xiaowu-mysql
WORDPRESS_DB_PORT=3306
WORDPRESS_DB_NAME=xiaowu_blog
WORDPRESS_DB_USER=xiaowu_user
WORDPRESS_DB_PASSWORD=your_secure_password_here
WORDPRESS_TABLE_PREFIX=wp_
WORDPRESS_DEBUG=false

# MySQL 配置
MYSQL_ROOT_PASSWORD=your_mysql_root_password
MYSQL_DATABASE=xiaowu_blog
MYSQL_USER=xiaowu_user
MYSQL_PASSWORD=your_secure_password_here
MYSQL_PORT=3306

# Redis 配置
REDIS_PASSWORD=your_redis_password_here
REDIS_PORT=6379

# PHP 配置
PHP_MEMORY_LIMIT=256M
PHP_UPLOAD_MAX_FILESIZE=100M
PHP_POST_MAX_SIZE=100M
PHP_MAX_EXECUTION_TIME=300
PHP_DISPLAY_ERRORS=false

# Nginx 配置
NGINX_PORT=8080
NGINX_HTTPS_PORT=8443

# SSL 配置（可选）
SSL_ENABLED=false
SSL_CERT_PATH=
SSL_KEY_PATH=

# 时区
TIMEZONE=Asia/Shanghai

# AI 服务配置（可选）
AI_API_KEY=
AI_API_URL=

# CDN 配置（可选）
CDN_ENABLED=false
CDN_URL=
```

### ⚠️ 重要的安全设置

1. **修改默认密码**
   ```bash
   MYSQL_ROOT_PASSWORD=your_strong_password_here
   MYSQL_PASSWORD=your_strong_password_here
   REDIS_PASSWORD=your_strong_password_here
   WORDPRESS_DB_PASSWORD=your_strong_password_here
   ```

2. **密码要求**
   - 最小长度: 8 个字符
   - 最大长度: 128 个字符
   - 避免使用简单密码
   - 避免使用特殊字符（仅限字母数字）

3. **SSL 配置（生产环境建议）**
   ```bash
   SSL_ENABLED=true
   SSL_CERT_PATH=/path/to/ssl/cert.pem
   SSL_KEY_PATH=/path/to/ssl/key.pem
   ```

---

## 🔍 故障排除

### 问题: 部署脚本无法执行

**解决方案:**
```bash
# 添加可执行权限
chmod +x deploy-enhanced.sh

# 检查脚本语法
bash -n deploy-enhanced.sh

# 使用 bash 显式运行
bash deploy-enhanced.sh
```

### 问题: Docker 命令无权限

**解决方案:**
```bash
# 将用户添加到 docker 组
sudo usermod -aG docker $USER

# 刷新组成员身份
newgrp docker

# 验证权限
docker ps
```

### 问题: 镜像拉取缓慢或超时

**解决方案:**
```bash
# 配置 Docker 镜像源
# 编辑 /etc/docker/daemon.json：
{
  "registry-mirrors": [
    "https://docker.m.daocloud.io",
    "https://mirror.aliyun.com"
  ]
}

# 重启 Docker
sudo systemctl restart docker

# 重新运行部署脚本
./deploy-enhanced.sh
```

### 问题: 磁盘空间不足

**解决方案:**
```bash
# 检查磁盘使用
df -h

# 清理 Docker 数据
docker system prune -a

# 释放空间后重新部署
./deploy-enhanced.sh
```

### 问题: 数据库连接失败

**解决方案:**
```bash
# 查看 MySQL 日志
docker-compose logs mysql

# 检查 MySQL 容器状态
docker-compose ps mysql

# 验证密码配置
grep MYSQL_ROOT_PASSWORD .env

# 手动连接测试
docker exec -it xiaowu-mysql mysql -uroot -p
```

### 问题: 部署后无法访问服务

**解决方案:**
```bash
# 检查容器状态
docker-compose ps

# 查看服务日志
docker-compose logs

# 检查端口是否开放
netstat -tuln | grep 8080

# 测试本地连接
curl http://localhost:8080

# 检查防火墙
sudo ufw status
```

---

## 📊 部署流程详解

### 第一步: 前置检查

```bash
✓ 检查脚本执行权限
✓ 验证所有必要的工具已安装
✓ 检查网络连接
✓ 验证磁盘空间充足（≥5GB）
```

### 第二步: 环境配置

```bash
✓ 检查或创建 .env 文件
✓ 验证环境变量安全性
✓ 检查数据库密码强度
✓ 验证 SQL 注入防护
✓ 验证 XSS 防护
```

### 第三步: Docker Compose 验证

```bash
✓ 验证 docker-compose.yml 存在
✓ 验证配置文件语法正确
✓ 解析所有依赖服务
```

### 第四步: 数据备份

```bash
✓ 检测现有运行的服务
✓ 如果存在，创建数据库备份
✓ 备份文件保存到: ./backups/mysql/
```

### 第五步: 停止旧服务

```bash
✓ 停止所有运行的容器
✓ 清理容器资源
✓ 保留数据卷和网络
```

### 第六步: 拉取镜像

```bash
✓ 从 Docker Hub 拉取最新镜像
✓ 验证镜像完整性
✓ 报告拉取进度
```

### 第七步: 启动服务

```bash
✓ 创建并启动所有容器
✓ 建立容器间的网络
✓ 配置卷挂载
```

### 第八步: 健康检查

```bash
✓ 等待 MySQL 就绪
✓ 等待 Redis 就绪
✓ 等待 Nginx 就绪
✓ 验证所有服务正常运行
```

### 第九步: 显示结果

```bash
✓ 显示容器运行状态
✓ 显示访问地址
✓ 提示首次使用步骤
✓ 显示常用命令参考
```

---

## 🔐 安全最佳实践

### 1. 密码管理

- ✅ 使用强密码（≥12 个字符）
- ✅ 使用混合字符（大小写、数字、符号）
- ❌ 避免使用常见字典词
- ❌ 避免在 git 中提交真实密码
- ✅ 使用密钥管理工具存储敏感信息

### 2. 访问控制

- ✅ 限制 root 用户访问
- ✅ 使用防火墙限制端口访问
- ✅ 配置 SSL/TLS 加密通信
- ✅ 启用 WordPress 和数据库认证

### 3. 备份策略

- ✅ 在每次部署前备份数据
- ✅ 定期备份数据库和文件
- ✅ 将备份存储在安全位置
- ✅ 定期测试备份恢复

### 4. 监控和日志

- ✅ 定期检查容器日志
- ✅ 监控系统资源使用
- ✅ 设置错误警报
- ✅ 保留审计日志

---

## 📚 常用命令

### 查看服务状态

```bash
# 查看所有容器状态
docker-compose ps

# 查看具体容器日志
docker-compose logs -f nginx
docker-compose logs -f php-fpm
docker-compose logs -f mysql
docker-compose logs -f redis
```

### 管理服务

```bash
# 停止所有服务
docker-compose stop

# 启动所有服务
docker-compose start

# 重启指定服务
docker-compose restart nginx

# 完全关闭（删除容器）
docker-compose down

# 完全关闭并删除卷
docker-compose down -v
```

### 进入容器

```bash
# 进入 MySQL 容器
docker exec -it xiaowu-mysql bash

# 进入 PHP-FPM 容器
docker exec -it xiaowu-php-fpm sh

# 进入 Nginx 容器
docker exec -it xiaowu-nginx sh

# 进入 Redis 容器
docker exec -it xiaowu-redis sh
```

### 数据库操作

```bash
# 连接数据库
docker exec -it xiaowu-mysql mysql -uroot -p

# 备份数据库
docker exec xiaowu-mysql mysqldump -uroot -p > backup.sql

# 恢复数据库
docker exec -i xiaowu-mysql mysql -uroot -p < backup.sql
```

### 系统维护

```bash
# 检查磁盘使用
df -h

# 检查 Docker 镜像
docker images

# 清理未使用的镜像
docker image prune -a

# 清理未使用的容器
docker container prune

# 完全清理 Docker 系统
docker system prune -a
```

---

## 🔄 更新和维护

### 更新系统

```bash
# 拉取最新镜像
docker-compose pull

# 重新启动服务以使用最新镜像
docker-compose up -d

# 清理旧镜像
docker image prune -a
```

### 升级 WordPress

```bash
# WordPress 自动更新通过 Web 界面
# 或手动更新：

# 1. 停止服务
docker-compose stop

# 2. 备份数据
docker exec xiaowu-mysql mysqldump -uroot -p > backup.sql

# 3. 更新 WordPress 核心
# 登录 WordPress 后台或使用 WP-CLI

# 4. 重启服务
docker-compose up -d
```

---

## 📖 参考资源

- [Docker 官方文档](https://docs.docker.com/)
- [Docker Compose 文档](https://docs.docker.com/compose/)
- [WordPress 部署指南](https://wordpress.org/documentation/)
- [小伍博客文档](README.md)
- [架构文档](ARCHITECTURE.md)
- [开发指南](DEVELOPER_GUIDE.md)

---

## 💡 提示

### 性能优化

1. **增加内存限制** - 如果遇到内存问题，编辑 `.env`:
   ```bash
   PHP_MEMORY_LIMIT=512M
   ```

2. **启用缓存** - 使用 Redis 缓存提升性能

3. **配置 CDN** - 为静态文件配置 CDN

### 生产环境建议

1. 启用 SSL/TLS 加密
2. 配置定期备份
3. 设置监控和告警
4. 使用强密码和安全认证
5. 隐藏敏感的错误信息
6. 定期更新依赖和补丁

---

**脚本版本:** 2.0  
**最后更新:** 2024  
**维护者:** Development Team
