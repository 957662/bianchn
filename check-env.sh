#!/bin/bash
set -e

echo "=================================="
echo "   小伍博客 - 一键部署脚本（修复版）"
echo "=================================="
echo ""

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 必须在项目根目录
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

# --------------------------------------------------
# 1. 检测系统
# --------------------------------------------------
echo "1. 检测操作系统..."
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
  OS="linux"
  echo -e "${GREEN}OS: Linux${NC}"
else
  echo -e "${RED}仅支持 Linux${NC}"
  exit 1
fi

# --------------------------------------------------
# 2. Node.js
# --------------------------------------------------
echo ""
echo "2. 检查 Node.js..."
if ! command -v node >/dev/null; then
  curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
  apt-get install -y nodejs
fi
node -v

# --------------------------------------------------
# 3. PHP
# --------------------------------------------------
echo ""
echo "3. 检查 PHP..."
if ! command -v php >/dev/null; then
  apt-get update
  apt-get install -y php php-fpm php-mysql php-xml php-curl php-zip php-gd php-mbstring
fi
php -v

# --------------------------------------------------
# 4. MySQL
# --------------------------------------------------
echo ""
echo "4. 检查 MySQL..."
if ! command -v mysql >/dev/null; then
  apt-get install -y mysql-server
  systemctl enable mysql
  systemctl start mysql
fi

# --------------------------------------------------
# 5. Redis
# --------------------------------------------------
echo ""
echo "5. 检查 Redis..."
if ! command -v redis-server >/dev/null; then
  apt-get install -y redis-server
  systemctl enable redis-server
  systemctl start redis-server
fi

# --------------------------------------------------
# 6. Nginx
# --------------------------------------------------
echo ""
echo "6. 检查 Nginx..."
if ! command -v nginx >/dev/null; then
  apt-get install -y nginx
  systemctl enable nginx
  systemctl start nginx
fi

# --------------------------------------------------
# 7. PM2
# --------------------------------------------------
echo ""
echo "7. 检查 PM2..."
if ! command -v pm2 >/dev/null; then
  npm install -g pm2
fi

# --------------------------------------------------
# 8. WordPress
# --------------------------------------------------
echo ""
echo "8. 检查 WordPress..."
mkdir -p wordpress
if [ ! -f "wordpress/wp-config-sample.php" ]; then
  wget https://wordpress.org/latest.tar.gz -O wordpress.tar.gz
  tar -xzf wordpress.tar.gz --strip-components=1 -C wordpress
fi

mkdir -p wordpress/wp-content/uploads
mkdir -p wordpress/wp-content/plugins

# --------------------------------------------------
# 9. 管理面板 env（关键修复点）
# --------------------------------------------------
echo ""
echo "9. 检查 admin-panel/.env..."

cd admin-panel

if [ ! -f ".env" ]; then
  echo -e "${YELLOW}创建 admin-panel/.env${NC}"
  cat > .env << 'EOF'
VITE_API_URL=http://127.0.0.1:8080/wp-json/xiaowu/v1
VITE_APP_TITLE=小伍博客
PORT=3000
HOST=0.0.0.0
EOF
else
  echo -e "${GREEN}.env 已存在${NC}"
fi

# --------------------------------------------------
# 10. 安装依赖
# --------------------------------------------------
echo ""
echo "10. 安装管理面板依赖..."
if [ ! -d "node_modules" ]; then
  npm install
fi

# --------------------------------------------------
# 11. 构建
# --------------------------------------------------
echo ""
echo "11. 构建管理面板..."
npm run build

# --------------------------------------------------
# 12. PM2 启动（关键修复点）
# --------------------------------------------------
echo ""
echo "12. 启动管理面板..."

pm2 delete xiaowu-admin >/dev/null 2>&1 || true

pm2 start ecosystem.config.js
pm2 save

# --------------------------------------------------
# 13. 端口检查
# --------------------------------------------------
echo ""
echo "13. 检查端口监听..."
ss -lntp | grep 3000 || echo -e "${YELLOW}⚠ 未检测到 3000 端口监听${NC}"

# --------------------------------------------------
# 完成
# --------------------------------------------------
echo ""
echo "=================================="
echo -e "${GREEN}部署完成！${NC}"
echo "=================================="
echo ""
echo -e "管理后台: ${GREEN}http://服务器IP:3000${NC}"
echo -e "WordPress: ${GREEN}http://服务器IP/wp-admin${NC}"
echo ""
echo "PM2 管理命令："
echo "  pm2 logs xiaowu-admin"
echo "  pm2 restart xiaowu-admin"
echo "  pm2 stop xiaowu-admin"
echo ""
