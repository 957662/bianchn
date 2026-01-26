#!/bin/bash

# 小伍博客 - 数据库初始化脚本
# 用于首次部署后初始化数据库配置

set -e

echo "======================================"
echo "   数据库初始化脚本"
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

# 加载环境变量
if [ -f .env ]; then
  export $(grep -v '^#' .env | xargs)
fi

# 默认配置
DB_HOST=${DB_HOST:-xiaowu-mysql}
DB_NAME=${DB_NAME:-xiaowu_blog}
DB_USER=${DB_USER:-xiaowu_user}
DB_PASSWORD=${DB_PASSWORD:-xiaowu_pass}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-xiaowu_root_pass}

# ==========================================
# 检查 MySQL 连接
# ==========================================

echo -e "${BLUE}[1/6] 检查 MySQL 连接...${NC}"

if ! docker exec xiaowu-mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
  echo -e "  ${RED}无法连接到 MySQL${NC}"
  exit 1
fi

echo -e "  ${GREEN}MySQL 连接正常${NC}"
echo ""

# ==========================================
# 检查数据库是否存在
# ==========================================

echo -e "${BLUE}[2/6] 检查数据库...${NC}"

DB_EXISTS=$(docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "SHOW DATABASES LIKE '${DB_NAME}';" | grep "${DB_NAME}")

if [ -z "$DB_EXISTS" ]; then
  echo -e "  ${YELLOW}数据库不存在，创建中...${NC}"
  docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  echo -e "  ${GREEN}数据库创建成功${NC}"
else
  echo -e "  ${GREEN}数据库已存在${NC}"
fi

echo ""

# ==========================================
# 检查用户是否存在
# ==========================================

echo -e "${BLUE}[3/6] 检查数据库用户...${NC}"

USER_EXISTS=$(docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "SELECT User FROM mysql.user WHERE User='${DB_USER}';" | grep "${DB_USER}")

if [ -z "$USER_EXISTS" ]; then
  echo -e "  ${YELLOW}用户不存在，创建中...${NC}"
  docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "CREATE USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASSWORD}';"
  docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';"
  docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;"
  echo -e "  ${GREEN}用户创建成功${NC}"
else
  echo -e "  ${GREEN}用户已存在${NC}"
  # 更新权限
  docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'%';"
  docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;"
  echo -e "  ${GREEN}权限已更新${NC}"
fi

echo ""

# ==========================================
# 测试用户连接
# ==========================================

echo -e "${BLUE}[4/6] 测试用户连接...${NC}"

if docker exec xiaowu-mysql mysql -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SELECT 1;" &>/dev/null; then
  echo -e "  ${GREEN}用户连接成功${NC}"
else
  echo -e "  ${RED}用户连接失败${NC}"
  exit 1
fi

echo ""

# ==========================================
# 导入初始数据（可选）
# ==========================================

echo -e "${BLUE}[5/6] 检查 WordPress 表...${NC}"

TABLE_COUNT=$(docker exec xiaowu-mysql mysql -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SHOW TABLES;" | grep -c "wp_")

if [ "$TABLE_COUNT" -eq 0 ]; then
  echo -e "  ${YELLOW}数据库为空，请先通过 Web 界面完成 WordPress 安装${NC}"
  echo -e "  ${YELLOW}访问: http://localhost:8080${NC}"
else
  echo -e "  ${GREEN}已存在 ${TABLE_COUNT} 个表${NC}"
fi

echo ""

# ==========================================
# 显示数据库信息
# ==========================================

echo -e "${BLUE}[6/6] 数据库信息${NC}"
echo ""
echo "  数据库主机: $DB_HOST"
echo "  数据库名称: $DB_NAME"
echo "  数据库用户: $DB_USER"
echo "  数据库密码: $DB_PASSWORD"
echo ""

# 显示数据库大小
DB_SIZE=$(docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = '${DB_NAME}';" | tail -1)

if [ ! -z "$DB_SIZE" ]; then
  echo "  数据库大小: ${DB_SIZE} MB"
fi

echo ""

# ==========================================
# 完成
# ==========================================

echo "======================================"
echo -e "${GREEN}   数据库初始化完成${NC}"
echo "======================================"
echo ""

echo "后续步骤："
echo "1. 访问 WordPress 安装页面"
echo "   http://localhost:8080"
echo ""
echo "2. 输入数据库信息："
echo "   数据库名: $DB_NAME"
echo "   用户名: $DB_USER"
echo "   密码: $DB_PASSWORD"
echo "   数据库主机: $DB_HOST"
echo "   表前缀: wp_"
echo ""
echo "3. 完成安装后，登录后台激活插件"
echo ""
