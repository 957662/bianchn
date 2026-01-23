# AI服务插件 (xiaowu-ai)

xiaowu-ai是AI服务插件，提供文章优化、智能搜索、内容推荐、代码生成、联网搜索等功能。该插件封装了多个大模型服务商的API，提供统一的AI能力接口。

## 结构

```
xiaowu-ai/
├── xiaowu-ai.php               # 主插件文件
├── includes/
│   ├── ai-service.php         # AI服务主类
│   ├── article-optimizer.php  # 文章优化
│   ├── smart-search.php       # 智能搜索
│   ├── recommendation.php     # 推荐系统
│   ├── code-generator.php     # 代码生成
│   ├── web-search.php         # 联网搜索
│   └── cache-manager.php      # 缓存管理
├── api/
│   ├── article-optimization.php    # 文章优化API
│   ├── search.php                 # 搜索API
│   ├── recommendation.php         # 推荐API
│   ├── code-generation.php        # 代码生成API
│   └── web-search.php             # 联网搜索API
├── admin/
│   ├── settings-page.php    # 设置页面
│   └── stats-page.php        # 统计页面
├── assets/
│   ├── css/
│   │   └── admin.css        # 后台样式
│   └── js/
│       └── admin.js         # 后台脚本
└── templates/
    └── optimization-result.php    # 优化结果模板
```

## 关键文件

| 文件 | 目的 |
|------|------|
| `xiaowu-ai.php` | 插件主文件 - 注册插件、初始化服务 |
| `includes/ai-service.php` | AI服务核心类 - API调用封装 |
| `includes/article-optimizer.php` | 文章优化功能 - SEO、可读性优化 |
| `includes/smart-search.php` | 智能搜索 - 语义搜索、联想推荐 |
| `includes/cache-manager.php` | 缓存管理 - Redis缓存操作 |
| `api/article-optimization.php` | 文章优化API端点 |

## 依赖

**本插件依赖**:
- WordPress核心 - 用户系统、文章类型
- Redis扩展 - 缓存功能
- GuzzleHTTP - HTTP客户端
- `xiaowu-user` - 用户管理（可选）

**依赖本插件的**:
- `xiaowu-blog` 主题 - 文章编辑器集成
- `admin-panel` - AI设置界面

## 功能模块

### 文章优化
- 标题优化
- 内容结构优化
- SEO优化
- 可读性优化

### 智能搜索
- 语义搜索
- 联想推荐
- 搜索结果排序

### 内容推荐
- 协同过滤
- 基于内容的推荐
- 个性化推荐

### 代码生成
- WordPress短代码生成
- 插件功能代码生成
- 主题模板代码生成

### 联网搜索
- 信息检索
- 结果整理
- 关键点提取

## 规范

### 文件命名
- 主类文件: `{name}.php`（如 `ai-service.php`）
- API文件: `{feature}.php`（如 `article-optimization.php`）
- 模板文件: `{name}.php`（如 `optimization-result.php`）

### 代码模式

**AI服务类**:
```php
class AIService {
    public function __construct($config) {
        $this->provider = $config['provider'];
        $this->api_key = $config['api_key'];
        $this->model = $config['model'];
    }

    public function call($prompt, $options = []) {
        // API调用的标准模式
    }
}
```

### 错误处理
- 所有API调用必须有try-catch
- 记录详细的错误日志
- 返回用户友好的错误信息
- 对可恢复错误自动重试

### 测试
- 单元测试: 使用PHPUnit测试核心功能
- 集成测试: 测试API调用
- Mock测试: Mock AI服务商响应

## 添加新功能

### 添加新的AI功能

1. 在 `includes/` 创建功能文件（如 `summarizer.php`）
2. 在 `ai-service.php` 添加方法
3. 在 `api/` 创建API端点
4. 在 `admin/` 添加设置界面
5. 添加测试

**检查清单**:
- [ ] 遵循命名约定
- [ ] 有对应测试文件
- [ ] API端点有权限检查
- [ ] 错误处理完善
- [ ] 日志记录详细
- [ ] 缓存策略合理
