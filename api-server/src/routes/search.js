/**
 * 搜索相关路由
 */

const express = require('express');
const router = express.Router();
const { optionalAuth } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 搜索接口
 */
router.get('/search', async (req, res) => {
  try {
    const { q, limit = 10 } = req.query;

    if (!q || q.length < 2) {
      return res.status(400).json({
        success: false,
        message: '搜索关键词不能少于2个字符',
      });
    }

    const searchPattern = `%${q}%`;

    // 搜索文章
    const posts = await db.query(
      `SELECT id, title, 'excerpt as excerpt, 'published' as type, created_at
       FROM posts
       WHERE (title LIKE ? OR content LIKE ?) AND status = 'publish'
       ORDER BY created_at DESC
       LIMIT ?
      `,
      [searchPattern, searchPattern, limit]
    );

    // 搜索模型
    const models = await db.query(
      `SELECT id, title, 'published' as type, created_at
       FROM models
       WHERE (title LIKE ? OR description LIKE ?) AND status = 'published'
       ORDER BY created_at DESC
       LIMIT ?
      `,
      [searchPattern, searchPattern, limit]
    );

    const results = [
      ...posts.map(p => ({ ...p, url: `/posts/${p.id}` })),
      ...models.map(m => ({ ...m, url: `/gallery/models/${m.id}` })),
    ];

    res.json(results);
  } catch (error) {
    console.error('Search error:', error);
    res.status(500).json({ success: false, message: '搜索失败' });
  }
});

/**
 * 获取搜索统计
 */
router.get('/analytics/stats', async (req, res) => {
  try {
    const { days = 30 } = req.query;

    // 模拟数据
    const totalSearches = Math.floor(Math.random() * 500) + 1000;
    const uniqueSearchers = Math.floor(totalSearches / 3);
    const avgResults = (Math.random() * 10 + 2).toFixed(1);

    const popularTerms = [
      { term: 'Vue', count: Math.floor(Math.random() * 200) + 50 },
      { term: 'WordPress', count: Math.floor(Math.random() * 200) + 50 },
      { term: '3D', count: Math.floor(Math.random() * 200) + 50 },
      { term: 'AI', count: Math.floor(Math.random() * 200) + 50 },
      { term: 'TypeScript', count: Math.floor(Math.random() * 200) + 50 },
    ];

    res.json({
      total_searches,
      unique_searchers,
      avg_results: parseFloat(avgResults),
      popular_terms: popularTerms,
    });
  } catch (error) {
    console.error('Search stats error:', error);
    res.status(500).json({ success: false, message: '获取搜索统计失败' });
  }
});

/**
 * 获取热门搜索
 */
router.get('/popular', async (req, res) => {
  try {
    const { limit = 10 } = req.query;

    const popularTerms = [
      { term: 'Vue', count: Math.floor(Math.random() * 200) + 50 },
      { term: 'WordPress', count: Math.floor(Math.random() * 200) + 50 },
      { term: '3D', count: Math.floor(Math.random() * 200) + 50 },
      { term: 'AI', count: Math.floor(Math.random() * 200) + 50 },
      { term: 'TypeScript', count: Math.floor(Math.random() * 200) + 50 },
    ];

    res.json(popularTerms.slice(0, limit));
  } catch (error) {
    console.error('Popular terms error:', error);
    res.status(500).json({ success: false, message: '获取热门搜索失败' });
  }
});

/**
 * 获取最近搜索
 */
router.get('/recent', async (req, res) => {
  try {
    const { limit = 20 } = req.query;

    const recentSearches = [
      { term: 'Vue', time: `${Math.floor(Math.random() * 10) + 5}分钟前` },
      { term: '3D', time: `${Math.floor(Math.random() * 10) + 5}分钟前` },
      { term: 'AI', time: `${Math.floor(Math.random() * 10) + 5}分钟前` },
      { term: 'TypeScript', time: `${Math.floor(Math.random() * 10) + 5}分钟前` },
      { term: 'WordPress', time: `${Math.floor(Math.random() * 10) + 5}分钟前` },
    ];

    res.json(recentSearches.slice(0, limit));
  } catch (error) {
    console.error('Recent searches error:', error);
    res.status(500).json({ success: false, message: '获取最近搜索失败' });
  }
});

module.exports = router;
