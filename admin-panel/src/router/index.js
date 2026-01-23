import { createRouter, createWebHistory } from 'vue-router'
import { useUserStore } from '@/stores/user'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'

NProgress.configure({ showSpinner: false })

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { title: '登录', requiresAuth: false }
  },
  {
    path: '/',
    component: () => import('@/views/Layout.vue'),
    redirect: '/dashboard',
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/Dashboard.vue'),
        meta: { title: '仪表盘', icon: 'Odometer' }
      },
      {
        path: 'posts',
        name: 'Posts',
        component: () => import('@/views/Posts/Index.vue'),
        meta: { title: '文章管理', icon: 'Document' }
      },
      {
        path: 'posts/create',
        name: 'PostCreate',
        component: () => import('@/views/Posts/Edit.vue'),
        meta: { title: '创建文章', icon: 'Edit', hidden: true }
      },
      {
        path: 'posts/edit/:id',
        name: 'PostEdit',
        component: () => import('@/views/Posts/Edit.vue'),
        meta: { title: '编辑文章', icon: 'Edit', hidden: true }
      },
      {
        path: 'comments',
        name: 'Comments',
        component: () => import('@/views/Comments/Index.vue'),
        meta: { title: '评论管理', icon: 'ChatDotRound' }
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('@/views/Users/Index.vue'),
        meta: { title: '用户管理', icon: 'User' }
      },
      {
        path: 'gallery',
        name: 'Gallery',
        component: () => import('@/views/Gallery/Index.vue'),
        meta: { title: '3D图库', icon: 'Picture' }
      },
      {
        path: 'search',
        name: 'Search',
        component: () => import('@/views/Search/Index.vue'),
        meta: { title: '搜索管理', icon: 'Search' }
      },
      {
        path: 'ai',
        name: 'AI',
        component: () => import('@/views/AI/Index.vue'),
        meta: { title: 'AI设置', icon: 'MagicStick' }
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('@/views/Settings/Index.vue'),
        meta: { title: '系统设置', icon: 'Setting' }
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFound.vue'),
    meta: { title: '页面不存在', requiresAuth: false }
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// 路由守卫
router.beforeEach((to, from, next) => {
  NProgress.start()

  const userStore = useUserStore()
  const requiresAuth = to.matched.some(record => record.meta.requiresAuth !== false)

  // 设置页面标题
  document.title = to.meta.title ? `${to.meta.title} - 小伍博客管理后台` : '小伍博客管理后台'

  if (requiresAuth && !userStore.isLoggedIn) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
  } else if (to.name === 'Login' && userStore.isLoggedIn) {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

router.afterEach(() => {
  NProgress.done()
})

export default router
