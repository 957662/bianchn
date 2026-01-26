import { createApp } from 'vue';
import { createPinia } from 'pinia';
import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import 'element-plus/theme-chalk/dark/css-vars.css';
import zhCn from 'element-plus/es/locale/lang/zh-cn';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import router from './router';
import App from './App.vue';
import './assets/styles/main.scss';

const app = createApp(App);
const pinia = createPinia();

// 注册所有Element Plus图标
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component);
}

app.use(pinia);
app.use(router);
app.use(ElementPlus, {
  locale: zhCn,
  size: 'default',
});

app.mount('#app');

// 检查是否是首次部署，自动跳转到部署向导
if (!localStorage.getItem('xiaowu_first_deploy')) {
  // 检查是否已完成WordPress安装
  const wpInstalled = localStorage.getItem('xiaowu_wp_installed');
  if (!wpInstalled) {
    // 检查当前URL是否包含setup参数，避免循环
    if (!window.location.search.includes('skip-wizard')) {
      window.location.href = '/setup';
    }
  }
}
