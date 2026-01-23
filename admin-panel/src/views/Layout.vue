<template>
  <div class="layout-container">
    <el-container>
      <!-- 侧边栏 -->
      <el-aside :width="sidebarWidth" class="layout-aside">
        <div class="logo-container">
          <img v-if="!appStore.sidebarCollapsed" src="/logo.svg" alt="Logo" class="logo" />
          <span v-if="!appStore.sidebarCollapsed" class="logo-text">小伍博客</span>
        </div>

        <el-menu
          :default-active="activeMenu"
          :collapse="appStore.sidebarCollapsed"
          :collapse-transition="false"
          class="sidebar-menu"
          background-color="#304156"
          text-color="#bfcbd9"
          active-text-color="#409EFF"
          router
        >
          <template v-for="route in menuRoutes" :key="route.path">
            <el-menu-item :index="route.path" v-if="!route.meta?.hidden">
              <el-icon><component :is="route.meta?.icon" /></el-icon>
              <template #title>{{ route.meta?.title }}</template>
            </el-menu-item>
          </template>
        </el-menu>
      </el-aside>

      <!-- 主体区域 -->
      <el-container class="layout-main">
        <!-- 顶部导航 -->
        <el-header class="layout-header">
          <div class="header-left">
            <el-icon class="collapse-icon" @click="appStore.toggleSidebar">
              <Fold v-if="!appStore.sidebarCollapsed" />
              <Expand v-else />
            </el-icon>

            <el-breadcrumb separator="/">
              <el-breadcrumb-item :to="{ path: '/' }">首页</el-breadcrumb-item>
              <el-breadcrumb-item v-if="currentRoute">{{ currentRoute.meta?.title }}</el-breadcrumb-item>
            </el-breadcrumb>
          </div>

          <div class="header-right">
            <!-- 主题切换 -->
            <el-tooltip content="切换主题" placement="bottom">
              <el-icon class="header-icon" @click="toggleTheme">
                <Sunny v-if="appStore.theme === 'light'" />
                <Moon v-else />
              </el-icon>
            </el-tooltip>

            <!-- 刷新 -->
            <el-tooltip content="刷新页面" placement="bottom">
              <el-icon class="header-icon" @click="refreshPage">
                <Refresh />
              </el-icon>
            </el-tooltip>

            <!-- 用户菜单 -->
            <el-dropdown class="user-dropdown" @command="handleCommand">
              <div class="user-info">
                <el-avatar :src="userStore.userAvatar" :size="32">
                  {{ userStore.userName.charAt(0) }}
                </el-avatar>
                <span class="user-name">{{ userStore.userName }}</span>
                <el-icon><ArrowDown /></el-icon>
              </div>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="profile">
                    <el-icon><User /></el-icon>
                    个人资料
                  </el-dropdown-item>
                  <el-dropdown-item command="settings">
                    <el-icon><Setting /></el-icon>
                    系统设置
                  </el-dropdown-item>
                  <el-dropdown-item divided command="logout">
                    <el-icon><SwitchButton /></el-icon>
                    退出登录
                  </el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </el-header>

        <!-- 内容区域 -->
        <el-main class="layout-content">
          <router-view v-slot="{ Component }">
            <transition name="fade-transform" mode="out-in">
              <keep-alive>
                <component :is="Component" :key="$route.path" />
              </keep-alive>
            </transition>
          </router-view>
        </el-main>
      </el-container>
    </el-container>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import { ElMessageBox } from 'element-plus'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const appStore = useAppStore()

const sidebarWidth = computed(() => appStore.sidebarCollapsed ? '64px' : '200px')
const activeMenu = computed(() => route.path)
const currentRoute = computed(() => route)

const menuRoutes = computed(() => {
  return router.options.routes
    .find(r => r.path === '/')
    ?.children?.filter(r => !r.meta?.hidden) || []
})

function toggleTheme() {
  const newTheme = appStore.theme === 'light' ? 'dark' : 'light'
  appStore.setTheme(newTheme)
}

function refreshPage() {
  router.go(0)
}

async function handleCommand(command) {
  switch (command) {
    case 'profile':
      router.push('/profile')
      break
    case 'settings':
      router.push('/settings')
      break
    case 'logout':
      try {
        await ElMessageBox.confirm('确定要退出登录吗?', '提示', {
          confirmButtonText: '确定',
          cancelButtonText: '取消',
          type: 'warning'
        })
        userStore.logout()
      } catch {
        // 用户取消
      }
      break
  }
}
</script>

<style lang="scss" scoped>
.layout-container {
  width: 100%;
  height: 100vh;
  overflow: hidden;
}

.layout-aside {
  background-color: #304156;
  transition: width 0.3s;
  overflow-x: hidden;
}

.logo-container {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 60px;
  padding: 0 20px;
  background-color: #2b3a4b;

  .logo {
    width: 32px;
    height: 32px;
  }

  .logo-text {
    margin-left: 12px;
    font-size: 18px;
    font-weight: 600;
    color: #fff;
  }
}

.sidebar-menu {
  border-right: none;
}

.layout-main {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

.layout-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  background-color: #fff;
  border-bottom: 1px solid var(--el-border-color-light);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.collapse-icon {
  font-size: 20px;
  cursor: pointer;
  transition: all 0.3s;

  &:hover {
    color: var(--el-color-primary);
  }
}

.header-right {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  font-size: 20px;
  cursor: pointer;
  transition: all 0.3s;

  &:hover {
    color: var(--el-color-primary);
  }
}

.user-dropdown {
  cursor: pointer;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-name {
  font-size: 14px;
  color: var(--el-text-color-primary);
}

.layout-content {
  flex: 1;
  overflow-y: auto;
  background-color: #f0f2f5;
}

/* 过渡动画 */
.fade-transform-leave-active,
.fade-transform-enter-active {
  transition: all 0.3s;
}

.fade-transform-enter-from {
  opacity: 0;
  transform: translateX(-30px);
}

.fade-transform-leave-to {
  opacity: 0;
  transform: translateX(30px);
}
</style>
