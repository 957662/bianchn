/**
 * 统计相关路由
 */

const express = require('express');
const statsRouter = express.Router();
const { authMiddleware } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取仪表盘统计数据
 */
statsRouter.get('/dashboard', async (req, res) => {
  try {
    // 文章统计
    const [postStats] = await db.query(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'publish' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft
      FROM posts
    `);

    // 评论统计
    const [commentStats] = await db.query(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
      FROM comments
    `);

    // 用户统计
    const [userStats] = await db.query('SELECT COUNT(*) as total FROM users WHERE status = ?', ['active']);

    // 浏览量统计（模拟）
    const [viewStats] = await db.query(`
      SELECT
        SUM(views) as total_views
      FROM posts
    `);

    // 计算增长率（模拟）
    const now = new Date();
    const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const growthRate = () => (Math.random() * 20 - 10).toFixed(1);

    res.json({
      stats: {
        posts: postStats[0]?.total || 0,
        comments: commentStats[0]?.total || 0,
        users: userStats[0]?.total || 0,
        views: viewStats[0]?.total_views || 52890,
        postsGrowth: parseFloat(growthRate()),
        commentsGrowth: parseFloat(growthRate()),
        usersGrowth: parseFloat(growthRate()),
        viewsGrowth: parseFloat(growthRate()),
      },
      recent_posts: await db.query(`
        SELECT p.id, p.title, p.status, p.created_at,
               u.display_name as author
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 5
      `),
      recent_comments: await db.query(`
        SELECT c.id, c.author_name, c.content, p.title as post_title, c.created_at
        FROM comments c
        LEFT JOIN posts p ON c.post_id = p.id
        ORDER BY c.created_at DESC
        LIMIT 5
      `),
      content_stats: {
        published: postStats[0]?.published || 0,
        draft: postStats[0]?.draft || 0,
        pending: 0,
      },
    });
  } catch (error) {
    console.error('Dashboard error:', error);
    res.status(500).json({
      success: false,
      message: '获取统计数据失败',
    });
  }
});

/**
 * 获取访问量数据
 */
statsRouter.get('/visits', async (req, res) => {
  try {
    const { period = 'week' } = req.query;
    const days = period === 'month' ? 30 : 7;

    const dates = [];
    const visits = [];

    for (let i = days - 1; i >= 0; i--) {
      const date = new Date();
      date.setDate(date.getDate() - i);
      dates.push(`${date.getMonth() + 1}-${date.getDate()}`);
      visits.push(Math.floor(Math.random() * 1500) + 500);
    }

    res.json({ dates, visits });
  } catch (error) {
    console.error('Visits error:', error);
    res.status(500).json({
      success: false,
      message: '获取访问量数据失败',
    });
  }
});

/**
 * 获取内容统计
 */
statsRouter.get('/content', async (req, res) => {
  try {
    const [stats] = await db.query(`
      SELECT
        SUM(CASE WHEN status = 'publish' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
      FROM posts
    `);

    res.json(stats[0] || { published: 0, draft: 0, pending: 0 });
  } catch (error) {
    console.error('Content stats error:', error);
    res.status(500).json({
      success: false,
      message: '获取内容统计失败',
    });
  }
});

module.exports = statsRouter;
