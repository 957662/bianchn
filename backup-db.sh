#!/bin/bash

# 小伍博客 - 数据库备份脚本

set -e

echo "======================================"
echo "   数据库备份"
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
DB_NAME=${DB_NAME:-xiaowu_blog}
DB_USER=${DB_USER:-xiaowu_user}
DB_PASSWORD=${DB_PASSWORD:-xiaowu_pass}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-xiaowu_root_pass}

# 备份目录
BACKUP_DIR="${PROJECT_ROOT}/backups"
mkdir -p "$BACKUP_DIR"

# 生成备份文件名
BACKUP_FILE="${BACKUP_DIR}/xiaowu_blog_$(date +%Y%m%d_%H%M%S).sql"

echo -e "${BLUE}开始备份数据库...${NC}"
echo ""

# 执行备份
docker exec xiaowu-mysql mysqldump \
  -uroot -p"${DB_ROOT_PASSWORD}" \
  --single-transaction \
  --routines \
  --triggers \
  "${DB_NAME}" > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
  # 压缩备份文件
  gzip "$BACKUP_FILE"
  BACKUP_FILE="${BACKUP_FILE}.gz"

  FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

  echo -e "${GREEN}备份完成！${NC}"
  echo ""
  echo "备份文件: $BACKUP_FILE"
  echo "文件大小: $FILE_SIZE"
  echo ""

  # 清理旧备份（保留最近30天）
  echo -e "${YELLOW}清理30天前的旧备份...${NC}"
  find "$BACKUP_DIR" -name "xiaowu_blog_*.sql.gz" -mtime +30 -delete

  # 显示备份列表
  echo "当前备份文件："
  ls -lh "$BACKUP_DIR"/xiaowu_blog_*.sql.gz 2>/dev/null || echo "  (无备份文件)"
else
  echo -e "${RED}备份失败！${NC}"
  exit 1
fi

echo ""
