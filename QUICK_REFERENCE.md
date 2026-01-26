# 快速参考指南

本指南提供了项目常用操作的快速参考。

## 部署相关脚本

| 脚本 | 用途 | 使用场景 |
|------|------|---------|
| `./check-env.sh` | 检查环境配置 | 部署前检查 |
| `./check-system.sh` | 系统健康检查 | 诊断问题 |
| `./deploy.sh` | 一键部署 | 首次部署 |
| `./start-services.sh` | 启动服务 | 服务器重启后 |
| `./stop-services.sh` | 停止服务 | 维护操作 |
| `./restart-services.sh` | 重启服务 | 更新后重启 |

## 数据库操作脚本

| 脚本 | 用途 | 使用场景 |
|------|------|---------|
| `./init-db.sh` | 初始化数据库 | 首次部署后 |
| `./backup-db.sh` | 备份数据库 | 定期备份 |
| `./restore-db.sh` | 恢复数据库 | 数据恢复 |

## Docker Compose 常用命令

```bash
# 查看服务状态
docker-compose ps

# 启动所有服务
docker-compose up -d

# 停止所有服务
docker-compose stop

# 重启所有服务
docker-compose restart

# 完全停止并删除容器
docker-compose down

# 查看所有服务日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs -f nginx
docker-compose logs -f php-fpm
docker-compose logs -f mysql
docker-compose logs -f redis

# 进入容器
docker exec -it xiaowu-nginx sh
docker exec -it xiaowu-php-fpm sh
docker exec -it xiaowu-mysql bash
docker exec -it xiaowu-redis sh

# 重启特定服务
docker-compose restart nginx
docker-compose restart php-fpm
```

## 访问地址

| 服务 | 地址 | 说明 |
|------|------|------|
| WordPress前台 | http://localhost:8080 | 公开博客前台 |
| WordPress后台 | http://localhost:8080/wp-admin | WordPress管理界面 |
| 管理面板 | http://localhost:3000 | Vue3后台管理 |
| MySQL | localhost:3306 | 数据库连接 |
| Redis | localhost:6379 | 缓存服务 |

## 端口说明

| 端口 | 服务 | 协议 |
|------|------|------|
| 8080 | WordPress (Nginx) | HTTP |
| 8443 | WordPress (Nginx SSL) | HTTPS |
| 3000 | 管理面板 | HTTP |
| 3306 | MySQL | MySQL |
| 6379 | Redis | Redis |
| 9000 | PHP-FPM | FastCGI |

## 配置文件

| 文件 | 用途 |
|------|------|
| `.env` | 项目环境变量 |
| `docker-compose.yml` | Docker 服务配置 |
| `docker/nginx/conf.d/default.conf` | Nginx 站点配置 |
| `docker/nginx/nginx.conf` | Nginx 主配置 |
| `docker/php/php.ini` | PHP 配置 |
| `docker/php/php-fpm.conf` | PHP-FPM 配置 |
| `mysql/conf/my.cnf` | MySQL 配置 |
| `admin-panel/.env` | 前端环境变量 |

## 日志文件

| 日志 | 位置 | 查看命令 |
|------|------|---------|
| Nginx 访问日志 | logs/nginx/access.log | tail -f logs/nginx/access.log |
| Nginx 错误日志 | logs/nginx/error.log | tail -f logs/nginx/error.log |
| PHP 错误日志 | logs/php/error.log | tail -f logs/php/error.log |
| PHP 慢查询日志 | logs/php/slow.log | tail -f logs/php/slow.log |
| Docker 容器日志 | 通过 docker-compose 查看 | docker-compose logs -f [service] |

## 数据目录

| 目录 | 用途 |
|------|------|
| mysql/data | MySQL 数据文件 |
| redis/data | Redis 数据文件 |
| wordpress/wp-content/uploads | 上传文件 |
| backups | 数据库备份 |

## WordPress 插件

| 插件名 | 目录 | 功能 |
|--------|------|------|
| xiaowu-ai | wp-content/plugins/xiaowu-ai | AI 服务 |
| xiaowu-3d-gallery | wp-content/plugins/xiaowu-3d-gallery | 3D 图库 |
| xiaowu-comments | wp-content/plugins/xiaowu-comments | 评论管理 |
| xiaowu-search | wp-content/plugins/xiaowu-search | 智能搜索 |
| xiaowu-user | wp-content/plugins/xiaowu-user | 用户管理 |

## 开发模式

### 启动前端开发服务器

```bash
cd admin-panel
npm run dev
```

开发服务器运行在 http://localhost:3000，支持热重载。

### 构建生产版本

```bash
cd admin-panel
npm run build
```

构建产物输出到 `admin-panel/dist/` 目录。

## 常见问题

### Docker 相关

**Q: Docker 容器无法启动**
```bash
# 查看容器日志
docker-compose logs [service-name]

# 检查端口占用
netstat -tuln | grep [port]
ss -tuln | grep [port]
```

**Q: Docker 磁盘空间不足**
```bash
# 清理未使用的镜像
docker image prune -a

# 清理未使用的容器
docker container prune

# 清理未使用的卷
docker volume prune
```

### 数据库相关

**Q: 数据库连接失败**
```bash
# 测试 MySQL 连接
docker exec -it xiaowu-mysql mysql -uroot -p

# 检查 wp-config.php 配置
cat wordpress/wp-config.php
```

**Q: 数据库性能问题**
```bash
# 查看 MySQL 慢查询
docker exec xiaowu-mysql cat /var/log/mysql/slow.log

# 查看连接数
docker exec xiaowu-mysql mysql -uroot -e "SHOW PROCESSLIST;"
```

### PHP 相关

**Q: PHP 超时**
编辑 `docker/php/php.ini`:
```ini
max_execution_time = 300
max_input_time = 300
```

**Q: 上传文件大小限制**
编辑 `docker/nginx/conf.d/default.conf`:
```nginx
client_max_body_size 100M;
```

编辑 `docker/php/php.ini`:
```ini
upload_max_filesize = 100M
post_max_size = 100M
```

### Nginx 相关

**Q: 404 错误**
检查 Nginx 配置中的 root 路径是否正确。

**Q: 静态资源 404**
确保文件权限正确：
```bash
docker exec xiaowu-nginx ls -la /var/www/html
```

### WordPress 相关

**Q: 白屏问题**
1. 启用调试模式，编辑 `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);
```

2. 查看错误日志

**Q: 插件激活失败**
检查 PHP 错误日志和插件权限：
```bash
docker exec xiaowu-nginx ls -la /var/www/html/wp-content/plugins
```

## 系统服务配置

### 开机自动启动

```bash
# 复制 systemd 服务文件
sudo cp xiaowu-blog.service /etc/systemd/system/

# 重载 systemd 配置
sudo systemctl daemon-reload

# 启用开机自启
sudo systemctl enable xiaowu-blog.service

# 手动启动服务
sudo systemctl start xiaowu-blog.service

# 查看服务状态
sudo systemctl status xiaowu-blog.service
```

### Docker 开机自动启动

```bash
# 启用 Docker 服务
sudo systemctl enable docker

# 确保开机启动
sudo systemctl start docker
```

## 备份策略

### 自动备份计划

使用 cron 设置定期备份：

```bash
# 编辑 crontab
crontab -e

# 每天凌晨 3 点备份数据库
0 3 * * * /workspace/backup-db.sh >> /workspace/logs/backup.log 2>&1
```

### 手动备份

```bash
# 备份数据库
./backup-db.sh

# 备份整个项目
./stop-services.sh
tar -czf xiaowu_full_backup_$(date +%Y%m%d).tar.gz \
  wordpress/wp-content \
  mysql/data \
  redis/data \
  backups/ \
  .env \
  docker-compose.yml
./start-services.sh
```

## 性能监控

### 服务器资源

```bash
# CPU 和内存
htop

# 磁盘 I/O
iotop

# 网络连接
netstat -anp | grep ESTABLISHED
```

### Docker 资源

```bash
# 容器资源使用
docker stats

# 容器详情
docker inspect [container-name]
```

### 应用性能

```bash
# WordPress 缓存命中率
# 通过 WP Rocket 或类似插件查看

# Redis 缓存状态
docker exec xiaowu-redis redis-cli info memory
docker exec xiaowu-redis redis-cli info stats
```

## 安全建议

1. 修改所有默认密码
2. 限制后台访问 IP
3. 启用 HTTPS
4. 定期更新 WordPress 和插件
5. 配置防火墙规则
6. 定期备份数据
7. 禁用文件编辑（wp-config.php）:
   ```php
   define('DISALLOW_FILE_EDIT', true);
   ```
8. 使用安全密钥（WordPress salting）

## 获取帮助

- 查看完整文档: `cat README.md`
- 运行环境检查: `./check-env.sh`
- 运行系统检查: `./check-system.sh`
- 查看脚本帮助: 运行脚本不带参数查看提示
