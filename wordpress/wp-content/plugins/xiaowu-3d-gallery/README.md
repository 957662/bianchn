# 小伍3D图库插件

WordPress 3D模型展示系统插件，支持GLB、GLTF、OBJ、FBX等格式的3D模型上传、管理和展示。

## 功能特性

- ✅ 支持多种3D模型格式 (GLB, GLTF, OBJ, FBX)
- ✅ Three.js驱动的交互式3D查看器
- ✅ CDN存储支持 (腾讯云COS, 阿里云OSS, 本地存储)
- ✅ AI驱动的缩略图生成
- ✅ 自定义查看器配置 (旋转、缩放、灯光等)
- ✅ 模型分类和标签管理
- ✅ 浏览统计和分析
- ✅ 响应式设计
- ✅ REST API支持
- ✅ 短代码支持

## 系统要求

- WordPress 6.0+
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.2+

## 安装

1. 将插件文件夹上传到 `/wp-content/plugins/xiaowu-3d-gallery/`
2. 在WordPress管理后台激活插件
3. 访问 "3D模型" 菜单开始使用

## 快速开始

### 上传模型

1. 进入 "3D模型" > "添加新模型"
2. 填写标题和描述
3. 上传3D模型文件 (最大100MB)
4. 配置查看器选项
5. 发布

### 使用短代码

显示单个模型:
```
[xiaowu_3d_viewer id="123" width="100%" height="600px" auto_rotate="true"]
```

显示模型图库:
```
[xiaowu_3d_gallery category="characters" limit="12" columns="3"]
```

### REST API

获取模型列表:
```
GET /wp-json/xiaowu-3d/v1/models
```

获取单个模型:
```
GET /wp-json/xiaowu-3d/v1/models/{id}
```

上传模型:
```
POST /wp-json/xiaowu-3d/v1/models/upload
```

## 配置

### CDN设置

在 `wp-config.php` 中配置CDN:

```php
// 本地存储 (默认)
define('CDN_PROVIDER', 'local');

// 腾讯云COS
define('CDN_PROVIDER', 'tencent');
define('CDN_BUCKET', 'your-bucket');
define('CDN_REGION', 'ap-guangzhou');
define('CDN_ACCESS_KEY', 'your-access-key');
define('CDN_SECRET_KEY', 'your-secret-key');

// 阿里云OSS
define('CDN_PROVIDER', 'aliyun');
define('CDN_BUCKET', 'your-bucket');
define('CDN_REGION', 'oss-cn-hangzhou');
define('CDN_ACCESS_KEY', 'your-access-key');
define('CDN_SECRET_KEY', 'your-secret-key');
```

### 查看器配置

每个模型都可以配置:

- **自动旋转**: 启用/禁用自动旋转
- **旋转速度**: 0.1 - 5.0
- **缩放功能**: 启用/禁用
- **平移功能**: 启用/禁用
- **灯光模式**: 工作室/自然光/暗光/自定义
- **背景颜色**: 十六进制颜色代码
- **相机位置**: X, Y, Z坐标

## 技术架构

### 文件结构

```
xiaowu-3d-gallery/
├── xiaowu-3d-gallery.php      # 主插件文件
├── includes/
│   ├── model-post-type.php     # 自定义文章类型
│   ├── model-uploader.php      # 文件上传处理
│   ├── cdn-manager.php         # CDN管理
│   ├── thumbnail-generator.php # 缩略图生成
│   └── viewer.php              # 查看器渲染
├── admin/
│   ├── model-list.php          # 模型列表页面
│   └── settings.php            # 设置页面
├── assets/
│   ├── css/
│   │   ├── viewer.css          # 查看器样式
│   │   └── admin.css           # 管理样式
│   └── js/
│       ├── viewer.js           # Three.js查看器
│       └── admin.js            # 管理脚本
└── templates/
    ├── single-model.php        # 单模型模板
    └── archive-model.php       # 归档模板
```

### 数据库表

**xiaowu_model_views** - 浏览记录
- id: 记录ID
- model_id: 模型ID
- user_id: 用户ID (可选)
- ip_address: IP地址
- user_agent: 用户代理
- viewed_at: 浏览时间

### 自定义元数据

- `_model_file_url`: 模型文件URL
- `_model_file_format`: 文件格式
- `_model_file_size`: 文件大小
- `_model_cdn_url`: CDN URL
- `_model_thumbnail_url`: 缩略图URL
- `_model_metadata`: 模型元数据 (JSON)
- `_viewer_config`: 查看器配置 (JSON)
- `_view_count`: 浏览次数
- `_download_count`: 下载次数

## 开发

### 钩子和过滤器

```php
// 自定义模型上传处理
add_filter('xiaowu_3d_before_upload', function($file) {
    // 处理文件
    return $file;
});

// 自定义查看器配置
add_filter('xiaowu_3d_viewer_config', function($config, $model_id) {
    // 修改配置
    return $config;
}, 10, 2);

// 模型加载完成
add_action('xiaowu_3d_model_loaded', function($model_id) {
    // 处理模型加载事件
});
```

### 扩展查看器

```javascript
// 自定义查看器行为
if (window.XiaowuViewer) {
    const viewer = XiaowuViewer.init('my-viewer-id');

    // 访问Three.js对象
    viewer.scene
    viewer.camera
    viewer.renderer
    viewer.model
}
```

## 与其他插件集成

### 小伍AI服务

自动使用AI服务生成缩略图:

```php
// 在上传模型后自动生成AI缩略图
wp_schedule_single_event(
    time() + 20,
    'xiaowu_generate_model_thumbnail',
    array($model_id, $file_url)
);
```

## 常见问题

**Q: 支持哪些3D格式?**
A: 支持GLB、GLTF、OBJ、FBX格式。推荐使用GLB格式以获得最佳兼容性。

**Q: 文件大小限制是多少?**
A: 默认限制100MB。可以在服务器配置中调整 `upload_max_filesize` 和 `post_max_size`。

**Q: 如何启用CDN?**
A: 在 `wp-config.php` 中配置CDN提供商和凭证。

**Q: 查看器不显示怎么办?**
A: 检查浏览器控制台错误。确保Three.js库已正确加载。

**Q: 如何自定义查看器样式?**
A: 可以通过覆盖 `assets/css/viewer.css` 中的CSS类来自定义样式。

## 更新日志

### 1.0.0
- 初始版本发布
- 支持GLB、GLTF、OBJ、FBX格式
- Three.js交互式查看器
- CDN存储支持
- AI缩略图生成
- REST API
- 短代码支持

## 许可证

GPL v2或更高版本

## 作者

小伍同学 - https://xiaowu.blog

## 支持

如有问题或建议，请访问: https://github.com/yourusername/xiaowu-3d-gallery
