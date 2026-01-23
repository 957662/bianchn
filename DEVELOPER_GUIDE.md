# 开发指南

## 项目目的

小伍同学的个人博客是一个基于WordPress的现代化博客系统，集成AI大模型能力，提供智能化的内容创作、管理和阅读体验。它在更大系统中担任个人知识管理平台的核心角色，支持多用户协作、AI辅助创作、3D内容展示等功能。

**核心职责**:
- 提供稳定、高效的内容管理平台
- 集成AI能力提升用户体验
- 支持多用户协作和权限管理
- 提供优美的用户界面和管理后台

**相关系统**:
- 大模型API服务 - 提供AI能力支持
- CDN存储服务 - 提供静态资源和3D模型存储
- 邮件服务 - 提供用户验证和通知服务

## 环境搭建

### 前置条件

- PHP 8.1 或更高版本
- MySQL 8.0 或更高版本
- Redis 6.0 或更高版本
- Nginx 1.20 或更高版本
- Node.js 18 或更高版本（用于前端开发）
- Composer 2 或更高版本
- Git

### 安装

#### 1. 克隆仓库

```bash
git clone https://github.com/yourusername/xiaowu-blog.git
cd xiaowu-blog
```

#### 2. 安装WordPress

```bash
# 下载WordPress
wget https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz
mv wordpress/* .
rm -rf wordpress latest.tar.gz

# 配置WordPress
cp wp-config-sample.php wp-config.php
```

#### 3. 配置数据库

```bash
# 创建数据库
mysql -u root -p
```

```sql
CREATE DATABASE xiaowu_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'xiaowu_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON xiaowu_blog.* TO 'xiaowu_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 4. 编辑 wp-config.php

```php
define('DB_NAME', 'xiaowu_blog');
define('DB_USER', 'xiaowu_user');
define('DB_PASSWORD', 'your_secure_password');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');

// 安全密钥
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',     'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

// Redis缓存
define('WP_REDIS_HOST', '127.0.0.1');
define('WP_REDIS_PORT', 6379);

// 文件上传路径
define('UPLOADS', 'wp-content/uploads');
```

#### 5. 安装插件依赖

```bash
# 进入插件目录
cd wordpress/wp-content/plugins

# 克隆自定义插件
git clone https://github.com/yourusername/xiaowu-ai.git xiaowu-ai
git clone https://github.com/yourusername/xiaowu-3d-gallery.git xiaowu-3d-gallery
git clone https://github.com/yourusername/xiaowu-comments.git xiaowu-comments
git clone https://github.com/yourusername/xiaowu-user.git xiaowu-user
git clone https://github.com/yourusername/xiaowu-search.git xiaowu-search

# 安装PHP依赖
cd xiaowu-ai
composer install

# 重复其他插件
cd ../xiaowu-3d-gallery && composer install
cd ../xiaowu-comments && composer install
cd ../xiaowu-user && composer install
cd ../xiaowu-search && composer install
```

#### 6. 安装前端依赖

```bash
# 进入后台管理面板目录
cd admin-panel

# 安装依赖
npm install
```

#### 7. 配置环境变量

```bash
# 复制环境变量模板
cp .env.example .env
```

编辑 `.env` 文件：

```env
# WordPress
WP_URL=https://localhost
WP_API_URL=https://localhost/wp-json
WP_ADMIN_URL=https://localhost/wp-admin

# AI服务
AI_PROVIDER=openai
AI_API_KEY=your_api_key_here
AI_MODEL=gpt-4
AI_MAX_TOKENS=4000

# 图像生成
IMG_GEN_API_KEY=your_image_gen_key_here

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# CDN
CDN_PROVIDER=tencent
CDN_SECRET_ID=your_secret_id
CDN_SECRET_KEY=your_secret_key
CDN_BUCKET=xiaowu-blog

# 邮件服务
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM=noreply@example.com

# Nginx
NGINX_HOST=localhost
NGINX_PORT=80
NGINX_SSL_PORT=443
```

### 环境变量

| 变量 | 必需 | 描述 | 示例 |
|------|------|------|------|
| `DB_NAME` | 是 | 数据库名称 | `xiaowu_blog` |
| `DB_USER` | 是 | 数据库用户名 | `xiaowu_user` |
| `DB_PASSWORD` | 是 | 数据库密码 | `your_secure_password` |
| `DB_HOST` | 是 | 数据库主机 | `localhost` |
| `AI_API_KEY` | 是 | 大模型API密钥 | `sk-...` |
| `AI_PROVIDER` | 是 | AI服务提供商 | `openai` |
| `CDN_SECRET_KEY` | 是 | CDN密钥 | `your_secret_key` |
| `SMTP_PASSWORD` | 是 | 邮件服务密码 | `your_app_password` |
| `WP_DEBUG` | 否 | 启用WordPress调试 | `true` |
| `REDIS_PASSWORD` | 否 | Redis密码 | `your_redis_password` |

⚠️ **绝不提交密钥**。使用 `.env` 文件或密钥管理器，并将 `.env` 添加到 `.gitignore`。

### 运行

#### 开发环境

```bash
# 启动PHP内置服务器（仅用于开发）
php -S localhost:8000 -t .

# 启动Redis
redis-server

# 启动MySQL
sudo systemctl start mysql

# 启动Nginx
sudo systemctl start nginx

# 启动前端开发服务器
cd admin-panel
npm run dev
```

#### 使用Docker（推荐）

```bash
# 构建并启动所有服务
docker-compose up -d

# 查看日志
docker-compose logs -f

# 停止服务
docker-compose down

# 重新构建
docker-compose build
```

### 初始化WordPress

1. 访问 `http://localhost/wp-admin/install.php`
2. 按照提示完成WordPress安装
3. 安装并激活所有自定义插件
4. 配置插件设置

## 开发工作流

### 代码质量工具

| 工具 | 命令 | 目的 |
|------|------|------|
| PHP CS Fixer | `composer cs-fix` | PHP代码格式化 |
| PHPStan | `composer phpstan` | PHP静态分析 |
| ESLint | `npm run lint` | JavaScript代码检查 |
| Prettier | `npm run format` | 代码格式化 |
| Tests | `npm run test` | 单元/集成测试 |

### 提交前检查

这些会在提交时自动运行：
1. ESLint代码检查
2. Prettier代码格式化
3. PHP代码规范检查
4. 单元测试

手动运行：
```bash
# 前端
cd admin-panel
npm run lint
npm run format
npm run test

# 后端
cd wordpress/wp-content/plugins/xiaowu-ai
composer phpcs
composer phpstan
composer test
```

### 分支策略

- `main` - 生产就绪代码
- `develop` - 开发分支
- `feature/*` - 新功能
- `fix/*` - Bug修复
- `hotfix/*` - 紧急修复

### Pull Request流程

1. 从 `develop` 创建功能分支
2. 编写代码和测试
3. 运行 `npm run validate` 和 `composer validate`
4. 创建PR并填写描述模板
5. 处理审查反馈
6. 通过所有检查后合并

## 常见任务

### 添加新的AI功能

**需修改的文件**:
1. `wordpress/wp-content/plugins/xiaowu-ai/includes/ai-service.php` - 添加AI服务方法
2. `wordpress/wp-content/plugins/xiaowu-ai/api/` - 添加API端点
3. `admin-panel/src/components/AISettings/` - 添加配置界面
4. `admin-panel/src/stores/ai.ts` - 更新状态管理

**步骤**:
1. 在AI服务类中添加新方法
2. 在API路由中注册新端点
3. 在前端添加配置组件
4. 编写单元测试
5. 更新文档

**示例提交**: `feat(ai): add image generation feature`

### 添加新的3D模型格式支持

**需修改的文件**:
1. `wordpress/wp-content/plugins/xiaowu-3d-gallery/includes/model-uploader.php` - 添加格式验证
2. `wordpress/wp-content/plugins/xiaowu-3d-gallery/includes/three-renderer.php` - 添加渲染逻辑
3. `admin-panel/src/components/ModelGallery/Uploader.vue` - 更新上传组件

**步骤**:
1. 在上传器中添加格式验证规则
2. 在Three.js渲染器中添加格式解析器
3. 更新前端上传组件的文件类型限制
4. 添加格式转换工具（如果需要）
5. 编写测试用例

### 添加新的用户角色

**需修改的文件**:
1. `wordpress/wp-content/plugins/xiaowu-user/includes/roles.php` - 定义角色
2. `wordpress/wp-content/plugins/xiaowu-user/includes/capabilities.php` - 定义权限
3. `admin-panel/src/components/UserManagement/RoleEditor.vue` - 添加角色编辑器

**步骤**:
1. 使用 `add_role()` 创建新角色
2. 使用 `add_cap()` 分配权限
3. 在前端添加角色管理界面
4. 更新权限检查逻辑
5. 测试角色权限

### 修复Bug

**流程**:
1. 编写复现bug的失败测试
2. 在代码中定位根因
3. 用最小改动修复
4. 验证测试通过
5. 检查其他地方是否有类似问题
6. 更新文档（如果需要）

**示例提交**: `fix(auth): handle expired tokens gracefully`

### 优化数据库查询

**需修改的文件**:
1. 数据库查询文件
2. 索引定义文件
3. Redis缓存相关文件

**步骤**:
1. 使用Query Monitor插件分析慢查询
2. 添加适当的数据库索引
3. 实现Redis缓存
4. 验证查询性能提升
5. 更新单元测试

### 添加新的API端点

**需修改的文件**:
1. `wordpress/wp-content/plugins/xiaowu-ai/api/` - 创建端点文件
2. `admin-panel/src/api/` - 添加API客户端方法
3. `.monkeycode/docs/INTERFACES.md` - 文档化端点

**步骤**:
1. 注册REST API路由
2. 实现权限检查
3. 实现业务逻辑
4. 添加输入验证
5. 编写测试
6. 更新接口文档

## 编码规范

### PHP编码规范

遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 编码标准。

```php
<?php
// 类命名：PascalCase
class ArticleOptimizer {

    // 方法命名：camelCase
    public function optimizeArticle($content): string
    {
        // 常量命名：SCREAMING_SNAKE_CASE
        $max_length = MAX_ARTICLE_LENGTH;

        // 缩进：4个空格
        if (empty($content)) {
            throw new InvalidArgumentException('Content cannot be empty');
        }

        return $this->applyOptimizations($content);
    }
}
```

### JavaScript/TypeScript编码规范

遵循 [Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript)。

```javascript
// 文件命名：kebab-case
// article-optimizer.js

// 导入语句
import { ref, computed } from 'vue';
import { useStore } from '@/stores';

// 常量：SCREAMING_SNAKE_CASE
const MAX_LENGTH = 1000;

// 类：PascalCase
class ArticleOptimizer {
  // 方法：camelCase
  optimize(content) {
    return content.trim();
  }
}

// 默认导出
export default ArticleOptimizer;
```

### 文件组织

#### WordPress插件结构

```
plugin-name/
├── plugin-name.php          # 主插件文件
├── includes/
│   ├── class-plugin-name.php  # 主类
│   ├── functions.php          # 辅助函数
│   └── admin/                 # 后台功能
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── templates/
└── uninstall.php
```

#### Vue.js组件结构

```
ComponentName.vue
├── <template>          # 模板
├── <script setup>      # 组合式API脚本
├── <style scoped>      # 样式
└── <style>            # 全局样式（可选）
```

### 命名约定

| 类型 | 约定 | 示例 |
|------|------|------|
| PHP文件 | kebab-case | `article-optimizer.php` |
| PHP类 | PascalCase | `ArticleOptimizer` |
| PHP方法 | camelCase | `optimizeArticle` |
| PHP常量 | SCREAMING_SNAKE | `MAX_LENGTH` |
| JS文件 | kebab-case | `article-optimizer.js` |
| JS类 | PascalCase | `ArticleOptimizer` |
| JS函数 | camelCase | `optimizeArticle` |
| Vue组件 | PascalCase | `ArticleEditor` |
| SQL表 | snake_case | `user_comments` |

### 错误处理

```php
// 推荐：特定错误类型
throw new ArticleNotFoundException($articleId);

// 避免：通用错误
throw new Error('Article not found');
```

```javascript
// 推荐：特定错误类型
throw new ArticleNotFoundError('Article not found');

// 避免：通用错误
throw new Error('Something went wrong');
```

### 日志

```php
// 包含上下文
error_log('Article optimized', [
    'article_id' => $articleId,
    'optimization_score' => $score,
    'user_id' => $userId
]);

// 使用适当级别
error_log();  // 错误
wp_debug_log();  // 调试（开发环境）
```

```javascript
// 包含上下文
logger.info('Article created', { articleId, title });

// 使用适当级别
logger.debug()  // 开发详情
logger.info()   // 正常操作
logger.warn()   // 可恢复问题
logger.error()  // 需要关注的故障
```

### 测试

#### PHP测试（使用PHPUnit）

```php
<?php
class ArticleOptimizerTest extends WP_UnitTestCase {

    public function test_optimize_article_should_improve_readability() {
        $optimizer = new ArticleOptimizer();
        $original = 'This is a test article.';
        $optimized = $optimizer->optimize($original);

        $this->assertNotEmpty($optimized);
        $this->assertNotEquals($original, $optimized);
    }
}
```

#### JavaScript测试（使用Vitest）

```javascript
import { describe, it, expect } from 'vitest';
import { optimizeArticle } from './article-optimizer';

describe('ArticleOptimizer', () => {
  it('should improve readability', () => {
    const original = 'This is a test article.';
    const optimized = optimizeArticle(original);

    expect(optimized).toBeTruthy();
    expect(optimized).not.toBe(original);
  });
});
```

### 安全规范

1. **数据验证**: 所有用户输入必须验证
2. **SQL注入**: 使用WordPress预处理语句
3. **XSS防护**: 使用WordPress的 `esc_*()` 函数
4. **CSRF防护**: 使用WordPress Nonces
5. **权限检查**: 使用 `current_user_can()`

```php
// SQL查询安全
global $wpdb;
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}posts WHERE post_status = %s",
        'publish'
    )
);

// XSS防护
echo esc_html($user_input);

// CSRF防护
wp_nonce_field('my_plugin_action', 'my_plugin_nonce');
if (!isset($_POST['my_plugin_nonce']) || !wp_verify_nonce($_POST['my_plugin_nonce'], 'my_plugin_action')) {
    die('Security check failed');
}

// 权限检查
if (!current_user_can('edit_posts')) {
    wp_die(__('You do not have permission to edit posts.'));
}
```

### 性能优化

1. **使用对象缓存**: 利用Redis缓存频繁查询的数据
2. **减少数据库查询**: 使用 `WP_Query` 和相关函数
3. **延迟加载**: 使用WordPress的 `lazyload` 功能
4. **CDN**: 静态资源使用CDN
5. **图片优化**: 使用WebP格式

```php
// 使用对象缓存
$cached_data = wp_cache_get('my_cache_key', 'my_cache_group');
if (false === $cached_data) {
    $cached_data = expensive_operation();
    wp_cache_set('my_cache_key', $cached_data, 'my_cache_group', 3600);
}

// 使用WP_Query
$query = new WP_Query([
    'post_type' => 'post',
    'posts_per_page' => 10,
    'post_status' => 'publish'
]);
```

## 构建与部署

### 本地构建

#### 前端构建

```bash
cd admin-panel

# 开发构建
npm run dev

# 生产构建
npm run build

# 预览生产构建
npm run preview
```

#### 后端构建

```bash
# 安装依赖
cd wordpress/wp-content/plugins/xiaowu-ai
composer install --no-dev

# 生成优化文件
composer dump-autoload --optimize
```

### 生产部署

#### 1. 备份现有数据

```bash
# 备份数据库
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# 备份文件
tar -czf files_backup_$(date +%Y%m%d).tar.gz wordpress/
```

#### 2. 更新代码

```bash
# 拉取最新代码
git pull origin main

# 更新依赖
cd wordpress/wp-content/plugins/xiaowu-ai
composer install

cd admin-panel
npm install
npm run build
```

#### 3. 运行数据库迁移

```bash
# 检查是否需要数据库更新
php wp-content/plugins/xiaowu-ai/includes/migrate.php
```

#### 4. 清除缓存

```bash
# 清除Redis缓存
redis-cli FLUSHDB

# 清除WordPress缓存
wp cache flush
```

#### 5. 重启服务

```bash
# 重启PHP-FPM
sudo systemctl restart php-fpm

# 重启Nginx
sudo systemctl restart nginx

# 重启Redis
sudo systemctl restart redis
```

### Docker部署

```bash
# 构建镜像
docker-compose build

# 启动服务
docker-compose up -d

# 查看状态
docker-compose ps

# 查看日志
docker-compose logs -f

# 停止服务
docker-compose down

# 完全清理（包括数据卷）
docker-compose down -v
```

### 监控与日志

#### Nginx日志

```bash
# 访问日志
tail -f /var/log/nginx/access.log

# 错误日志
tail -f /var/log/nginx/error.log
```

#### PHP-FPM日志

```bash
tail -f /var/log/php-fpm/error.log
```

#### WordPress调试日志

在 `wp-config.php` 中启用：

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 调试日志位置：/wp-content/debug.log
```

#### 自定义日志

```bash
# 查看AI服务日志
tail -f wordpress/wp-content/uploads/xiaowu-ai.log

# 查看用户操作日志
tail -f wordpress/wp-content/uploads/xiaowu-user.log
```

## 故障排除

### 常见问题

#### WordPress白屏

1. 启用调试模式
2. 检查PHP错误日志
3. 禁用所有插件
4. 逐个启用插件定位问题

#### 数据库连接失败

1. 检查MySQL服务状态
2. 验证 `wp-config.php` 中的数据库配置
3. 检查数据库用户权限

#### Redis连接失败

1. 检查Redis服务状态
2. 验证Redis配置
3. 检查防火墙设置

#### AI API调用失败

1. 验证API密钥
2. 检查API配额
3. 查看网络连接
4. 查看错误日志

### 性能优化建议

1. 启用对象缓存
2. 使用CDN
3. 优化图片
4. 使用缓存插件
5. 定期清理数据库

## 贡献指南

1. Fork项目
2. 创建功能分支
3. 提交更改
4. 推送到分支
5. 创建Pull Request

请确保：
- 代码符合规范
- 所有测试通过
- 更新相关文档
- 添加必要的注释
