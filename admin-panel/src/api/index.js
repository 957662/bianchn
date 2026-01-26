import apiClient from './axios';

// 认证API
export const authAPI = {
  // 登录
  login(username, password) {
    return apiClient.post('/jwt-auth/v1/token', {
      username,
      password,
    });
  },

  // 验证token
  validateToken(token) {
    return apiClient.post(
      '/jwt-auth/v1/token/validate',
      {},
      {
        headers: { Authorization: `Bearer ${token}` },
      }
    );
  },

  // 获取当前用户信息
  getCurrentUser() {
    return apiClient.get('/wp/v2/users/me');
  },
};

// 文章API
export const postsAPI = {
  // 获取文章列表
  getPosts(params = {}) {
    return apiClient.get('/wp/v2/posts', { params });
  },

  // 获取单篇文章
  getPost(id) {
    return apiClient.get(`/wp/v2/posts/${id}`);
  },

  // 创建文章
  createPost(data) {
    return apiClient.post('/wp/v2/posts', data);
  },

  // 更新文章
  updatePost(id, data) {
    return apiClient.put(`/wp/v2/posts/${id}`, data);
  },

  // 删除文章
  deletePost(id) {
    return apiClient.delete(`/wp/v2/posts/${id}`);
  },

  // AI优化文章
  optimizeWithAI(postId, content) {
    return apiClient.post('/xiaowu-ai/v1/optimize-article', {
      post_id: postId,
      content,
    });
  },

  // 获取分类列表
  getCategories() {
    return apiClient.get('/wp/v2/categories');
  },

  // 获取标签列表
  getTags() {
    return apiClient.get('/wp/v2/tags');
  },

  // 获取作者列表
  getAuthors() {
    return apiClient.get('/wp/v2/users?who=authors');
  },
};

// 评论API
export const commentsAPI = {
  // 获取评论列表
  getComments(params = {}) {
    return apiClient.get('/xiaowu-comments/v1/comments', { params });
  },

  // 获取评论统计
  getStats() {
    return apiClient.get('/xiaowu-comments/v1/stats');
  },

  // AI检测垃圾评论
  analyzeSpam(id) {
    return apiClient.post(`/xiaowu-comments/v1/comments/${id}/analyze-spam`);
  },

  // 审核评论
  updateComment(id, data) {
    return apiClient.put(`/xiaowu-comments/v1/comments/${id}`, data);
  },

  // 删除评论
  deleteComment(id) {
    return apiClient.delete(`/xiaowu-comments/v1/comments/${id}`);
  },

  // 批量操作
  bulkAction(action, ids) {
    return apiClient.post('/xiaowu-comments/v1/comments/bulk', {
      action,
      ids,
    });
  },
};

// 用户API
export const usersAPI = {
  // 获取用户列表
  getUsers(params = {}) {
    return apiClient.get('/xiaowu-user/v1/users', { params });
  },

  // 获取用户详情
  getUser(id) {
    return apiClient.get(`/xiaowu-user/v1/users/${id}`);
  },

  // 创建用户
  createUser(data) {
    return apiClient.post('/xiaowu-user/v1/users', data);
  },

  // 更新用户
  updateUser(id, data) {
    return apiClient.put(`/xiaowu-user/v1/users/${id}`, data);
  },

  // 删除用户
  deleteUser(id) {
    return apiClient.delete(`/xiaowu-user/v1/users/${id}`);
  },

  // 重置密码
  resetPassword(id, newPassword) {
    return apiClient.post(`/xiaowu-user/v1/users/${id}/reset-password`, {
      new_password: newPassword,
    });
  },
};

// 3D图库API
export const galleryAPI = {
  // 获取模型列表
  getModels(params = {}) {
    return apiClient.get('/xiaowu-3d-gallery/v1/models', { params });
  },

  // 获取模型详情
  getModel(id) {
    return apiClient.get(`/xiaowu-3d-gallery/v1/models/${id}`);
  },

  // 获取分类
  getCategories() {
    return apiClient.get('/xiaowu-3d-gallery/v1/categories');
  },

  // 上传模型
  uploadModel(formData) {
    return apiClient.post('/xiaowu-3d-gallery/v1/models/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  // 更新模型信息
  updateModel(id, data) {
    return apiClient.put(`/xiaowu-3d-gallery/v1/models/${id}`, data);
  },

  // 删除模型
  deleteModel(id) {
    return apiClient.delete(`/xiaowu-3d-gallery/v1/models/${id}`);
  },
};

// 搜索API
export const searchAPI = {
  // 执行搜索
  search(query, params = {}) {
    return apiClient.get('/xiaowu-search/v1/search', {
      params: { q: query, ...params },
    });
  },

  // 获取搜索统计
  getStats(days = 30) {
    return apiClient.get('/xiaowu-search/v1/analytics/stats', {
      params: { days },
    });
  },

  // 获取热门搜索
  getPopular(limit = 10) {
    return apiClient.get('/xiaowu-search/v1/popular', {
      params: { limit },
    });
  },

  // 获取最近搜索
  getRecentSearches(limit = 20) {
    return apiClient.get('/xiaowu-search/v1/recent', {
      params: { limit },
    });
  },

  // 重建索引
  rebuildIndex(type = 'all') {
    return apiClient.post('/xiaowu-search/v1/index/rebuild', { type });
  },

  // 优化索引
  optimizeIndex() {
    return apiClient.post('/xiaowu-search/v1/index/optimize');
  },

  // 保存配置
  saveConfig(data) {
    return apiClient.post('/xiaowu-search/v1/settings', data);
  },
};

// AI服务API
export const aiAPI = {
  // 获取AI设置
  getSettings() {
    return apiClient.get('/xiaowu-ai/v1/settings');
  },

  // 保存AI设置
  saveSettings(data) {
    return apiClient.post('/xiaowu-ai/v1/settings', data);
  },

  // 测试AI连接
  testConnection(provider, apiKey) {
    return apiClient.post('/xiaowu-ai/v1/test-connection', {
      provider,
      api_key: apiKey,
    });
  },

  // 生成文章大纲
  generateOutline(topic) {
    return apiClient.post('/xiaowu-ai/v1/generate-outline', { topic });
  },

  // 优化文章内容
  optimizeContent(postId, content) {
    return apiClient.post('/xiaowu-ai/v1/optimize-article', {
      post_id: postId,
      content,
    });
  },

  // 生成图片
  generateImage(prompt, options = {}) {
    return apiClient.post('/xiaowu-ai/v1/generate-image', {
      prompt,
      ...options,
    });
  },

  // 智能推荐
  getRecommendations(postId) {
    return apiClient.get(`/xiaowu-ai/v1/recommendations/${postId}`);
  },

  // 获取使用统计
  getUsage() {
    return apiClient.get('/xiaowu-ai/v1/usage');
  },

  // 获取使用趋势
  getUsageTrend(days = 7) {
    return apiClient.get('/xiaowu-ai/v1/usage/trend', {
      params: { days },
    });
  },

  // 获取任务列表
  getTasks(limit = 20) {
    return apiClient.get('/xiaowu-ai/v1/tasks', {
      params: { limit },
    });
  },
};

// 统计API
export const statsAPI = {
  // 获取仪表盘统计
  getDashboardStats() {
    return apiClient.get('/xiaowu/v1/stats/dashboard');
  },

  // 获取访问统计
  getVisitStats(days = 30) {
    return apiClient.get('/xiaowu/v1/stats/visits', {
      params: { days },
    });
  },

  // 获取内容统计
  getContentStats() {
    return apiClient.get('/xiaowu/v1/stats/content');
  },
};

// 媒体API
export const mediaAPI = {
  // 上传媒体文件
  uploadMedia(formData) {
    return apiClient.post('/wp/v2/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
  },

  // 获取媒体列表
  getMedia(params = {}) {
    return apiClient.get('/wp/v2/media', { params });
  },

  // 删除媒体
  deleteMedia(id) {
    return apiClient.delete(`/wp/v2/media/${id}`, {
      params: { force: true },
    });
  },
};

// 系统设置API
export const settingsAPI = {
  // 获取设置
  getSettings() {
    return apiClient.get('/xiaowu/v1/settings');
  },

  // 保存基本设置
  saveBasic(data) {
    return apiClient.post('/xiaowu/v1/settings/basic', data);
  },

  // 保存评论设置
  saveComments(data) {
    return apiClient.post('/xiaowu/v1/settings/comments', data);
  },

  // 保存媒体设置
  saveMedia(data) {
    return apiClient.post('/xiaowu/v1/settings/media', data);
  },

  // 保存性能设置
  savePerformance(data) {
    return apiClient.post('/xiaowu/v1/settings/performance', data);
  },

  // 保存安全设置
  saveSecurity(data) {
    return apiClient.post('/xiaowu/v1/settings/security', data);
  },

  // 保存邮件设置
  saveEmail(data) {
    return apiClient.post('/xiaowu/v1/settings/email', data);
  },

  // 清除缓存
  clearCache() {
    return apiClient.post('/xiaowu/v1/settings/cache/clear');
  },

  // 发送测试邮件
  sendTestEmail(email) {
    return apiClient.post('/xiaowu/v1/settings/email/test', { email });
  },
};

// 部署向导API
export const deploymentAPI = {
  // 检查环境
  checkEnvironment() {
    return apiClient.get('/xiaowu/v1/deployment/environment');
  },

  // 测试数据库连接
  testDB(config) {
    return apiClient.post('/xiaowu/v1/deployment/test-db', config);
  },

  // 测试AI连接
  testAI(config) {
    return apiClient.post('/xiaowu/v1/deployment/test-ai', config);
  },

  // 测试邮件发送
  testEmail(config) {
    return apiClient.post('/xiaowu/v1/deployment/test-email', config);
  },

  // 保存配置
  saveConfig(type, data) {
    return apiClient.post(`/xiaowu/v1/deployment/save/${type}`, data);
  },

  // 生成配置文件
  generateConfig(data) {
    return apiClient.post('/xiaowu/v1/deployment/generate', data);
  },

  // 应用配置
  applyConfig() {
    return apiClient.post('/xiaowu/v1/deployment/apply');
  },

  // 标记部署完成
  markCompleted() {
    return apiClient.post('/xiaowu/v1/deployment/complete');
  },
};

export default {
  auth: authAPI,
  posts: postsAPI,
  comments: commentsAPI,
  users: usersAPI,
  gallery: galleryAPI,
  search: searchAPI,
  ai: aiAPI,
  stats: statsAPI,
  media: mediaAPI,
  settings: settingsAPI,
  deployment: deploymentAPI,
};
