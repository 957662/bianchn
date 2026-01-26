# CORS 安全配置文档

## 概述

本文档详细说明了 Xiaowu 博客项目中跨源资源共享 (CORS) 的配置、验证和管理方法。

## 目录

1. [架构设计](#架构设计)
2. [后端配置](#后端配置)
3. [前端配置](#前端配置)
4. [Nginx 配置](#nginx-配置)
5. [测试和验证](#测试和验证)
6. [管理和维护](#管理和维护)
7. [常见问题](#常见问题)

---

## 架构设计

### CORS 安全模型

```
┌─────────────────┐
│   前端 (Vue3)   │
│ localhost:3000  │
│ localhost:5173  │
└────────┬────────┘
         │ CORS 预检请求
         │ OPTIONS /wp-json/*
         ▼
┌─────────────────┐
│   Nginx 代理    │  ◄─── CORS 头处理
│   端口 80/443   │
└────────┬────────┘
         │ 内部路由
         ▼
┌─────────────────┐
│   PHP-FPM       │
│   WordPress     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ CORSManager     │
│  - 源验证       │
│  - 头处理       │
│  - 管理端点     │
└─────────────────┘
```

### 安全特性

1. **源白名单**: 只允许配置的来源进行跨源请求
2. **预检请求处理**: 正确处理 OPTIONS 预检请求
3. **凭证支持**: 支持发送 cookies 和身份验证头
4. **方法限制**: 只允许必要的 HTTP 方法
5. **头部限制**: 只允许必要的请求/响应头
6. **缓存控制**: 预检结果缓存时间设置为 3600 秒

---

## 后端配置

### 1. CORSManager 类

位置: `/wordpress/wp-content/plugins/xiaowu-base/includes/class-cors-manager.php`

**主要方法:**

```php
// 检查来源是否被允许
is_origin_allowed($origin): bool

// 获取允许的源列表
get_allowed_origins(): array

// 处理 CORS 响应头
handle_cors_headers(): void

// 处理 OPTIONS 预检请求
handle_preflight_request(): void

// 添加允许的来源
add_allowed_origin($origin): bool

// 移除允许的来源
remove_allowed_origin($origin): bool
```

### 2. 默认允许的源

```php
$default_origins = [
    'http://localhost:3000',      // Vue3 Vite 开发服务器
    'http://localhost:5173',      // Vue3 Vite 默认端口
    'http://localhost:8080',      // Vue3 webpack 默认端口
    get_home_url(),              // 生产环境主域名
];
```

### 3. 初始化配置

在 `xiaowu-base.php` 中自动初始化:

```php
// plugins_loaded 钩子中
$cors_manager = new Xiaowu\Security\CORSManager();
$cors_manager->handle_cors_headers();
$cors_manager->register_rest_routes();
```

### 4. REST API 管理端点

所有管理端点需要管理员权限:

#### 获取 CORS 状态
```
GET /wp-json/xiaowu/v1/cors-status
Authorization: Bearer {token}
```

响应:
```json
{
  "success": true,
  "allowed_origins": [
    "http://localhost:3000",
    "http://localhost:5173",
    "https://yourdomain.com"
  ]
}
```

#### 添加允许的源
```
POST /wp-json/xiaowu/v1/cors-add-origin
Authorization: Bearer {token}
Content-Type: application/json

{
  "origin": "https://subdomain.example.com"
}
```

响应:
```json
{
  "success": true,
  "message": "源已添加",
  "allowed_origins": [...]
}
```

#### 移除允许的源
```
POST /wp-json/xiaowu/v1/cors-remove-origin
Authorization: Bearer {token}
Content-Type: application/json

{
  "origin": "https://subdomain.example.com"
}
```

---

## 前端配置

### 1. Axios 配置

文件: `/admin-panel/src/api/axios.js`

**关键配置:**

```javascript
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/wp-json',
  timeout: 30000,
  withCredentials: true,  // ✅ CORS: 发送凭证
  headers: {
    'Content-Type': 'application/json',
  },
});
```

### 2. CORS 错误处理

```javascript
apiClient.interceptors.response.use(
  response => response.data,
  error => {
    if (error.message === 'Network Error' && !error.response) {
      ElMessage.error('跨域请求被阻止,请检查服务器 CORS 配置');
    }
    // ... 其他错误处理
  }
);
```

### 3. 环境变量配置

`.env.local`:
```bash
VITE_API_URL=https://yourdomain.com/wp-json
```

`.env.development`:
```bash
VITE_API_URL=http://localhost/wp-json
```

### 4. 请求示例

```javascript
import apiClient from '@/api/axios';

// 自动添加 CORS 头
const response = await apiClient.get('/xiaowu/v1/articles', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'X-WP-Nonce': nonce
  }
});
```

---

## Nginx 配置

### 1. CORS 配置文件

文件: `/docker/nginx/conf.d/cors.conf`

**作用:**
- 在 Nginx 层面处理 CORS 头
- 对所有响应添加必要的 CORS 头
- 处理 OPTIONS 预检请求

### 2. 配置详解

```nginx
# 允许的 HTTP 方法
add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, PATCH, OPTIONS' always;

# 允许的请求头
add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, X-RateLimit-*' always;

# 允许凭证
add_header 'Access-Control-Allow-Credentials' 'true' always;

# 预检缓存时间 (3600 秒 = 1 小时)
add_header 'Access-Control-Max-Age' '3600' always;

# 暴露的响应头 (客户端可访问)
add_header 'Access-Control-Expose-Headers' 'X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset' always;
```

### 3. 自定义允许的源

编辑 `/docker/nginx/conf.d/cors.conf`，更新源检查:

```nginx
# 生产环境域名
if ($http_origin ~* ^https?://yourdomain\.com$) {
  set $cors_origin $http_origin;
}

if ($http_origin ~* ^https?://www\.yourdomain\.com$) {
  set $cors_origin $http_origin;
}

# 更多子域名...
if ($http_origin ~* ^https?://api\.yourdomain\.com$) {
  set $cors_origin $http_origin;
}
```

---

## 测试和验证

### 1. 运行集成测试

```bash
# 进入 Docker 容器
docker-compose exec php bash

# 运行 CORS 测试
cd /var/www/html/wp-content/plugins/xiaowu-base
php test-cors.php
```

**预期输出:**
```
========== CORS 集成测试开始 ==========

测试 1: 检查 CORS 管理器初始化... ✅ PASS
测试 2: 获取 CORS 状态... ✅ PASS
测试 3: 验证预检请求处理... ✅ PASS
测试 4: 添加新的 CORS 源... ✅ PASS
测试 5: 移除 CORS 源... ✅ PASS
测试 6: 验证 CORS 响应头... ✅ PASS
测试 7: 验证无效源被拒绝... ✅ PASS

========== 测试报告 ==========

总测试数: 7
✅ 通过: 7
⚠️  警告: 0
❌ 失败: 0

✅ 所有测试通过！CORS 配置正常。
```

### 2. cURL 测试

**测试预检请求:**

```bash
curl -X OPTIONS http://localhost/wp-json/xiaowu/v1/articles \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v
```

**查看响应头:**
```
< Access-Control-Allow-Origin: http://localhost:3000
< Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS
< Access-Control-Allow-Headers: Content-Type, Authorization, X-RateLimit-*
< Access-Control-Allow-Credentials: true
< Access-Control-Max-Age: 3600
```

**测试普通 GET 请求:**

```bash
curl -X GET http://localhost/wp-json/xiaowu/v1/articles \
  -H "Origin: http://localhost:3000" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -v
```

### 3. 浏览器开发者工具测试

1. 打开浏览器开发者工具 (F12)
2. 转到 "Network" 标签
3. 发送 API 请求
4. 检查响应头中是否包含 `Access-Control-Allow-*` 头
5. 查看 "Console" 标签是否有 CORS 错误

### 4. Firefox/Chrome 扩展测试

使用 "CORS Toggle" 或类似扩展来测试 CORS 场景。

---

## 管理和维护

### 1. 添加生产域名

**方法 1: 通过 REST API (推荐)**

```bash
curl -X POST https://yourdomain.com/wp-json/xiaowu/v1/cors-add-origin \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"origin": "https://newdomain.com"}'
```

**方法 2: 通过 WordPress 管理后台**

1. 登录 WordPress 管理后台
2. 导航到插件设置
3. 找到 "CORS 管理" 部分
4. 添加新的允许源

**方法 3: 通过 PHP 代码**

```php
$cors_manager = new Xiaowu\Security\CORSManager();
$cors_manager->add_allowed_origin('https://newdomain.com');
```

### 2. 监控 CORS 错误

**检查 PHP 错误日志:**

```bash
docker-compose logs php | grep -i cors
```

**检查 Nginx 访问日志:**

```bash
docker-compose logs nginx | grep "OPTIONS"
```

### 3. CORS 相关的 WordPress 过滤器

在主题或插件中自定义 CORS 行为:

```php
// 添加自定义允许的源
add_filter('xiaowu_allowed_cors_origins', function($origins) {
    $origins[] = 'https://custom.domain.com';
    return $origins;
});

// 自定义 CORS 头
add_filter('xiaowu_cors_headers', function($headers) {
    $headers['Custom-Header'] = 'custom-value';
    return $headers;
});
```

---

## 常见问题

### Q: 出现 "Access to XMLHttpRequest has been blocked by CORS policy" 错误

**原因:** 浏览器阻止了跨源请求

**解决方案:**
1. 验证前端 Origin 是否在后端允许列表中
2. 检查 `withCredentials: true` 是否设置
3. 确保后端返回了正确的 CORS 头
4. 运行 `curl` 测试验证后端配置

### Q: OPTIONS 预检请求返回 405 错误

**原因:** Nginx 或 PHP 不允许 OPTIONS 方法

**解决方案:**
1. 检查 Nginx 配置中 OPTIONS 是否被允许
2. 确保 `cors.conf` 被正确包含在 Nginx 配置中
3. 重启 Nginx: `docker-compose restart nginx`

### Q: 即使设置了 `withCredentials: true`，cookies 仍未发送

**原因:** 后端未设置 `Access-Control-Allow-Credentials: true`

**解决方案:**
1. 验证 CORSManager 类中是否设置了此头
2. 检查 Nginx 配置中是否也设置了此头
3. 某些浏览器安全策略可能阻止跨源 cookies

### Q: 特定方法 (如 DELETE) 不工作

**原因:** 该方法未在 `Access-Control-Allow-Methods` 中

**解决方案:**
1. 更新 `cors.conf` 中的允许方法
2. 重启 Nginx
3. 验证预检响应中是否包含该方法

### Q: 生产环境中 CORS 配置不生效

**原因:** 使用了不同的 Nginx 配置或未更新源列表

**解决方案:**
1. 验证 Docker 中挂载的是否是正确的配置文件
2. 使用 REST API 端点添加生产域名
3. 检查 Nginx 是否已重启
4. 查看 Nginx 错误日志: `docker-compose logs nginx`

### Q: 如何临时禁用 CORS 检查用于调试?

**警告:** 仅用于开发环境

```php
// 临时禁用 (在 wp-config.php 中)
define('DISABLE_CORS_CHECK', true);
```

然后在 CORSManager 中检查:

```php
if (defined('DISABLE_CORS_CHECK') && DISABLE_CORS_CHECK) {
    return true; // 允许所有来源
}
```

---

## 参考资源

- [MDN - CORS](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
- [WordPress REST API - CORS](https://developer.wordpress.org/rest-api/)
- [Nginx CORS 配置](https://enable-cors.org/server_nginx.html)
- [Axios 文档](https://axios-http.com/)

---

## 版本历史

| 版本 | 日期 | 更改 |
|------|------|------|
| 1.0 | 2024-01-15 | 初始版本 |

---

**文档维护者:** Xiaowu Blog Team  
**最后更新:** 2024-01-15
