#!/bin/bash

# 小伍博客 - 服务启动脚本
# 用于服务器重启后快速恢复服务

set -e

echo "======================================"
echo "   小伍博客 - 服务启动"
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

# ==========================================
# 检查 Docker 服务状态
# ==========================================

echo -e "${BLUE}[1/5] 检查 Docker 服务...${NC}"

if ! systemctl is-active --quiet docker 2>/dev/null; then
  echo -e "  ${YELLOW}Docker 服务未运行，正在启动...${NC}"
  systemctl start docker
  sleep 3
fi

if systemctl is-active --quiet docker; then
  echo -e "  ${GREEN}Docker 服务已运行${NC}"
else
  echo -e "  ${RED}Docker 服务启动失败${NC}"
  exit 1
fi

echo ""

# ==========================================
# 检查容器状态
# ==========================================

echo -e "${BLUE}[2/5] 检查容器状态...${NC}"

CONTAINERS=("xiaowu-nginx" "xiaowu-php-fpm" "xiaowu-mysql" "xiaowu-redis" "xiaowu-admin-panel")

RUNNING_CONTAINERS=0
STOPPED_CONTAINERS=0

for container in "${CONTAINERS[@]}"; do
  if docker ps --format '{{.Names}}' | grep -q "^${container}$"; then
    echo -e "  ${GREEN}运行中${NC} $container"
    ((RUNNING_CONTAINERS++))
  elif docker ps -a --format '{{.Names}}' | grep -q "^${container}$"; then
    echo -e "  ${YELLOW}已停止${NC} $container"
    ((STOPPED_CONTAINERS++))
  else
    echo -e "  ${RED}不存在${NC} $container"
  fi
done

echo ""
echo "运行中: $RUNNING_CONTAINERS, 已停止: $STOPPED_CONTAINERS"
echo ""

# ==========================================
# 启动 Docker Compose 服务
# ==========================================

echo -e "${BLUE}[3/5] 启动 Docker Compose 服务...${NC}"

if docker-compose ps -q | grep -q .; then
  echo "  服务已存在，执行启动操作..."
  docker-compose start
else
  echo "  服务未创建，执行首次启动..."
  docker-compose up -d
fi

if [ $? -ne 0 ]; then
  echo -e "  ${RED}服务启动失败${NC}"
  exit 1
fi

echo -e "  ${GREEN}服务启动完成${NC}"
echo ""

# ==========================================
# 等待服务就绪
# ==========================================

echo -e "${BLUE}[4/5] 等待服务就绪...${NC}"

# 等待 MySQL
echo -n "  等待 MySQL..."
for i in {1..30}; do
  if docker exec xiaowu-mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
    echo -e " ${GREEN}就绪${NC}"
    break
  fi
  if [ $i -eq 30 ]; then
    echo -e " ${RED}超时${NC}"
    exit 1
  fi
  echo -n "."
  sleep 1
done

# 等待 Redis
echo -n "  等待 Redis..."
for i in {1..30}; do
  if docker exec xiaowu-redis redis-cli ping 2>/dev/null | grep -q PONG; then
    echo -e " ${GREEN}就绪${NC}"
    break
  fi
  if [ $i -eq 30 ]; then
    echo -e " ${RED}超时${NC}"
    exit 1
  fi
  echo -n "."
  sleep 1
done

# 等待 PHP-FPM
echo -n "  等待 PHP-FPM..."
for i in {1..30}; do
  if docker exec xiaowu-php-fpm php -v &>/dev/null; then
    echo -e " ${GREEN}就绪${NC}"
    break
  fi
  if [ $i -eq 30 ]; then
    echo -e " ${RED}超时${NC}"
    exit 1
  fi
  echo -n "."
  sleep 1
done

# 等待 Nginx
echo -n "  等待 Nginx..."
for i in {1..30}; do
  if curl -s http://localhost:8080 &>/dev/null; then
    echo -e " ${GREEN}就绪${NC}"
    break
  fi
  if [ $i -eq 30 ]; then
    echo -e " ${RED}超时${NC}"
    exit 1
  fi
  echo -n "."
  sleep 1
done

echo ""

# ==========================================
# 健康检查
# ==========================================

echo -e "${BLUE}[5/5] 执行健康检查...${NC}"

# 检查 MySQL
if docker exec xiaowu-mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
  echo -e "  ${GREEN}MySQL${NC} 健康"
else
  echo -e "  ${RED}MySQL${NC} 异常"
fi

# 检查 Redis
if docker exec xiaowu-redis redis-cli ping 2>/dev/null | grep -q PONG; then
  echo -e "  ${GREEN}Redis${NC} 健康"
else
  echo -e "  ${RED}Redis${NC} 异常"
fi

# 检查 PHP-FPM
if docker exec xiaowu-php-fpm php -v &>/dev/null; then
  echo -e "  ${GREEN}PHP-FPM${NC} 健康"
else
  echo -e "  ${RED}PHP-FPM${NC} 异常"
fi

# 检查 Nginx
if curl -s http://localhost:8080 &>/dev/null; then
  echo -e "  ${GREEN}Nginx${NC} 健康"
else
  echo -e "  ${RED}Nginx${NC} 异常"
fi

echo ""

# ==========================================
# 显示结果
# ==========================================

echo "======================================"
echo -e "${GREEN}   服务启动成功！${NC}"
echo "======================================"
echo ""

echo "服务状态："
docker-compose ps

echo ""
echo "======================================"
echo "   访问地址"
echo "======================================"
echo ""
echo -e "  WordPress前台:  ${GREEN}http://localhost:8080${NC}"
echo -e "  WordPress后台:  ${GREEN}http://localhost:8080/wp-admin${NC}"
echo -e "  管理面板:      ${GREEN}http://localhost:3000${NC}"
echo ""
