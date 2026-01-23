# 3D图库插件 (xiaowu-3d-gallery)

xiaowu-3d-gallery是3D图库插件，提供3D模型上传、展示、管理等功能。基于Three.js实现浏览器端3D渲染，支持多种模型格式和交互操作。

## 结构

```
xiaowu-3d-gallery/
├── xiaowu-3d-gallery.php        # 主插件文件
├── includes/
│   ├── model-post-type.php   # 自定义文章类型
│   ├── model-uploader.php    # 模型上传器
│   ├── three-renderer.php    # Three.js渲染器
│   ├── viewer.php            # 查看器
│   ├── thumbnail-generator.php  # 缩略图生成
│   └── cdn-manager.php       # CDN管理
├── api/
│   ├── models.php            # 模型API
│   └── upload.php            # 上传API
├── admin/
│   ├── model-list.php        # 模型列表
│   ├── model-edit.php        # 模型编辑
│   └── viewer-config.php     # 查看器配置
├── assets/
│   ├── css/
│   │   ├── viewer.css        # 查看器样式
│   │   └── admin.css         # 后台样式
│   └── js/
│       ├── three.min.js      # Three.js库
│       ├── GLTFLoader.js     # GLTF加载器
│       ├── viewer.js         # 查看器主脚本
│       └── admin.js          # 后台脚本
└── templates/
    ├── model-viewer.php      # 模型查看器模板
    └── model-grid.php        # 模型网格模板
```

## 关键文件

| 文件 | 目的 |
|------|------|
| `xiaowu-3d-gallery.php` | 插件主文件 - 注册自定义文章类型、短代码 |
| `includes/model-uploader.php` | 模型上传器 - 文件验证、CDN上传 |
| `includes/three-renderer.php` | Three.js渲染器 - 场景初始化、渲染循环 |
| `assets/js/viewer.js` | 前端查看器 - 3D交互、事件处理 |
| `api/models.php` | 模型API - CRUD操作 |

## 依赖

**本插件依赖**:
- WordPress核心 - 自定义文章类型、媒体库
- CDN服务 - 模型文件存储
- `xiaowu-user` - 用户权限
- Three.js - 前端3D渲染库

**依赖本插件的**:
- `xiaowu-blog` 主题 - 3D模型展示页面
- `admin-panel` - 模型管理界面

## 功能模块

### 模型上传
- 支持多种格式（GLB, GLTF, OBJ, FBX）
- 文件验证和大小限制
- CDN上传和加速
- 元数据提取

### 3D渲染
- Three.js场景管理
- 模型加载和解析
- 材质和纹理应用
- 灯光和相机设置

### 交互功能
- 旋转、缩放、平移
- 自动旋转
- 全屏模式
- 截图保存

### 查看器配置
- 灯光设置
- 相机位置
- 背景设置
- 动画控制

## 规范

### 文件命名
- 功能文件: `{name}.php`（如 `model-uploader.php`）
- 模板文件: `{name}.php`（如 `model-viewer.php`）
- JavaScript文件: `{name}.js`（如 `viewer.js`）

### 代码模式

**模型上传器**:
```php
class ModelUploader {
    public function upload($file, $metadata) {
        // 验证文件
        $this->validate($file);

        // 上传到CDN
        $cdn_url = $this->uploadToCDN($file);

        // 创建模型记录
        $model_id = $this->createModel($cdn_url, $metadata);

        // 生成缩略图
        $this->generateThumbnail($model_id, $cdn_url);

        return $model_id;
    }
}
```

### 错误处理
- 上传失败时提供详细错误信息
- 模型加载失败时显示错误提示
- 记录上传和渲染日志

### 测试
- 上传测试: 测试不同格式和大小的文件
- 渲染测试: 测试不同模型的渲染效果
- 兼容性测试: 测试不同浏览器的兼容性

## 添加新功能

### 添加新的3D格式支持

1. 在 `model-uploader.php` 添加格式验证
2. 在 `three-renderer.php` 添加加载器
3. 在 `viewer.js` 添加解析逻辑
4. 添加格式说明文档
5. 测试上传和渲染

**检查清单**:
- [ ] 遵循命名约定
- [ ] 添加格式验证
- [ ] 添加加载器
- [ ] 更新文档
- [ ] 测试渲染效果
