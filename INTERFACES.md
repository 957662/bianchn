# 接口定义

本文档定义了小伍同学个人博客系统的所有公开接口，包括WordPress REST API、自定义插件API、前端组件接口等。

## WordPress REST API

### 文章接口

#### 获取文章列表
```http
GET /wp-json/wp/v2/posts
```

**查询参数**:
- `page` (integer) - 页码，默认1
- `per_page` (integer) - 每页数量，默认10，最大100
- `search` (string) - 搜索关键词
- `categories` (integer) - 分类ID过滤
- `tags` (integer) - 标签ID过滤
- `author` (integer) - 作者ID过滤
- `status` (string) - 状态过滤 (publish, draft, pending, etc.)

**响应示例**:
```json
{
  "id": 1,
  "date": "2025-01-22T10:00:00",
  "date_gmt": "2025-01-22T02:00:00",
  "modified": "2025-01-22T10:00:00",
  "modified_gmt": "2025-01-22T02:00:00",
  "slug": "my-first-post",
  "status": "publish",
  "type": "post",
  "link": "https://example.com/my-first-post",
  "title": {
    "rendered": "My First Post"
  },
  "content": {
    "rendered": "<p>Post content...</p>",
    "protected": false
  },
  "excerpt": {
    "rendered": "<p>Post excerpt...</p>",
    "protected": false
  },
  "author": 1,
  "featured_media": 0,
  "comment_status": "open",
  "ping_status": "open",
  "sticky": false,
  "template": "",
  "format": "standard",
  "categories": [1],
  "tags": [],
  "meta": {}
}
```

#### 获取单篇文章
```http
GET /wp-json/wp/v2/posts/{id}
```

#### 创建文章
```http
POST /wp-json/wp/v2/posts
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "title": "Article Title",
  "content": "Article content...",
  "status": "draft",
  "categories": [1],
  "tags": [2, 3]
}
```

#### 更新文章
```http
POST /wp-json/wp/v2/posts/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

#### 删除文章
```http
DELETE /wp-json/wp/v2/posts/{id}
Authorization: Bearer {token}
```

### 分类接口

#### 获取分类列表
```http
GET /wp-json/wp/v2/categories
```

**响应示例**:
```json
{
  "id": 1,
  "name": "Uncategorized",
  "slug": "uncategorized",
  "description": "",
  "parent": 0,
  "count": 1,
  "link": "https://example.com/category/uncategorized"
}
```

### 标签接口

#### 获取标签列表
```http
GET /wp-json/wp/v2/tags
```

### 媒体接口

#### 上传媒体
```http
POST /wp-json/wp/v2/media
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**请求参数**:
- `file` (file) - 文件
- `title` (string) - 标题（可选）
- `alt_text` (string) - 替代文本（可选）

### 用户接口

#### 获取当前用户
```http
GET /wp-json/wp/v2/users/me
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "id": 1,
  "name": "admin",
  "url": "",
  "description": "",
  "link": "https://example.com/author/admin",
  "slug": "admin",
  "roles": ["administrator"],
  "avatar_urls": {}
}
```

### 评论接口

#### 获取文章评论
```http
GET /wp-json/wp/v2/comments?post={post_id}
```

#### 创建评论
```http
POST /wp-json/wp/v2/comments
Content-Type: application/json
```

**请求体**:
```json
{
  "post": 1,
  "author_name": "访客",
  "author_email": "visitor@example.com",
  "content": "评论内容",
  "parent": 0
}
```

## 自定义插件API

### AI服务接口

#### 文章优化
```http
POST /wp-json/xiaowu-ai/v1/optimize-article
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "title": "原始标题",
  "content": "原始内容",
  "type": "seo|readability|style",
  "language": "zh-CN"
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "optimized_title": "优化后的标题",
    "optimized_content": "<p>优化后的内容...</p>",
    "suggestions": [
      {
        "type": "title",
        "original": "原始标题",
        "suggested": "优化后的标题",
        "reason": "更吸引眼球"
      },
      {
        "type": "content",
        "position": "paragraph-1",
        "suggestion": "添加引言段落",
        "reason": "提高可读性"
      }
    ],
    "seo_score": 85,
    "readability_score": 90
  }
}
```

#### 智能搜索
```http
GET /wp-json/xiaowu-search/v1/search?q={query}&limit={limit}
```

**查询参数**:
- `q` (string, required) - 搜索关键词
- `limit` (integer) - 返回结果数量，默认10
- `type` (string) - 搜索类型 (all|posts|comments|models)，默认all
- `semantic` (boolean) - 是否启用语义搜索，默认true

**响应示例**:
```json
{
  "success": true,
  "data": {
    "total": 15,
    "results": [
      {
        "type": "post",
        "id": 1,
        "title": "文章标题",
        "excerpt": "文章摘要...",
        "relevance": 0.95,
        "url": "https://example.com/post/1"
      },
      {
        "type": "model",
        "id": 2,
        "title": "3D模型名称",
        "thumbnail": "https://cdn.example.com/models/thumbnail.jpg",
        "relevance": 0.88,
        "url": "https://example.com/model/2"
      }
    ],
    "suggestions": ["搜索建议1", "搜索建议2"]
  }
}
```

#### 内容推荐
```http
POST /wp-json/xiaowu-ai/v1/recommend
Content-Type: application/json
```

**请求体**:
```json
{
  "user_id": 1,
  "context": {
    "current_post_id": 1,
    "recent_posts": [2, 3, 5],
    "user_preferences": {
      "categories": [1, 2],
      "tags": [10, 20]
    }
  },
  "limit": 5
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "recommendations": [
      {
        "type": "post",
        "id": 10,
        "title": "推荐文章标题",
        "reason": "基于您最近阅读的文章",
        "relevance": 0.92,
        "url": "https://example.com/post/10"
      },
      {
        "type": "model",
        "id": 5,
        "title": "推荐3D模型",
        "reason": "与您浏览的内容相关",
        "relevance": 0.87,
        "url": "https://example.com/model/5"
      }
    ]
  }
}
```

#### 代码生成
```http
POST /wp-json/xiaowu-ai/v1/generate-code
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "description": "实现一个WordPress短代码显示最新文章",
  "language": "php",
  "framework": "wordpress",
  "context": {
    "file": "functions.php",
    "purpose": "shortcode"
  }
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "code": "<?php\nfunction latest_posts_shortcode($atts) {\n  // implementation\n}\nadd_shortcode('latest_posts', 'latest_posts_shortcode');\n",
    "explanation": "这段代码创建了一个WordPress短代码，可以在内容中插入最新文章列表。",
    "file_suggestion": "wp-content/themes/xiaowu-blog/functions.php",
    "dependencies": [],
    "tested": false
  }
}
```

#### 联网搜索
```http
POST /wp-json/xiaowu-ai/v1/web-search
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "query": "WordPress最新版本特性",
  "num_results": 5,
  "language": "zh-CN"
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "title": "WordPress 6.5 新特性",
        "url": "https://wordpress.org/news/2024/03/wordpress-6-5/",
        "snippet": "WordPress 6.5 引入了许多新特性，包括...",
        "source": "wordpress.org",
        "published_date": "2024-03-05"
      }
    ]
  }
}
```

### 3D图库接口

#### 上传3D模型
```http
POST /wp-json/xiaowu-3d/v1/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**请求参数**:
- `model` (file, required) - 3D模型文件 (.glb, .gltf, .obj, .fbx)
- `thumbnail` (file, optional) - 缩略图
- `title` (string, required) - 模型标题
- `description` (string, optional) - 模型描述
- `category` (integer, optional) - 分类ID

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "模型标题",
    "description": "模型描述",
    "model_url": "https://cdn.example.com/models/model1.glb",
    "thumbnail_url": "https://cdn.example.com/models/thumbnail1.jpg",
    "format": "glb",
    "file_size": 1024000,
    "uploaded_at": "2025-01-22T10:00:00"
  }
}
```

#### 获取3D模型列表
```http
GET /wp-json/xiaowu-3d/v1/models
```

**查询参数**:
- `page` (integer) - 页码
- `per_page` (integer) - 每页数量
- `category` (integer) - 分类ID

#### 获取单个3D模型
```http
GET /wp-json/xiaowu-3d/v1/models/{id}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "模型标题",
    "description": "模型描述",
    "model_url": "https://cdn.example.com/models/model1.glb",
    "thumbnail_url": "https://cdn.example.com/models/thumbnail1.jpg",
    "format": "glb",
    "file_size": 1024000,
    "category": {
      "id": 1,
      "name": "类别名称"
    },
    "view_count": 100,
    "download_count": 50,
    "created_at": "2025-01-22T10:00:00",
    "viewer_config": {
      "auto_rotate": true,
      "lighting": "studio",
      "background_color": "#ffffff"
    }
  }
}
```

#### 更新3D模型
```http
POST /wp-json/xiaowu-3d/v1/models/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "title": "更新后的标题",
  "description": "更新后的描述",
  "viewer_config": {
    "auto_rotate": false,
    "lighting": "environment"
  }
}
```

#### 删除3D模型
```http
DELETE /wp-json/xiaowu-3d/v1/models/{id}
Authorization: Bearer {token}
```

### 用户管理接口

#### 用户注册
```http
POST /wp-json/xiaowu-user/v1/register
Content-Type: application/json
```

**请求体**:
```json
{
  "username": "newuser",
  "email": "user@example.com",
  "password": "securePassword123",
  "display_name": "显示名称"
}
```

**响应示例**:
```json
{
  "success": true,
  "message": "注册成功，请查收验证邮件",
  "data": {
    "user_id": 2,
    "username": "newuser",
    "email": "user@example.com",
    "role": "subscriber",
    "status": "pending"
  }
}
```

#### 邮箱验证
```http
GET /wp-json/xiaowu-user/v1/verify?token={verification_token}
```

**响应示例**:
```json
{
  "success": true,
  "message": "账户已激活"
}
```

#### 请求密码重置
```http
POST /wp-json/xiaowu-user/v1/forgot-password
Content-Type: application/json
```

**请求体**:
```json
{
  "email": "user@example.com"
}
```

#### 重置密码
```http
POST /wp-json/xiaowu-user/v1/reset-password
Content-Type: application/json
```

**请求体**:
```json
{
  "token": "reset_token",
  "password": "newPassword123",
  "password_confirm": "newPassword123"
}
```

### 评论系统接口

#### 获取评论列表（增强）
```http
GET /wp-json/xiaowu-comment/v1/comments?post_id={post_id}
```

**查询参数**:
- `post_id` (integer, required) - 文章ID
- `parent` (integer) - 父评论ID（用于获取子评论）
- `page` (integer) - 页码
- `per_page` (integer) - 每页数量
- `order` (string) - 排序 (asc|desc)，默认desc
- `status` (string) - 状态过滤 (approved|pending|spam)

#### 创建评论（增强）
```http
POST /wp-json/xiaowu-comment/v1/comments
Content-Type: application/json
```

**请求体**:
```json
{
  "post_id": 1,
  "author_name": "访客",
  "author_email": "visitor@example.com",
  "content": "评论内容",
  "parent_id": 0,
  "user_id": 0
}
```

#### 评论点赞
```http
POST /wp-json/xiaowu-comment/v1/like
Content-Type: application/json
```

**请求体**:
```json
{
  "comment_id": 1
}
```

#### 审核评论
```http
POST /wp-json/xiaowu-comment/v1/moderate
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "comment_id": 1,
  "action": "approve|reject|spam"
}
```

### AI图像生成接口

#### 生成图像
```http
POST /wp-json/xiaowu-ai/v1/generate-image
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "prompt": "现代化的博客图标，简约风格，蓝色",
  "style": "icon|illustration|photo|3d",
  "size": "256x256|512x512|1024x1024",
  "format": "png|jpg",
  "num_images": 1
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "images": [
      {
        "url": "https://cdn.example.com/generated/image1.png",
        "size": "512x512",
        "format": "png"
      }
    ],
    "generation_time": 2.5
  }
}
```

### AI配置接口

#### 获取AI配置
```http
GET /wp-json/xiaowu-ai/v1/config
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "provider": "openai",
    "model": "gpt-4",
    "api_key_configured": true,
    "features": {
      "article_optimization": true,
      "smart_search": true,
      "recommendation": true,
      "code_generation": true,
      "web_search": true
    }
  }
}
```

#### 更新AI配置
```http
POST /wp-json/xiaowu-ai/v1/config
Authorization: Bearer {token}
Content-Type: application/json
```

**请求体**:
```json
{
  "provider": "openai",
  "api_key": "sk-...",
  "model": "gpt-4",
  "features": {
    "article_optimization": true,
    "smart_search": true,
    "recommendation": false,
    "code_generation": true,
    "web_search": true
  },
  "custom_prompts": {
    "blog_context": "这是一个个人博客，主要分享技术文章和学习笔记..."
  }
}
```

## 认证方式

### WordPress JWT认证

本系统使用JWT（JSON Web Token）进行API认证。

#### 获取Token
```http
POST /wp-json/jwt-auth/v1/token
Content-Type: application/json
```

**请求体**:
```json
{
  "username": "admin",
  "password": "password"
}
```

**响应示例**:
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user_email": "admin@example.com",
  "user_nicename": "admin",
  "user_display_name": "Admin"
}
```

#### 使用Token
将Token添加到请求头：
```
Authorization: Bearer {token}
```

#### 刷新Token
```http
POST /wp-json/jwt-auth/v1/token/refresh
Authorization: Bearer {token}
```

## WebSocket接口（实时功能）

### 评论通知
```javascript
// 连接WebSocket
const ws = new WebSocket('wss://example.com/wp-json/xiaowu/v1/ws/comment');

// 订阅文章评论
ws.send(JSON.stringify({
  action: 'subscribe',
  channel: 'post_comments',
  post_id: 1
}));

// 接收新评论
ws.onmessage = (event) => {
  const comment = JSON.parse(event.data);
  console.log('新评论:', comment);
};
```

### AI任务状态
```javascript
const ws = new WebSocket('wss://example.com/wp-json/xiaowu/v1/ws/ai-task');

// 监听AI任务状态
ws.onmessage = (event) => {
  const task = JSON.parse(event.data);
  console.log('AI任务状态:', task.status, task.progress);
};
```

## 前端组件接口

### Vue.js后台管理面板组件

#### ArticleEditor组件
```vue
<template>
  <article-editor
    v-model:content="articleContent"
    v-model:title="articleTitle"
    :ai-enabled="true"
    @ai-optimize="handleAIOptimize"
    @save="handleSave"
    @publish="handlePublish"
  />
</template>

<script setup>
import { ref } from 'vue';
import ArticleEditor from './components/ArticleEditor.vue';

const articleContent = ref('');
const articleTitle = ref('');

const handleAIOptimize = async () => {
  // 调用AI优化接口
};
</script>
```

#### ModelGallery组件
```vue
<template>
  <model-gallery
    :models="models"
    :upload-enabled="hasPermission"
    @upload="handleModelUpload"
    @delete="handleModelDelete"
    @select="handleModelSelect"
  />
</template>
```

#### AIDashboard组件
```vue
<template>
  <ai-dashboard
    :config="aiConfig"
    @update-config="handleConfigUpdate"
    :stats="aiStats"
  />
</template>
```

## 错误码

| 错误码 | 说明 |
|--------|------|
| 200 | 成功 |
| 201 | 创建成功 |
| 400 | 请求参数错误 |
| 401 | 未认证 |
| 403 | 权限不足 |
| 404 | 资源不存在 |
| 422 | 验证失败 |
| 429 | 请求过于频繁 |
| 500 | 服务器内部错误 |
| 503 | 服务不可用 |

## 速率限制

- 未认证用户: 60请求/分钟
- 已认证用户: 300请求/分钟
- 管理员: 1000请求/分钟

速率限制响应头：
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 299
X-RateLimit-Reset: 1705905600
```
