#!/bin/bash

# 小伍博客 - 服务停止脚本

set -e

echo "======================================"
echo "   小伍博客 - 服务停止"
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

# 检查容器状态
echo -e "${BLUE}[1/3] 检查容器状态...${NC}"

if ! docker-compose ps -q | grep -q .; then
  echo -e "  ${YELLOW}没有运行中的容器${NC}"
  exit 0
fi

docker-compose ps

echo ""

# 停止服务
echo -e "${BLUE}[2/3] 停止服务...${NC}"
docker-compose stop

if [ $? -eq 0 ]; then
  echo -e "  ${GREEN}服务已停止${NC}"
else
  echo -e "  ${RED}停止服务失败${NC}"
  exit 1
fi

echo ""

# 清理（可选）
echo -e "${BLUE}[3/3] 清理资源...${NC}"

read -p "是否删除容器和匿名卷？(y/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
  docker-compose down
  echo -e "  ${GREEN}容器和匿名卷已清理${NC}"
else
  echo -e "  ${YELLOW}跳过清理${NC}"
fi

echo ""
echo "======================================"
echo -e "${GREEN}   停止完成${NC}"
echo "======================================"
