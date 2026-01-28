/**
 * AI 相关路由
 */

const express = require('express');
const router = express.Router();
const { authMiddleware } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取 AI 设置
 */
router.get('/settings', authMiddleware, async (req, res) => {
  try {
    const settings = await db.queryOne('SELECT * FROM settings WHERE option_key = ?', ['ai_settings']);
    const config = settings ? JSON.parse(settings.option_value) : {
      provider: 'openai',
      model: 'gpt-4',
      maxTokens: 4000,
      temperature: 0.7,
      enabled: true,
    };

    res.json(config);
  } catch (error) {
    console.error('Get AI settings error:', error);
    res.status(500).json({ success: false, message: '获取 AI 设置失败' });
  }
});

/**
 * 保存 AI 设置
 */
router.post('/settings', authMiddleware, async (req, res) => {
  try {
    const userRole = req.user.role;
    if (userRole !== 'administrator') {
      return res.status(403).json({ success: false, message: '权限不足' });
    }

    const config = JSON.stringify(req.body);
    await db.query(`
      INSERT INTO settings (option_key, option_value) VALUES (?, ?)
      ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)
    `, ['ai_settings', config]);

    res.json({ success: true });
  } catch (error) {
    console.error('Save AI settings error:', error);
    res.status(500).json({ success: false, message: '保存 AI 设置失败' });
  }
});

/**
 * 测试 AI 连接
 */
router.post('/test-connection', authMiddleware, async (req, res) => {
  try {
    const { provider, apiKey } = req.body;

    if (!provider || !apiKey) {
      return res.status(400).json({ success: false, message: '参数不完整' });
    }

    // 模拟测试连接
    await new Promise(resolve => setTimeout(resolve, 1000));

    res.json({
      success: true,
      message: '连接成功',
    });
  } catch (error) {
    console.error('Test AI connection error:', error);
    res.status(500).json({ success: false, message: '测试连接失败' });
  }
});

/**
 * 生成文章大纲
 */
router.post('/generate-outline', authMiddleware, async (req, res) => {
  try {
    const { topic } = req.body;

    if (!topic) {
      return res.status(400).json({ success: false, message: '主题不能为空' });
    }

    // 模拟 AI 生成大纲
    await new Promise(resolve => setTimeout(resolve, 1500));

    const outline = `# ${topic}

## 引言
- 背景介绍
- 问题陈述

## 主体
### 要点1
- 详细说明
- 示例支持

### 要点2
- 详细说明
- 示例支持

### 要点3
- 详细说明
- 示例支持

## 结论
- 总结
- 建议
- 相关资源
`;

    res.json({
      success: true,
      outline,
    });
  } catch (error) {
    console.error('Generate outline error:', error);
    res.status(500).json({ success: false, message: '生成大纲失败' });
  }
});

/**
 * 优化文章内容
 */
router.post('/optimize-article', authMiddleware, async (req, res) => {
  try {
    const { post_id, content } = req.body;

    if (!content) {
      return res.status(400).json({ success: false, message: '内容不能为空' });
    }

    // 模拟 AI 优化
    await new Promise(resolve => setTimeout(resolve, 1500));

    const suggestions = [
      '建议增加更多实际案例',
      '可以添加代码示例',
      '建议增加相关文章引用',
      '可以优化段落结构',
    ];

    res.json({
      success: true,
      optimized: content + '\n\n[AI优化建议：' + suggestions.join('；') + ']',
      suggestions,
    });
  } catch (error) {
    console.error('Optimize article error:', error);
    res.status(500).json({ success: false, message: 'AI 优化失败' });
  }
});

module.exports = router;
