#!/bin/bash

set -e

echo "=================================="
echo "   小伍博客 - 一键部署脚本"
echo "=================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 检测操作系统
echo "1. 检测操作系统..."
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
  OS="linux"
  echo -e "${GREEN}OS: Linux${NC}"
elif [[ "$OSTYPE" == "darwin"* ]]; then
  OS="macos"
  echo -e "${GREEN}OS: macOS${NC}"
else
  echo -e "${YELLOW}OS: $OSTYPE${NC}"
  echo -e "${RED}警告: 仅支持Linux和macOS${NC}"
  exit 1
fi

# 检查并安装 Node.js
echo ""
echo "2. 检查 Node.js..."
if ! command -v node &> /dev/null; then
  echo -e "${YELLOW}Node.js 未安装，正在安装...${NC}"
  if [[ "$OS" == "linux" ]]; then
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
    apt-get install -y nodejs
  else
    brew install node
  fi
  echo -e "${GREEN}Node.js 安装完成${NC}"
else
  echo -e "${GREEN}Node.js: 已安装${NC}"
fi

# 检查并安装 PHP
echo ""
echo "3. 检查 PHP..."
if ! command -v php &> /dev/null; then
  echo -e "${YELLOW}PHP 未安装，正在安装...${NC}"
  if [[ "$OS" == "linux" ]]; then
    apt-get update && apt-get install -y php php-fpm php-mysql php-xml php-curl php-zip php-gd php-mbstring
  else
    brew install php
  fi
  echo -e "${GREEN}PHP 安装完成${NC}"
else
  echo -e "${GREEN}PHP: 已安装${NC}"
fi

# 检查并启动 PHP-FPM
echo ""
echo "4. 检查 PHP-FPM..."
if [[ "$OS" == "linux" ]]; then
  if ! systemctl is-active --quiet php*-fpm 2>/dev/null; then
    echo -e "${YELLOW}PHP-FPM 未运行，正在启动...${NC}"
    systemctl enable php*-fpm 2>/dev/null || true
    systemctl start php*-fpm 2>/dev/null || service php*-fpm start 2>/dev/null || true
    echo -e "${GREEN}PHP-FPM 已启动${NC}"
  else
    echo -e "${GREEN}PHP-FPM: 已运行${NC}"
  fi
else
  echo -e "${GREEN}PHP-FPM: 已配置${NC}"
fi

# 检查并安装 MySQL
echo ""
echo "5. 检查 MySQL..."
if ! command -v mysql &> /dev/null && ! command -v mysqld &> /dev/null; then
  echo -e "${YELLOW}MySQL 未安装，正在安装...${NC}"
  if [[ "$OS" == "linux" ]]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get install -y mysql-server
    systemctl enable mysql
    systemctl start mysql
  else
    brew install mysql
    brew services start mysql
  fi
  echo -e "${GREEN}MySQL 安装完成${NC}"
else
  if [[ "$OS" == "linux" ]]; then
    if ! systemctl is-active --quiet mysql 2>/dev/null; then
      echo -e "${YELLOW}MySQL 未运行，正在启动...${NC}"
      systemctl start mysql 2>/dev/null || service mysql start 2>/dev/null || true
    fi
  else
    if ! brew services list | grep mysql | grep -q started; then
      brew services start mysql 2>/dev/null || true
    fi
  fi
  echo -e "${GREEN}MySQL: 已运行${NC}"
fi

# 检查并安装 Redis
echo ""
echo "6. 检查 Redis..."
if ! command -v redis-server &> /dev/null; then
  echo -e "${YELLOW}Redis 未安装，正在安装...${NC}"
  if [[ "$OS" == "linux" ]]; then
    apt-get install -y redis-server
    systemctl enable redis-server
    systemctl start redis-server 2>/dev/null || service redis-server start 2>/dev/null || true
  else
    brew install redis
    brew services start redis
  fi
  echo -e "${GREEN}Redis 安装完成${NC}"
else
  if ! redis-cli ping &> /dev/null 2>&1; then
    echo -e "${YELLOW}Redis 未运行，正在启动...${NC}"
    if [[ "$OS" == "linux" ]]; then
      systemctl start redis-server 2>/dev/null || service redis-server start 2>/dev/null || true
    else
      brew services start redis
    fi
  fi
  echo -e "${GREEN}Redis: 已运行${NC}"
fi

# 检查并安装 Nginx
echo ""
echo "7. 检查 Nginx..."
if ! command -v nginx &> /dev/null; then
  echo -e "${YELLOW}Nginx 未安装，正在安装...${NC}"
  if [[ "$OS" == "linux" ]]; then
    apt-get install -y nginx
    systemctl enable nginx
    systemctl start nginx
  else
    brew install nginx
    brew services start nginx
  fi
  echo -e "${GREEN}Nginx 安装完成${NC}"
else
  if [[ "$OS" == "linux" ]] && ! systemctl is-active --quiet nginx 2>/dev/null; then
    systemctl start nginx 2>/dev/null || service nginx start 2>/dev/null || true
  fi
  echo -e "${GREEN}Nginx: 已安装${NC}"
fi

# 检查并安装 PM2
echo ""
echo "8. 检查 PM2..."
if ! command -v pm2 &> /dev/null; then
  echo -e "${YELLOW}PM2 未安装，正在安装...${NC}"
  npm install -g pm2
  echo -e "${GREEN}PM2 安装完成${NC}"
else
  echo -e "${GREEN}PM2: 已安装${NC}"
fi

# 检查并安装 Composer
echo ""
echo "9. 检查 Composer..."
if ! command -v composer &> /dev/null; then
  echo -e "${YELLOW}Composer 未安装，正在安装...${NC}"
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
  echo -e "${GREEN}Composer 安装完成${NC}"
else
  echo -e "${GREEN}Composer: 已安装${NC}"
fi

# 检查并安装 WP-CLI
echo ""
echo "10. 检查 WP-CLI..."
if ! command -v wp &> /dev/null; then
  echo -e "${YELLOW}WP-CLI 未安装，正在安装...${NC}"
  curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod +x wp-cli.phar
  mv wp-cli.phar /usr/local/bin/wp 2>/dev/null || mv wp-cli.phar /usr/bin/wp 2>/dev/null || true
  echo -e "${GREEN}WP-CLI 安装完成${NC}"
else
  echo -e "${GREEN}WP-CLI: 已安装${NC}"
fi

# 进入项目目录
cd "$(dirname "${BASH_SOURCE[0]}")"

# 创建必要的目录
echo ""
echo "11. 创建项目目录..."
mkdir -p wordpress/wp-content/uploads
mkdir -p wordpress/wp-content/plugins
echo -e "${GREEN}目录创建完成${NC}"

# 检查并下载 WordPress
echo ""
echo "12. 检查 WordPress..."
if [ ! -f "wordpress/wp-config-sample.php" ]; then
  echo -e "${YELLOW}WordPress 未安装，正在下载...${NC}"
  if [ ! -f "wordpress.tar.gz" ]; then
    wget https://wordpress.org/latest.tar.gz -O wordpress.tar.gz
  fi
  mkdir -p wordpress
  tar -xzf wordpress.tar.gz --strip-components=1 -C wordpress
  echo -e "${GREEN}WordPress 下载完成${NC}"
else
  echo -e "${GREEN}WordPress: 已安装${NC}"
fi

# 检查插件目录
echo ""
echo "13. 检查插件..."
PLUGINS_DIR="wordpress/wp-content/plugins"
REQUIRED_PLUGINS=("xiaowu-ai" "xiaowu-3d-gallery" "xiaowu-comments" "xiaowu-search" "xiaowu-user" "xiaowu-deployment")

ALL_PLUGINS_EXIST=true
for plugin in "${REQUIRED_PLUGINS[@]}"; do
  if [ ! -d "$PLUGINS_DIR/$plugin" ]; then
    echo -e "${YELLOW}$plugin: 不存在${NC}"
    ALL_PLUGINS_EXIST=false
  fi
done

if [ "$ALL_PLUGINS_EXIST" = false ]; then
  echo -e "${YELLOW}部分插件不存在，请检查 wordpress/wp-content/plugins/ 目录${NC}"
else
  echo -e "${GREEN}所有插件: 已存在${NC}"
fi

# 安装管理面板依赖
echo ""
echo "14. 安装管理面板依赖..."
cd admin-panel
if [ ! -d "node_modules" ]; then
  echo -e "${YELLOW}正在安装依赖...${NC}"
  npm install
  echo -e "${GREEN}依赖安装完成${NC}"
else
  echo -e "${GREEN}管理面板依赖: 已安装${NC}"
fi

# 构建管理面板
echo ""
echo "15. 构建管理面板..."
npm run build
echo -e "${GREEN}构建完成${NC}"

# 使用 PM2 启动管理面板
echo ""
echo "16. 启动管理面板..."
if command -v pm2 &> /dev/null; then
  pm2 delete xiaowu-admin 2>/dev/null || true
  pm2 start ecosystem.config.js
  pm2 save
  echo -e "${GREEN}管理面板已启动${NC}"
else
  echo -e "${YELLOW}请先安装 PM2: npm install -g pm2${NC}"
fi

# 检查配置文件
echo ""
echo "17. 检查配置文件..."
if [ ! -f ".env" ]; then
  echo -e "${YELLOW}.env 文件不存在，请通过部署向导配置${NC}"
fi

if [ ! -f "admin-panel/.env" ]; then
  echo -e "${YELLOW}admin-panel/.env 文件不存在，正在创建...${NC}"
  cat > admin-panel/.env << 'EOF'
VITE_API_URL=http://localhost:8080/wp-json/xiaowu/v1
VITE_APP_TITLE=小伍博客
EOF
  echo -e "${GREEN}admin-panel/.env 文件已创建${NC}"
fi

# 显示完成信息
echo ""
echo "=================================="
echo "   环境准备完成"
echo "=================================="
echo ""
echo -e "${GREEN}所有环境已准备就绪！${NC}"
echo ""
echo "下一步操作："
echo -e "${BLUE}1. 访问部署配置向导:${NC}"
echo -e "   ${GREEN}http://localhost:3000/setup${NC}"
echo ""
echo -e "${BLUE}2. 在向导中完成以下配置:${NC}"
echo "   - 数据库连接配置"
echo "   - AI 服务配置"
echo "   - CDN 配置"
echo "   - 邮件服务配置"
echo ""
echo -e "${BLUE}3. 配置完成后，访问:${NC}"
echo -e "   ${GREEN}管理后台: http://localhost:3000${NC}"
echo -e "   ${GREEN}WordPress后台: http://localhost:8080/wp-admin${NC}"
echo ""
echo "管理命令："
echo "  cd admin-panel"
echo "  pm2:stop     - 停止管理面板"
echo "  pm2:restart  - 重启管理面板"
echo "  pm2:logs     - 查看日志"
echo "  pm2:monit    - 监控状态"
echo ""
echo "=================================="
