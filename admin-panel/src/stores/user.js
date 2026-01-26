import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authAPI } from '@/api';
import router from '@/router';
import { ElMessage } from 'element-plus';

export const useUserStore = defineStore('user', () => {
  const user = ref(null);
  const token = ref(localStorage.getItem('wp_token') || '');
  const nonce = ref(localStorage.getItem('wp_nonce') || '');

  const isLoggedIn = computed(() => !!token.value && !!user.value);
  const userName = computed(() => user.value?.name || '游客');
  const userAvatar = computed(() => user.value?.avatar_urls?.['96'] || '');
  const userRole = computed(() => user.value?.roles?.[0] || 'subscriber');
  const isAdmin = computed(() => userRole.value === 'administrator');

  // 登录
  async function login(username, password) {
    try {
      const response = await authAPI.login(username, password);

      token.value = response.token;
      nonce.value = response.nonce || '';

      localStorage.setItem('wp_token', token.value);
      localStorage.setItem('wp_nonce', nonce.value);

      await fetchUserInfo();

      ElMessage.success('登录成功');
      router.push('/');

      return response;
    } catch (error) {
      ElMessage.error(error.response?.data?.message || '登录失败');
      throw error;
    }
  }

  // 登出
  function logout() {
    user.value = null;
    token.value = '';
    nonce.value = '';

    localStorage.removeItem('wp_token');
    localStorage.removeItem('wp_nonce');

    ElMessage.success('已退出登录');
    router.push('/login');
  }

  // 获取用户信息
  async function fetchUserInfo() {
    try {
      const data = await authAPI.getCurrentUser();
      user.value = data;
      return data;
    } catch (error) {
      console.error('获取用户信息失败:', error);
      logout();
      throw error;
    }
  }

  // 验证token
  async function validateToken() {
    if (!token.value) {
      return false;
    }

    try {
      await authAPI.validateToken(token.value);
      return true;
    } catch (error) {
      logout();
      return false;
    }
  }

  // 初始化用户信息(用于页面刷新)
  async function initUser() {
    if (token.value) {
      try {
        await fetchUserInfo();
      } catch (error) {
        console.error('初始化用户失败:', error);
      }
    }
  }

  return {
    user,
    token,
    nonce,
    isLoggedIn,
    userName,
    userAvatar,
    userRole,
    isAdmin,
    login,
    logout,
    fetchUserInfo,
    validateToken,
    initUser,
  };
});
