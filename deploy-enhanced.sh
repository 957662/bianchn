#!/bin/bash

# ==========================================
# 小伍博客 - 增强版部署脚本 v2.0
# 包含完整的输入验证和安全检查
# ==========================================

set -e

# 启用更严格的错误处理
set -o pipefail

# ==========================================
# 颜色和日志定义
# ==========================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[⚠]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_section() {
    echo ""
    echo "======================================"
    echo -e "${CYAN}  $1${NC}"
    echo "======================================"
    echo ""
}

# ==========================================
# 输入验证函数
# ==========================================

# 验证输入不为空
validate_not_empty() {
    local input="$1"
    local field_name="$2"
    
    if [ -z "$input" ]; then
        log_error "$field_name 不能为空"
        return 1
    fi
    return 0
}

# 验证输入长度
validate_length() {
    local input="$1"
    local min_len="$2"
    local max_len="$3"
    local field_name="$4"
    
    local len=${#input}
    
    if [ "$len" -lt "$min_len" ] || [ "$len" -gt "$max_len" ]; then
        log_error "$field_name 长度必须在 $min_len-$max_len 个字符之间（当前: $len）"
        return 1
    fi
    return 0
}

# 验证是否为数字
validate_numeric() {
    local input="$1"
    local field_name="$2"
    
    if ! [[ "$input" =~ ^[0-9]+$ ]]; then
        log_error "$field_name 必须为数字"
        return 1
    fi
    return 0
}

# 验证是否为 URL
validate_url() {
    local input="$1"
    local field_name="$2"
    
    # 简单的 URL 验证正则
    if ! [[ "$input" =~ ^https?://.*$ ]]; then
        log_error "$field_name 必须是有效的 URL（以 http:// 或 https:// 开头）"
        return 1
    fi
    return 0
}

# 验证 IP 地址
validate_ip() {
    local input="$1"
    local field_name="$2"
    
    # 简单的 IPv4 验证
    if ! [[ "$input" =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; then
        log_error "$field_name 必须是有效的 IP 地址"
        return 1
    fi
    return 0
}

# 防止 SQL 注入
validate_no_sql_injection() {
    local input="$1"
    local field_name="$2"
    
    # 检查危险的 SQL 关键字
    local dangerous_keywords=("DROP" "DELETE" "TRUNCATE" "INSERT" "UPDATE" "ALTER" "CREATE" "EXEC" "EXECUTE" "UNION" "SELECT")
    
    local upper_input=$(echo "$input" | tr '[:lower:]' '[:upper:]')
    
    for keyword in "${dangerous_keywords[@]}"; do
        if [[ "$upper_input" == *"$keyword"* ]]; then
            log_error "$field_name 包含非法 SQL 关键字: $keyword"
            return 1
        fi
    done
    
    return 0
}

# 防止 XSS 攻击
validate_no_xss() {
    local input="$1"
    local field_name="$2"
    
    # 检查危险的 HTML/JavaScript 标签
    local dangerous_patterns=("<script" "javascript:" "onerror=" "onclick=" "onload=" "<iframe" "<object" "eval(")
    
    for pattern in "${dangerous_patterns[@]}"; do
        if [[ "$input" == *"$pattern"* ]]; then
            log_error "$field_name 包含非法代码: $pattern"
            return 1
        fi
    done
    
    return 0
}

# 安全的文件路径验证
validate_file_path() {
    local path="$1"
    local field_name="$2"
    
    # 防止路径遍历
    if [[ "$path" == *".."* ]] || [[ "$path" == /* ]]; then
        log_error "$field_name 包含非法路径字符"
        return 1
    fi
    return 0
}

# ==========================================
# 安全检查函数
# ==========================================

# 检查脚本权限
check_script_permissions() {
    log_info "检查脚本权限..."
    
    if [ "$EUID" -eq 0 ] && [ -z "$DOCKER_ALLOW_ROOT" ]; then
        log_warning "不建议以 root 身份运行此脚本"
        read -p "继续吗？(y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    
    log_success "权限检查通过"
}

# 检查依赖
check_dependencies() {
    log_info "检查依赖..."
    
    local deps=("docker" "docker-compose" "curl" "jq")
    local missing_deps=()
    
    for cmd in "${deps[@]}"; do
        if ! command -v "$cmd" &> /dev/null; then
            missing_deps+=("$cmd")
        fi
    done
    
    if [ ${#missing_deps[@]} -gt 0 ]; then
        log_error "缺少依赖: ${missing_deps[*]}"
        return 1
    fi
    
    log_success "所有依赖已安装"
}

# 验证 Docker Compose 配置
validate_docker_compose() {
    log_info "验证 docker-compose.yml 配置..."
    
    if [ ! -f "docker-compose.yml" ]; then
        log_error "docker-compose.yml 文件不存在"
        return 1
    fi
    
    if ! docker-compose config > /dev/null 2>&1; then
        log_error "docker-compose.yml 配置无效"
        return 1
    fi
    
    log_success "docker-compose 配置有效"
}

# 验证网络连接
validate_network() {
    log_info "检查网络连接..."
    
    # 测试 DNS
    if ! ping -c 1 8.8.8.8 &> /dev/null; then
        log_warning "无法连接到互联网，某些功能可能不可用"
    else
        log_success "网络连接正常"
    fi
}

# 验证磁盘空间
validate_disk_space() {
    log_info "检查磁盘空间..."
    
    local required_space=5000  # 5GB in MB
    local available_space=$(df . | awk 'NR==2 {print $4}')
    
    if [ "$available_space" -lt "$required_space" ]; then
        log_error "磁盘空间不足（需要: ${required_space}MB，可用: ${available_space}MB）"
        return 1
    fi
    
    log_success "磁盘空间充足（可用: ${available_space}MB）"
}

# ==========================================
# 数据库操作函数
# ==========================================

# 备份数据库
backup_database() {
    log_section "数据库备份"
    
    local backup_dir="./backups/mysql"
    mkdir -p "$backup_dir"
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="$backup_dir/backup_${timestamp}.sql.gz"
    
    log_info "正在备份数据库到 $backup_file..."
    
    docker exec xiaowu-mysql mysqldump -uroot -p"${MYSQL_ROOT_PASSWORD}" --all-databases | gzip > "$backup_file"
    
    log_success "数据库备份完成"
}

# ==========================================
# 主程序
# ==========================================

main() {
    # 项目根目录
    PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    cd "$PROJECT_ROOT"
    
    log_section "小伍博客 - 增强版部署脚本 v2.0"
    
    # ==========================================
    # 第一步: 前置检查
    # ==========================================
    
    log_section "第一步: 前置检查"
    
    check_script_permissions
    check_dependencies
    validate_network
    validate_disk_space
    
    # ==========================================
    # 第二步: 环境配置
    # ==========================================
    
    log_section "第二步: 环境配置"
    
    # 检查 .env 文件
    if [ ! -f ".env" ]; then
        log_warning "未找到 .env 文件，使用默认配置"
        
        # 创建默认 .env（带默认值）
        cat > .env << 'EOF'
# WordPress 配置
WORDPRESS_DB_HOST=xiaowu-mysql
WORDPRESS_DB_PORT=3306
WORDPRESS_DB_NAME=xiaowu_blog
WORDPRESS_DB_USER=xiaowu_user
WORDPRESS_DB_PASSWORD=xiaowu_password_2024
WORDPRESS_TABLE_PREFIX=wp_
WORDPRESS_DEBUG=false

# MySQL 配置
MYSQL_ROOT_PASSWORD=xiaowu_mysql_root_2024
MYSQL_DATABASE=xiaowu_blog
MYSQL_USER=xiaowu_user
MYSQL_PASSWORD=xiaowu_password_2024
MYSQL_PORT=3306

# Redis 配置
REDIS_PASSWORD=xiaowu_redis_2024
REDIS_PORT=6379

# PHP 配置
PHP_MEMORY_LIMIT=256M
PHP_UPLOAD_MAX_FILESIZE=100M
PHP_POST_MAX_SIZE=100M
PHP_MAX_EXECUTION_TIME=300
PHP_DISPLAY_ERRORS=false

# Nginx 配置
NGINX_PORT=8080
NGINX_HTTPS_PORT=8443

# SSL 配置（可选）
SSL_ENABLED=false
SSL_CERT_PATH=
SSL_KEY_PATH=

# 时区
TIMEZONE=Asia/Shanghai

# AI 服务配置（可选）
AI_API_KEY=
AI_API_URL=

# CDN 配置（可选）
CDN_ENABLED=false
CDN_URL=
EOF
        
        log_warning "已创建 .env 文件，请根据需要修改配置"
        log_info "重要: 请修改以下默认密码:"
        log_info "  - WORDPRESS_DB_PASSWORD"
        log_info "  - MYSQL_ROOT_PASSWORD"
        log_info "  - MYSQL_PASSWORD"
        log_info "  - REDIS_PASSWORD"
        
        read -p "按 Enter 继续（或按 Ctrl+C 取消）..."
    fi
    
    # 加载环境变量
    source .env
    
    # 验证环境变量
    if ! validate_not_empty "$MYSQL_ROOT_PASSWORD" "MySQL Root 密码"; then
        exit 1
    fi
    
    if ! validate_length "$MYSQL_ROOT_PASSWORD" 8 128 "MySQL Root 密码"; then
        exit 1
    fi
    
    if ! validate_no_sql_injection "$MYSQL_ROOT_PASSWORD" "MySQL Root 密码"; then
        exit 1
    fi
    
    log_success "环境配置验证通过"
    
    # ==========================================
    # 第三步: Docker Compose 验证
    # ==========================================
    
    log_section "第三步: Docker Compose 验证"
    
    validate_docker_compose
    
    # ==========================================
    # 第四步: 备份现有数据
    # ==========================================
    
    log_section "第四步: 备份现有数据"
    
    if docker-compose ps -q | grep -q .; then
        log_info "检测到已运行的服务，创建备份..."
        backup_database
    else
        log_info "未检测到运行的服务"
    fi
    
    # ==========================================
    # 第五步: 停止现有服务
    # ==========================================
    
    log_section "第五步: 停止现有服务"
    
    if docker-compose ps -q | grep -q .; then
        log_info "停止现有服务..."
        docker-compose down
        log_success "服务已停止"
    else
        log_info "未检测到运行的服务"
    fi
    
    # ==========================================
    # 第六步: 拉取镜像
    # ==========================================
    
    log_section "第六步: 拉取 Docker 镜像"
    
    log_info "拉取镜像（这可能需要几分钟）..."
    if ! docker-compose pull; then
        log_error "拉取镜像失败"
        log_warning "故障排除:"
        log_warning "1. 检查网络连接"
        log_warning "2. 尝试配置 Docker 镜像源"
        exit 1
    fi
    
    log_success "镜像拉取完成"
    
    # ==========================================
    # 第七步: 启动服务
    # ==========================================
    
    log_section "第七步: 启动服务"
    
    log_info "启动 Docker Compose 服务..."
    if ! docker-compose up -d; then
        log_error "服务启动失败"
        log_info "查看详细日志: docker-compose logs"
        exit 1
    fi
    
    log_success "服务已启动"
    
    # ==========================================
    # 第八步: 健康检查
    # ==========================================
    
    log_section "第八步: 健康检查"
    
    # 等待 MySQL
    log_info "等待 MySQL 就绪..."
    local retry=0
    while [ $retry -lt 60 ]; do
        if docker exec xiaowu-mysql mysqladmin ping -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent 2>/dev/null; then
            log_success "MySQL 就绪"
            break
        fi
        retry=$((retry + 1))
        if [ $retry -eq 60 ]; then
            log_error "MySQL 启动超时"
            exit 1
        fi
        echo -n "."
        sleep 1
    done
    
    # 等待 Redis
    log_info "等待 Redis 就绪..."
    retry=0
    while [ $retry -lt 30 ]; do
        if docker exec xiaowu-redis redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null | grep -q PONG; then
            log_success "Redis 就绪"
            break
        fi
        retry=$((retry + 1))
        if [ $retry -eq 30 ]; then
            log_error "Redis 启动超时"
            exit 1
        fi
        echo -n "."
        sleep 1
    done
    
    # 等待 Nginx
    log_info "等待 Nginx 就绪..."
    retry=0
    while [ $retry -lt 30 ]; do
        if curl -s http://localhost:${NGINX_PORT:-8080} &>/dev/null; then
            log_success "Nginx 就绪"
            break
        fi
        retry=$((retry + 1))
        if [ $retry -eq 30 ]; then
            log_error "Nginx 启动超时"
            exit 1
        fi
        echo -n "."
        sleep 1
    done
    
    # ==========================================
    # 第九步: 显示部署结果
    # ==========================================
    
    log_section "部署完成 ✓"
    
    echo ""
    echo "服务状态:"
    docker-compose ps
    
    echo ""
    log_section "访问地址"
    echo ""
    log_info "WordPress 前台: http://localhost:${NGINX_PORT:-8080}"
    log_info "WordPress 后台: http://localhost:${NGINX_PORT:-8080}/wp-admin"
    log_info "REST API: http://localhost:${NGINX_PORT:-8080}/wp-json"
    echo ""
    
    log_section "首次使用"
    echo ""
    echo "1. 访问 WordPress 后台: http://localhost:${NGINX_PORT:-8080}/wp-admin"
    echo "2. 完成 WordPress 安装配置"
    echo "3. 激活 xiaowu-base 安全插件"
    echo "4. 激活其他小伍系列插件"
    echo "5. 配置系统设置和 API 密钥"
    echo ""
    
    log_section "常用命令"
    echo ""
    echo "查看日志:"
    echo "  docker-compose logs -f"
    echo ""
    echo "停止服务:"
    echo "  docker-compose stop"
    echo ""
    echo "启动服务:"
    echo "  docker-compose start"
    echo ""
    echo "进入容器:"
    echo "  docker exec -it xiaowu-mysql bash"
    echo "  docker exec -it xiaowu-php-fpm sh"
    echo ""
    
    log_success "部署脚本执行完成！"
}

# 错误处理
trap 'log_error "脚本执行失败"; exit 1' ERR

# 执行主程序
main "$@"
