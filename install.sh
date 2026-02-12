#!/bin/bash
#
# ========================================
# 小伍博客 - WordPress 原生部署安装脚本
# ========================================
# 此脚本将自动安装和配置 WordPress 博客系统
#
# 使用方法:
#   sudo ./install.sh
#
# ========================================

set -e  # 遇到错误立即退出

# 颜色输出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查是否以 root 权限运行
check_root() {
    if [ "$EUID" -ne 0 ]; then
        log_error "请使用 root 权限运行此脚本"
        log_info "使用: sudo $0"
        exit 1
    fi
}

# 获取脚本目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$SCRIPT_DIR"
WP_DIR="$PROJECT_DIR/wordpress"

# 创建 .env 文件（如果不存在）
create_env_file() {
    log_info "检查环境变量配置..."

    if [ ! -f "$PROJECT_DIR/.env" ]; then
        log_warning ".env 文件不存在，正在创建..."

        # 生成随机密码
        DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        REDIS_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)
        ADMIN_PASSWORD=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-16)

        # 生成 WordPress 安全密钥
        AUTH_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        SECURE_AUTH_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        LOGGED_IN_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        NONCE_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        AUTH_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        SECURE_AUTH_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        LOGGED_IN_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
        NONCE_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)

        cat > "$PROJECT_DIR/.env" << EOF
# ============================================
# 小伍博客 - 环境配置文件
# ============================================
# 生成时间: $(date)

# ============================================
# WordPress 配置
# ============================================
WP_HOME=http://localhost
WP_SITEURL=http://localhost
WP_URL=http://localhost
WP_API_URL=http://localhost/wp-json

# WordPress 数据库
DB_NAME=xiaowu_blog
DB_USER=wordpress
DB_PASSWORD=$DB_PASSWORD
DB_HOST=localhost
DB_PORT=3306
DB_CHARSET=utf8mb4
DB_COLLATE=utf8mb4_unicode_ci

# WordPress 安全密钥
AUTH_KEY=$AUTH_KEY
SECURE_AUTH_KEY=$SECURE_AUTH_KEY
LOGGED_IN_KEY=$LOGGED_IN_KEY
NONCE_KEY=$NONCE_KEY
AUTH_SALT=$AUTH_SALT
SECURE_AUTH_SALT=$SECURE_AUTH_SALT
LOGGED_IN_SALT=$LOGGED_IN_SALT
NONCE_SALT=$NONCE_SALT

# WordPress 管理员账户
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=$ADMIN_PASSWORD
WP_ADMIN_EMAIL=admin@localhost.local

# ============================================
# Redis 缓存配置
# ============================================
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=$REDIS_PASSWORD
REDIS_DB=0

# ============================================
# Nginx 配置
# ============================================
NGINX_HOST=localhost
NGINX_PORT=80

# ============================================
# PHP-FPM 配置
# ============================================
PHP_MAX_EXECUTION_TIME=300
PHP_MEMORY_LIMIT=512M
PHP_POST_MAX_SIZE=100M
PHP_UPLOAD_MAX_FILESIZE=100M

# ============================================
# 邮件配置
# ============================================
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM=noreply@localhost.local
SMTP_FROM_NAME=小伍同学博客

# ============================================
# AI 服务配置 (可选)
# ============================================
AI_PROVIDER=openai
AI_API_KEY=
AI_MODEL=gpt-4
AI_MAX_TOKENS=4000
AI_TEMPERATURE=0.7

# ============================================
# CORS 配置
# ============================================
CORS_ALLOWED_ORIGINS=http://localhost,http://localhost:3000,http://localhost:5173
CORS_ALLOW_CREDENTIALS=true

# ============================================
# 速率限制配置
# ============================================
RATE_LIMIT_ENABLED=true
RATE_LIMIT_PUBLIC_REQUESTS=100
RATE_LIMIT_PUBLIC_WINDOW=60
RATE_LIMIT_AUTH_REQUESTS=1000
RATE_LIMIT_AUTH_WINDOW=60

# ============================================
# 调试配置
# ============================================
WP_DEBUG=false
WP_DEBUG_LOG=false
WP_DEBUG_DISPLAY=false
EOF

        log_success ".env 文件已创建"

        # 保存密码信息
        cat > "$PROJECT_DIR/INSTALL_INFO.txt" << EOF
========================================
小伍博客 - 安装信息
========================================
安装时间: $(date)

数据库密码: $DB_PASSWORD
Redis 密码: $REDIS_PASSWORD
管理员密码: $ADMIN_PASSWORD
管理员用户: admin

请妥善保管此信息，并删除此文件！
========================================
EOF
        chmod 600 "$PROJECT_DIR/INSTALL_INFO.txt"

        log_warning "重要信息已保存到: $PROJECT_DIR/INSTALL_INFO.txt"
    else
        log_success ".env 文件已存在"
    fi
}

# 安装依赖包
install_dependencies() {
    log_info "检查和安装依赖包..."

    # 更新包列表
    apt-get update -qq

    # 安装必要的包
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        curl \
        wget \
        unzip \
        git \
        nginx \
        mysql-server \
        php7.4 \
        php7.4-fpm \
        php7.4-mysql \
        php7.4-curl \
        php7.4-gd \
        php7.4-mbstring \
        php7.4-xml \
        php7.4-zip \
        php7.4-bcmath \
        php7.4-intl \
        php-redis \
        redis-server \
        > /dev/null 2>&1

    log_success "依赖包安装完成"
}

# 从 .env 文件安全地读取配置
get_env_value() {
    local key=$1
    local file="$PROJECT_DIR/.env"
    grep "^${key}=" "$file" 2>/dev/null | cut -d'=' -f2- | tr -d '\r\n' || echo ""
}

# 配置 MySQL
setup_mysql() {
    log_info "配置 MySQL 数据库..."

    # 启动 MySQL
    systemctl start mysql
    systemctl enable mysql > /dev/null 2>&1

    # 读取环境变量
    local DB_NAME=$(get_env_value "DB_NAME")
    local DB_USER=$(get_env_value "DB_USER")
    local DB_PASSWORD=$(get_env_value "DB_PASSWORD")

    # 创建数据库和用户
    mysql -uroot << MYSQL_SCRIPT
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
MYSQL_SCRIPT

    log_success "MySQL 配置完成"
}

# 配置 Redis
setup_redis() {
    log_info "配置 Redis 缓存..."

    # 读取环境变量
    local REDIS_PASSWORD=$(get_env_value "REDIS_PASSWORD")

    # 配置 Redis 密码
    if [ -n "$REDIS_PASSWORD" ]; then
        sed -i "s/^# requirepass.*/requirepass ${REDIS_PASSWORD}/" /etc/redis/redis.conf
        sed -i "s/^bind 127.0.0.1.*/bind 127.0.0.1/" /etc/redis/redis.conf
    fi

    # 启动 Redis
    systemctl restart redis-server
    systemctl enable redis-server > /dev/null 2>&1

    log_success "Redis 配置完成"
}

# 配置 PHP-FPM
setup_php_fpm() {
    log_info "配置 PHP-FPM..."

    # 读取环境变量
    local PHP_MAX_EXECUTION_TIME=$(get_env_value "PHP_MAX_EXECUTION_TIME")
    local PHP_MEMORY_LIMIT=$(get_env_value "PHP_MEMORY_LIMIT")
    local PHP_POST_MAX_SIZE=$(get_env_value "PHP_POST_MAX_SIZE")
    local PHP_UPLOAD_MAX_FILESIZE=$(get_env_value "PHP_UPLOAD_MAX_FILESIZE")
    local REDIS_PASSWORD=$(get_env_value "REDIS_PASSWORD")

    # 创建自定义 PHP 配置
    cat > /etc/php/7.4/fpm/conf.d/99-xiaowu-custom.ini << EOF
; 小伍博客自定义 PHP 配置

; 内存限制
memory_limit = ${PHP_MEMORY_LIMIT}

; 最大执行时间
max_execution_time = ${PHP_MAX_EXECUTION_TIME}

; 上传文件大小
upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE}
post_max_size = ${PHP_POST_MAX_SIZE}

; 输入变量
max_input_vars = 5000

; 时间区
date.timezone = Asia/Shanghai

; 会话保存
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379?auth=${REDIS_PASSWORD}"

; OpCache 配置
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
opcache.fast_shutdown = 1
opcache.enable_cli = 1
EOF

    # 配置 PHP-FPM 进程池
    cat > /etc/php/7.4/fpm/pool.d/xiaowu.conf << EOF
[xiaowu]
user = www-data
group = www-data

listen = /var/run/php/php7.4-fpm-xiaowu.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; 进程管理
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; 性能优化
pm.process_idle_timeout = 10s
request_terminate_timeout = ${PHP_MAX_EXECUTION_TIME}

; 访问日志
slowlog = /var/log/php7.4-fpm-xiaowu-slow.log
request_slowlog_timeout = 5s

; PHP 配置
php_admin_value[error_log] = /var/log/php7.4-fpm-xiaowu-error.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379?auth=${REDIS_PASSWORD}"
EOF

    # 重启 PHP-FPM
    systemctl restart php7.4-fpm

    log_success "PHP-FPM 配置完成"
}

# 配置 Nginx
setup_nginx() {
    log_info "配置 Nginx..."

    # 读取环境变量
    local NGINX_HOST=$(get_env_value "NGINX_HOST")
    local NGINX_PORT=$(get_env_value "NGINX_PORT")
    local PHP_UPLOAD_MAX_FILESIZE=$(get_env_value "PHP_UPLOAD_MAX_FILESIZE")
    local PHP_MAX_EXECUTION_TIME=$(get_env_value "PHP_MAX_EXECUTION_TIME")

    # 创建 Nginx 配置
    cat > /etc/nginx/sites-available/xiaowu-blog << EOF
# 小伍博客 Nginx 配置
server {
    listen ${NGINX_PORT};
    server_name ${NGINX_HOST} _;

    # 根目录
    root ${WP_DIR};
    index index.php index.html index.htm;

    # 日志
    access_log /var/log/nginx/xiaowu-blog-access.log;
    error_log /var/log/nginx/xiaowu-blog-error.log;

    # 客户端最大请求体大小
    client_max_body_size ${PHP_UPLOAD_MAX_FILESIZE};

    # Gzip 压缩
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # 速率限制
    limit_req_zone \$binary_remote_addr zone=api_limit:10m rate=10r/s;
    limit_req_zone \$binary_remote_addr zone=auth_limit:10m rate=5r/m;

    # WordPress 标准重写规则
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    # WordPress 管理后台
    location ~ ^/wp-admin/ {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    # PHP 处理
    location ~ \.php$ {
        # 速率限制（仅对 API）
        if (\$request_uri ~ ^/wp-json/) {
            limit_req zone=api_limit burst=20 nodelay;
        }

        try_files \$uri =404;
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php7.4-fpm-xiaowu.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;

        # 超时设置
        fastcgi_read_timeout ${PHP_MAX_EXECUTION_TIME};
        fastcgi_send_timeout ${PHP_MAX_EXECUTION_TIME};
        fastcgi_connect_timeout 60;

        # 缓冲区设置
        fastcgi_buffering on;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 64k;
        fastcgi_temp_file_write_size 128k;
    }

    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 禁止访问敏感文件
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ /wp-config.php {
        deny all;
    }

    location ~ /\.ht {
        deny all;
    }

    # 禁止访问备份和配置文件
    location ~* \.(bak|backup|config|sql|log|txt)\$ {
        deny all;
    }

    # WordPress REST API
    location ~ ^/wp-json/ {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    # 禁止访问 wp-content 和 wp-includes 的 PHP 文件
    location ~* ^/wp-content/.*\.php\$ {
        deny all;
    }

    location ~* ^/wp-includes/.*\.php\$ {
        deny all;
    }
}
EOF

    # 启用站点
    ln -sf /etc/nginx/sites-available/xiaowu-blog /etc/nginx/sites-enabled/xiaowu-blog

    # 禁用默认站点
    rm -f /etc/nginx/sites-enabled/default

    # 测试配置
    nginx -t

    # 重启 Nginx
    systemctl restart nginx
    systemctl enable nginx > /dev/null 2>&1

    log_success "Nginx 配置完成"
}

# 创建 wp-config.php
create_wp_config() {
    log_info "创建 WordPress 配置文件..."

    # 读取环境变量
    source "$PROJECT_DIR/.env"

    cat > "$WP_DIR/wp-config.php" << 'PHPEOF'
<?php
/**
 * WordPress 基础配置
 */

// ** 数据库设置 - 具体信息来自您正在使用的主机 ** //
/** WordPress 数据库名称 */
define( 'DB_NAME', getenv('DB_NAME') ?: 'xiaowu_blog' );

/** MySQL 数据库用户名 */
define( 'DB_USER', getenv('DB_USER') ?: 'wordpress' );

/** MySQL 数据库密码 */
define( 'DB_PASSWORD', getenv('DB_PASSWORD') ?: '' );

/** MySQL 主机 */
define( 'DB_HOST', getenv('DB_HOST') ?: 'localhost' );

/** 创建数据表时默认的文字编码 */
define( 'DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4' );

/** 数据库整理类型。如不确定请勿更改 */
define( 'DB_COLLATE', getenv('DB_COLLATE') ?: '' );

/**#@+
 * 身份密钥设置
 *
 * 您可以到 {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org 密钥服务}
 * 获得随机密钥
 */
define( 'AUTH_KEY',         getenv('AUTH_KEY') ?: 'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  getenv('SECURE_AUTH_KEY') ?: 'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    getenv('LOGGED_IN_KEY') ?: 'put your unique phrase here' );
define( 'NONCE_KEY',        getenv('NONCE_KEY') ?: 'put your unique phrase here' );
define( 'AUTH_SALT',        getenv('AUTH_SALT') ?: 'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', getenv('SECURE_AUTH_SALT') ?: 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   getenv('LOGGED_IN_SALT') ?: 'put your unique phrase here' );
define( 'NONCE_SALT',       getenv('NONCE_SALT') ?: 'put your unique phrase here' );

/**#@-*/

/**
 * WordPress 数据表前缀
 */
\$table_prefix = getenv('DB_TABLE_PREFIX') ?: 'wp_';

/**
 * WordPress 开发者模式
 */
define( 'WP_DEBUG', filter_var(getenv('WP_DEBUG'), FILTER_VALIDATE_BOOLEAN) ?: false );
define( 'WP_DEBUG_LOG', filter_var(getenv('WP_DEBUG_LOG'), FILTER_VALIDATE_BOOLEAN) ?: false );
define( 'WP_DEBUG_DISPLAY', filter_var(getenv('WP_DEBUG_DISPLAY'), FILTER_VALIDATE_BOOLEAN) ?: false );

/* 耐心一点，一切都好了。开始编辑吧！ */

/** WordPress 目录的绝对路径。 */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** 设置 WordPress 变量和包含文件。 */
require_once ABSPATH . 'wp-settings.php';

// ====================================
// 小伍博客自定义配置
// ====================================

// 站点 URL
if (getenv('WP_HOME')) {
    define('WP_HOME', getenv('WP_HOME'));
}
if (getenv('WP_SITEURL')) {
    define('WP_SITEURL', getenv('WP_SITEURL'));
}

// Redis 缓存配置
if (getenv('REDIS_HOST')) {
    define('WP_REDIS_HOST', getenv('REDIS_HOST'));
    define('WP_REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
    if (getenv('REDIS_PASSWORD')) {
        define('WP_REDIS_PASSWORD', getenv('REDIS_PASSWORD'));
    }
    define('WP_REDIS_DATABASE', getenv('REDIS_DB') ?: 0);
    define('WP_REDIS_MAXTTL', 86400);
}

// 自动保存间隔
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

// 禁用文件编辑
define('DISALLOW_FILE_EDIT', true);

// 强制 SSL 管理
define('FORCE_SSL_ADMIN', false);

// 增加上传文件大小
@ini_set('upload_max_filesize', getenv('PHP_UPLOAD_MAX_FILESIZE') ?: '100M');
@ini_set('post_max_size', getenv('PHP_POST_MAX_SIZE') ?: '100M');
@ini_set('max_execution_time', getenv('PHP_MAX_EXECUTION_TIME') ?: 300);
@ini_set('memory_limit', getenv('PHP_MEMORY_LIMIT') ?: '512M');
PHPEOF

    log_success "WordPress 配置文件创建完成"
}

# 安装 Redis Object Cache 插件
install_redis_plugin() {
    log_info "安装 Redis Object Cache 插件..."

    REDIS_PLUGIN_DIR="$WP_DIR/wp-content/plugins/redis-cache"

    if [ ! -d "$REDIS_PLUGIN_DIR" ]; then
        git clone https://github.com/rhubarbgroup/redis-cache.git "$REDIS_PLUGIN_DIR" > /dev/null 2>&1 || {
            log_warning "无法从 Git 安装 Redis 插件，请手动安装"
        }
    fi

    log_success "Redis 插件安装完成"
}

# 设置文件权限
setup_permissions() {
    log_info "设置文件权限..."

    # 设置目录所有者
    chown -R www-data:www-data "$WP_DIR"

    # 设置权限
    find "$WP_DIR" -type d -exec chmod 755 {} \;
    find "$WP_DIR" -type f -exec chmod 644 {} \;

    log_success "文件权限设置完成"
}

# 创建服务管理脚本
create_management_scripts() {
    log_info "创建管理脚本..."

    # 启动脚本
    cat > "$PROJECT_DIR/start.sh" << 'EOF'
#!/bin/bash
sudo systemctl start nginx
sudo systemctl start php7.4-fpm
sudo systemctl start mysql
sudo systemctl start redis-server
echo "所有服务已启动"
EOF

    # 停止脚本
    cat > "$PROJECT_DIR/stop.sh" << 'EOF'
#!/bin/bash
sudo systemctl stop nginx
sudo systemctl stop php7.4-fpm
echo "服务已停止"
EOF

    # 重启脚本
    cat > "$PROJECT_DIR/restart.sh" << 'EOF'
#!/bin/bash
sudo systemctl restart nginx
sudo systemctl restart php7.4-fpm
sudo systemctl restart mysql
sudo systemctl restart redis-server
echo "所有服务已重启"
EOF

    # 状态检查脚本
    cat > "$PROJECT_DIR/status.sh" << 'EOF'
#!/bin/bash
echo "=== 服务状态 ==="
systemctl status nginx --no-pager | grep Active
systemctl status php7.4-fpm --no-pager | grep Active
systemctl status mysql --no-pager | grep Active
systemctl status redis-server --no-pager | grep Active
echo ""
echo "=== 端口监听 ==="
netstat -tuln | grep -E ':(80|3306|6379|9000)' || ss -tuln | grep -E ':(80|3306|6379|9000)'
EOF

    chmod +x "$PROJECT_DIR/start.sh"
    chmod +x "$PROJECT_DIR/stop.sh"
    chmod +x "$PROJECT_DIR/restart.sh"
    chmod +x "$PROJECT_DIR/status.sh"

    log_success "管理脚本创建完成"
}

# 显示安装完成信息
show_completion_info() {
    echo ""
    echo "========================================"
    log_success "安装完成！"
    echo "========================================"
    echo ""
    echo "访问地址:"
    echo "  前台: http://localhost"
    echo "  后台: http://localhost/wp-admin"
    echo ""
    echo "管理命令:"
    echo "  启动服务: ./start.sh"
    echo "  停止服务: ./stop.sh"
    echo "  重启服务: ./restart.sh"
    echo "  查看状态: ./status.sh"
    echo ""
    echo "下一步:"
    echo "1. 访问 http://localhost 完成 WordPress 安装"
    echo "2. 查看 INSTALL_INFO.txt 获取登录凭据"
    echo "3. 在 WordPress 后台激活小伍博客插件"
    echo ""
    echo "========================================"
}

# 主安装流程
main() {
    echo ""
    echo "========================================"
    echo "  小伍博客 - WordPress 原生部署安装"
    echo "========================================"
    echo ""

    check_root
    create_env_file
    install_dependencies
    setup_mysql
    setup_redis
    setup_php_fpm
    setup_nginx
    create_wp_config
    install_redis_plugin
    setup_permissions
    create_management_scripts
    show_completion_info
}

# 运行主流程
main
