import apiClient from './axios'

// 认证API
export const authAPI = {
  // 登录
  login(username, password) {
    return apiClient.post('/jwt-auth/v1/token', {
      username,
      password
    })
  },

  // 验证token
  validateToken(token) {
    return apiClient.post('/jwt-auth/v1/token/validate', {}, {
      headers: { Authorization: `Bearer ${token}` }
    })
  },

  // 获取当前用户信息
  getCurrentUser() {
    return apiClient.get('/wp/v2/users/me')
  }
}

// 文章API
export const postsAPI = {
  // 获取文章列表
  getPosts(params = {}) {
    return apiClient.get('/wp/v2/posts', { params })
  },

  // 获取单篇文章
  getPost(id) {
    return apiClient.get(`/wp/v2/posts/${id}`)
  },

  // 创建文章
  createPost(data) {
    return apiClient.post('/wp/v2/posts', data)
  },

  // 更新文章
  updatePost(id, data) {
    return apiClient.put(`/wp/v2/posts/${id}`, data)
  },

  // 删除文章
  deletePost(id) {
    return apiClient.delete(`/wp/v2/posts/${id}`)
  },

  // AI优化文章
  optimizeWithAI(postId, content) {
    return apiClient.post('/xiaowu-ai/v1/optimize-article', {
      post_id: postId,
      content
    })
  }
}

// 评论API
export const commentsAPI = {
  // 获取评论列表
  getComments(params = {}) {
    return apiClient.get('/xiaowu-comments/v1/comments', { params })
  },

  // 审核评论
  moderateComment(id, status) {
    return apiClient.put(`/xiaowu-comments/v1/comments/${id}/moderate`, {
      status
    })
  },

  // 删除评论
  deleteComment(id) {
    return apiClient.delete(`/xiaowu-comments/v1/comments/${id}`)
  },

  // 批量操作
  bulkAction(action, ids) {
    return apiClient.post('/xiaowu-comments/v1/comments/bulk', {
      action,
      ids
    })
  }
}

// 用户API
export const usersAPI = {
  // 获取用户列表
  getUsers(params = {}) {
    return apiClient.get('/xiaowu-user/v1/users', { params })
  },

  // 获取用户详情
  getUser(id) {
    return apiClient.get(`/xiaowu-user/v1/users/${id}`)
  },

  // 创建用户
  createUser(data) {
    return apiClient.post('/xiaowu-user/v1/users', data)
  },

  // 更新用户
  updateUser(id, data) {
    return apiClient.put(`/xiaowu-user/v1/users/${id}`, data)
  },

  // 删除用户
  deleteUser(id) {
    return apiClient.delete(`/xiaowu-user/v1/users/${id}`)
  },

  // 重置密码
  resetPassword(id, newPassword) {
    return apiClient.post(`/xiaowu-user/v1/users/${id}/reset-password`, {
      new_password: newPassword
    })
  }
}

// 3D图库API
export const galleryAPI = {
  // 获取模型列表
  getModels(params = {}) {
    return apiClient.get('/xiaowu-3d-gallery/v1/models', { params })
  },

  // 获取模型详情
  getModel(id) {
    return apiClient.get(`/xiaowu-3d-gallery/v1/models/${id}`)
  },

  // 上传模型
  uploadModel(formData) {
    return apiClient.post('/xiaowu-3d-gallery/v1/models/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 更新模型信息
  updateModel(id, data) {
    return apiClient.put(`/xiaowu-3d-gallery/v1/models/${id}`, data)
  },

  // 删除模型
  deleteModel(id) {
    return apiClient.delete(`/xiaowu-3d-gallery/v1/models/${id}`)
  }
}

// 搜索API
export const searchAPI = {
  // 执行搜索
  search(query, params = {}) {
    return apiClient.get('/xiaowu-search/v1/search', {
      params: { q: query, ...params }
    })
  },

  // 获取搜索统计
  getStats(days = 30) {
    return apiClient.get('/xiaowu-search/v1/analytics/stats', {
      params: { days }
    })
  },

  // 获取热门搜索
  getPopular(limit = 10) {
    return apiClient.get('/xiaowu-search/v1/popular', {
      params: { limit }
    })
  },

  // 重建索引
  rebuildIndex(type = 'all') {
    return apiClient.post('/xiaowu-search/v1/index/rebuild', { type })
  },

  // 优化索引
  optimizeIndex() {
    return apiClient.post('/xiaowu-search/v1/index/optimize')
  }
}

// AI服务API
export const aiAPI = {
  // 获取AI设置
  getSettings() {
    return apiClient.get('/xiaowu-ai/v1/settings')
  },

  // 保存AI设置
  saveSettings(data) {
    return apiClient.post('/xiaowu-ai/v1/settings', data)
  },

  // 测试AI连接
  testConnection(provider, apiKey) {
    return apiClient.post('/xiaowu-ai/v1/test-connection', {
      provider,
      api_key: apiKey
    })
  },

  // 生成文章大纲
  generateOutline(topic) {
    return apiClient.post('/xiaowu-ai/v1/generate-outline', { topic })
  },

  // 生成图片
  generateImage(prompt, options = {}) {
    return apiClient.post('/xiaowu-ai/v1/generate-image', {
      prompt,
      ...options
    })
  },

  // 智能推荐
  getRecommendations(postId) {
    return apiClient.get(`/xiaowu-ai/v1/recommendations/${postId}`)
  }
}

// 统计API
export const statsAPI = {
  // 获取仪表盘统计
  getDashboardStats() {
    return apiClient.get('/xiaowu/v1/stats/dashboard')
  },

  // 获取访问统计
  getVisitStats(days = 30) {
    return apiClient.get('/xiaowu/v1/stats/visits', {
      params: { days }
    })
  },

  // 获取内容统计
  getContentStats() {
    return apiClient.get('/xiaowu/v1/stats/content')
  }
}

// 媒体API
export const mediaAPI = {
  // 上传媒体文件
  uploadMedia(formData) {
    return apiClient.post('/wp/v2/media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  },

  // 获取媒体列表
  getMedia(params = {}) {
    return apiClient.get('/wp/v2/media', { params })
  },

  // 删除媒体
  deleteMedia(id) {
    return apiClient.delete(`/wp/v2/media/${id}`, {
      params: { force: true }
    })
  }
}

export default {
  auth: authAPI,
  posts: postsAPI,
  comments: commentsAPI,
  users: usersAPI,
  gallery: galleryAPI,
  search: searchAPI,
  ai: aiAPI,
  stats: statsAPI,
  media: mediaAPI
}
