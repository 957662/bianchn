# Git 提交规范指南

## 📝 概述

本项目采用约定式提交规范（Conventional Commits），通过 Commitlint 和 Husky 工具强制执行。所有提交必须遵循本规范。

---

## 🎯 提交信息格式

```
<type>[optional scope]: <subject>

[optional body]

[optional footer(s)]
```

### 格式说明

- **type**: 提交类型（必填）- 见下表
- **scope**: 影响范围（可选）- 例如: `security`, `validation`, `api`
- **subject**: 简短描述（必填）- 使用命令式语气，不以句号结尾
- **body**: 详细说明（可选）- 解释"是什么"和"为什么"，而非"如何做"
- **footer**: 页脚信息（可选）- 记录 Breaking Changes 和相关 Issue

---

## 📌 提交类型（Type）

| 类型 | 图标 | 说明 | 示例 |
|------|------|------|------|
| `feat` | ✨ | 新功能 | `feat(auth): add login validation` |
| `fix` | 🐛 | 修复 bug | `fix(api): resolve rate limiter timeout` |
| `docs` | 📝 | 文档变更 | `docs: update API documentation` |
| `style` | 💄 | 代码样式（不影响逻辑） | `style(ui): fix button alignment` |
| `refactor` | ♻️ | 代码重构 | `refactor(db): optimize query performance` |
| `perf` | ⚡ | 性能优化 | `perf(cache): reduce query time by 50%` |
| `test` | 🧪 | 测试相关 | `test(security): add XSS protection tests` |
| `chore` | 🔧 | 构建/依赖变更 | `chore(deps): update eslint to v8.45` |
| `ci` | 🤖 | CI/CD 配置 | `ci: add GitHub Actions workflow` |
| `revert` | ⏮️ | 恢复之前提交 | `revert: commit abc123def` |
| `security` | 🔒 | 安全修复 | `security: fix SQL injection vulnerability` |
| `deps` | 📦 | 依赖更新 | `deps: bump axios to ^1.5.0` |
| `locale` | 🌍 | 国际化 | `locale(zh): add Chinese translations` |
| `config` | ⚙️ | 配置文件 | `config: add nginx SSL settings` |
| `release` | 🎉 | 发布版本 | `release: version 1.0.0` |

---

## 📋 提交示例

### ✅ 良好示例

#### 新功能提交
```
feat(validation): add email format validation

实现了邮箱格式验证功能，支持以下特性：
- RFC 5322 标准兼容
- 可配置的严格模式
- 异步验证支持

Closes #123
```

#### Bug 修复提交
```
fix(security): fix SQL injection vulnerability in user search

在用户搜索功能中发现 SQL 注入漏洞。该修复：
- 使用参数化查询替代字符串连接
- 添加输入验证
- 实现安全日志记录

此更改破坏与旧版本 API 的兼容性。

BREAKING CHANGE: User search API now requires validated input
```

#### 文档更新
```
docs(api): add rate limiting documentation

- 添加速率限制配置指南
- 记录 429 响应示例
- 更新 API 端点文档
```

#### 性能优化
```
perf(database): optimize post query performance

优化了文章列表查询的性能：
- 添加数据库索引（post_status, created_at）
- 实现查询结果缓存
- 减少 JOIN 操作

性能提升：
- 平均查询时间从 500ms 降至 50ms
- 减少数据库连接 60%
```

### ❌ 错误示例

```
❌ fixed stuff              # 类型不规范，描述不清
❌ Fix: API bug             # 类型应为小写，不需要冒号
❌ feat: fix the bug        # 类型和描述不匹配
❌ feat: add new feature.   # 末尾有句号
❌ feature(user): add auth  # 类型应为 'feat' 而非 'feature'
```

---

## 🔍 作用域（Scope）指南

作用域应该描述受影响的系统部分：

### 推荐作用域

```
# 安全相关
- security
- validation
- input-validation
- sql-injection
- xss-protection

# API 相关
- api
- rest-api
- rate-limiting
- cors
- middleware

# 数据库相关
- database
- db
- queries
- migration
- schema

# 前端相关
- ui
- components
- router
- store
- styles

# 其他
- config
- build
- ci
- deployment
- docs
```

### 作用域示例

```
feat(input-validation): add email validator
fix(sql-injection): escape user input
docs(api-security): update rate limiting guide
perf(database): add index on post_id
```

---

## 📖 主题（Subject）编写规则

1. **使用命令式语气**
   - ✅ "add email validation" 
   - ❌ "added email validation"
   - ❌ "adds email validation"

2. **不以句号结尾**
   - ✅ "fix authentication bug"
   - ❌ "fix authentication bug."

3. **首字母小写**
   - ✅ "update documentation"
   - ❌ "Update documentation"

4. **简洁明了**
   - ✅ "add rate limiting" (50 字符)
   - ❌ "implement a comprehensive rate limiting system to prevent API abuse" (过长)

5. **说明做了什么，而不是如何做**
   - ✅ "refactor user service"
   - ❌ "change forEach to map in user service"

---

## 📄 主体（Body）编写规则

Body 用于详细说明提交内容，应该回答以下问题：

1. **为什么做这个改变？**
2. **这个改变解决了什么问题？**
3. **这个改变有什么影响？**

### Body 规则

- 每行最多 100 个字符
- 使用具体的术语和示例
- 解释业务需求和技术原因
- 避免重复 Subject 中已说明的内容

### Body 示例

```
feat(security): implement input validation system

该功能实现了完整的输入验证框架，提供以下能力：

- 规则基础的验证引擎，支持 15+ 验证规则
- 自动数据清理和转义
- SQL 注入和 XSS 攻击防护
- 错误消息国际化支持

这个改变是因为：
- 当前系统缺乏统一的验证标准
- 存在多个安全漏洞风险
- 需要提高代码可维护性

影响范围：
- 所有用户输入处理必须使用新的验证系统
- 现有验证逻辑应迁移到新框架
```

---

## 🚨 破坏性变更（Breaking Changes）

当更改会影响现有 API 或功能时，必须记录为破坏性变更：

```
feat(api): redesign user authentication

新的认证系统提供更好的安全性和可扩展性：
- 支持多因素认证
- OAuth2 集成
- 自动令牌刷新

BREAKING CHANGE: 旧的 /auth/login 端点已移除
旧的客户端需要迁移到 /api/v2/auth/login 端点
```

### 标记破坏性变更

有三种方式表示破坏性变更：

1. **在 Footer 中明确标记**
   ```
   BREAKING CHANGE: description
   ```

2. **在类型后加感叹号**
   ```
   feat!: redesign API
   ```

3. **在作用域后加感叹号**
   ```
   feat(api)!: redesign API
   ```

---

## 🔗 相关 Issue 链接

使用以下关键字链接相关 Issue：

- `Closes #123` - 关闭 Issue
- `Refs #123` - 引用 Issue
- `Related to #123` - 相关 Issue
- `Fixes #123` - 修复 Issue
- `Resolves #123` - 解决 Issue

### 示例

```
fix(auth): resolve token expiration issue

修复了令牌过期后无法自动刷新的问题。

Closes #456
Refs #445, #448
```

---

## 🛠️ 使用 Commitizen 交互式提交

为了更容易地遵循规范，可以使用 Commitizen 提供的交互式提交工具：

```bash
npm run commitizen
# 或
npx cz
```

### 交互式提交流程

1. 选择提交类型
2. 输入影响范围（可选）
3. 输入简短描述
4. 输入详细说明（可选）
5. 是否有破坏性变更？
6. 是否有相关 Issue？
7. 确认提交信息

---

## ✔️ 自动检查

Husky 和 Commitlint 会在以下时刻自动检查：

### Pre-commit Hook
- 运行 ESLint 检查 JS/Vue 文件
- 运行 Prettier 格式化代码
- 检查 PHP 文件语法
- 所有检查通过后暂存文件

### Commit-msg Hook
- 验证提交信息格式
- 检查类型是否有效
- 检查主题是否符合规范
- 确保 Body 和 Footer 格式正确

如果检查失败，提交将被中止，并显示详细错误信息。

---

## 🚀 快速参考

### 常见提交命令

```bash
# 交互式提交
npm run commitizen

# 常见提交
git commit -m "feat(auth): add login validation"
git commit -m "fix(api): resolve rate limiter issue"
git commit -m "docs: update README"
git commit -m "style(ui): fix button alignment"
git commit -m "test(security): add XSS tests"

# 带详细信息的提交
git commit -m "feat(security): implement validation system

- Add input validator class
- Add SQL injection protection
- Add XSS protection

Closes #123"
```

### 绕过检查（仅在紧急情况下使用）

```bash
# 绕过 Husky 钩子
git commit --no-verify -m "emergency fix"

# ⚠️ 不推荐！这会绕过代码检查和提交信息验证
```

---

## 📊 生成 Changelog

提交信息规范化后，可以自动生成 Changelog：

```bash
npm run changelog
# 生成 CHANGELOG.md
```

---

## 🤝 团队约定

本项目要求所有成员遵循此规范：

- ✅ 必须使用规范的提交格式
- ✅ 使用 Commitizen 进行交互式提交
- ✅ 在 PR 中检查提交历史
- ✅ 定期更新依赖和工具
- ❌ 不允许绕过自动检查
- ❌ 不允许空白或无意义的提交信息

---

## 📚 参考资源

- [约定式提交规范](https://www.conventionalcommits.org/zh-hans/)
- [Commitlint 文档](https://commitlint.js.org/)
- [Husky 文档](https://typicode.github.io/husky/)
- [Commitizen 文档](http://commitizen.github.io/cz-cli/)

---

## ❓ 常见问题

### Q: 如何提交多个不相关的改动？
A: 应该分别提交。每个提交应该是一个完整的逻辑单元。

### Q: 提交信息太长怎么办？
A: 在 Subject 中简要说明，在 Body 中详细解释。Subject 最多 50 字符。

### Q: 能否修改之前的提交？
A: 可以使用 `git commit --amend`，但不推荐修改已推送的提交。

### Q: 如何处理紧急修复？
A: 仍然应该遵循规范。紧急修复应该是高优先级的 Bug 修复，不是理由来忽视规范。

### Q: Commitizen 提示找不到命令怎么办？
A: 运行 `npm install` 或 `npm run prepare` 以安装依赖和设置 Husky。

---

## 🎓 学习路径

1. 理解约定式提交规范的目的和好处
2. 学习各种提交类型的区别
3. 练习编写规范的提交信息
4. 使用 Commitizen 工具进行交互式提交
5. 在代码审查中检查提交信息质量
6. 建立团队约定和最佳实践

---

**最后更新**: 2024  
**维护者**: Team  
**版本**: 1.0.0
