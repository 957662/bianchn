#!/bin/bash

# 小伍博客 - 环境检查脚本
# 用于检查项目部署环境的配置状态

set -e

echo "======================================"
echo "   环境检查"
echo "======================================"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 项目根目录
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_ROOT"

# 计数器
PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# 辅助函数
check_pass() {
  echo -e "  ${GREEN}[OK]${NC} $1"
  ((PASS_COUNT++))
}

check_fail() {
  echo -e "  ${RED}[FAIL]${NC} $1"
  ((FAIL_COUNT++))
}

check_warn() {
  echo -e "  ${YELLOW}[WARN]${NC} $1"
  ((WARN_COUNT++))
}

# ==========================================
# 1. 检查环境变量文件
# ==========================================

echo -e "${BLUE}[1] 环境变量文件检查${NC}"
echo ""

# 根目录 .env
if [ -f .env ]; then
  check_pass ".env 文件存在"

  # 检查必需的环境变量
  REQUIRED_VARS=(
    "DB_NAME"
    "DB_USER"
    "DB_PASSWORD"
    "DB_ROOT_PASSWORD"
  )

  for var in "${REQUIRED_VARS[@]}"; do
    if grep -q "^${var}=" .env; then
      VALUE=$(grep "^${var}=" .env | cut -d'=' -f2)
      if [ -z "$VALUE" ]; then
        check_warn "${var} 未设置值"
      else
        # 隐藏敏感信息
        if [[ "$var" == *"PASSWORD"* ]] || [[ "$var" == *"KEY"* ]]; then
          check_pass "${var}=***"
        else
          check_pass "${var}=${VALUE}"
        fi
      fi
    else
      check_warn "${var} 未配置"
    fi
  done
else
  check_fail ".env 文件不存在"
fi

echo ""

# admin-panel .env
if [ -f admin-panel/.env ]; then
  check_pass "admin-panel/.env 文件存在"

  # 检查关键配置
  if grep -q "VITE_API_URL=" admin-panel/.env; then
    API_URL=$(grep "^VITE_API_URL=" admin-panel/.env | cut -d'=' -f2)
    check_pass "API_URL=$API_URL"
  fi
else
  check_fail "admin-panel/.env 文件不存在"
fi

echo ""

# ==========================================
# 2. 检查 Docker 环境
# ==========================================

echo -e "${BLUE}[2] Docker 环境检查${NC}"
echo ""

if command -v docker &> /dev/null; then
  DOCKER_VERSION=$(docker --version)
  check_pass "Docker: $DOCKER_VERSION"

  # 检查 Docker 服务
  if systemctl is-active --quiet docker 2>/dev/null; then
    check_pass "Docker 服务运行中"
  else
    check_warn "Docker 服务未运行"
  fi
else
  check_fail "Docker 未安装"
fi

if command -v docker-compose &> /dev/null; then
  COMPOSE_VERSION=$(docker-compose --version)
  check_pass "Docker Compose: $COMPOSE_VERSION"
else
  check_fail "Docker Compose 未安装"
fi

echo ""

# ==========================================
# 3. 检查必需目录
# ==========================================

echo -e "${BLUE}[3] 目录检查${NC}"
echo ""

REQUIRED_DIRS=(
  "wordpress/wp-content/uploads"
  "wordpress/wp-content/plugins"
  "mysql/data"
  "redis/data"
  "logs/nginx"
  "docker/nginx/conf.d"
  "docker/php"
)

for dir in "${REQUIRED_DIRS[@]}"; do
  if [ -d "$dir" ]; then
    check_pass "$dir"
  else
    check_warn "$dir 不存在（将在部署时创建）"
  fi
done

echo ""

# ==========================================
# 4. 检查配置文件
# ==========================================

echo -e "${BLUE}[4] 配置文件检查${NC}"
echo ""

CONFIG_FILES=(
  "docker-compose.yml"
  "docker/nginx/conf.d/default.conf"
  "docker/nginx/nginx.conf"
  "docker/php/php.ini"
  "docker/php/php-fpm.conf"
  "mysql/conf/my.cnf"
)

for file in "${CONFIG_FILES[@]}"; do
  if [ -f "$file" ]; then
    check_pass "$file"
  else
    check_fail "$file 不存在"
  fi
done

echo ""

# ==========================================
# 5. 检查端口占用
# ==========================================

echo -e "${BLUE}[5] 端口占用检查${NC}"
echo ""

PORTS=("8080" "3000" "3306" "6379" "9000")
PORT_NAMES=("WordPress" "管理面板" "MySQL" "Redis" "PHP-FPM")

for i in "${!PORTS[@]}"; do
  PORT="${PORTS[$i]}"
  NAME="${PORT_NAMES[$i]}"

  if netstat -tuln 2>/dev/null | grep -q ":$PORT " || ss -tuln 2>/dev/null | grep -q ":$PORT "; then
    check_warn "端口 $PORT ($NAME) 已被占用"
  else
    check_pass "端口 $PORT ($NAME) 可用"
  fi
done

echo ""

# ==========================================
# 6. 检查 Node.js 环境（用于前端构建）
# ==========================================

echo -e "${BLUE}[6] Node.js 环境检查${NC}"
echo ""

if command -v node &> /dev/null; then
  NODE_VERSION=$(node -v)
  check_pass "Node.js: $NODE_VERSION"

  if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm -v)
    check_pass "npm: $NPM_VERSION"
  fi
else
  check_warn "Node.js 未安装（Docker部署不需要）"
fi

echo ""

# ==========================================
# 7. 检查 WordPress 文件
# ==========================================

echo -e "${BLUE}[7] WordPress 文件检查${NC}"
echo ""

if [ -f "wordpress/wp-config.php" ]; then
  check_pass "wp-config.php 存在"
else
  check_warn "wp-config.php 不存在（WordPress未配置）"
fi

# 检查插件
PLUGIN_DIR="wordpress/wp-content/plugins"
if [ -d "$PLUGIN_DIR" ]; then
  PLUGINS=("xiaowu-ai" "xiaowu-3d-gallery" "xiaowu-comments" "xiaowu-search" "xiaowu-user")

  for plugin in "${PLUGINS[@]}"; do
    if [ -d "$PLUGIN_DIR/$plugin" ]; then
      check_pass "插件 $plugin 存在"
    else
      check_fail "插件 $plugin 不存在"
    fi
  done
else
  check_fail "插件目录不存在"
fi

echo ""

# ==========================================
# 8. 检查管理面板文件
# ==========================================

echo -e "${BLUE}[8] 管理面板检查${NC}"
echo ""

ADMIN_PANEL_FILES=(
  "admin-panel/package.json"
  "admin-panel/src/main.js"
  "admin-panel/src/App.vue"
  "admin-panel/vite.config.js"
)

for file in "${ADMIN_PANEL_FILES[@]}"; do
  if [ -f "$file" ]; then
    check_pass "$file"
  else
    check_fail "$file 不存在"
  fi
done

# 检查是否已构建
if [ -d "admin-panel/dist" ]; then
  check_pass "管理面板已构建"
else
  check_warn "管理面板未构建（部署时将自动构建）"
fi

echo ""

# ==========================================
# 9. 检查脚本权限
# ==========================================

echo -e "${BLUE}[9] 脚本权限检查${NC}"
echo ""

SCRIPTS=("deploy.sh" "start-services.sh" "stop-services.sh" "restart-services.sh" "check-system.sh" "init-db.sh" "backup-db.sh" "restore-db.sh")

for script in "${SCRIPTS[@]}"; do
  if [ -f "$script" ]; then
    if [ -x "$script" ]; then
      check_pass "$script 可执行"
    else
      check_warn "$script 不可执行（运行 chmod +x $script）"
    fi
  else
    check_warn "$script 不存在"
  fi
done

echo ""

# ==========================================
# 检查结果汇总
# ==========================================

echo "======================================"
echo "   检查结果"
echo "======================================"
echo ""
echo -e "${GREEN}通过${NC}: $PASS_COUNT"
echo -e "${YELLOW}警告${NC}: $WARN_COUNT"
echo -e "${RED}失败${NC}: $FAIL_COUNT"
echo ""

if [ $FAIL_COUNT -eq 0 ] && [ $WARN_COUNT -eq 0 ]; then
  echo -e "${GREEN}所有检查通过！可以开始部署。${NC}"
  echo ""
  echo "运行部署命令："
  echo "  ./deploy.sh"
  exit 0
elif [ $FAIL_COUNT -eq 0 ]; then
  echo -e "${YELLOW}检查完成，存在一些警告。${NC}"
  echo ""
  echo "建议操作："
  echo "1. 修复警告项"
  echo "2. 运行部署: ./deploy.sh"
  exit 0
else
  echo -e "${RED}检查失败！请修复以下问题后重试。${NC}"
  echo ""
  echo "常见解决方案："
  echo "1. 创建 .env 文件: cp .env.example .env"
  echo "2. 创建 admin-panel/.env 文件"
  echo "3. 确保所有脚本可执行: chmod +x *.sh"
  echo "4. 安装 Docker: curl -fsSL https://get.docker.com | sh"
  exit 1
fi
