/**
 * 文章相关路由
 */

const express = require('express');
const router = express.Router();
const { authMiddleware, optionalAuth } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取文章列表
 */
router.get('/', async (req, res) => {
  try {
    const {
      page = 1,
      per_page = 20,
      status,
      search,
      author,
    } = req.query;

    let whereClause = [];
    let params = [];

    // 状态过滤
    if (status) {
      whereClause.push('p.status = ?');
      params.push(status);
    }

    // 作者过滤
    if (author) {
      whereClause.push('p.author_id = ?');
      params.push(author);
    }

    // 搜索
    if (search) {
      whereClause.push('(p.title LIKE ? OR p.content LIKE ?)');
      params.push(`%${search}%`, `%${search}%`);
    }

    const whereSql = whereClause.length > 0 ? 'WHERE ' + whereClause.join(' AND ') : '';
    const offset = (page - 1) * per_page;

    // 获取文章总数
    const countSql = `SELECT COUNT(*) as total FROM posts p ${whereSql}`;
    const countResult = await db.queryOne(countSql, params);
    const total = countResult ? countResult.total : 0;

    // 获取文章列表
    const sql = `
      SELECT p.id, p.title, p.content, p.excerpt, p.featured_image,
             p.author_id, u.display_name as author,
             p.status, p.views, p.comment_status,
             p.created_at, p.updated_at,
             (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.status = 'approved') as comments_count
      FROM posts p
      LEFT JOIN users u ON p.author_id = u.id
      ${whereSql}
      ORDER BY p.created_at DESC
      LIMIT ? OFFSET ?
    `;

    const posts = await db.query(sql, [...params, per_page, offset]);

    // 获取分类
    for (const post of posts) {
      const categories = await db.query(
        'SELECT c.name FROM categories c ' +
        'JOIN post_categories pc ON c.id = pc.category_id ' +
        'WHERE pc.post_id = ?',
        [post.id]
      );
      post.categories = categories.map(c => c.name);
    }

    res.json({
      posts,
      total,
      page: parseInt(page),
      per_page: parseInt(per_page),
    });
  } catch (error) {
    console.error('Get posts error:', error);
    res.status(500).json({
      success: false,
      message: '获取文章列表失败',
    });
  }
});

/**
 * 获取单篇文章
 */
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;

    const sql = `
      SELECT p.id, p.title, p.content, p.excerpt, p.featured_image,
             p.author_id, u.display_name as author,
             p.status, p.views, p.comment_status,
             p.created_at, p.updated_at
      FROM posts p
      LEFT JOIN users u ON p.author_id = u.id
      WHERE p.id = ?
    `;

    const post = await db.queryOne(sql, [id]);

    if (!post) {
      return res.status(404).json({
        success: false,
        message: '文章不存在',
      });
    }

    // 获取分类
    const categories = await db.query(
      'SELECT c.name FROM categories c ' +
      'JOIN post_categories pc ON c.id = pc.category_id ' +
      'WHERE pc.post_id = ?',
      [id]
    );
    post.categories = categories.map(c => c.name);

    res.json(post);
  } catch (error) {
    console.error('Get post error:', error);
    res.status(500).json({
      success: false,
      message: '获取文章失败',
    });
  }
});

/**
 * 创建文章
 */
router.post('/', authMiddleware, async (req, res) => {
  try {
    const { title, content, excerpt, featured_image, status = 'draft', categories = [] } = req.body;
    const userId = req.user.userId;

    if (!title || !content) {
      return res.status(400).json({
        success: false,
        message: '标题和内容不能为空',
      });
    }

    const postId = await db.insert(
      'INSERT INTO posts (title, content, excerpt, featured_image, author_id, status) VALUES (?, ?, ?, ?, ?, ?)',
      [title, content, excerpt || null, featured_image || null, userId, status]
    );

    // 添加分类关联
    for (const categoryName of categories) {
      let categoryId = await db.queryOne('SELECT id FROM categories WHERE name = ?', [categoryName]);
      if (!categoryId) {
        categoryId = await db.insert('INSERT INTO categories (name, slug) VALUES (?, ?)', [categoryName, categoryName.toLowerCase()]);
        categoryId = { id: categoryId };
      }
      await db.insert('INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)', [postId, categoryId.id]);
    }

    // 返回新创建的文章
    const newPost = await db.queryOne('SELECT * FROM posts WHERE id = ?', [postId]);
    res.json(newPost);
  } catch (error) {
    console.error('Create post error:', error);
    res.status(500).json({
      success: false,
      message: '创建文章失败',
    });
  }
});

/**
 * 更新文章
 */
router.put('/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { title, content, excerpt, featured_image, status, categories } = req.body;
    const userId = req.user.userId;

    // 检查文章是否存在且属于当前用户
    const existingPost = await db.queryOne(
      'SELECT * FROM posts WHERE id = ? AND author_id = ?',
      [id, userId]
    );

    if (!existingPost) {
      return res.status(404).json({
        success: false,
        message: '文章不存在或无权限修改',
      });
    }

    // 更新文章
    const updates = [];
    const values = [];

    if (title) {
      updates.push('title = ?');
      values.push(title);
    }
    if (content !== undefined) {
      updates.push('content = ?');
      values.push(content);
    }
    if (excerpt !== undefined) {
      updates.push('excerpt = ?');
      values.push(excerpt || null);
    }
    if (featured_image !== undefined) {
      updates.push('featured_image = ?');
      values.push(featured_image || null);
    }
    if (status) {
      updates.push('status = ?');
      values.push(status);
    }

    if (updates.length > 0) {
      const sql = `UPDATE posts SET ${updates.join(', ')} WHERE id = ? AND author_id = ?`;
      await db.query(sql, [...values, id, userId]);
    }

    // 更新分类
    if (categories) {
      await db.query('DELETE FROM post_categories WHERE post_id = ?', [id]);
      for (const categoryName of categories) {
        let categoryId = await db.queryOne('SELECT id FROM categories WHERE name = ?', [categoryName]);
        if (!categoryId) {
          categoryId = await db.insert('INSERT INTO categories (name, slug) VALUES (?, ?)', [categoryName, categoryName.toLowerCase()]);
          categoryId = { id: categoryId };
        }
        await db.insert('INSERT INTO post_categories (post_id, category_id) VALUES (?, ?)', [id, categoryId.id]);
      }
    }

    res.json({ success: true });
  } catch (error) {
    console.error('Update post error:', error);
    res.status(500).json({
      success: false,
      message: '更新文章失败',
    });
  }
});

/**
 * 删除文章
 */
router.delete('/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const userId = req.user.userId;
    const userRole = req.user.role;

    // 管理员可以删除任意文章，作者只能删除自己的文章
    let whereClause = 'id = ? AND author_id = ?';
    let params = [id, userId];

    if (userRole === 'administrator') {
      whereClause = 'id = ?';
      params = [id];
    }

    await db.query(`DELETE FROM posts WHERE ${whereClause}`, params);
    res.json({ success: true });
  } catch (error) {
    console.error('Delete post error:', error);
    res.status(500).json({
      success: false,
      message: '删除文章失败',
    });
  }
});

/**
 * 获取分类列表
 */
router.get('/categories', async (req, res) => {
  try {
    const categories = await db.query('SELECT * FROM categories ORDER BY name');
    res.json(categories);
  } catch (error) {
    console.error('Get categories error:', error);
    res.status(500).json({
      success: false,
      message: '获取分类失败',
    });
  }
});

/**
 * AI 优化文章
 */
router.post('/:id/optimize', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { content } = req.body;

    // 模拟 AI 优化
    const optimizedContent = content + '\n\n[AI优化：文章已优化，增加了结构化输出和段落划分]';

    res.json({
      success: true,
      optimized: optimizedContent,
      suggestions: [
        '建议添加更多示例代码',
        '可以增加相关文章链接',
        '建议添加 SEO 关键词',
      ],
    });
  } catch (error) {
    console.error('AI optimize error:', error);
    res.status(500).json({
      success: false,
      message: 'AI 优化失败',
    });
  }
});

module.exports = router;
