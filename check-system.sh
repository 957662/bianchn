#!/bin/bash

# 小伍博客 - 系统检查脚本
# 用于诊断和排查部署问题

set -e

echo "======================================"
echo "   小伍博客 - 系统检查"
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
  echo -e "  ${GREEN}[PASS]${NC} $1"
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
# 1. 系统环境检查
# ==========================================

echo -e "${BLUE}[1] 系统环境${NC}"
echo ""

# 操作系统
echo "操作系统:"
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
  check_pass "Linux 系统"
  KERNEL_VERSION=$(uname -r)
  echo "    内核版本: $KERNEL_VERSION"
elif [[ "$OSTYPE" == "darwin"* ]]; then
  check_pass "macOS 系统"
else
  check_fail "不支持的操作系统: $OSTYPE"
fi

# CPU
CPU_COUNT=$(nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo "Unknown")
echo "    CPU核心: $CPU_COUNT"

# 内存
if command -v free &> /dev/null; then
  TOTAL_MEM=$(free -m | awk '/Mem:/ {print $2}')
  AVAIL_MEM=$(free -m | awk '/Mem:/ {print $7}')
  echo "    内存总量: ${TOTAL_MEM}MB"
  echo "    可用内存: ${AVAIL_MEM}MB"

  if [ "$TOTAL_MEM" -lt 1024 ]; then
    check_warn "内存不足，建议至少 2GB"
  else
    check_pass "内存充足"
  fi
fi

# 磁盘空间
DISK_TOTAL=$(df -h "$PROJECT_ROOT" | awk 'NR==2 {print $2}')
DISK_AVAIL=$(df -h "$PROJECT_ROOT" | awk 'NR==2 {print $4}')
DISK_PERCENT=$(df "$PROJECT_ROOT" | awk 'NR==2 {print $5}' | sed 's/%//')
echo "    磁盘总量: $DISK_TOTAL"
echo "    可用空间: $DISK_AVAIL"
echo "    使用率: ${DISK_PERCENT}%"

if [ "$DISK_PERCENT" -gt 80 ]; then
  check_warn "磁盘空间不足"
else
  check_pass "磁盘空间充足"
fi

echo ""

# ==========================================
# 2. Docker 环境检查
# ==========================================

echo -e "${BLUE}[2] Docker 环境${NC}"
echo ""

# Docker
echo "Docker:"
if command -v docker &> /dev/null; then
  DOCKER_VERSION=$(docker --version)
  echo "    版本: $DOCKER_VERSION"
  check_pass "Docker 已安装"

  # 检查 Docker 服务状态
  if systemctl is-active --quiet docker 2>/dev/null; then
    check_pass "Docker 服务运行中"
  else
    check_fail "Docker 服务未运行"
  fi
else
  check_fail "Docker 未安装"
fi

# Docker Compose
echo ""
echo "Docker Compose:"
if command -v docker-compose &> /dev/null; then
  COMPOSE_VERSION=$(docker-compose --version)
  echo "    版本: $COMPOSE_VERSION"
  check_pass "Docker Compose 已安装"
else
  check_fail "Docker Compose 未安装"
fi

echo ""

# ==========================================
# 3. 项目文件检查
# ==========================================

echo -e "${BLUE}[3] 项目文件${NC}"
echo ""

REQUIRED_FILES=(
  "docker-compose.yml"
  ".env.example"
  "wordpress/wp-config.php"
  "admin-panel/package.json"
)

for file in "${REQUIRED_FILES[@]}"; do
  if [ -f "$file" ]; then
    check_pass "$file 存在"
  else
    check_fail "$file 不存在"
  fi
done

echo ""

# ==========================================
# 4. 配置文件检查
# ==========================================

echo -e "${BLUE}[4] 配置文件${NC}"
echo ""

# 根目录 .env
if [ -f .env ]; then
  check_pass ".env 文件存在"

  # 检查关键配置
  if grep -q "DB_NAME=" .env; then
    DB_NAME=$(grep "^DB_NAME=" .env | cut -d'=' -f2)
    echo "    数据库名: $DB_NAME"
  fi

  if grep -q "DB_USER=" .env; then
    DB_USER=$(grep "^DB_USER=" .env | cut -d'=' -f2)
    echo "    数据库用户: $DB_USER"
  fi
else
  check_warn ".env 文件不存在，已复制 .env.example"
  cp .env.example .env
fi

# admin-panel .env
if [ -f admin-panel/.env ]; then
  check_pass "admin-panel/.env 文件存在"
else
  check_warn "admin-panel/.env 文件不存在"
fi

echo ""

# ==========================================
# 5. 容器状态检查
# ==========================================

echo -e "${BLUE}[5] 容器状态${NC}"
echo ""

if docker ps &>/dev/null; then
  CONTAINERS=("xiaowu-nginx" "xiaowu-php-fpm" "xiaowu-mysql" "xiaowu-redis" "xiaowu-admin-panel")

  RUNNING_COUNT=0
  TOTAL_COUNT=0

  for container in "${CONTAINERS[@]}"; do
    STATUS=$(docker ps --format '{{.Status}}' -f "name=$container" 2>/dev/null)
    if [ -n "$STATUS" ]; then
      echo "    $container: ${GREEN}运行中${NC} - $STATUS"
      ((RUNNING_COUNT++))
      ((TOTAL_COUNT++))
      check_pass "$container 运行中"
    else
      # 检查容器是否存在但未运行
      if docker ps -a --format '{{.Names}}' | grep -q "^${container}$"; then
        echo "    $container: ${YELLOW}已停止${NC}"
        ((TOTAL_COUNT++))
        check_warn "$container 已停止"
      else
        echo "    $container: ${RED}不存在${NC}"
        check_fail "$container 不存在"
      fi
    fi
  done

  echo ""
  echo "    容器总计: $TOTAL_COUNT"
  echo "    运行中: $RUNNING_COUNT"
else
  check_fail "Docker 未运行或无权限"
fi

echo ""

# ==========================================
# 6. 服务健康检查
# ==========================================

echo -e "${BLUE}[6] 服务健康${NC}"
echo ""

# MySQL
echo "MySQL:"
if docker ps --format '{{.Names}}' | grep -q "^xiaowu-mysql$"; then
  if docker exec xiaowu-mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
    check_pass "MySQL 响应正常"

    # 检查数据库连接数
    CONN_COUNT=$(docker exec xiaowu-mysql mysql -uroot -e "SHOW PROCESSLIST;" 2>/dev/null | wc -l)
    echo "    当前连接: $CONN_COUNT"
  else
    check_fail "MySQL 无响应"
  fi
else
  check_warn "MySQL 容器未运行"
fi

# Redis
echo ""
echo "Redis:"
if docker ps --format '{{.Names}}' | grep -q "^xiaowu-redis$"; then
  if docker exec xiaowu-redis redis-cli ping 2>/dev/null | grep -q PONG; then
    check_pass "Redis 响应正常"

    # 检查 Redis 内存使用
    REDIS_INFO=$(docker exec xiaowu-redis redis-cli info memory 2>/dev/null)
    REDIS_MEM=$(echo "$REDIS_INFO" | grep "used_memory_human:" | cut -d':' -f2)
    echo "    内存使用: $REDIS_MEM"
  else
    check_fail "Redis 无响应"
  fi
else
  check_warn "Redis 容器未运行"
fi

# Nginx
echo ""
echo "Nginx:"
if docker ps --format '{{.Names}}' | grep -q "^xiaowu-nginx$"; then
  if curl -s http://localhost:8080 &>/dev/null; then
    check_pass "Nginx 响应正常"

    # 检查 HTTP 状态码
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080)
    echo "    HTTP 状态: $HTTP_CODE"
  else
    check_fail "Nginx 无法访问"
  fi
else
  check_warn "Nginx 容器未运行"
fi

# PHP-FPM
echo ""
echo "PHP-FPM:"
if docker ps --format '{{.Names}}' | grep -q "^xiaowu-php-fpm$"; then
  if docker exec xiaowu-php-fpm php -v &>/dev/null; then
    check_pass "PHP-FPM 响应正常"

    # 检查 PHP 版本
    PHP_VERSION=$(docker exec xiaowu-php-fpm php -v | head -1)
    echo "    $PHP_VERSION"
  else
    check_fail "PHP-FPM 无响应"
  fi
else
  check_warn "PHP-FPM 容器未运行"
fi

echo ""

# ==========================================
# 7. 端口占用检查
# ==========================================

echo -e "${BLUE}[7] 端口检查${NC}"
echo ""

PORTS=("8080" "3000" "3306" "6379" "9000")
PORT_SERVICES=("WordPress" "管理面板" "MySQL" "Redis" "PHP-FPM")

for i in "${!PORTS[@]}"; do
  PORT="${PORTS[$i]}"
  SERVICE="${PORT_SERVICES[$i]}"

  if netstat -tuln 2>/dev/null | grep -q ":$PORT " || ss -tuln 2>/dev/null | grep -q ":$PORT "; then
    check_pass "端口 $PORT ($SERVICE) 正在监听"
  else
    check_warn "端口 $PORT ($SERVICE) 未监听"
  fi
done

echo ""

# ==========================================
# 8. 日志检查
# ==========================================

echo -e "${BLUE}[8] 错误日志检查${NC}"
echo ""

# Nginx 错误日志
if [ -f "logs/nginx/error.log" ]; then
  ERROR_COUNT=$(tail -n 100 "logs/nginx/error.log" | grep -c "error" || echo 0)
  if [ "$ERROR_COUNT" -eq 0 ]; then
    check_pass "Nginx 无错误 (最近100行)"
  else
    check_warn "Nginx 发现 $ERROR_COUNT 个错误 (最近100行)"
  fi
fi

echo ""

# ==========================================
# 检查结果汇总
# ==========================================

echo "======================================"
echo "   检查结果汇总"
echo "======================================"
echo ""
echo -e "${GREEN}通过${NC}: $PASS_COUNT"
echo -e "${YELLOW}警告${NC}: $WARN_COUNT"
echo -e "${RED}失败${NC}: $FAIL_COUNT"
echo ""

if [ $FAIL_COUNT -eq 0 ] && [ $WARN_COUNT -eq 0 ]; then
  echo -e "${GREEN}所有检查通过！系统状态良好。${NC}"
  exit 0
elif [ $FAIL_COUNT -eq 0 ]; then
  echo -e "${YELLOW}检查完成，存在一些警告，请关注。${NC}"
  exit 0
else
  echo -e "${RED}检查发现 $FAIL_COUNT 个失败项，请修复后重试。${NC}"
  echo ""
  echo "常见解决方案："
  echo "1. 确保 Docker 服务已启动: systemctl start docker"
  echo "2. 运行部署脚本: ./deploy.sh"
  echo "3. 检查配置文件: .env"
  echo "4. 查看容器日志: docker-compose logs"
  exit 1
fi
