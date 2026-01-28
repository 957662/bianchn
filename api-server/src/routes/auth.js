/**
 * 认证相关路由
 */

const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const db = require('../models/database');
const config = require('../config/config');

/**
 * 用户登录
 */
router.post('/token', async (req, res) => {
  try {
    const { username, password } = req.body;

    if (!username || !password) {
      return res.status(400).json({
        success: false,
        message: '用户名和密码不能为空',
      });
    }

    // 查询用户
    const user = await db.queryOne(
      'SELECT id, username, email, password, display_name, role, avatar_url, status FROM users WHERE username = ? OR email = ?',
      [username, username]
    );

    if (!user) {
      return res.status(401).json({
        success: false,
        message: '用户名或密码错误',
      });
    }

    // 检查用户状态
    if (user.status !== 'active') {
      return res.status(403).json({
        success: false,
        message: user.status === 'banned' ? '用户已被封禁' : '用户未激活',
      });
    }

    // 验证密码
    const isPasswordValid = await bcrypt.compare(password, user.password);
    if (!isPasswordValid) {
      return res.status(401).json({
        success: false,
        message: '用户名或密码错误',
      });
    }

    // 生成 JWT token
    const token = jwt.sign(
      { userId: user.id, username: user.username, role: user.role },
      config.jwt.secret,
      { expiresIn: config.jwt.expiresIn }
    );

    res.json({
      success: true,
      token,
      nonce: Buffer.from(Date.now().toString()).toString('base64'),
      user: {
        id: user.id,
        name: user.display_name || user.username,
        email: user.email,
        roles: [user.role],
        avatar_urls: {
          '96': user.avatar_url || '',
        },
      },
    });
  } catch (error) {
    console.error('Login error:', error);
    res.status(500).json({
      success: false,
      message: '登录失败',
    });
  }
});

/**
 * 验证 token
 */
router.post('/token/validate', async (req, res) => {
  try {
    const authHeader = req.headers.authorization;
    const token = authHeader && authHeader.startsWith('Bearer ')
      ? authHeader.substring(7)
      : null;

    if (!token) {
      return res.status(401).json({
        success: false,
        message: 'Token 不存在',
      });
    }

    jwt.verify(token, config.jwt.secret, (err, decoded) => {
      if (err) {
        return res.status(401).json({
          success: false,
          message: 'Token 无效',
        });
      }
      res.json({ success: true, valid: true });
    });
  } catch (error) {
    res.status(500).json({
      success: false,
      message: '验证失败',
    });
  }
});

module.exports = router;
