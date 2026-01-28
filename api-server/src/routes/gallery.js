/**
 * 3D 图库相关路由
 */

const express = require('express');
const router = express.Router();
const { authMiddleware } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取3D模型列表
 */
router.get('/models', async (req, res) => {
  try {
    const { page = 1, per_page = 20, category, status } = req.query;

    let whereClause = [];
    let params = [];

    if (category) {
      whereClause.push('category = ?');
      params.push(category);
    }

    if (status) {
      whereClause.push('status = ?');
      params.push(status);
    }

    const whereSql = whereClause.length > 0 ? 'WHERE ' + whereClause.join(' AND ') : '';
    const offset = (page - 1) * per_page;

    const [countResult] = await db.query(`SELECT COUNT(*) as total FROM model_3d ${whereSql}`, params);
    const total = countResult ? countResult.total : 0;

    const models = await db.query(
      `SELECT * FROM model_3d ${whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, per_page, offset]
    );

    res.json({ models, total, page: parseInt(page), per_page: parseInt(per_page) });
  } catch (error) {
    console.error('Get models error:', error);
    res.status(500).json({ success: false, message: '获取模型列表失败' });
  }
});

/**
 * 上传3D模型
 */
router.post('/models/upload', authMiddleware, async (req, res) => {
  try {
    const { title, description, category } = req.body;
    const userId = req.user.userId;

    if (!title) {
      return res.status(400).json({ success: false, message: '标题不能为空' });
    }

    // 模拟文件上传（实际应该处理 multipart/form-data）
    const fileUrl = `https://picsum.photos/400/400?random=${Date.now()}`;
    const thumbnailUrl = `https://picsum.photos/200/200?random=${Date.now()}`;

    const modelId = await db.insert(
      'INSERT INTO model_3d (title, description, category, file_url, thumbnail_url, author_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [title, description, category, fileUrl, thumbnailUrl, userId, 'published']
    );

    const newModel = await db.queryOne('SELECT * FROM model_3d WHERE id = ?', [modelId]);
    res.json(newModel);
  } catch (error) {
    console.error('Upload model error:', error);
    res.status(500).json({ success: false, message: '上传模型失败' });
  }
});

/**
 * 获取分类列表
 */
router.get('/categories', async (req, res) => {
  try {
    const categories = await db.query('SELECT DISTINCT category FROM model_3d WHERE category IS NOT NULL ORDER BY category');
    res.json(categories.map(c => c.category));
  } catch (error) {
    console.error('Get categories error:', error);
    res.status(500).json({ success: false, message: '获取分类失败' });
  }
});

module.exports = router;
