#!/bin/bash

# 环境配置验证脚本
# 检查部署前所有必需的环境变量和依赖

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env.local"
ERRORS=0
WARNINGS=0

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 打印函数
print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
    ((ERRORS++))
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((WARNINGS++))
}

# 检查文件是否存在
check_file() {
    local file=$1
    local desc=$2
    if [ -f "$file" ]; then
        print_success "$desc 存在"
    else
        print_error "$desc 不存在: $file"
    fi
}

# 检查目录是否存在
check_dir() {
    local dir=$1
    local desc=$2
    if [ -d "$dir" ]; then
        print_success "$desc 存在"
    else
        print_error "$desc 不存在: $dir"
    fi
}

# 检查命令是否可用
check_command() {
    local cmd=$1
    local desc=$2
    if command -v "$cmd" &> /dev/null; then
        local version=$($cmd --version 2>&1 | head -n 1)
        print_success "$desc 已安装 ($version)"
    else
        print_error "$desc 未安装"
    fi
}

# 检查环境变量
check_env_var() {
    local var=$1
    local value=$(grep "^$var=" "$ENV_FILE" 2>/dev/null | cut -d'=' -f2-)
    if [ -z "$value" ]; then
        print_warning "环境变量 $var 未设置"
    else
        if [[ "$var" == *"PASSWORD"* ]] || [[ "$var" == *"KEY"* ]] || [[ "$var" == *"SECRET"* ]] || [[ "$var" == *"TOKEN"* ]]; then
            print_success "环境变量 $var 已设置 (已隐藏敏感信息)"
        else
            print_success "环境变量 $var = $value"
        fi
    fi
}

# ========================================
# 开始检查
# ========================================

print_header "Xiaowu 博客项目 - 部署前检查"

# 1. 检查基本文件结构
print_header "1. 检查基本文件结构"
check_dir "/workspaces/bianchn/wordpress" "WordPress 目录"
check_dir "/workspaces/bianchn/admin-panel" "Admin Panel 目录"
check_dir "/workspaces/bianchn/docker" "Docker 配置目录"
check_dir "/workspaces/bianchn/mysql" "MySQL 数据目录"
check_dir "/workspaces/bianchn/redis" "Redis 数据目录"
check_file "/workspaces/bianchn/docker-compose.yml" "docker-compose.yml"
check_file "/workspaces/bianchn/wordpress/wp-config.php" "WordPress 配置文件"

# 2. 检查自定义插件
print_header "2. 检查自定义插件"
check_dir "/workspaces/bianchn/wordpress/wp-content/plugins/xiaowu-base" "xiaowu-base 插件"
check_file "/workspaces/bianchn/wordpress/wp-content/plugins/xiaowu-base/xiaowu-base.php" "xiaowu-base 主文件"
check_file "/workspaces/bianchn/wordpress/wp-content/plugins/xiaowu-base/includes/class-rate-limiter.php" "RateLimiter 类"
check_file "/workspaces/bianchn/wordpress/wp-content/plugins/xiaowu-base/includes/class-cors-manager.php" "CORSManager 类"
check_file "/workspaces/bianchn/wordpress/wp-content/plugins/xiaowu-base/includes/class-api-middleware.php" "APIMiddleware 类"

# 3. 检查系统命令
print_header "3. 检查系统命令"
check_command "docker" "Docker"
check_command "docker-compose" "Docker Compose"
check_command "git" "Git"
check_command "php" "PHP CLI"
check_command "mysql" "MySQL CLI"
check_command "curl" "cURL"

# 4. 检查 Node.js 和 npm (用于 Admin Panel)
print_header "4. 检查 Node.js 环境"
check_command "node" "Node.js"
check_command "npm" "npm"

# 5. 检查环境配置文件
print_header "5. 检查环境配置"
if [ -f "$ENV_FILE" ]; then
    print_success "环境配置文件存在: $ENV_FILE"
    
    # 检查关键环境变量
    echo -e "\n检查关键环境变量:"
    check_env_var "DB_PASSWORD"
    check_env_var "DB_NAME"
    check_env_var "MYSQL_ROOT_PASSWORD"
    check_env_var "REDIS_PASSWORD"
    check_env_var "CORS_ALLOWED_ORIGINS"
    check_env_var "WP_HOME"
    check_env_var "VITE_API_URL"
else
    print_error "环境配置文件不存在: $ENV_FILE"
    print_warning "请复制 .env.example 为 .env.local 并填写相应的值"
    echo "  cp .env.example .env.local"
    echo "  nano .env.local  # 编辑配置"
fi

# 6. 检查 Admin Panel 依赖
print_header "6. 检查 Admin Panel"
if [ -f "/workspaces/bianchn/admin-panel/package.json" ]; then
    print_success "Admin Panel package.json 存在"
    if [ -d "/workspaces/bianchn/admin-panel/node_modules" ]; then
        print_success "Admin Panel 依赖已安装"
    else
        print_warning "Admin Panel 依赖未安装 (运行 npm install)"
    fi
else
    print_error "Admin Panel package.json 不存在"
fi

# 7. 检查配置文件
print_header "7. 检查代码配置文件"
check_file "/workspaces/bianchn/admin-panel/.eslintrc.cjs" "ESLint 配置"
check_file "/workspaces/bianchn/admin-panel/.prettierrc" "Prettier 配置"
check_file "/workspaces/bianchn/admin-panel/vite.config.js" "Vite 配置"

# 8. 检查 Nginx 配置
print_header "8. 检查 Nginx 配置"
check_file "/workspaces/bianchn/docker/nginx/nginx.conf" "Nginx 主配置"
check_file "/workspaces/bianchn/docker/nginx/conf.d/cors.conf" "CORS 配置"

# 9. 检查 PHP 配置
print_header "9. 检查 PHP 配置"
check_file "/workspaces/bianchn/docker/php/Dockerfile" "PHP Dockerfile"
check_file "/workspaces/bianchn/docker/php/php.ini" "php.ini"
check_file "/workspaces/bianchn/docker/php/php-fpm.conf" "php-fpm.conf"

# 10. 检查数据库配置
print_header "10. 检查 MySQL 配置"
check_file "/workspaces/bianchn/mysql/conf/my.cnf" "MySQL 配置文件"

# 11. 检查部署脚本
print_header "11. 检查部署脚本"
check_file "/workspaces/bianchn/deploy.sh" "主部署脚本"
check_file "/workspaces/bianchn/start-services.sh" "启动服务脚本"
check_file "/workspaces/bianchn/stop-services.sh" "停止服务脚本"
check_file "/workspaces/bianchn/check-env.sh" "环境检查脚本"

# 12. 检查 PHP 扩展 (需要在 Docker 运行时检查)
print_header "12. PHP 扩展检查 (需要 Docker 运行时检查)"
print_warning "以下检查需要 Docker 容器正在运行"
echo "运行以下命令验证 PHP 扩展:"
echo "  docker-compose exec php php -m | grep -E 'redis|json|mysql|curl|gd'"

# 13. 总结
print_header "检查总结"
echo -e "错误: ${RED}$ERRORS${NC}"
echo -e "警告: ${YELLOW}$WARNINGS${NC}"

if [ $ERRORS -eq 0 ]; then
    print_success "所有关键检查通过！"
    echo -e "\n${GREEN}系统已准备好部署。${NC}"
    echo -e "\n接下来的步骤:"
    echo "  1. 编辑 .env.local 文件，填写实际的配置值"
    echo "  2. 运行: docker-compose up -d"
    echo "  3. 运行: ./deploy.sh"
    echo "  4. 访问: https://yourdomain.com"
    exit 0
else
    print_error "存在 $ERRORS 个关键错误，请修复后再部署"
    exit 1
fi
