/**
 * 评论相关路由
 */

const express = require('express');
const router = express.Router();
const { authMiddleware } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取评论列表
 */
router.get('/comments', async (req, res) => {
  try {
    const {
      page = 1,
      per_page = 20,
      status,
      post_id,
    } = req.query;

    let whereClause = [];
    let params = [];

    // 状态过滤
    if (status) {
      whereClause.push('c.status = ?');
      params.push(status);
    }

    // 文章过滤
    if (post_id) {
      whereClause.push('c.post_id = ?');
      params.push(post_id);
    }

    const whereSql = whereClause.length > 0 ? 'WHERE ' + whereClause.join(' AND ') : '';
    const offset = (page - 1) * per_page;

    // 获取总数
    const countSql = `SELECT COUNT(*) as total FROM comments c ${whereSql}`;
    const countResult = await db.queryOne(countSql, params);
    const total = countResult ? countResult.total : 0;

    // 获取评论列表
    const sql = `
      SELECT c.id, c.author_name, c.author_email, c.content, c.status,
             c.created_at, p.title as post_title
      FROM comments c
      LEFT JOIN posts p ON c.post_id = p.id
      ${whereSql}
      ORDER BY c.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const comments = await db.query(sql, [...params, per_page, offset]);

    res.json({
      comments,
      total,
      page: parseInt(page),
      per_page: parseInt(per_page),
    });
  } catch (error) {
    console.error('Get comments error:', error);
    res.status(500).json({
      success: false,
      message: '获取评论列表失败',
    });
  }
});

/**
 * 获取评论统计
 */
router.get('/stats', async (req, res) => {
  try {
    const [stats] = await db.query(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END) as spam
      FROM comments
    `);

    res.json(stats[0] || { total: 0, approved: 0, pending: 0, spam: 0 });
  } catch (error) {
    console.error('Comment stats error:', error);
    res.status(500).json({
      success: false,
      message: '获取评论统计失败',
    });
  }
});

/**
 * 更新评论状态
 */
router.put('/comments/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { status } = req.body;
    const userRole = req.user.role;

    if (!status) {
      return res.status(400).json({
        success: false,
        message: '状态不能为空',
      });
    }

    // 管理员可以修改任意评论
    let whereClause = 'id = ?';
    let params = [id];

    if (userRole !== 'administrator') {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    await db.query('UPDATE comments SET status = ? WHERE id = ?', [status, id]);

    res.json({ success: true });
  } catch (error) {
    console.error('Update comment error:', error);
    res.status(500).json({
      success: false,
      message: '更新评论失败',
    });
  }
});

/**
 * 删除评论
 */
router.delete('/comments/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const userRole = req.user.role;

    if (userRole !== 'administrator') {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    await db.query('DELETE FROM comments WHERE id = ?', [id]);

    res.json({ success: true });
  } catch (error) {
    console.error('Delete comment error:', error);
    res.status(500).json({
      success: false,
      message: '删除评论失败',
    });
  }
});

/**
 * 批量操作评论
 */
router.post('/comments/bulk', authMiddleware, async (req, res) => {
  try {
    const { action, ids } = req.body;
    const userRole = req.user.role;

    if (!action || !Array.isArray(ids) || ids.length === 0) {
      return res.status(400).json({
        success: false,
        message: '参数错误',
      });
    }

    if (userRole !== 'administrator') {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    const placeholders = ids.map(() => '?').join(',');
    await db.query(`UPDATE comments SET status = ? WHERE id IN (${placeholders})`, [action, ...ids]);

    res.json({ success: true, processed: ids.length });
  } catch (error) {
    console.error('Bulk comment action error:', error);
    res.status(500).json({
      success: false,
      message: '批量操作失败',
    });
  }
});

module.exports = router;
