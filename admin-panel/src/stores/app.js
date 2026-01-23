import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const theme = ref(localStorage.getItem('theme') || 'light')
  const loading = ref(false)

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function setSidebarCollapsed(value) {
    sidebarCollapsed.value = value
  }

  function setTheme(value) {
    theme.value = value
    localStorage.setItem('theme', value)

    if (value === 'dark') {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  function setLoading(value) {
    loading.value = value
  }

  return {
    sidebarCollapsed,
    theme,
    loading,
    toggleSidebar,
    setSidebarCollapsed,
    setTheme,
    setLoading
  }
})
