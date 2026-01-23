import axios from 'axios'
import { ElMessage } from 'element-plus'
import router from '@/router'

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/wp-json',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json'
  }
})

// 请求拦截器
apiClient.interceptors.request.use(
  config => {
    const token = localStorage.getItem('wp_token')
    if (token) {
      config.headers['Authorization'] = `Bearer ${token}`
    }

    const nonce = localStorage.getItem('wp_nonce')
    if (nonce) {
      config.headers['X-WP-Nonce'] = nonce
    }

    return config
  },
  error => {
    console.error('请求错误:', error)
    return Promise.reject(error)
  }
)

// 响应拦截器
apiClient.interceptors.response.use(
  response => {
    return response.data
  },
  error => {
    if (error.response) {
      const { status, data } = error.response

      switch (status) {
        case 401:
          ElMessage.error('未授权,请重新登录')
          localStorage.removeItem('wp_token')
          localStorage.removeItem('wp_nonce')
          router.push('/login')
          break
        case 403:
          ElMessage.error('没有权限执行此操作')
          break
        case 404:
          ElMessage.error('请求的资源不存在')
          break
        case 500:
          ElMessage.error('服务器错误,请稍后重试')
          break
        default:
          ElMessage.error(data?.message || '请求失败')
      }
    } else if (error.request) {
      ElMessage.error('网络错误,请检查网络连接')
    } else {
      ElMessage.error('请求配置错误')
    }

    return Promise.reject(error)
  }
)

export default apiClient
