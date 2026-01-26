#!/bin/bash

# 小伍博客 - 服务重启脚本

set -e

echo "======================================"
echo "   小伍博客 - 服务重启"
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
echo -e "${BLUE}[1/2] 检查容器状态...${NC}"

if ! docker-compose ps -q | grep -q .; then
  echo -e "  ${YELLOW}没有运行中的容器，执行启动...${NC}"
  bash ./start-services.sh
  exit 0
fi

docker-compose ps

echo ""

# 重启服务
echo -e "${BLUE}[2/2] 重启服务...${NC}"
docker-compose restart

if [ $? -ne 0 ]; then
  echo -e "  ${RED}重启失败${NC}"
  exit 1
fi

echo -e "  ${GREEN}重启完成${NC}"

# 等待服务就绪
echo ""
echo -e "${BLUE}等待服务就绪...${NC}"

for i in {1..10}; do
  if curl -s http://localhost:8080 &>/dev/null; then
    echo -e "  ${GREEN}服务已就绪${NC}"
    break
  fi
  echo -n "."
  sleep 2
done

echo ""

echo "======================================"
echo -e "${GREEN}   重启完成${NC}"
echo "======================================"
echo ""

echo "访问地址："
echo -e "  WordPress前台:  ${GREEN}http://localhost:8080${NC}"
echo -e "  WordPress后台:  ${GREEN}http://localhost:8080/wp-admin${NC}"
echo -e "  管理面板:      ${GREEN}http://localhost:3000${NC}"
echo ""
