/**
 * 用户相关路由
 */

const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { authMiddleware } = require('../middleware/auth');
const db = require('../models/database');

/**
 * 获取用户列表
 */
router.get('/users', authMiddleware, async (req, res) => {
  try {
    const { page = 1, per_page = 20, search } = req.query;
    const userRole = req.user.role;

    // 管理员可以查看所有用户
    let whereClause = '';
    let params = [];

    if (userRole !== 'administrator') {
      whereClause = 'WHERE role != ?';
      params.push('administrator');
    }

    // 搜索
    if (search) {
      whereClause += (whereClause ? ' AND ' : 'WHERE ') + '(username LIKE ? OR email LIKE ?)';
      params.push(`%${search}%`, `%${search}%`);
    }

    const offset = (page - 1) * per_page;

    // 获取用户列表
    const users = await db.query(
      `SELECT id, username, email, display_name, avatar_url, role, status, created_at
       FROM users
       ${whereClause}
       ORDER BY created_at DESC
       LIMIT ? OFFSET ?
      `,
      [...params, per_page, offset]
    );

    // 获取总数
    const countSql = `SELECT COUNT(*) as total FROM users ${whereClause}`;
    const countResult = await db.queryOne(countSql, params);
    const total = countResult ? countResult.total : 0;

    res.json({
      users,
      total,
      page: parseInt(page),
      per_page: parseInt(per_page),
    });
  } catch (error) {
    console.error('Get users error:', error);
    res.status(500).json({
      success: false,
      message: '获取用户列表失败',
    });
  }
});

/**
 * 获取当前用户信息
 */
router.get('/me', authMiddleware, async (req, res) => {
  try {
    const user = await db.queryOne(
      'SELECT id, username, email, display_name, avatar_url, role, status FROM users WHERE id = ?',
      [req.user.userId]
    );

    if (!user) {
      return res.status(404).json({
        success: false,
        message: '用户不存在',
      });
    }

    res.json(user);
  } catch (error) {
    console.error('Get current user error:', error);
    res.status(500).json({
      success: false,
      message: '获取用户信息失败',
    });
  }
});

/**
 * 获取单个用户
 */
router.get('/users/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const requestingRole = req.user.role;

    // 管理员可以查看任意用户
    let whereClause = 'WHERE u.id = ?';
    let params = [id];

    if (requestingRole !== 'administrator') {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    const user = await db.queryOne(
      `SELECT u.id, u.username, u.email, u.display_name, u.avatar_url, u.role, u.status, u.created_at
       FROM users u
       ${whereClause}
      `,
      params
    );

    if (!user) {
      return res.status(404).json({
        success: false,
        message: '用户不存在',
      });
    }

    res.json(user);
  } catch (error) {
    console.error('Get user error:', error);
    res.status(500).json({
      success: false,
      message: '获取用户失败',
    });
  }
});

/**
 * 创建用户
 */
router.post('/users', authMiddleware, async (req, res) => {
  try {
    const { name, email, password, role = 'subscriber' } = req.body;
    const requestingRole = req.user.role;

    // 只有管理员可以创建用户
    if (requestingRole !== 'administrator') {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    if (!name || !email || !password) {
      return res.status(400).json({
        success: false,
        message: '必填字段不能为空',
      });
    }

    // 检查邮箱是否已存在
    const existingUser = await db.queryOne(
      'SELECT id FROM users WHERE email = ? OR username = ?',
      [email, name]
    );

    if (existingUser) {
      return res.status(409).json({
        success: false,
        message: '用户名或邮箱已存在',
      });
    }

    // 加密密码
    const hashedPassword = await bcrypt.hash(password, 10);

    // 创建用户
    const userId = await db.insert(
      'INSERT INTO users (username, email, password, display_name, role, status) VALUES (?, ?, ?, ?, ?, ?)',
      [name, email, hashedPassword, name, role, 'active']
    );

    res.json({ success: true, user: { id: userId, username: name, email } });
  } catch (error) {
    console.error('Create user error:', error);
    res.status(500).json({
      success: false,
      message: '创建用户失败',
    });
  }
});

/**
 * 更新用户
 */
router.put('/users/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { display_name, avatar_url, role, status } = req.body;
    const requestingRole = req.user.role;

    // 只有管理员或用户本人可以更新
    if (requestingRole !== 'administrator' && req.user.userId !== parseInt(id)) {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    const updates = [];
    const values = [];

    if (display_name) {
      updates.push('display_name = ?');
      values.push(display_name);
    }
    if (avatar_url) {
      updates.push('avatar_url = ?');
      values.push(avatar_url);
    }
    if (role && requestingRole === 'administrator') {
      updates.push('role = ?');
      values.push(role);
    }
    if (status && requestingRole === 'administrator') {
      updates.push('status = ?');
      values.push(status);
    }

    if (updates.length > 0) {
      values.push(id);
      await db.query(
        `UPDATE users SET ${updates.join(', ')} WHERE id = ?`,
        values
      );
    }

    res.json({ success: true });
  } catch (error) {
    console.error('Update user error:', error);
    res.status(500).json({
      success: false,
      message: '更新用户失败',
    });
  }
});

/**
 * 删除用户
 */
router.delete('/users/:id', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const requestingRole = req.user.role;

    // 只有管理员或用户本人可以删除
    if (requestingRole !== 'administrator' && req.user.userId !== parseInt(id)) {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    await db.query('DELETE FROM users WHERE id = ?', [id]);

    res.json({ success: true });
  } catch (error) {
    console.error('Delete user error:', error);
    res.status(500).json({
      success: false,
      message: '删除用户失败',
    });
  }
});

/**
 * 重置密码
 */
router.post('/users/:id/reset-password', authMiddleware, async (req, res) => {
  try {
    const { id } = req.params;
    const { new_password } = req.body;
    const requestingRole = req.user.role;

    // 只有管理员可以重置他人密码
    if (requestingRole !== 'administrator' && req.user.userId !== parseInt(id)) {
      return res.status(403).json({
        success: false,
        message: '权限不足',
      });
    }

    if (!new_password || new_password.length < 6) {
      return res.status(400).json({
        success: false,
        message: '密码长度不能少于6位',
      });
    }

    // 加密新密码
    const hashedPassword = await bcrypt.hash(new_password, 10);

    await db.query('UPDATE users SET password = ? WHERE id = ?', [hashedPassword, id]);

    res.json({ success: true });
  } catch (error) {
    console.error('Reset password error:', error);
    res.status(500).json({
      success: false,
      message: '重置密码失败',
    });
  }
});

module.exports = router;
