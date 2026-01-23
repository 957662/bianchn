#!/bin/bash

set -e

echo "=================================="
echo "   小伍博客 - 一键部署脚本"
echo "=================================="
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 检查Docker和Docker Compose
if ! command -v docker &> /dev/null; then
  echo -e "${RED}错误: 未安装Docker${NC}"
  echo "请访问 https://docs.docker.com/get-docker/ 安装Docker"
  exit 1
fi

if ! command -v docker-compose &> /dev/null; then
  echo -e "${RED}错误: 未安装Docker Compose${NC}"
  echo "请访问 https://docs.docker.com/compose/install/ 安装Docker Compose"
  exit 1
fi

echo -e "${GREEN}Docker环境检查通过${NC}"
echo ""

# 创建必要的目录
echo "创建必要的目录..."
mkdir -p wordpress/wp-content/uploads
mkdir -p wordpress/wp-content/plugins
mkdir -p mysql/data
mkdir -p redis/data

echo -e "${GREEN}目录创建完成${NC}"
echo ""

# 检查.env文件
if [ ! -f .env ]; then
  echo -e "${YELLOW}警告: .env文件不存在，使用.env.example创建${NC}"
  cp .env.example .env
  echo -e "${YELLOW}请先编辑.env文件配置环境变量${NC}"
  echo "编辑完成后重新运行此脚本"
  exit 1
fi

echo -e "${GREEN}配置文件检查通过${NC}"
echo ""

# 拉取Docker镜像
echo "拉取Docker镜像..."
docker-compose pull

if [ $? -ne 0 ]; then
  echo -e "${RED}错误: 拉取Docker镜像失败${NC}"
  exit 1
fi

echo -e "${GREEN}镜像拉取完成${NC}"
echo ""

# 启动服务
echo "启动Docker服务..."
docker-compose up -d

if [ $? -ne 0 ]; then
  echo -e "${RED}错误: 启动Docker服务失败${NC}"
  exit 1
fi

echo -e "${GREEN}Docker服务启动成功${NC}"
echo ""

# 等待MySQL服务就绪
echo "等待MySQL服务就绪..."
for i in {1..30}; do
  if docker exec xiaowu-mysql mysqladmin ping -h localhost --silent; then
    echo -e "${GREEN}MySQL服务已就绪${NC}"
    break
  fi
  echo "等待中... ($i/30)"
  sleep 2
done

if [ $i -eq 31 ]; then
  echo -e "${RED}错误: MySQL服务启动超时${NC}"
  exit 1
fi

# 等待WordPress服务就绪
echo "等待WordPress服务就绪..."
sleep 5

# 显示服务状态
echo ""
echo "=================================="
echo "   服务状态"
echo "=================================="
docker-compose ps

echo ""
echo "=================================="
echo "   部署完成"
echo "=================================="
echo ""
echo -e "${GREEN}部署成功！${NC}"
echo ""
echo "访问地址："
echo -e "  后台管理: ${GREEN}http://localhost:8080/wp-admin${NC}"
echo -e "  前台页面: ${GREEN}http://localhost:8080${NC}"
echo -e "  部署向导: ${GREEN}http://localhost:3000/setup${NC}"
echo ""
echo "首次访问需要完成WordPress安装和配置"
echo ""
echo "查看日志："
echo "  docker-compose logs -f"
echo ""
echo "停止服务："
echo "  docker-compose down"
echo ""
echo "重启服务："
echo "  docker-compose restart"
