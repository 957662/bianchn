# 小伍博客 - 部署说明

## 部署检测逻辑

### 自动检测机制

小伍博客使用 must-use plugin 进行部署状态检测，确保正确的用户体验：

1. **首次部署**
   - 访问 `http://your-domain.com/` → 显示部署向导页面
   - 点击"开始安装 WordPress" → 跳转到 `/wp-admin/install.php`
   - 完成 WordPress 安装后 → 自动标记部署完成

2. **已部署状态**
   - 访问 `http://your-domain.com/` → 直接显示博客首页
   - 访问 `http://your-domain.com/wp-admin/` → 进入管理后台（需要登录）

### 检测原理

部署检测通过以下条件判断：

```php
// 1. 检查部署完成标记
if (get_option('xiaowu_deployment_completed')) {
    return; // 已完成部署，显示博客
}

// 2. 检查 WordPress 是否已安装
if (is_blog_installed()) {
    // 3. 检查是否有管理员用户
    $admins = get_users(array('role' => 'administrator'));
    if (!empty($admins)) {
        // 自动标记部署完成
        update_option('xiaowu_deployment_completed', current_time('mysql'));
    }
}
```

### 文件说明

- `wp-content/mu-plugins/xiaowu-deployment-check.php` - 部署检测插件
  - mu-plugins 目录中的插件会自动加载
  - 在 WordPress 加载前执行检测
  - 无法通过常规插件管理页面禁用

- `wp-content/plugins/xiaowu-deployment/admin/deployment-landing.php` - 部署引导页面
  - 精美的欢迎界面
  - 展示系统功能
  - 提供安装入口

- `wp-content/plugins/xiaowu-deployment/xiaowu-deployment.php` - 部署向导主插件
  - 提供完整的管理界面
  - 支持系统配置
  - 仅在后台显示

### 手动重置部署状态

如果需要重新显示部署向导（例如重新部署）：

```bash
# 方法 1: 通过 WP-CLI
wp option delete xiaowu_deployment_completed --allow-root

# 方法 2: 通过数据库
mysql -u root -p
USE xiaowu_blog;
DELETE FROM wp_options WHERE option_name = 'xiaowu_deployment_completed';
```

### URL 访问规则

| URL | 首次部署 | 已部署 |
|-----|---------|--------|
| `/` | 部署向导 | 博客首页 |
| `/wp-admin/` | 安装程序/登录 | 管理后台 |
| `/wp-admin/install.php` | WordPress 安装 | 404 |
| `/wp-login.php` | 登录页面 | 登录页面 |

### 故障排查

**问题**: 访问网站仍然显示部署向导，但已经完成安装

**解决**:
1. 检查 `xiaowu_deployment_completed` 选项是否设置
2. 确保数据库中有管理员用户
3. 清除浏览器缓存

```bash
# 检查部署状态
wp option get xiaowu_deployment_completed --allow-root

# 手动设置部署完成
wp option update xiaowu_deployment_completed "$(date '+%Y-%m-%d %H:%M:%S')" --allow-root
```

**问题**: 无法访问管理后台

**解决**:
1. 确保使用 `/wp-admin/` 访问（不是 `/admin`）
2. 检查管理员用户是否存在
3. 重置管理员密码

```bash
# 列出管理员用户
wp user list --role=administrator --allow-root

# 重置管理员密码
wp user update admin --user_pass=new_password --allow-root
```

## 更新日志

### v2.0.0 (2026-02-12)

- ✨ 智能部署检测系统
- ✨ 自动标记部署完成
- ✨ 精美的部署引导页面
- 🐛 修复 xiaowu-search 插件致命错误
- 🐛 修复 PHP 8.1+ 废弃警告
- 📝 完善部署文档
