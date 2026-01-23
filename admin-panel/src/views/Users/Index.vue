<template>
  <div class="users-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>用户管理</span>
          <el-button type="primary" :icon="Plus" @click="handleAddUser">
            添加用户
          </el-button>
        </div>
      </template>

      <!-- 搜索和筛选 -->
      <div class="filter-bar">
        <el-form :inline="true" :model="filters">
          <el-form-item>
            <el-input
              v-model="filters.search"
              placeholder="搜索用户名或邮箱"
              :prefix-icon="Search"
              clearable
              @clear="handleSearch"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item>
            <el-select v-model="filters.role" placeholder="角色" clearable @change="handleSearch">
              <el-option label="全部" value="" />
              <el-option label="管理员" value="administrator" />
              <el-option label="编辑" value="editor" />
              <el-option label="作者" value="author" />
              <el-option label="订阅者" value="subscriber" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
            <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 用户列表 -->
      <el-table
        :data="users"
        v-loading="loading"
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="头像" width="100">
          <template #default="{ row }">
            <el-avatar :src="row.avatar" :size="40">
              {{ row.name.charAt(0) }}
            </el-avatar>
          </template>
        </el-table-column>
        <el-table-column prop="name" label="用户名" width="150" />
        <el-table-column prop="email" label="邮箱" min-width="200" />
        <el-table-column prop="role" label="角色" width="100">
          <template #default="{ row }">
            <el-tag :type="getRoleType(row.role)" size="small">
              {{ getRoleLabel(row.role) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="posts_count" label="文章数" width="100" />
        <el-table-column prop="registered_at" label="注册时间" width="180">
          <template #default="{ row }">
            {{ formatDate(row.registered_at) }}
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
              {{ row.status === 'active' ? '正常' : '待激活' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="250" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="viewUser(row)">
              查看
            </el-button>
            <el-button link type="primary" size="small" @click="editUser(row)">
              编辑
            </el-button>
            <el-button link type="warning" size="small" @click="resetPassword(row)">
              重置密码
            </el-button>
            <el-button
              v-if="row.id !== 1"
              link
              type="danger"
              size="small"
              @click="deleteUser(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 批量操作 -->
      <div v-if="selectedUsers.length > 0" class="batch-actions">
        <span>已选择 {{ selectedUsers.length }} 项</span>
        <el-button size="small" type="success" @click="batchActivate">批量激活</el-button>
        <el-button size="small" type="warning" @click="batchDeactivate">批量禁用</el-button>
        <el-button size="small" type="danger" @click="batchDelete">批量删除</el-button>
      </div>

      <!-- 分页 -->
      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSearch"
          @current-change="handleSearch"
        />
      </div>
    </el-card>

    <!-- 编辑用户对话框 -->
    <el-dialog
      v-model="editDialogVisible"
      :title="editingUser ? '编辑用户' : '添加用户'"
      width="600px"
    >
      <el-form v-if="editDialogVisible" :model="userForm" :rules="userRules" label-width="100px">
        <el-form-item label="用户名" prop="username">
          <el-input
            v-model="userForm.username"
            placeholder="请输入用户名"
            :disabled="!!editingUser"
          />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input
            v-model="userForm.email"
            type="email"
            placeholder="请输入邮箱"
            :disabled="!!editingUser"
          />
        </el-form-item>
        <el-form-item v-if="!editingUser" label="密码" prop="password">
          <el-input
            v-model="userForm.password"
            type="password"
            placeholder="请输入密码"
          />
        </el-form-item>
        <el-form-item label="显示名称" prop="display_name">
          <el-input
            v-model="userForm.display_name"
            placeholder="请输入显示名称"
          />
        </el-form-item>
        <el-form-item label="角色" prop="role">
          <el-select v-model="userForm.role" placeholder="请选择角色">
            <el-option label="订阅者" value="subscriber" />
            <el-option label="作者" value="author" />
            <el-option label="编辑" value="editor" />
            <el-option label="管理员" value="administrator" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveUser">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import { usersAPI } from '@/api'
import dayjs from 'dayjs'

const loading = ref(false)
const saving = ref(false)

// 筛选条件
const filters = ref({
  search: '',
  role: ''
})

// 用户列表
const users = ref([])

// 选中的用户
const selectedUsers = ref([])

// 分页
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

// 编辑对话框
const editDialogVisible = ref(false)
const editingUser = ref(null)

// 用户表单
const userForm = ref({
  username: '',
  email: '',
  password: '',
  display_name: '',
  role: 'subscriber'
})

// 表单验证规则
const userRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 3, message: '用户名至少3个字符', trigger: 'blur' }
  ],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码至少6个字符', trigger: 'blur' }
  ],
  display_name: [
    { required: true, message: '请输入显示名称', trigger: 'blur' }
  ],
  role: [
    { required: true, message: '请选择角色', trigger: 'change' }
  ]
}

// 加载用户列表
async function loadUsers() {
  loading.value = true
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.per_page,
      search: filters.value.search,
      role: filters.value.role
    }
    const data = await usersAPI.getUsers(params)
    users.value = data.users || []
    pagination.value.total = data.total || 0
  } catch (error) {
    console.error('加载用户列表失败:', error)
    ElMessage.error('加载用户列表失败')
  } finally {
    loading.value = false
  }
}

// 搜索
function handleSearch() {
  pagination.value.page = 1
  loadUsers()
}

// 重置
function handleReset() {
  filters.value = {
    search: '',
    role: ''
  }
  pagination.value.page = 1
  loadUsers()
}

// 选择变化
function handleSelectionChange(selection) {
  selectedUsers.value = selection
}

// 添加用户
function handleAddUser() {
  editingUser.value = null
  userForm.value = {
    username: '',
    email: '',
    password: '',
    display_name: '',
    role: 'subscriber'
  }
  editDialogVisible.value = true
}

// 查看用户
function viewUser(user) {
  console.log('查看用户:', user)
  // TODO: 打开用户详情对话框
}

// 编辑用户
function editUser(user) {
  editingUser.value = user
  userForm.value = {
    username: user.username,
    email: user.email,
    display_name: user.display_name,
    role: user.role
  }
  editDialogVisible.value = true
}

// 重置密码
async function resetPassword(user) {
  try {
    await ElMessageBox.confirm(`确定要重置用户"${user.name}"的密码吗?`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    loading.value = true
    const newPassword = Math.random().toString(36).slice(-8)
    await usersAPI.resetPassword(user.id, newPassword)
    ElMessage.success(`新密码已生成: ${newPassword}`)
  } catch (error) {
    if (error !== 'cancel') {
      console.error('重置密码失败:', error)
      ElMessage.error('重置密码失败')
    }
  } finally {
    loading.value = false
  }
}

// 删除用户
async function deleteUser(user) {
  if (user.id === 1) {
    ElMessage.warning('不能删除超级管理员')
    return
  }

  try {
    await ElMessageBox.confirm(`确定要删除用户"${user.name}"吗?`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    loading.value = true
    await usersAPI.deleteUser(user.id)
    ElMessage.success('删除成功')
    loadUsers()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  } finally {
    loading.value = false
  }
}

// 保存用户
async function saveUser() {
  saving.value = true
  try {
    if (editingUser.value) {
      await usersAPI.updateUser(editingUser.value.id, userForm.value)
      ElMessage.success('更新成功')
    } else {
      await usersAPI.createUser(userForm.value)
      ElMessage.success('创建成功')
    }
    editDialogVisible.value = false
    loadUsers()
  } catch (error) {
    console.error('保存用户失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 批量激活
async function batchActivate() {
  try {
    await ElMessageBox.confirm(`确定要激活选中的 ${selectedUsers.value.length} 个用户吗?`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    loading.value = true
    // TODO: 实现批量激活
    ElMessage.success('批量激活成功')
    loadUsers()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量激活失败:', error)
      ElMessage.error('批量激活失败')
    }
  } finally {
    loading.value = false
  }
}

// 批量禁用
async function batchDeactivate() {
  try {
    await ElMessageBox.confirm(`确定要禁用选中的 ${selectedUsers.value.length} 个用户吗?`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    loading.value = true
    // TODO: 实现批量禁用
    ElMessage.success('批量禁用成功')
    loadUsers()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量禁用失败:', error)
      ElMessage.error('批量禁用失败')
    }
  } finally {
    loading.value = false
  }
}

// 批量删除
async function batchDelete() {
  if (selectedUsers.value.some(u => u.id === 1)) {
    ElMessage.warning('不能删除超级管理员')
    return
  }

  try {
    await ElMessageBox.confirm(`确定要删除选中的 ${selectedUsers.value.length} 个用户吗?`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    loading.value = true
    const promises = selectedUsers.value.map(user => usersAPI.deleteUser(user.id))
    await Promise.all(promises)
    ElMessage.success('批量删除成功')
    loadUsers()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量删除失败:', error)
      ElMessage.error('批量删除失败')
    }
  } finally {
    loading.value = false
  }
}

// 格式化日期
function formatDate(date) {
  return dayjs(date).format('YYYY-MM-DD HH:mm')
}

// 获取角色类型
function getRoleType(role) {
  const types = {
    administrator: 'danger',
    editor: 'warning',
    author: 'success',
    subscriber: 'info'
  }
  return types[role] || 'info'
}

// 获取角色标签
function getRoleLabel(role) {
  const labels = {
    administrator: '管理员',
    editor: '编辑',
    author: '作者',
    subscriber: '订阅者'
  }
  return labels[role] || role
}

onMounted(() => {
  loadUsers()
})
</script>

<style lang="scss" scoped>
.users-container {
  padding: 20px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.filter-bar {
  margin-bottom: 20px;
}

.batch-actions {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  margin-top: 12px;

  span {
    color: #606266;
    font-size: 14px;
  }
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

@media (max-width: 768px) {
  .users-container {
    padding: 12px;
  }

  .filter-bar {
    :deep(.el-form) {
      display: flex;
      flex-direction: column;

      .el-form-item {
        width: 100%;
        margin-right: 0;

        .el-input,
        .el-select {
          width: 100%;
        }
      }
    }
  }
}
</style>
