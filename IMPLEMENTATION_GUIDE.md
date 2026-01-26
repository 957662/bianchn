# 需求优化执行计划

## 文档概述

本文档为《需求优化分析报告》的配套执行计划，提供详细的实施步骤、代码示例和验收标准。

## 优化项目详解与实施指南

### 【高优先级】第一阶段：安全性加固 (Week 1)

#### 任务1.1 API速率限制实现

**目标**: 防止API被滥用和DDoS攻击

**实施步骤**:

1. **创建速率限制中间件**

```php
<?php
// wordpress/wp-content/plugins/xiaowu-base/includes/rate-limiter.php

namespace XiaowuBase\Security;

class RateLimiter {
    const LIMIT_REQUESTS = 100;      // 公开API限制
    const LIMIT_WINDOW = 60;         // 时间窗口（秒）
    const LIMIT_AUTH_REQUESTS = 1000; // 认证用户限制
    
    private $redis;
    
    public function __construct() {
        global $wpdb;
        try {
            $this->redis = new \Redis();
            $this->redis->connect('127.0.0.1', 6379);
        } catch (\Exception $e) {
            error_log('Redis连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 检查请求是否超过限流
     * 
     * @param string $identifier 标识符（IP地址或用户ID）
     * @param bool $authenticated 是否为认证用户
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function check_limit($identifier, $authenticated = false) {
        $limit = $authenticated ? self::LIMIT_AUTH_REQUESTS : self::LIMIT_REQUESTS;
        $window = self::LIMIT_WINDOW;
        
        $key = "rate_limit:{$identifier}";
        
        if ($this->redis) {
            $current = $this->redis->incr($key);
            
            if ($current === 1) {
                $this->redis->expire($key, $window);
            }
            
            $remaining = max(0, $limit - $current);
            $reset_at = time() + $this->redis->ttl($key);
            
            return [
                'allowed' => $current <= $limit,
                'remaining' => $remaining,
                'reset_at' => $reset_at,
                'limit' => $limit
            ];
        }
        
        // Redis不可用时，允许请求但记录警告
        return [
            'allowed' => true,
            'remaining' => $limit,
            'reset_at' => time() + $window,
            'limit' => $limit
        ];
    }
    
    /**
     * 获取客户端IP地址
     */
    public function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * 应用速率限制头
     */
    public function apply_headers($check_result) {
        header('X-RateLimit-Limit: ' . $check_result['limit']);
        header('X-RateLimit-Remaining: ' . $check_result['remaining']);
        header('X-RateLimit-Reset: ' . $check_result['reset_at']);
    }
}
```

2. **在REST API中集成速率限制**

```php
<?php
// wordpress/wp-content/plugins/xiaowu-base/includes/api-middleware.php

namespace XiaowuBase;

class APIMiddleware {
    private $rate_limiter;
    
    public function __construct() {
        $this->rate_limiter = new Security\RateLimiter();
        add_filter('rest_post_dispatch', [$this, 'apply_rate_limit'], 10, 3);
    }
    
    /**
     * 应用速率限制
     */
    public function apply_rate_limit($response, $server, $request) {
        $ip = $this->rate_limiter->get_client_ip();
        $user_id = get_current_user_id();
        
        // 认证用户使用用户ID作为标识符
        $identifier = $user_id ? "user:{$user_id}" : "ip:{$ip}";
        $authenticated = $user_id > 0;
        
        $check = $this->rate_limiter->check_limit($identifier, $authenticated);
        
        // 应用响应头
        $this->rate_limiter->apply_headers($check);
        
        // 超过限制时返回429错误
        if (!$check['allowed']) {
            return new \WP_Error(
                'rest_rate_limit_exceeded',
                '请求过于频繁，请稍后再试',
                ['status' => 429]
            );
        }
        
        return $response;
    }
}

new APIMiddleware();
```

3. **配置Nginx速率限制**

```nginx
# docker/nginx/conf.d/default.conf

# 配置速率限制区域
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=auth_limit:10m rate=5r/m;

server {
    listen 80;
    server_name _;

    # API端点速率限制
    location ~ ^/wp-json/xiaowu- {
        limit_req zone=api_limit burst=20 nodelay;
        limit_req_status 429;
        
        proxy_pass http://php-fpm:9000;
        # ... 其他配置
    }
    
    # 登录端点限制
    location ~ ^/wp-json/wp/v2/users/.*authenticate {
        limit_req zone=auth_limit burst=5 nodelay;
        limit_req_status 429;
        
        proxy_pass http://php-fpm:9000;
        # ... 其他配置
    }
}
```

**验收标准**:
- [ ] API请求超过限制返回429状态码
- [ ] 响应头包含 X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
- [ ] Redis不可用时有降级处理
- [ ] 认证用户的限制比公开用户更宽松
- [ ] 可通过配置文件调整限制参数

**预计工时**: 4-6小时

---

#### 任务1.2 CORS安全配置

**目标**: 安全地处理跨域请求

**实施步骤**:

1. **创建CORS配置管理类**

```php
<?php
// wordpress/wp-content/plugins/xiaowu-base/includes/cors-manager.php

namespace XiaowuBase\Security;

class CORSManager {
    
    public function __construct() {
        add_filter('rest_pre_serve_request', [$this, 'handle_cors'], 10);
        add_filter('rest_pre_options_posts', [$this, 'handle_preflight']);
    }
    
    /**
     * 获取允许的来源列表
     */
    private function get_allowed_origins() {
        $allowed = [
            'http://localhost:3000',      // 本地开发
            'http://localhost:8080',      // WordPress本地
            'https://monkeycode-ai.online',
            'https://www.monkeycode-ai.online',
        ];
        
        // 从配置获取自定义来源
        $custom_origins = get_option('xiaowu_cors_origins', []);
        if (is_array($custom_origins)) {
            $allowed = array_merge($allowed, $custom_origins);
        }
        
        return apply_filters('xiaowu_allowed_origins', $allowed);
    }
    
    /**
     * 检查来源是否被允许
     */
    private function is_origin_allowed($origin) {
        $allowed = $this->get_allowed_origins();
        return in_array($origin, $allowed, true);
    }
    
    /**
     * 处理CORS请求
     */
    public function handle_cors($return) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? 
            sanitize_text_field($_SERVER['HTTP_ORIGIN']) : '';
        
        if ($this->is_origin_allowed($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 3600');
            header('Vary: Origin');
        } else {
            header('Vary: Origin');
        }
        
        return $return;
    }
    
    /**
     * 处理预检请求
     */
    public function handle_preflight($response) {
        return true;
    }
}

new CORSManager();
```

2. **前端CORS配置**

```javascript
// admin-panel/src/api/index.js

const API_BASE_URL = process.env.VITE_API_URL || 'http://localhost:8080/wp-json';

// 创建axios实例配置
const apiClient = axios.create({
    baseURL: API_BASE_URL,
    withCredentials: true,  // 允许跨域请求携带凭证
    headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    }
});

// 请求拦截器
apiClient.interceptors.request.use(
    config => {
        const token = localStorage.getItem('wp_auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    error => Promise.reject(error)
);

// 响应拦截器处理CORS错误
apiClient.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 0 || error.message === 'Network Error') {
            console.error('CORS错误或网络错误', error);
            ElMessage.error('网络请求失败，请检查服务器连接');
        }
        return Promise.reject(error);
    }
);

export default apiClient;
```

**验收标准**:
- [ ] 允许的域名可以成功跨域请求
- [ ] 非允许的域名被正确拒绝
- [ ] 预检请求(OPTIONS)正确处理
- [ ] 包含正确的CORS响应头
- [ ] 凭证(Cookie)正确传递

**预计工时**: 3-4小时

---

#### 任务1.3 输入验证规范

**目标**: 防止SQL注入、XSS等攻击

**实施步骤**:

1. **创建通用验证和过滤类**

```php
<?php
// wordpress/wp-content/plugins/xiaowu-base/includes/validator.php

namespace XiaowuBase\Security;

class Validator {
    
    /**
     * 验证规则定义
     */
    private static $rules = [
        'email' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        'url' => '/^https?:\/\/.+\..+$/',
        'slug' => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        'username' => '/^[a-zA-Z0-9_-]{3,20}$/',
        'phone' => '/^\+?1?\d{9,15}$/',
    ];
    
    /**
     * 验证文本字段
     */
    public static function validate_text($value, $min = 1, $max = 255) {
        $value = trim($value);
        $length = strlen($value);
        
        if ($length < $min || $length > $max) {
            return new \WP_Error(
                'invalid_text',
                "文本长度必须在 {$min} 到 {$max} 之间"
            );
        }
        
        return $value;
    }
    
    /**
     * 验证邮箱
     */
    public static function validate_email($value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return new \WP_Error('invalid_email', '邮箱格式不正确');
        }
        return $value;
    }
    
    /**
     * 验证URL
     */
    public static function validate_url($value) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', 'URL格式不正确');
        }
        return $value;
    }
    
    /**
     * 验证整数
     */
    public static function validate_integer($value, $min = null, $max = null) {
        if (!is_numeric($value) || intval($value) != $value) {
            return new \WP_Error('invalid_integer', '必须为整数');
        }
        
        $int = intval($value);
        
        if ($min !== null && $int < $min) {
            return new \WP_Error('invalid_integer', "不能小于 {$min}");
        }
        
        if ($max !== null && $int > $max) {
            return new \WP_Error('invalid_integer', "不能大于 {$max}");
        }
        
        return $int;
    }
    
    /**
     * 验证数组
     */
    public static function validate_array($value, $expected_keys = []) {
        if (!is_array($value)) {
            return new \WP_Error('invalid_array', '必须为数组');
        }
        
        if (!empty($expected_keys)) {
            $missing = array_diff($expected_keys, array_keys($value));
            if (!empty($missing)) {
                return new \WP_Error(
                    'missing_keys',
                    '缺少必需的字段: ' . implode(', ', $missing)
                );
            }
        }
        
        return $value;
    }
    
    /**
     * 验证选项值（防止注入）
     */
    public static function validate_enum($value, $allowed) {
        if (!in_array($value, $allowed, true)) {
            return new \WP_Error(
                'invalid_enum',
                '无效的选项值'
            );
        }
        return $value;
    }
    
    /**
     * 清理文本（防止XSS）
     */
    public static function sanitize_text($value) {
        return sanitize_text_field($value);
    }
    
    /**
     * 清理HTML内容
     */
    public static function sanitize_html($value) {
        return wp_kses_post($value);
    }
    
    /**
     * 批量验证
     */
    public static function validate_request($data, $rules) {
        $errors = [];
        $validated = [];
        
        foreach ($rules as $field => $field_rules) {
            if (!isset($data[$field])) {
                if (in_array('required', $field_rules)) {
                    $errors[$field] = "字段 '{$field}' 是必需的";
                }
                continue;
            }
            
            $value = $data[$field];
            
            foreach ($field_rules as $rule => $params) {
                // 处理 'required' 规则
                if ($rule === 'required' && empty($value)) {
                    $errors[$field] = "字段 '{$field}' 是必需的";
                    continue;
                }
                
                // 处理其他规则
                if (method_exists(static::class, "validate_{$rule}")) {
                    $is_params_array = is_array($params);
                    $method_params = $is_params_array ? 
                        array_merge([$value], $params) : 
                        [$value, $params];
                    
                    $result = call_user_func_array(
                        [static::class, "validate_{$rule}"],
                        $method_params
                    );
                    
                    if (is_wp_error($result)) {
                        $errors[$field] = $result->get_error_message();
                        break;
                    }
                    
                    $value = $result;
                }
            }
            
            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }
        
        if (!empty($errors)) {
            return new \WP_Error('validation_error', '数据验证失败', $errors);
        }
        
        return $validated;
    }
}
```

2. **在API端点中使用验证**

```php
<?php
// wordpress/wp-content/plugins/xiaowu-ai/includes/api.php

class AIService {
    
    public function register_routes() {
        register_rest_route('xiaowu-ai/v1', '/optimize-article', [
            'methods' => 'POST',
            'callback' => [$this, 'optimize_article'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'title' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function($value) {
                        $result = \XiaowuBase\Security\Validator::validate_text($value, 1, 200);
                        return !is_wp_error($result);
                    },
                    'sanitize_callback' => function($value) {
                        return \XiaowuBase\Security\Validator::sanitize_text($value);
                    }
                ],
                'content' => [
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function($value) {
                        $result = \XiaowuBase\Security\Validator::validate_text($value, 10, 10000);
                        return !is_wp_error($result);
                    },
                    'sanitize_callback' => function($value) {
                        return \XiaowuBase\Security\Validator::sanitize_html($value);
                    }
                ],
                'type' => [
                    'type' => 'string',
                    'required' => false,
                    'default' => 'seo',
                    'validate_callback' => function($value) {
                        $result = \XiaowuBase\Security\Validator::validate_enum(
                            $value,
                            ['seo', 'readability', 'style']
                        );
                        return !is_wp_error($result);
                    }
                ]
            ]
        ]);
    }
    
    public function optimize_article($request) {
        $title = $request->get_param('title');
        $content = $request->get_param('content');
        $type = $request->get_param('type');
        
        // 数据已经通过验证和清理
        // 可以安全使用
        
        // ... 处理逻辑
        
        return rest_ensure_response([
            'success' => true,
            'data' => []
        ]);
    }
}
```

**验收标准**:
- [ ] 所有API端点都实现了输入验证
- [ ] 验证规则清晰，错误消息有帮助
- [ ] 数据在使用前被正确清理
- [ ] 测试通过SQL注入、XSS测试

**预计工时**: 6-8小时

---

#### 任务1.4 安全文档编写

**创建安全指南文档**: `/workspaces/bianchn/SECURITY_GUIDELINES.md`

```markdown
# 安全指南

## API安全

### 1. 速率限制
...配置详情...

### 2. 输入验证
...最佳实践...

### 3. 认证和授权
...实现细节...
```

**预计工时**: 2-3小时

---

### 【高优先级】第二阶段：代码质量工具 (Week 1-2)

#### 任务2.1 ESLint和Prettier配置

**目标**: 统一前端代码风格，提高代码质量

**实施步骤**:

1. **安装依赖**

```bash
cd /workspaces/bianchn/admin-panel

npm install --save-dev \
  eslint \
  prettier \
  eslint-config-prettier \
  eslint-plugin-vue \
  @vue/eslint-config-prettier \
  @vitejs/plugin-vue
```

2. **创建.eslintrc.cjs配置**

```javascript
// admin-panel/.eslintrc.cjs

module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true
  },
  extends: [
    'plugin:vue/vue3-essential',
    'eslint:recommended',
    'prettier'
  ],
  parserOptions: {
    ecmaVersion: 2021,
    sourceType: 'module'
  },
  rules: {
    'vue/multi-word-component-names': 'off',
    'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
    'no-debugger': process.env.NODE_ENV === 'production' ? 'warn' : 'off'
  }
}
```

3. **创建.prettierrc配置**

```json
{
  "semi": true,
  "trailingComma": "es5",
  "singleQuote": true,
  "printWidth": 100,
  "tabWidth": 2,
  "useTabs": false,
  "bracketSpacing": true,
  "arrowParens": "avoid"
}
```

4. **在package.json中添加脚本**

```json
{
  "scripts": {
    "lint": "eslint src --ext .vue,.js,.jsx,.cjs,.mjs --fix",
    "format": "prettier --write src"
  }
}
```

**验收标准**:
- [ ] 所有.vue和.js文件通过ESLint检查
- [ ] 代码风格统一
- [ ] 可通过 `npm run lint` 自动修复常见问题

**预计工时**: 2-3小时

---

#### 任务2.2 Git钩子自动检查

**目标**: 在提交代码时自动检查代码质量

**实施步骤**:

1. **安装Husky和lint-staged**

```bash
npm install --save-dev husky lint-staged

# 初始化Husky
npx husky install

# 添加pre-commit钩子
npx husky add .husky/pre-commit "npx lint-staged"
```

2. **创建.lintstagedrc配置**

```json
{
  "*.{js,jsx,vue}": "eslint --fix",
  "*.{js,jsx,vue,json,md}": "prettier --write"
}
```

3. **安装commitlint**

```bash
npm install --save-dev commitlint @commitlint/config-conventional

# 添加commit-msg钩子
npx husky add .husky/commit-msg 'npx commitlint --edit "$1"'
```

4. **创建commitlint.config.js**

```javascript
// commitlint.config.js

module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      [
        'feat',      // 新功能
        'fix',       // 修复
        'docs',      // 文档
        'style',     // 格式
        'refactor',  // 重构
        'test',      // 测试
        'chore',     // 构建/工具
        'perf'       // 性能
      ]
    ]
  }
}
```

**验收标准**:
- [ ] 提交时自动运行代码检查
- [ ] 不符合规范的代码无法提交
- [ ] 提交消息遵循规范

**预计工时**: 1-2小时

---

### 【高优先级】第三阶段：性能优化 (Week 2)

#### 任务3.1 前端代码分割

**目标**: 减少初始加载时间，提高首屏速度

**实施步骤**:

1. **配置Vite代码分割**

```javascript
// admin-panel/vite.config.js

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor': [
            'vue',
            'vue-router',
            'pinia',
            'element-plus',
            'axios'
          ],
          'utils': [
            './src/utils/index.js',
            './src/api/index.js'
          ]
        }
      }
    }
  },
  
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    }
  }
})
```

2. **实现路由级别懒加载**

```javascript
// admin-panel/src/router/index.js

import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    component: () => import('@/views/Layout.vue'),
    children: [
      {
        path: 'dashboard',
        component: () => import('@/views/Dashboard.vue')
      },
      {
        path: 'posts',
        component: () => import('@/views/Posts/Index.vue')
      },
      {
        path: 'users',
        component: () => import('@/views/Users/Index.vue')
      },
      {
        path: 'gallery',
        component: () => import('@/views/Gallery/Index.vue')
      },
      {
        path: 'search',
        component: () => import('@/views/Search/Index.vue')
      },
      {
        path: 'ai',
        component: () => import('@/views/AI/Index.vue')
      },
      {
        path: 'settings',
        component: () => import('@/views/Settings/Index.vue')
      }
    ]
  }
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})
```

3. **实现组件预加载提示**

```vue
<!-- admin-panel/src/components/AsyncComponentLoader.vue -->

<template>
  <Suspense>
    <template #default>
      <slot />
    </template>
    <template #fallback>
      <div class="loading-container">
        <el-skeleton :rows="5" animated />
      </div>
    </template>
  </Suspense>
</template>

<style scoped>
.loading-container {
  padding: 20px;
}
</style>
```

**验收标准**:
- [ ] 初始加载包体积减少50%以上
- [ ] 首屏加载时间< 2秒
- [ ] 路由切换时平滑加载

**预计工时**: 3-4小时

---

### 【高优先级】第四阶段：TypeScript迁移 (Week 2-3)

#### 任务4.1 配置TypeScript

**实施步骤**:

1. **安装TypeScript依赖**

```bash
cd /workspaces/bianchn/admin-panel

npm install --save-dev \
  typescript \
  @vue/test-utils \
  @vitejs/plugin-vue \
  ts-node
```

2. **创建tsconfig.json**

```json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    "esModuleInterop": true,
    "allowSyntheticDefaultImports": true,

    /* Bundler mode */
    "moduleResolution": "node",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "strict": true,
    "noEmit": true,
    "jsx": "preserve",

    /* Paths */
    "baseUrl": ".",
    "paths": {
      "@/*": ["./src/*"]
    }
  },
  "include": ["src/**/*.ts", "src/**/*.tsx", "src/**/*.vue"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
```

3. **创建类型定义文件**

```typescript
// admin-panel/src/types/models.ts

/**
 * 用户类型定义
 */
export interface User {
  id: number
  name: string
  email: string
  avatar?: string
  role: UserRole
  createdAt: string
  updatedAt: string
}

export type UserRole = 'admin' | 'editor' | 'author' | 'contributor' | 'subscriber'

/**
 * 文章类型定义
 */
export interface Post {
  id: number
  title: string
  content: string
  excerpt?: string
  featured_media?: number
  author: number
  status: PostStatus
  categories: number[]
  tags: number[]
  createdAt: string
  updatedAt: string
}

export type PostStatus = 'publish' | 'draft' | 'pending' | 'private' | 'trash'

/**
 * API响应类型
 */
export interface ApiResponse<T> {
  success: boolean
  data?: T
  message?: string
  errors?: Record<string, string>
}

/**
 * 分页结果
 */
export interface PaginatedResult<T> {
  items: T[]
  total: number
  page: number
  perPage: number
  totalPages: number
}
```

```typescript
// admin-panel/src/types/api.ts

import type { User, Post, ApiResponse } from './models'

/**
 * 用户API类型
 */
export interface UserAPI {
  getUsers(): Promise<ApiResponse<User[]>>
  getUserById(id: number): Promise<ApiResponse<User>>
  createUser(data: Partial<User>): Promise<ApiResponse<User>>
  updateUser(id: number, data: Partial<User>): Promise<ApiResponse<User>>
  deleteUser(id: number): Promise<ApiResponse<void>>
}

/**
 * 文章API类型
 */
export interface PostAPI {
  getPosts(): Promise<ApiResponse<Post[]>>
  getPostById(id: number): Promise<ApiResponse<Post>>
  createPost(data: Partial<Post>): Promise<ApiResponse<Post>>
  updatePost(id: number, data: Partial<Post>): Promise<ApiResponse<Post>>
  deletePost(id: number): Promise<ApiResponse<void>>
}
```

**验收标准**:
- [ ] TypeScript严格模式下编译通过
- [ ] 核心API模块完成类型定义
- [ ] 主要组件使用TypeScript重写

**预计工时**: 5-7天

---

### 【高优先级】第五阶段：测试框架 (Week 3)

#### 任务5.1 前端测试配置

**实施步骤**:

1. **安装Vitest依赖**

```bash
npm install --save-dev \
  vitest \
  @vitest/ui \
  @vue/test-utils \
  jsdom \
  happy-dom
```

2. **创建vitest.config.js**

```javascript
// admin-panel/vitest.config.js

import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    coverage: {
      reporter: ['text', 'json', 'html'],
      exclude: [
        'node_modules/',
        'src/main.js',
      ]
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    }
  }
})
```

3. **编写第一个组件测试**

```typescript
// admin-panel/src/components/__tests__/Dashboard.spec.ts

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Dashboard from '@/views/Dashboard.vue'

describe('Dashboard.vue', () => {
  let wrapper: any
  
  beforeEach(() => {
    wrapper = mount(Dashboard, {
      global: {
        stubs: {
          'el-card': true,
          'el-row': true,
          'el-col': true
        }
      }
    })
  })
  
  it('renders properly', () => {
    expect(wrapper.exists()).toBe(true)
  })
  
  it('displays title', () => {
    const title = wrapper.find('h1')
    expect(title.exists()).toBe(true)
  })
  
  it('loads data on mount', async () => {
    const mockApi = vi.spyOn(console, 'log')
    // ... 测试代码
    expect(mockApi).toHaveBeenCalled()
  })
})
```

4. **在package.json中添加测试脚本**

```json
{
  "scripts": {
    "test": "vitest",
    "test:ui": "vitest --ui",
    "test:coverage": "vitest --coverage"
  }
}
```

**验收标准**:
- [ ] 关键组件覆盖率> 60%
- [ ] 所有测试通过
- [ ] 可生成覆盖率报告

**预计工时**: 4-5天

---

## 监控清单

使用此清单跟踪项目优化进度：

### 第一阶段（Week 1）
- [ ] API速率限制实现并测试
- [ ] CORS安全配置完成
- [ ] 输入验证规范化
- [ ] ESLint/Prettier配置完成
- [ ] Git钩子自动检查启用

**预期成果**: 
- 安全性基本加固
- 代码质量工具到位
- 代码风格统一

### 第二阶段（Week 2）
- [ ] 前端代码分割完成
- [ ] 性能优化配置
- [ ] TypeScript环境配置
- [ ] 核心模块类型定义

**预期成果**:
- 首屏加载时间< 2秒
- 代码包体积减少50%
- TypeScript编译通过

### 第三阶段（Week 3）
- [ ] 测试框架配置
- [ ] 关键组件测试编写
- [ ] 测试覆盖率> 60%

**预期成果**:
- 建立测试体系
- 代码质量有保障

## 验收标准总结

| 类别 | 指标 | 目标值 |
|------|------|--------|
| 代码质量 | ESLint通过率 | 100% |
| 代码质量 | 测试覆盖率 | > 60% |
| 性能 | 首屏加载时间 | < 2秒 |
| 性能 | API响应时间 | < 200ms |
| 安全 | API速率限制 | 已启用 |
| 安全 | CORS配置 | 已规范化 |

---

## 资源和帮助

### 文档引用
- [Vue 3 官方文档](https://vuejs.org)
- [TypeScript 官方文档](https://www.typescriptlang.org)
- [Vitest 官方文档](https://vitest.dev)
- [WordPress 插件开发](https://developer.wordpress.org/plugins/)

### 工具和服务
- ESLint: https://eslint.org
- Prettier: https://prettier.io
- Husky: https://typicode.github.io/husky

## 下一步

完成上述优化后，建议继续进行：

1. **更新主文档** - 将新的优化内容更新到项目文档
2. **团队培训** - 对团队进行新工具和规范的培训
3. **持续集成** - 配置 CI/CD 管道自动化检查和测试
4. **性能监控** - 部署 Sentry 和性能监控系统
5. **定期审查** - 建立代码审查和性能评估机制

