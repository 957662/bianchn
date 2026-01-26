# 小伍同学的个人博客

集成大模型AI能力的现代化博客系统，提供智能化的内容创作、管理和阅读体验。

## 项目特点

- **AI辅助创作**: 集成大模型API，提供文章优化、智能排版、代码生成等能力
- **3D图库展示**: 基于Three.js的3D模型展示功能
- **智能搜索**: 集成AI增强的全文搜索，支持语义搜索
- **多用户管理**: 支持管理员、编辑、作者、订阅用户等角色权限
- **现代化界面**: Vue3 + Element Plus 构建的后台管理界面
- **容器化部署**: Docker + Docker Compose 一键部署

## 技术栈

### 后端
- **CMS**: WordPress 6.0+
- **语言**: PHP 8.1+
- **数据库**: MySQL 8.0+
- **缓存**: Redis 6.0+
- **Web服务器**: Nginx 1.20+

### 前端
- **框架**: Vue 3 (Composition API)
- **UI库**: Element Plus
- **状态管理**: Pinia
- **路由**: Vue Router 4
- **构建工具**: Vite 5

## 快速开始

### 前置要求

- Docker 20.10+
- Docker Compose 2.0+
- 至少 2GB 内存
- 至少 10GB 可用磁盘空间

### 一键部署

```bash
# 克隆项目
git clone https://github.com/957662/bianchn.git
cd bianchn

# 运行部署脚本
./deploy.sh
```

部署脚本会自动完成以下操作：
1. 检查系统环境
2. 安装 Docker 和 Docker Compose（如果未安装）
3. 创建必要的目录结构
4. 检查并创建配置文件
5. 安装和构建前端依赖
6. 构建并启动 Docker 容器
7. 等待所有服务就绪

部署完成后，访问以下地址：
- WordPress前台: http://localhost:8080
- WordPress后台: http://localhost:8080/wp-admin
- 管理面板: http://localhost:3000

### 首次部署步骤

1. 访问 http://localhost:8080 完成 WordPress 安装

2. 登录 WordPress 后台，激活以下插件：
   - 小伍AI服务 (xiaowu-ai)
   - 小伍3D图库 (xiaowu-3d-gallery)
   - 小伍评论管理 (xiaowu-comments)
   - 小伍搜索 (xiaowu-search)
   - 小伍用户管理 (xiaowu-user)

3. 配置 AI 服务：
   - 进入 AI设置页面
   - 选择 AI 服务提供商（OpenAI/通义千问等）
   - 输入 API 密钥
   - 测试连接

4. （可选）配置 CDN 存储：
   - 支持腾讯云 COS 或阿里云 OSS
   - 用于存储大文件和静态资源

5. （可选）配置邮件服务：
   - 用于邮件通知和用户验证

## 管理脚本

项目提供了一组管理脚本，用于日常运维：

### 部署相关

| 脚本 | 说明 |
|------|------|
| `./deploy.sh` | 一键部署，首次部署使用 |
| `./start-services.sh` | 启动所有服务，服务器重启后使用 |
| `./stop-services.sh` | 停止所有服务 |
| `./restart-services.sh` | 重启所有服务 |

### 数据库相关

| 脚本 | 说明 |
|------|------|
| `./init-db.sh` | 初始化数据库配置 |
| `./backup-db.sh` | 备份数据库 |
| `./restore-db.sh` | 恢复数据库 |

### 系统检查

| 脚本 | 说明 |
|------|------|
| `./check-system.sh` | 系统健康检查 |
| `./check-env.sh` | 环境变量检查 |

### Docker Compose 常用命令

```bash
# 查看服务状态
docker-compose ps

# 查看日志
docker-compose logs -f
docker-compose logs -f nginx
docker-compose logs -f php-fpm
docker-compose logs -f mysql

# 重启单个服务
docker-compose restart nginx

# 进入容器
docker exec -it xiaowu-nginx sh
docker exec -it xiaowu-php-fpm sh
docker exec -it xiaowu-mysql bash

# 完全停止并删除容器
docker-compose down
```

## 配置文件

### 环境变量

项目根目录的 `.env` 文件包含主要配置：

```bash
# 数据库配置
DB_NAME=xiaowu_blog
DB_USER=xiaowu_user
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password

# AI服务配置
AI_PROVIDER=openai
AI_API_KEY=your_api_key_here
AI_MODEL=gpt-4

# CDN配置
CDN_PROVIDER=tencent
CDN_SECRET_ID=your_secret_id
CDN_SECRET_KEY=your_secret_key

# 邮件服务配置
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
```

### Nginx 配置

Nginx 配置文件位于 `docker/nginx/conf.d/default.conf`，包含：
- WordPress 标准重写规则
- PHP-FPM 代理配置
- 静态文件缓存
- 上传文件大小限制
- 安全访问控制

### PHP 配置

PHP 配置文件位于 `docker/php/php.ini`，包含：
- 内存限制：256M
- 最大执行时间：300秒
- 上传文件大小：100M
- Redis 会话存储
- OpCache 优化

## 目录结构

```
bianchn/
├── wordpress/                 # WordPress 核心和插件
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
├── docker/                 # Docker 配置
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── conf.d/
│   └── php/
│       ├── Dockerfile
│       ├── php.ini
│       └── php-fpm.conf
├── mysql/                  # MySQL 配置和数据
│   ├── conf/my.cnf
│   └── data/
├── redis/                  # Redis 数据
│   └── data/
├── logs/                   # 日志文件
│   ├── nginx/
│   └── php/
├── backups/                # 数据库备份
├── docker-compose.yml       # Docker Compose 配置
├── .env                   # 环境变量
└── *.sh                   # 管理脚本
```

## 服务器重启后恢复部署

项目设计了自动恢复机制，服务器重启后只需执行：

```bash
./start-services.sh
```

此脚本会：
1. 检查 Docker 服务状态
2. 启动 Docker 服务（如需要）
3. 检查容器状态
4. 启动所有服务
5. 等待服务就绪
6. 执行健康检查

如需实现开机自动启动，可创建 systemd 服务：

```bash
sudo nano /etc/systemd/system/xiaowu-blog.service
```

内容如下：

```ini
[Unit]
Description=小伍博客服务
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
ExecStart=/path/to/bianchn/start-services.sh
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
```

启用服务：

```bash
sudo systemctl daemon-reload
sudo systemctl enable xiaowu-blog.service
```

## 故障排除

### 查看系统状态

```bash
./check-system.sh
```

此脚本会检查：
- 系统环境（操作系统、内存、磁盘）
- Docker 环境（版本、服务状态）
- 项目文件完整性
- 配置文件
- 容器状态
- 服务健康状态
- 端口占用
- 错误日志

### 常见问题

### Docker 部署相关

**Docker 镜像拉取超时/失败**

如果遇到类似 "failed to resolve source metadata" 或网络超时错误，这是由于 Docker 镜像拉取网络问题：

```bash
# 方法1: 使用国内镜像加速（推荐）
sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json <<EOF
{
  "registry-mirrors": [
    "https://docker.m.daocloud.io",
    "https://dockerhub.azk8s.cn"
  ]
}
EOF
sudo systemctl daemon-reload
sudo systemctl restart docker

# 方法2: 手动拉取镜像
docker pull php:8.1-fpm-alpine
docker pull mysql:8.0
docker pull redis:7-alpine
docker pull nginx:alpine

# 方法3: 配置代理
export HTTP_PROXY=http://your-proxy:port
export HTTPS_PROXY=http://your-proxy:port
```

**Docker 容器无法启动**

```bash
# 查看容器日志
docker-compose logs nginx
docker-compose logs php-fpm
docker-compose logs mysql

# 检查端口占用
netstat -tuln | grep -E '8080|3000|3306'
```

**数据库连接失败**

```bash
# 测试数据库连接
docker exec xiaowu-mysql mysql -uroot -p

# 检查 wp-config.php 配置
cat wordpress/wp-config.php
```

**无法访问 WordPress**

```bash
# 检查 Nginx 状态
docker-compose ps nginx

# 查看 Nginx 日志
docker-compose logs nginx

# 测试端口
curl http://localhost:8080
```

**前端构建失败**

```bash
# 清理并重新安装
cd admin-panel
rm -rf node_modules dist
npm install
npm run build
```

## 备份与恢复

### 备份数据库

```bash
./backup-db.sh
```

备份文件会保存到 `backups/` 目录，自动压缩并保留30天。

### 恢复数据库

```bash
./restore-db.sh
```

按提示选择备份文件进行恢复。

### 完整备份

```bash
# 停止服务
./stop-services.sh

# 备份数据库
./backup-db.sh

# 备份文件
tar -czf xiaowu_blog_backup_$(date +%Y%m%d).tar.gz \
  wordpress/wp-content \
  docker/nginx/conf.d \
  docker/php \
  .env
```

## 开发模式

### 启动前端开发服务器

```bash
cd admin-panel
npm run dev
```

前端开发服务器运行在 http://localhost:3000

### 热重载开发

前端开发支持热重载，修改代码后自动刷新浏览器。

## 性能优化

### 优化建议

1. 启用 OpCache（默认已配置）
2. 使用 Redis 缓存（默认已配置）
3. 配置 CDN 存储静态资源
4. 启用 Nginx Gzip 压缩（默认已配置）
5. 定期清理缓存和优化数据库

### 缓存清理

```bash
# Redis 缓存
docker exec xiaowu-redis redis-cli FLUSHALL

# WordPress 对象缓存
# 在 WordPress 后台清理
```

## 安全建议

1. 修改默认数据库密码
2. 限制 WordPress 后台访问 IP
3. 启用 HTTPS（使用 Let's Encrypt）
4. 定期更新 WordPress 和插件
5. 配置防火墙规则
6. 定期备份数据

## 项目文档

- [项目概述](./INDEX.md)
- [架构设计](./ARCHITECTURE.md)
- [开发指南](./DEVELOPER_GUIDE.md)
- [接口定义](./INTERFACES.md)
- [部署手册](./DEPLOYMENT_MANUAL.md)

## 许可证

GPL v2 or later

## 联系方式

- GitHub: https://github.com/957662/bianchn
- Issues: https://github.com/957662/bianchn/issues
