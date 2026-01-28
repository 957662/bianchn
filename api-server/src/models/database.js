/**
 * 数据库连接池和操作
 */

const mysql = require('mysql2/promise');
const config = require('../config/config');

// 创建连接池
const pool = mysql.createPool(config.mysql);

/**
 * 执行查询
 * @param {string} sql SQL语句
 * @param {Array} params 参数
 * @returns {Promise<Array>}
 */
async function query(sql, params = []) {
  try {
    const [rows] = await pool.execute(sql, params);
    return rows;
  } catch (error) {
    console.error('Database query error:', error);
    throw error;
  }
}

/**
 * 执行查询并返回单行
 * @param {string} sql SQL语句
 * @param {Array} params 参数
 * @returns {Promise<Object|null>}
 */
async function queryOne(sql, params = []) {
  try {
    const [rows] = await pool.execute(sql, params);
    return rows.length > 0 ? rows[0] : null;
  } catch (error) {
    console.error('Database query error:', error);
    throw error;
  }
}

/**
 * 执行插入并返回ID
 * @param {string} sql SQL语句
 * @param {Array} params 参数
 * @returns {Promise<number>} 插入的ID
 */
async function insert(sql, params = []) {
  try {
    const [result] = await pool.execute(sql, params);
    return result.insertId;
  } catch (error) {
    console.error('Database insert error:', error);
    throw error;
  }
}

/**
 * 执行更新
 * @param {string} sql SQL语句
 * @param {Array} params 参数
 * @returns {Promise<number>} 影响的行数
 */
async function update(sql, params = []) {
  try {
    const [result] = await pool.execute(sql, params);
    return result.affectedRows;
  } catch (error) {
    console.error('Database update error:', error);
    throw error;
  }
}

/**
 * 执行删除
 * @param {string} sql SQL语句
 * @param {Array} params 参数
 * @returns {Promise<number>} 删除的行数
 */
async function del(sql, params = []) {
  try {
    const [result] = await pool.execute(sql, params);
    return result.affectedRows;
  } catch (error) {
    console.error('Database delete error:', error);
    throw error;
  }
}

/**
 * 初始化数据库表
 */
async function initDatabase() {
  console.log('Initializing database tables...');

  // 创建用户表
  await query(`
    CREATE TABLE IF NOT EXISTS users (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(100) NOT NULL UNIQUE,
      email VARCHAR(100) NOT NULL UNIQUE,
      password VARCHAR(255) NOT NULL,
      display_name VARCHAR(100),
      avatar_url VARCHAR(255),
      role ENUM('administrator', 'editor', 'author', 'subscriber') DEFAULT 'subscriber',
      status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_username (username),
      INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建文章表
  await query(`
    CREATE TABLE IF NOT EXISTS posts (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      content LONGTEXT,
      excerpt TEXT,
      featured_image VARCHAR(255),
      author_id INT,
      status ENUM('publish', 'draft', 'pending', 'private') DEFAULT 'draft',
      comment_status ENUM('open', 'closed') DEFAULT 'open',
      views INT DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
      INDEX idx_status (status),
      INDEX idx_author (author_id),
      INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建分类表
  await query(`
    CREATE TABLE IF NOT EXISTS categories (
      id INT AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(100) NOT NULL UNIQUE,
      slug VARCHAR(100) NOT NULL UNIQUE,
      description TEXT,
      parent_id INT DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建文章分类关联表
  await query(`
    CREATE TABLE IF NOT EXISTS post_categories (
      post_id INT NOT NULL,
      category_id INT NOT NULL,
      PRIMARY KEY (post_id, category_id),
      FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
      FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建评论表
  await query(`
    CREATE TABLE IF NOT EXISTS comments (
      id INT AUTO_INCREMENT PRIMARY KEY,
      post_id INT NOT NULL,
      author_name VARCHAR(100) NOT NULL,
      author_email VARCHAR(100),
      author_ip VARCHAR(45),
      content TEXT NOT NULL,
      status ENUM('approved', 'pending', 'spam', 'trash') DEFAULT 'pending',
      parent_id INT DEFAULT 0,
      user_id INT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
      INDEX idx_status (status),
      INDEX idx_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建3D模型表
  await query(`
    CREATE TABLE IF NOT EXISTS model_3d (
      id INT AUTO_INCREMENT PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      description TEXT,
      category VARCHAR(100),
      file_url VARCHAR(255) NOT NULL,
      thumbnail_url VARCHAR(255),
      file_size INT,
      author_id INT,
      status ENUM('published', 'draft', 'pending') DEFAULT 'draft',
      views INT DEFAULT 0,
      downloads INT DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
      INDEX idx_status (status),
      INDEX idx_author (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建媒体文件表
  await query(`
    CREATE TABLE IF NOT EXISTS media (
      id INT AUTO_INCREMENT PRIMARY KEY,
      filename VARCHAR(255) NOT NULL,
      original_name VARCHAR(255),
      mime_type VARCHAR(100) NOT NULL,
      file_size INT NOT NULL,
      file_path VARCHAR(500) NOT NULL,
      url VARCHAR(500) NOT NULL,
      uploader_id INT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL,
      INDEX idx_uploader (uploader_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建设置表
  await query(`
    CREATE TABLE IF NOT EXISTS settings (
      id INT AUTO_INCREMENT PRIMARY KEY,
      option_key VARCHAR(100) NOT NULL UNIQUE,
      option_value LONGTEXT,
      autoload BOOLEAN DEFAULT TRUE,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 创建统计表
  await query(`
    CREATE TABLE IF NOT EXISTS analytics (
      id INT AUTO_INCREMENT PRIMARY KEY,
      event_type VARCHAR(50) NOT NULL,
      event_data JSON,
      user_id INT,
      ip_address VARCHAR(45),
      user_agent TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
      INDEX idx_event_type (event_type),
      INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);

  // 插入默认管理员用户（如果不存在）
  const adminExists = await queryOne('SELECT id FROM users WHERE username = ?', ['admin']);
  if (!adminExists) {
    const bcrypt = require('bcryptjs');
    const hashedPassword = await bcrypt.hash('admin123', 10);

    await insert(
      'INSERT INTO users (username, email, password, display_name, role, status) VALUES (?, ?, ?, ?, ?, ?)',
      ['admin', 'admin@xiaowu.com', hashedPassword, '小伍同学', 'administrator', 'active']
    );
    console.log('Default admin user created (username: admin, password: admin123)');
  }

  // 插入默认分类
  const defaultCategories = ['技术', 'Vue', 'WordPress', 'AI', '3D', '教程', '生活'];
  for (const catName of defaultCategories) {
    const exists = await queryOne('SELECT id FROM categories WHERE name = ?', [catName]);
    if (!exists) {
      await insert('INSERT INTO categories (name, slug) VALUES (?, ?)', [catName, catName.toLowerCase()]);
    }
  }

  console.log('Database initialized successfully!');
}

module.exports = {
  pool,
  query,
  queryOne,
  insert,
  update,
  del,
  initDatabase,
};
