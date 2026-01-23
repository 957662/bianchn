#!/bin/bash

set -e

echo "=================================="
echo "   小伍博客 - Node.js服务器自动部署脚本"
echo "=================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 获取脚本所在目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$SCRIPT_DIR"

# 配置变量
PROJECT_NAME="xiaowu-blog"
DOMAIN=""
DOMAIN_SSL=""
ADMIN_EMAIL=""
DB_NAME="xiaowu_blog"
DB_USER="xiaowu_user"
DB_PASSWORD=""
AI_PROVIDER="openai"
AI_API_KEY=""
CDN_PROVIDER="local"
SMTP_HOST=""
SMTP_PORT="587"
SMTP_ENCRYPTION="tls"
SMTP_USERNAME=""
SMTP_PASSWORD=""

# 解析命令行参数
while [[ $# -gt 0 ]]; do
  case $1 in
    --domain)
      DOMAIN="$2"
      shift 2
      ;;
    --domain-ssl)
      DOMAIN_SSL="$2"
      shift 2
      ;;
    --admin-email)
      ADMIN_EMAIL="$2"
      shift 2
      ;;
    --db-password)
      DB_PASSWORD="$2"
      shift 2
      ;;
    --ai-provider)
      AI_PROVIDER="$2"
      shift 2
      ;;
    --ai-api-key)
      AI_API_KEY="$2"
      shift 2
      ;;
    --cdn-provider)
      CDN_PROVIDER="$2"
      shift 2
      ;;
    --smtp-host)
      SMTP_HOST="$2"
      shift 2
      ;;
    --smtp-username)
      SMTP_USERNAME="$2"
      shift 2
      ;;
    --smtp-password)
      SMTP_PASSWORD="$2"
      shift 2
      ;;
    --help)
      show_help
      exit 0
      ;;
    *)
      echo -e "${RED}未知参数: $1${NC}"
      show_help
      exit 1
      ;;
  esac
done

show_help() {
  echo "用法: $0 [选项]"
  echo ""
  echo "选项:"
  echo "  --domain DOMAIN              网站域名 (如: example.com)"
  echo "  --domain-ssl DOMAIN         SSL域名 (如: www.example.com)"
  echo "  --admin-email EMAIL          管理员邮箱"
  echo "  --db-password PASSWORD        数据库密码"
  echo "  --ai-provider PROVIDER      AI服务提供商 (openai/qwen/wenxin/claude)"
  echo "  --ai-api-key KEY            AI API密钥"
  echo "  --cdn-provider PROVIDER      CDN提供商 (local/tencent/aliyun/qiniu)"
  echo "  --smtp-host HOST             SMTP服务器"
  echo "  --smtp-username USERNAME     SMTP用户名"
  echo "  --smtp-password PASSWORD     SMTP密码"
  echo "  --help                      显示帮助信息"
  echo ""
  echo "示例:"
  echo "  $0 --domain example.com --admin-email admin@example.com"
}

# 检查是否以root权限运行
check_root() {
  if [ "$EUID" -ne 0 ]; then
    echo -e "${YELLOW}警告: 建议使用root或sudo运行此脚本${NC}"
    read -p "是否继续? (y/n): " -n 1 -r
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
      exit 1
    fi
  fi
}

# 检查操作系统
check_os() {
  if [[ "$OSTYPE" == "linux-gnu"* ]]; then
    OS="linux"
  elif [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
  else
    echo -e "${RED}不支持的操作系统: $OSTYPE${NC}"
    exit 1
  fi
  echo -e "${GREEN}操作系统: $OS${NC}"
}

# 安装Node.js
install_nodejs() {
  echo ""
  echo "=================================="
  echo "   安装 Node.js"
  echo "=================================="

  if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v)
    echo -e "${GREEN}Node.js 已安装: $NODE_VERSION${NC}"
    return
  fi

  echo "安装 Node.js 18 LTS..."

  if [[ "$OS" == "linux" ]]; then
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
  elif [[ "$OS" == "macos" ]]; then
    if ! command -v brew &> /dev/null; then
      echo -e "${YELLOW}未检测到Homebrew，尝试手动安装...${NC}"
      curl -o- https://nodejs.org/dist/v18.20.0/node-v18.20.0-linux-x64.tar.xz
      sudo mkdir -p /usr/local/node
      sudo tar -xf node-v18.20.0-linux-x64.tar.xz -C /usr/local/node
      sudo ln -sf /usr/local/node/bin/node /usr/local/bin/node
      sudo ln -sf /usr/local/node/bin/npm /usr/local/bin/npm
    else
      brew install node@18
    fi
  fi

  if command -v node &> /dev/null; then
    NODE_VERSION=$(node -v)
    echo -e "${GREEN}Node.js 安装成功: $NODE_VERSION${NC}"
  else
    echo -e "${RED}Node.js 安装失败${NC}"
    exit 1
  fi
}

# 安装PM2 (Process Manager for Node.js)
install_pm2() {
  echo ""
  echo "=================================="
  echo "   安装 PM2"
  echo "=================================="

  if command -v pm2 &> /dev/null; then
    PM2_VERSION=$(pm2 -v)
    echo -e "${GREEN}PM2 已安装: $PM2_VERSION${NC}"
    return
  fi

  npm install --global pm2

  if command -v pm2 &> /dev/null; then
    PM2_VERSION=$(pm2 -v)
    echo -e "${GREEN}PM2 安装成功: $PM2_VERSION${NC}"
  else
    echo -e "${RED}PM2 安装失败${NC}"
    exit 1
  fi
}

# 安装PHP和FPM
install_php() {
  echo ""
  echo "=================================="
  echo "   安装 PHP 8.1 和 PHP-FPM"
  echo "=================================="

  if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | awk '{print $2}')
    echo -e "${GREEN}PHP 已安装: $PHP_VERSION${NC}"
    return
  fi

  if [[ "$OS" == "linux" ]]; then
    sudo apt-get update
    sudo apt-get install -y software-properties-common
    sudo add-apt-repository ppa:ondrej/php
    sudo apt-get update
    sudo apt-get install -y php8.1 php8.1-fpm php8.1-mysql php8.1-curl php8.1-mbstring php8.1-redis php8.1-xml php8.1-gd php8.1-zip php8.1-cli
  elif [[ "$OS" == "macos" ]]; then
    brew install php@8.1
  fi

  if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1 | awk '{print $2}')
    echo -e "${GREEN}PHP 8.1 安装成功: $PHP_VERSION${NC}"
  else
    echo -e "${RED}PHP 安装失败${NC}"
    exit 1
  fi
}

# 安装MySQL
install_mysql() {
  echo ""
  echo "=================================="
  echo "   安装 MySQL 8.0"
  echo "=================================="

  if command -v mysql &> /dev/null; then
    MYSQL_VERSION=$(mysql --version | head -n 1)
    echo -e "${GREEN}MySQL 已安装: $MYSQL_VERSION${NC}"
    return
  fi

  if [[ "$OS" == "linux" ]]; then
    sudo apt-get install -y mysql-server mysql-client
  elif [[ "$OS" == "macos" ]]; then
    brew install mysql
  fi

  if command -v mysql &> /dev/null; then
    MYSQL_VERSION=$(mysql --version | head -n 1)
    echo -e "${GREEN}MySQL 8.0 安装成功: $MYSQL_VERSION${NC}"
  else
    echo -e "${RED}MySQL 安装失败${NC}"
    exit 1
  fi
}

# 安装Redis
install_redis() {
  echo ""
  echo "=================================="
  echo "   安装 Redis"
  echo "=================================="

  if command -v redis-server &> /dev/null; then
    REDIS_VERSION=$(redis-server --version | awk '{print $2}')
    echo -e "${GREEN}Redis 已安装: $REDIS_VERSION${NC}"
    return
  fi

  if [[ "$OS" == "linux" ]]; then
    sudo apt-get install -y redis-server
  elif [[ "$OS" == "macos" ]]; then
    brew install redis
  fi

  if command -v redis-server &> /dev/null; then
    echo -e "${GREEN}Redis 安装成功${NC}"
  else
    echo -e "${RED}Redis 安装失败${NC}"
    exit 1
  fi

  sudo systemctl start redis-server || redis-server --daemonize yes
  sudo systemctl enable redis-server

  echo -e "${GREEN}Redis 服务已启动${NC}"
}

# 安装Nginx
install_nginx() {
  echo ""
  echo "=================================="
  echo "   安装 Nginx"
  echo "=================================="

  if command -v nginx &> /dev/null; then
    NGINX_VERSION=$(nginx -v 2>&1 | head -n 1)
    echo -e "${GREEN}Nginx 已安装: $NGINX_VERSION${NC}"
    return
  fi

  if [[ "$OS" == "linux" ]]; then
    sudo apt-get install -y nginx
  elif [[ "$OS" == "macos" ]]; then
    brew install nginx
  fi

  if command -v nginx &> /dev/null; then
    NGINX_VERSION=$(nginx -v 2>&1 | head -n 1)
    echo -e "${GREEN}Nginx 安装成功: $NGINX_VERSION${NC}"
  else
    echo -e "${RED}Nginx 安装失败${NC}"
    exit 1
  fi
}

# 创建数据库
create_database() {
  echo ""
  echo "=================================="
  echo "   创建数据库"
  echo "=================================="

  if [ -z "$DB_PASSWORD" ]; then
    DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-24)
    echo -e "${BLUE}生成的数据库密码: $DB_PASSWORD${NC}"
    echo "请妥善保存此密码！"
  fi

  echo "创建数据库: $DB_NAME"

  sudo mysql -u root -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  sudo mysql -u root -e "CREATE USER IF NOT EXISTS \`$DB_USER\`@'localhost' IDENTIFIED BY '$DB_PASSWORD';"

  sudo mysql -u root -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO \`$DB_USER\`@'localhost';"

  sudo mysql -u root -e "FLUSH PRIVILEGES;"

  echo -e "${GREEN}数据库创建成功${NC}"
}

# 安装Composer
install_composer() {
  echo ""
  echo "=================================="
  echo "   安装 Composer"
  echo "=================================="

  if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer -V 2>&1 | head -n 1)
    echo -e "${GREEN}Composer 已安装: $COMPOSER_VERSION${NC}"
    return
  fi

  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

  sudo php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer

  if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer -V 2>&1 | head -n 1)
    echo -e "${GREEN}Composer 安装成功: $COMPOSER_VERSION${NC}"
  else
    echo -e "${RED}Composer 安装失败${NC}"
    exit 1
  fi
}

# 下载WordPress
download_wordpress() {
  echo ""
  echo "=================================="
  echo "   下载 WordPress"
  echo "=================================="

  WP_DIR="/var/www/$PROJECT_NAME"

  sudo mkdir -p $WP_DIR
  cd $WP_DIR

  if [ -f "wp-settings.php" ]; then
    echo -e "${GREEN}WordPress 已存在，跳过下载${NC}"
    return
  fi

  echo "下载最新版 WordPress..."
  sudo curl -O https://wordpress.org/latest.tar.gz
  sudo tar -xzf latest.tar.gz
  sudo mv wordpress/* .
  sudo rm -rf wordpress latest.tar.gz

  echo -e "${GREEN}WordPress 下载完成${NC}"
}

# 配置WordPress
configure_wordpress() {
  echo ""
  echo "=================================="
  echo "   配置 WordPress"
  echo "=================================="

  WP_DIR="/var/www/$PROJECT_NAME"

  cd $WP_DIR

  # 生成随机密钥
  AUTH_KEYS=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  SECURE_AUTH_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  LOGGED_IN_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  NONCE_KEY=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  AUTH_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  SECURE_AUTH_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  LOGGED_IN_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)
  NONCE_SALT=$(openssl rand -base64 64 | tr -d "=+/" | cut -c1-64)

  # 生成配置文件
  sudo tee wp-config.php << 'EOF'
<?php
/**
 * WordPress基础配置文件
 */

// 数据库设置
define('DB_NAME', '$DB_NAME');
define('DB_USER', '$DB_USER');
define('DB_PASSWORD', '$DB_PASSWORD');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');

// 身份验证密钥
define('AUTH_KEY',         '$AUTH_KEYS');
define('SECURE_AUTH_KEY',  '$SECURE_AUTH_KEY');
define('LOGGED_IN_KEY',    '$LOGGED_IN_KEY');
define('NONCE_KEY',        '$NONCE_KEY');
define('AUTH_SALT',        '$AUTH_SALT');
define('SECURE_AUTH_SALT', '$SECURE_AUTH_SALT');
define('LOGGED_IN_SALT',   '$LOGGED_IN_SALT');
define('NONCE_SALT',       '$NONCE_SALT');

// WordPress 数据表前缀
$table_prefix = 'wp_';

// 绝对路径
if (!defined('ABSPATH')) {
  define('ABSPATH', dirname(__FILE__) . '/');
}

/** 设置WordPress变量和包含文件 */
require_once ABSPATH . 'wp-settings.php';

// AI服务配置
define('XIAOWU_AI_PROVIDER', '$AI_PROVIDER');
define('XIAOWU_AI_API_KEY', '$AI_API_KEY');

// CDN配置
define('XIAOWU_CDN_PROVIDER', '$CDN_PROVIDER');

// Redis配置
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);

// 邮件配置
define('XIAOWU_SMTP_HOST', '$SMTP_HOST');
define('XIAOWU_SMTP_PORT', $SMTP_PORT);
define('XIAOWU_SMTP_ENCRYPTION', '$SMTP_ENCRYPTION');
define('XIAOWU_SMTP_USERNAME', '$SMTP_USERNAME');
define('XIAOWU_SMTP_PASSWORD', '$SMTP_PASSWORD');
EOF

  echo -e "${GREEN}WordPress 配置完成${NC}"
}

# 配置文件权限
configure_permissions() {
  echo ""
  echo "=================================="
  echo "   配置文件权限"
  echo "=================================="

  WP_DIR="/var/www/$PROJECT_NAME"

  sudo chown -R www-data:www-data $WP_DIR
  sudo find $WP_DIR -type d -exec chmod 755 {} \;
  sudo find $WP_DIR -type f -exec chmod 644 {} \;
  sudo chmod 777 $WP_DIR/wp-content/uploads

  echo -e "${GREEN}文件权限配置完成${NC}"
}

# 配置PHP-FPM
configure_php_fpm() {
  echo ""
  echo "=================================="
  echo "   配置 PHP-FPM"
  echo "=================================="

  PHP_POOL="/etc/php/8.1/fpm/pool.d/$PROJECT_NAME.conf"

  sudo tee $PHP_POOL << 'EOF'
[$PROJECT_NAME]
user = www-data
group = www-data
listen = /run/php/php8.1-$PROJECT_NAME.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
chdir = $DOCUMENT_ROOT

php_admin_value[error_log] = /var/log/php8.1-$PROJECT_NAME-error.log
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
php_value[session.save_handler] = redis
php_value[session.save_path] = /var/lib/php/sessions
php_value[session.gc_maxlifetime] = 1440
EOF

  sudo systemctl restart php8.1-fpm

  echo -e "${GREEN}PHP-FPM 配置完成${NC}"
}

# 配置Nginx
configure_nginx() {
  echo ""
  echo "=================================="
  echo "   配置 Nginx"
  echo "=================================="

  NGINX_CONF="/etc/nginx/sites-available/$PROJECT_NAME"

  sudo tee $NGINX_CONF << 'EOF'
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;
    root /var/www/$PROJECT_NAME/wordpress;
    index index.php index.html;

    client_max_body_size 100M;

    # 日志配置
    access_log /var/log/nginx/$PROJECT_NAME-access.log;
    error_log /var/log/nginx/$PROJECT_NAME-error.log;

    # WordPress配置
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP处理
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.1-$PROJECT_NAME.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include fastcgi_params;
        fastcgi_param PHP_VALUE "open_basedir=$document_root/:/tmp/";
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|otf|ttf)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 安全头部
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    # 禁止访问敏感文件
    location ~ /\. {
        deny all;
    }

    location ~ /(wp-config|wp-content)\.php$ {
        deny all;
    }
}
EOF

  # 启用站点配置
  sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/

  # 测试配置
  sudo nginx -t

  if [ $? -eq 0 ]; then
    sudo systemctl restart nginx
    echo -e "${GREEN}Nginx 配置完成并已重启${NC}"
  else
    echo -e "${RED}Nginx 配置测试失败${NC}"
    exit 1
  fi
}

# 启动Node.js管理面板
start_admin_panel() {
  echo ""
  echo "=================================="
  echo "   启动 Node.js 管理面板"
  echo "=================================="

  cd "$PROJECT_ROOT/admin-panel"

  if [ ! -d "node_modules" ]; then
    echo "安装管理面板依赖..."
    npm install
  fi

  # 使用PM2启动管理面板
  pm2 delete xiaowu-admin 2>/dev/null || true
  pm2 start ecosystem.config.js --name xiaowu-admin

  echo -e "${GREEN}管理面板已启动${NC}"
}

# 安装WordPress插件
install_plugins() {
  echo ""
  echo "=================================="
  echo "   激活 WordPress 插件"
  echo "=================================="

  WP_DIR="/var/www/$PROJECT_NAME/wordpress"
  WP_CLI="$WP_DIR/wp-cli.phar"

  if [ ! -f "$WP_CLI" ]; then
    echo "下载 WP-CLI..."
    sudo curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    sudo chmod +x $WP_CLI
  fi

  # 使用WP-CLI激活插件
  sudo php $WP_CLI plugin activate --allow-root xiaowu-deployment --path=$WP_DIR/wp-content/plugins/xiaowu-deployment
  sudo php $WP_CLI plugin activate --allow-root xiaowu-ai --path=$WP_DIR/wp-content/plugins/xiaowu-ai
  sudo php $WP_CLI plugin activate --allow-root xiaowu-3d-gallery --path=$WP_DIR/wp-content/plugins/xiaowu-3d-gallery
  sudo php $WP_CLI plugin activate --allow-root xiaowu-comments --path=$WP_DIR/wp-content/plugins/xiaowu-comments
  sudo php $WP_CLI plugin activate --allow-root xiaowu-search --path=$WP_DIR/wp-content/plugins/xiaowu-search
  sudo php $WP_CLI plugin activate --allow-root xiaowu-user --path=$WP_DIR/wp-content/plugins/xiaowu-user

  echo -e "${GREEN}插件激活完成${NC}"
}

# 主安装流程
main_install() {
  echo -e "${BLUE}开始自动安装和配置...${NC}"
  echo ""

  check_root
  check_os

  # 检查域名配置
  if [ -z "$DOMAIN" ]; then
    echo -e "${YELLOW}请使用 --domain 参数指定网站域名${NC}"
    echo "示例: $0 --domain example.com --admin-email admin@example.com --db-password mypassword"
    echo ""
    echo "运行以下命令查看所有选项："
    echo "$0 --help"
    exit 1
  fi

  # 安装所有必需的软件
  install_nodejs
  install_pm2
  install_php
  install_mysql
  install_redis
  install_nginx
  install_composer

  # 配置系统服务
  create_database
  download_wordpress
  configure_wordpress
  configure_permissions
  configure_php_fpm
  configure_nginx

  # 启动服务
  echo ""
  echo "=================================="
  echo "   启动所有服务"
  echo "=================================="

  sudo systemctl enable mysql
  sudo systemctl enable php8.1-fpm
  sudo systemctl enable redis-server
  sudo systemctl enable nginx

  sudo systemctl restart redis-server
  sudo systemctl restart php8.1-fpm
  sudo systemctl restart nginx

  # 安装WordPress插件
  install_plugins

  # 启动Node.js管理面板
  start_admin_panel
}

# 显示部署完成信息
show_completion() {
  echo ""
  echo "=================================="
  echo -e "${GREEN}   部署完成！${NC}"
  echo "=================================="
  echo ""
  echo "访问地址："
  echo -e "  前台网站: ${BLUE}http://$DOMAIN${NC}"
  echo -e "  后台管理: ${BLUE}http://$DOMAIN/wp-admin${NC}"
  echo -e "  部署向导: ${BLUE}http://$DOMAIN/setup${NC}"
  echo -e "  管理面板: ${BLUE}http://$DOMAIN:3000${NC}"
  echo ""
  echo "首次访问请："
  echo "  1. 访问部署向导完成系统配置"
  echo "  2. 或直接访问 /wp-admin 完成WordPress安装"
  echo ""
  echo "管理命令："
  echo "  查看PM2状态: pm2 status"
  echo "  重启管理面板: pm2 restart xiaowu-admin"
  echo "  查看管理面板日志: pm2 logs xiaowu-admin"
  echo "  停止管理面板: pm2 stop xiaowu-admin"
  echo ""
  echo "查看服务日志："
  echo "  Nginx: sudo tail -f /var/log/nginx/$PROJECT_NAME-error.log"
  echo "  PHP-FPM: sudo tail -f /var/log/php8.1-$PROJECT_NAME-error.log"
  echo "  MySQL: sudo tail -f /var/log/mysql/error.log"
  echo ""
  echo "生成的数据库密码: ${RED}$DB_PASSWORD${NC}"
  echo "请妥善保存！"
}

# 执行主安装流程
main_install
show_completion
