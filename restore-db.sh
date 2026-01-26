#!/bin/bash

# 小伍博客 - 数据库恢复脚本

set -e

echo "======================================"
echo "   数据库恢复"
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

# 列出可用备份
echo -e "${BLUE}可用备份文件：${NC}"
echo ""

if [ ! -d "$BACKUP_DIR" ]; then
  echo -e "${RED}备份目录不存在${NC}"
  exit 1
fi

BACKUP_FILES=("$BACKUP_DIR"/xiaowu_blog_*.sql.gz)

if [ ${#BACKUP_FILES[@]} -eq 0 ]; then
  echo -e "${YELLOW}没有找到备份文件${NC}"
  exit 0
fi

PS3="请选择要恢复的备份文件（输入编号）："
select BACKUP_FILE in "${BACKUP_FILES[@]}" "取消"; do
  case $REPLY in
    [1-9]*|[1-9][0-9]*)
      if [ "$BACKUP_FILE" != "取消" ]; then
        break
      fi
      ;;
    *)
      echo "已取消"
      exit 0
      ;;
  esac
done

echo ""
echo "选择的备份文件: $BACKUP_FILE"

# 确认操作
echo ""
read -p "确认要恢复此备份吗？数据库当前数据将被覆盖！(y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "已取消恢复操作"
  exit 0
fi

echo ""
echo -e "${BLUE}开始恢复数据库...${NC}"

# 解压备份文件
TEMP_SQL="/tmp/xiaowu_restore_$(date +%s).sql"
gunzip -c "$BACKUP_FILE" > "$TEMP_SQL"

# 执行恢复
docker cp "$TEMP_SQL" xiaowu-mysql:/tmp/restore.sql

docker exec xiaowu-mysql mysql -uroot -p"${DB_ROOT_PASSWORD}" "${DB_NAME}" < /tmp/restore.sql

if [ $? -eq 0 ]; then
  echo -e "${GREEN}恢复完成！${NC}"

  # 清理临时文件
  rm -f "$TEMP_SQL"
  docker exec xiaowu-mysql rm -f /tmp/restore.sql

  echo ""
  echo "建议操作："
  echo "1. 清理 WordPress 缓存"
  echo "2. 重新生成搜索索引"
  echo "3. 检查插件功能"
else
  echo -e "${RED}恢复失败！${NC}"
  exit 1
fi

echo ""
