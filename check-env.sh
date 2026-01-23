#!/bin/bash

set -e

echo "=================================="
echo "   小伍博客 - 环境检测与补全脚本"
echo "=================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 环境检测状态
DETECTED_MISSING=()

# 检测函数
check_command() {
  if command -v "$1" &> /dev/null; then
    return 0
  else
    return 1
  fi
}

check_version() {
  if command -v "$1" &> /dev/null; then
    "$1" "$2" | awk '{print $NF}'
    echo "$3"
  else
    echo "2" "未安装"
    echo "$3"
  fi
}

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

# 检测Node.js
echo ""
echo "2. 检测 Node.js..."
NODE_VERSION=$(node --version 2>/dev/null || echo "未安装")
if [[ "$NODE_VERSION" != "未安装" ]]; then
  NODE_MAJOR=$(echo "$NODE_VERSION" | cut -d'.' -f1)
  echo -e "  Node.js版本: ${GREEN}$NODE_VERSION${NC}"
  if [[ "$NODE_MAJOR" -lt 18 ]]; then
    echo -e "${RED}  需要Node.js 18+，当前版本过低${NC}"
    DETECTED_MISSING+=("Node.js 18+")
  fi
else
  echo -e "${YELLOW}Node.js: $NODE_VERSION${NC}"
  DETECTED_MISSING+=("Node.js")
fi

# 检测PHP
echo ""
echo "3. 检测 PHP..."
PHP_VERSION=$(php --version 2>/dev/null || echo "未安装")
if [[ "$PHP_VERSION" != "未安装" ]]; then
  PHP_MAJOR=$(echo "$PHP_VERSION" | cut -d'.' -f1 | cut -d'-' -f1)
  echo -e "  PHP版本: ${GREEN}$PHP_VERSION${NC}"
  if [[ "$PHP_MAJOR" -lt 8 ]]; then
    echo -e "${RED}  需要PHP 8.1+，当前版本过低${NC}"
    DETECTED_MISSING+=("PHP 8.1+")
  fi
else
  echo -e "${YELLOW}PHP: $PHP_VERSION${NC}"
  DETECTED_MISSING+=("PHP 8.1+")
fi

# 检测PHP-FPM
echo ""
echo "4. 检测 PHP-FPM..."
if [[ "$OS" == "linux" ]]; then
  if systemctl is-active --quiet php8.1-fpm || check_command php8.1-fpm; then
    echo -e "${GREEN}PHP-FPM: 已安装并运行${NC}"
  else
    echo -e "${YELLOW}PHP-FPM: 未运行${NC}"
    DETECTED_MISSING+=("PHP-FPM")
  fi
elif [[ "$OS" == "macos" ]]; then
  if brew list php@8.1-fpm &> /dev/null 2>/dev/null; then
    echo -e "${GREEN}PHP-FPM: 已安装${NC}"
  else
    echo -e "${YELLOW}PHP-FPM: 未安装${NC}"
    DETECTED_MISSING+=("PHP-FPM")
  fi
fi

# 检测MySQL
echo ""
echo "5. 检测 MySQL..."
if [[ "$OS" == "linux" ]]; then
  if systemctl is-active --quiet mysql || check_command mysqld; then
    MYSQL_VERSION=$(mysql --version 2>/dev/null | head -n1)
    echo -e "  MySQL版本: ${GREEN}$MYSQL_VERSION${NC}"
  else
    echo -e "${YELLOW}MySQL: 未运行${NC}"
    DETECTED_MISSING+=("MySQL")
  fi
elif [[ "$OS" == "macos" ]]; then
  if brew list mysql &> /dev/null 2>/dev/null; then
    MYSQL_VERSION=$(mysql --version 2>/dev/null | head -n1)
    echo -e "  MySQL版本: ${GREEN}$MYSQL_VERSION${NC}"
  else
    echo -e "${YELLOW}MySQL: 未安装${NC}"
    DETECTED_MISSING+=("MySQL")
  fi
fi

# 检测Redis
echo ""
echo "6. 检测 Redis..."
if redis-cli ping &> /dev/null 2>/dev/null; then
  echo -e "${GREEN}Redis: 已运行${NC}"
  REDIS_VERSION=$(redis-server --version 2>/dev/null || echo "未知版本")
  echo -e "  Redis版本: $REDIS_VERSION${NC}"
else
  echo -e "${YELLOW}Redis: 未运行${NC}"
  DETECTED_MISSING+=("Redis")
fi

# 检测Nginx
echo ""
echo "7. 检测 Nginx..."
if [[ "$OS" == "linux" ]]; then
  NGINX_VERSION=$(nginx -v 2>&1 | head -n1 || echo "未安装")
  if [[ "$NGINX_VERSION" != "未安装" ]]; then
    echo -e "  Nginx版本: ${GREEN}$NGINX_VERSION${NC}"
  else
    echo -e "${YELLOW}Nginx: 未安装${NC}"
    DETECTED_MISSING+=("Nginx")
  fi
elif [[ "$OS" == "macos" ]]; then
  if brew list nginx &> /dev/null 2>/dev/null; then
    NGINX_VERSION=$(nginx -v 2>&1 | head -n1 || echo "未安装")
    if [[ "$NGINX_VERSION" != "未安装" ]]; then
      echo -e "  Nginx版本: ${GREEN}$NGINX_VERSION${NC}"
    else
      echo -e "${YELLOW}Nginx: 未安装${NC}"
      DETECTED_MISSING+=("Nginx")
    fi
  fi
fi

# 检测PM2
echo ""
echo "8. 检测 PM2..."
if command -v pm2 &> /dev/null; then
  PM2_VERSION=$(pm2 --version 2>&1 | head -n1)
  echo -e "  PM2版本: ${GREEN}$PM2_VERSION${NC}"
else
  echo -e "${YELLOW}PM2: 未安装${NC}"
  DETECTED_MISSING+=("PM2")
fi

# 检测Composer
echo ""
echo "9. 检测 Composer..."
if command -v composer &> /dev/null; then
  COMPOSER_VERSION=$(composer -V 2>&1 | head -n1)
  echo -e "  Composer版本: ${GREEN}$COMPOSER_VERSION${NC}"
else
  echo -e "${YELLOW}Composer: 未安装${NC}"
  DETECTED_MISSING+=("Composer")
fi

# 检测WP-CLI
echo ""
echo "10. 检测 WP-CLI..."
if command -v wp &> /dev/null; then
  WP_CLI_VERSION=$(wp --version 2>&1 | head -n1 || echo "未安装")
  echo -e "  WP-CLI版本: ${GREEN}$WP_CLI_VERSION${NC}"
else
  echo -e "${YELLOW}WP-CLI: 未安装${NC}"
  DETECTED_MISSING+=("WP-CLI")
fi

# 检测项目文件
echo ""
echo "11. 检测项目文件..."
cd "$(dirname "${BASH_SOURCE[0]}")"

if [ -d "wordpress" ]; then
  echo -e "${GREEN}WordPress目录: 存在${NC}"
else
  echo -e "${YELLOW}WordPress目录: 不存在${NC}"
fi

if [ -d "wordpress/wp-content" ]; then
  echo -e "${GREEN}wp-content目录: 存在${NC}"
else
  echo -e "${YELLOW}wp-content目录: 不存在${NC}"
fi

if [ -d "admin-panel" ]; then
  echo -e "${GREEN}管理面板目录: 存在${NC}"
else
  echo -e "${YELLOW}管理面板目录: 不存在${NC}"
fi

# 检查插件目录
echo ""
echo "12. 检查插件目录..."
PLUGINS_DIR="wordpress/wp-content/plugins"
REQUIRED_PLUGINS=("xiaowu-ai" "xiaowu-3d-gallery" "xiaowu-comments" "xiaowu-search" "xiaowu-user" "xiaowu-deployment")

for plugin in "${REQUIRED_PLUGINS[@]}"; do
  if [ -d "$PLUGINS_DIR/$plugin" ]; then
    echo -e "${GREEN}$plugin: 存在${NC}"
  else
    echo -e "${YELLOW}$plugin: 不存在${NC}"
    DETECTED_MISSING+=("$plugin 插件")
  fi
done

# 检测配置文件
echo ""
echo "13. 检测配置文件..."
if [ -f "admin-panel/.env" ]; then
  echo -e "${GREEN}前端环境变量: 存在${NC}"
else
  echo -e "${YELLOW}前端环境变量: 不存在${NC}"
fi

# 检测npm是否已安装管理面板依赖
echo ""
echo "14. 检查管理面板依赖..."
cd admin-panel
if [ -f "package.json" ]; then
  if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}依赖未安装，请运行: npm install${NC}"
    DETECTED_MISSING+=("管理面板依赖")
  else
    echo -e "${GREEN}管理面板依赖: 已安装${NC}"
  fi
fi

# 检查PM2配置
echo ""
echo "15. 检查PM2配置..."
if [ -f "admin-panel/ecosystem.config.js" ]; then
  echo -e "${GREEN}PM2配置文件: 存在${NC}"
else
  echo -e "${YELLOW}PM2配置文件: 不存在${NC}"
fi

# 生成检测报告
echo ""
echo "=================================="
echo "   环境检测报告"
echo "=================================="
echo ""

if [ ${#DETECTED_MISSING[@]} -eq 0 ]; then
  echo -e "${GREEN}所有环境检测通过！${NC}"
  echo ""
  echo "下一步："
  echo -e "${BLUE}1. 访问 http://\$(hostname -f)/setup 或 http://localhost:3000/setup${NC}"
  echo "   使用部署配置向导完成系统设置"
  echo ""
  echo "  配置完成后，管理面板将自动启动"
  echo ""
  echo "管理命令："
  echo "  cd admin-panel && npm run pm2:start"
else
  echo -e "${RED}检测到以下缺失组件：${NC}"
  echo ""
  for item in "${DETECTED_MISSING[@]}"; do
    echo -e "${YELLOW}  - $item${NC}"
  done
  echo ""
  echo "补全方案："
  echo ""
  echo -e "${BLUE}Linux系统: 执行以下命令${NC}"
  echo ""
  for item in "${DETECTED_MISSING[@]}"; do
    case "$item" in
      *"Node.js"* | *"PHP 8.1"* | *"PHP-FPM"*)
        echo "  $item (使用包管理器安装)"
        ;;
      *"MySQL"* | *"Redis"* | *"Nginx"*)
        echo "  $item (使用包管理器安装)"
        ;;
      *"PM2"* | *"Composer"*)
        echo "  npm install -g $item"
        ;;
      *"WP-CLI"*)
        echo "  npm install -g wp-cli"
        ;;
      *插件*)
        echo "  检查 wordpress/wp-content/plugins/ 目录"
        ;;
      *"管理面板依赖"*)
        echo "  cd admin-panel && npm install"
        ;;
    esac
  done
  echo ""
  echo -e "${BLUE}macOS系统: 执行以下命令${NC}"
  echo ""
  for item in "${DETECTED_MISSING[@]}"; do
    case "$item" in
      *"Node.js"* | *"PHP 8.1"* | *"PHP-FPM"*)
        echo "  $item (brew install node@18 或 brew install php@8.1)"
        ;;
      *"MySQL"* | *"Redis"* | *"Nginx"*)
        echo "  $item (brew install $item)"
        ;;
      *"PM2"* | *"Composer"*)
        echo "  npm install -g $item"
        ;;
      *"WP-CLI"*)
        echo "  npm install -g wp-cli"
        ;;
      *插件*)
        echo "  检查 wordpress/wp-content/plugins/ 目录"
        ;;
      *"管理面板依赖"*)
        echo "  cd admin-panel && npm install"
        ;;
    esac
  done
  echo ""
  echo -e "${GREEN}补全完成后请重新运行此脚本${NC}"
fi

echo ""
echo "=================================="
