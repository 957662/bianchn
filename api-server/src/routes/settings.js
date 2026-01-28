/**
 * 系统设置相关路由
 */

const express = require('express');
const settingsRouter = express.Router();
const { authMiddleware } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取系统设置
 */
settingsRouter.get('/', authMiddleware, async (req, res) => {
  try {
    const settings = await db.query('SELECT option_key, option_value FROM settings');
    const config = {};
    settings.forEach(s => {
      config[s.option_key] = s.option_value;
    });
    res.json(config);
  } catch (error) {
    console.error('Get settings error:', error);
    res.status(500).json({ success: false, message: '获取设置失败' });
  }
});

/**
 * 保存基本设置
 */
settingsRouter.post('/basic', authMiddleware, async (req, res) => {
  try {
    const userRole = req.user.role;
    if (userRole !== 'administrator') {
      return res.status(403).json({ success: false, message: '权限不足' });
    }

    const { site_title, site_description, site_url } = req.body;

    if (site_title) {
      await db.query('INSERT INTO settings (option_key, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)', ['site_title', site_title]);
    }
    if (site_description) {
      await db.query('INSERT INTO settings (option_key, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)', ['site_description', site_description]);
    }
    if (site_url) {
      await db.query('INSERT INTO settings (option_key, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)', ['site_url', site_url]);
    }

    res.json({ success: true });
  } catch (error) {
    console.error('Save basic settings error:', error);
    res.status(500).json({ success: false, message: '保存基本设置失败' });
  }
});

/**
 * 保存评论设置
 */
settingsRouter.post('/comments', authMiddleware, async (req, res) => {
  try {
    const userRole = req.user.role;
    if (userRole !== 'administrator') {
      return res.status(403).json({ success: false, message: '权限不足' });
    }

    const { comments_require_moderation, comments_registration_required } = req.body;

    if (comments_require_moderation !== undefined) {
      await db.query('INSERT INTO settings (option_key, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)', ['comments_moderation', comments_require_moderation ? '1' : '0']);
    }
    if (comments_registration_required !== undefined) {
      await db.query('INSERT INTO settings (option_key, option_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)', ['comments_registration', comments_registration_required ? '1' : '0']);
    }

    res.json({ success: true });
  } catch (error) {
    console.error('Save comments settings error:', error);
    res.status(500).json({ success: false, message: '保存评论设置失败' });
  }
});

/**
 * 清除缓存
 */
settingsRouter.post('/cache/clear', authMiddleware, async (req, res) => {
  try {
    // 模拟清除缓存
    res.json({ success: true, message: '缓存已清除' });
  } catch (error) {
    console.error('Clear cache error:', error);
    res.status(500).json({ success: false, message: '清除缓存失败' });
  }
});

module.exports = settingsRouter;
