#!/bin/bash

set -e

echo "======================================"
echo "   小伍博客 - 一键部署脚本"
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
# 可选步骤: 配置 Docker 镜像源
# ==========================================

echo -e "${BLUE}[可选] 配置 Docker 镜像源${NC}"
echo ""

echo "Docker 镜像源配置选项："
echo "  1) 配置镜像源（修改 /etc/docker/daemon.json）"
echo "  2) 使用默认配置（不修改 Docker 配置）"
echo ""
read -p "请选择 (1-2，默认 2): " -n 1 -r
echo

# 默认使用配置 2（不修改）
if [[ -z $REPLY ]]; then
  REPLY="2"
fi

CONFIGURE_REGISTRY=false
REGISTRY_URL=""

case $REPLY in
  1)
    CONFIGURE_REGISTRY=true
    echo ""
    echo "可用 Docker 镜像源："
    echo "  1) DaoCloud - https://docker.m.daocloud.io"
    echo "  2) 阿里云 - https://dockerhub.azk8s.cn"
    echo "  3) 中科大 - https://docker.mirrors.ustc.edu.cn"
    echo "  4) Docker Hub (官方) - https://dockerhub.com"
    echo ""
    read -p "请选择 Docker 镜像源 (1-4，默认 1): " -n 1 -r
    echo

    # 默认选择 1
    if [[ -z $REPLY ]]; then
      REPLY="1"
    fi

    case $REPLY in
      1)
        REGISTRY_URL="https://docker.m.daocloud.io"
        ;;
      2)
        REGISTRY_URL="https://dockerhub.azk8s.cn"
        ;;
      3)
        REGISTRY_URL="https://docker.mirrors.ustc.edu.cn"
        ;;
      4)
        REGISTRY_URL="https://dockerhub.com"
        ;;
      *)
        echo -e "  ${YELLOW}无效选择，使用默认 DaoCloud${NC}"
        REGISTRY_URL="https://docker.m.daocloud.io"
        ;;
    esac

    # 配置 Docker daemon
    DAEMON_JSON="/etc/docker/daemon.json"

    # 检测系统是否为 root
    if [ "$EUID" -ne 0 ]; then
      echo -e "  ${YELLOW}警告: 需要root权限来配置Docker镜像源${NC}"
      echo -e "  ${YELLOW}请使用 sudo 运行此脚本，或选择使用默认配置${NC}"
      echo ""
      read -p "是否继续配置？(需要 sudo) (y/N): " -n 1 -r
      echo
      if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        CONFIGURE_REGISTRY=false
        REGISTRY_URL=""
      fi
    fi

    if [ "$CONFIGURE_REGISTRY" = true ]; then
      # 创建 Docker daemon 配置目录
      sudo mkdir -p /etc/docker

      # 备份现有配置
      if [ -f "$DAEMON_JSON" ]; then
        sudo cp "$DAEMON_JSON" "${DAEMON_JSON}.backup.$(date +%Y%m%d_%H%M%S)"
        echo -e "  ${YELLOW}已备份现有配置到 ${DAEMON_JSON}.backup.*${NC}"
      fi

      # 创建新的 daemon.json
      sudo tee "$DAEMON_JSON" > /dev/null <<EOF
{
  "registry-mirrors": [
    "$REGISTRY_URL"
  ],
  "max-concurrent-downloads": 3,
  "log-driver": "json-file",
  "log-level": "warn"
}
EOF

      echo -e "  ${GREEN}Docker 镜像源已配置为: $REGISTRY_URL${NC}"

      # 重启 Docker 服务
      echo -e "  ${YELLOW}重启 Docker 服务...${NC}"
      sudo systemctl daemon-reload
      sudo systemctl restart docker

      # 等待 Docker 重启
      sleep 3

      echo -e "  ${GREEN}Docker 服务已重启${NC}"
    fi
    ;;
  2)
    echo -e "  ${GREEN}使用默认 Docker 配置${NC}"
    ;;
  *)
    echo -e "  ${YELLOW}无效选择，使用默认配置${NC}"
    ;;
esac

echo ""

# ==========================================
# 第一步: 检查系统环境
# ==========================================

echo -e "${BLUE}[1/10] 检查系统环境...${NC}"

# 检测操作系统
if [[ "$OSTYPE" == "linux-gnu"* ]]; then
  OS="linux"
  echo -e "  OS: ${GREEN}Linux${NC}"
elif [[ "$OSTYPE" == "darwin"* ]]; then
  OS="macos"
  echo -e "  OS: ${GREEN}macOS${NC}"
else
  echo -e "  ${RED}不支持的操作系统${NC}"
  exit 1
fi

# 检查可用内存
if command -v free &> /dev/null; then
  TOTAL_MEM=$(free -m | awk '/Mem:/ {print $2}')
  echo -e "  内存: ${GREEN}${TOTAL_MEM}MB${NC}"
fi

# 检查磁盘空间
DISK_SPACE=$(df -m "$PROJECT_ROOT" | awk 'NR==2 {print $4}')
echo -e "  磁盘可用: ${GREEN}${DISK_SPACE}MB${NC}"

echo ""

# ==========================================
# 第二步: 检查并安装 Docker
# ==========================================

echo -e "${BLUE}[2/10] 检查 Docker...${NC}"

if ! command -v docker &> /dev/null; then
  echo -e "  ${YELLOW}Docker 未安装，正在安装...${NC}"

  if [[ "$OS" == "linux" ]]; then
    if command -v apt-get &> /dev/null; then
      curl -fsSL https://get.docker.com | sh
      usermod -aG docker $USER 2>/dev/null || true
    elif command -v yum &> /dev/null; then
      yum install -y docker
      systemctl enable docker
      systemctl start docker
    fi
  else
    echo -e "  ${RED}请手动安装 Docker: https://docs.docker.com/get-docker/${NC}"
    exit 1
  fi
fi

DOCKER_VERSION=$(docker --version)
echo -e "  ${GREEN}$DOCKER_VERSION${NC}"

# ==========================================
# 第三步: 检查并安装 Docker Compose
# ==========================================

echo -e "${BLUE}[3/10] 检查 Docker Compose...${NC}"

if ! command -v docker-compose &> /dev/null; then
  echo -e "  ${YELLOW}Docker Compose 未安装，正在安装...${NC}"

  if [[ "$OS" == "linux" ]]; then
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
  else
    echo -e "  ${RED}请手动安装 Docker Compose: https://docs.docker.com/compose/install/${NC}"
    exit 1
  fi
fi

COMPOSE_VERSION=$(docker-compose --version)
echo -e "  ${GREEN}$COMPOSE_VERSION${NC}"

echo ""

# ==========================================
# 第四步: 创建必要的目录结构
# ==========================================

echo -e "${BLUE}[4/10] 创建目录结构...${NC}"

DIRECTORIES=(
  "wordpress/wp-content/uploads"
  "wordpress/wp-content/plugins"
  "wordpress/wp-content/themes"
  "wordpress/wp-content/upgrade"
  "mysql/data"
  "mysql/conf"
  "redis/data"
  "logs/nginx"
  "logs/php"
  "docker/nginx/conf.d"
  "docker/php"
)

for dir in "${DIRECTORIES[@]}"; do
  if [ ! -d "$dir" ]; then
    mkdir -p "$dir"
    echo -e "  ${GREEN}创建${NC} $dir"
  fi
done

echo ""

# ==========================================
# 第五步: 检查并创建配置文件
# ==========================================

echo -e "${BLUE}[5/10] 检查配置文件...${NC}"

# 检查根目录 .env
if [ ! -f .env ]; then
  echo -e "  ${YELLOW}创建 .env 文件...${NC}"
  cp .env.example .env
  echo -e "  ${YELLOW}请编辑 .env 文件配置环境变量${NC}"
fi

# 检查 admin-panel .env
if [ ! -f admin-panel/.env ]; then
  echo -e "  ${YELLOW}创建 admin-panel/.env 文件...${NC}"
  cat > admin-panel/.env << 'EOF'
VITE_API_URL=http://localhost:8080/wp-json
VITE_APP_TITLE=小伍博客管理后台
VITE_APP_LOGO=/favicon.ico
HOST=0.0.0.0
PORT=3000
VITE_ENABLE_AI=true
VITE_ENABLE_3D_GALLERY=true
VITE_ENABLE_SMART_SEARCH=true
VITE_CDN_URL=
VITE_CDN_ENABLED=false
VITE_DEBUG=false
EOF
fi

echo -e "  ${GREEN}配置文件检查完成${NC}"
echo ""

# ==========================================
# 第六步: 检查 admin-panel 依赖
# ==========================================

echo -e "${BLUE}[6/10] 检查 admin-panel 依赖...${NC}"

if [ ! -d "admin-panel/node_modules" ]; then
  echo -e "  ${YELLOW}安装 admin-panel 依赖...${NC}"
  cd admin-panel
  npm install
  cd "$PROJECT_ROOT"
fi

# 检查是否需要构建
if [ ! -d "admin-panel/dist" ] || [ "admin-panel/dist" -ot "admin-panel/src" ]; then
  echo -e "  ${YELLOW}构建 admin-panel...${NC}"
  cd admin-panel
  npm run build
  cd "$PROJECT_ROOT"
fi

echo -e "  ${GREEN}admin-panel 准备完成${NC}"
echo ""

# ==========================================
# 第七步: 测试 Docker 连通性
# ==========================================

echo -e "${BLUE}[7/10] 测试 Docker 连通性...${NC}"

# 测试 Docker Hub 连通性
echo -n "  测试 Docker Hub 连通性..."
if curl -s --connect-timeout 3 --max-time 5 "https://registry-1.docker.io/v2/" &>/dev/null; then
  echo -e " ${GREEN}正常${NC}"
elif curl -s --connect-timeout 3 --max-time 5 "https://index.docker.io/v1/" &>/dev/null; then
  echo -e " ${GREEN}正常${NC}"
else
  echo -e " ${YELLOW}可能较慢或超时${NC}"
  echo -e "  ${YELLOW}如果拉取镜像缓慢，建议配置 Docker 镜像源${NC}"
fi

echo ""

# ==========================================
# 第八步: 检查 docker-compose 配置
# ==========================================

echo -e "${BLUE}[8/10] 检查 docker-compose 配置...${NC}"

if [ "$CONFIGURE_REGISTRY" = true ] && [ -n "$REGISTRY_URL" ]; then
  echo ""
  echo -e "${GREEN}Docker 镜像源已配置为: $REGISTRY_URL${NC}"
  echo ""
else
  echo -e "  ${GREEN}使用现有 Docker 配置${NC}"
fi

# ==========================================
# 第九步: 拉取 Docker 镜像
# ==========================================

echo -e "${BLUE}[9/10] 拉取 Docker 镜像...${NC}"

# 正常拉取
docker-compose pull

if [ $? -ne 0 ]; then
  echo -e "  ${RED}错误: 拉取Docker镜像失败${NC}"
  echo ""
  echo -e "${YELLOW}故障排除建议：${NC}"
  echo "1. 检查网络连接"
  echo "2. 尝试手动拉取: docker pull php:8.1-fpm-alpine"
  echo "3. 配置代理: export HTTP_PROXY=http://proxy:port"
  echo "4. 重新运行脚本并选择配置镜像源"
  exit 1
fi

echo -e "  ${GREEN}镜像拉取完成${NC}"

echo ""

# ==========================================
# 第十步: 停止并清理旧容器
# ==========================================

echo -e "${BLUE}[10/10] 清理旧容器...${NC}"

if docker-compose ps -q | grep -q .; then
  echo -e "  ${YELLOW}停止现有服务...${NC}"
  docker-compose down
fi

echo ""

# ==========================================
# 启动服务
# ==========================================

echo -e "${BLUE}[11/11] 启动服务...${NC}"
docker-compose up -d

if [ $? -ne 0 ]; then
  echo -e "  ${RED}服务启动失败${NC}"
  echo -e "  ${YELLOW}查看日志: docker-compose logs${NC}"
  exit 1
fi

echo -e "  ${GREEN}服务启动完成${NC}"

echo ""

# ==========================================
# 等待服务就绪并健康检查
# ==========================================

echo -e "${BLUE}[12/12] 等待服务就绪...${NC}"

# 等待 MySQL
echo -n "  等待 MySQL..."
for i in {1..60}; do
  if docker exec xiaowu-mysql mysqladmin ping -h localhost --silent 2>/dev/null; then
    echo -e " ${GREEN}就绪${NC}"
    break
  fi
  if [ $i -eq 60 ]; then
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
# 显示部署结果
# ==========================================

echo "======================================"
echo -e "${GREEN}   部署成功！${NC}"
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

echo "======================================"
echo "   首次部署操作"
echo "======================================"
echo ""
echo "1. 访问 WordPress 后台完成安装"
echo "2. 激活以下插件："
echo "   - 小伍AI服务 (xiaowu-ai)"
echo "   - 小伍3D图库 (xiaowu-3d-gallery)"
echo "   - 小伍评论管理 (xiaowu-comments)"
echo "   - 小伍搜索 (xiaowu-search)"
echo "   - 小伍用户管理 (xiaowu-user)"
echo "3. 配置AI服务API密钥"
echo "4. 配置CDN存储（可选）"
echo "5. 配置邮件服务（可选）"
echo ""

echo "======================================"
echo "   常用命令"
echo "======================================"
echo ""
echo "查看日志:"
echo "  docker-compose logs -f"
echo "  docker-compose logs -f nginx"
echo "  docker-compose logs -f php-fpm"
echo "  docker-compose logs -f mysql"
echo ""
echo "停止服务:"
echo "  docker-compose stop"
echo ""
echo "启动服务:"
echo "  docker-compose start"
echo ""
echo "重启服务:"
echo "  docker-compose restart"
echo "  docker-compose restart nginx"
echo ""
echo "完全停止并删除容器:"
echo "  docker-compose down"
echo ""
echo "查看容器状态:"
echo "  docker-compose ps"
echo ""
echo "进入容器:"
echo "  docker exec -it xiaowu-nginx sh"
echo "  docker exec -it xiaowu-php-fpm sh"
echo "  docker exec -it xiaowu-mysql bash"
echo ""
