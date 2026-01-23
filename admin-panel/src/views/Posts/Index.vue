<template>
  <div class="posts-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>文章管理</span>
          <el-button type="primary" :icon="Plus" @click="$router.push('/posts/create')">
            写文章
          </el-button>
        </div>
      </template>

      <!-- 搜索和筛选 -->
      <div class="filter-bar">
        <el-form :inline="true" :model="filters">
          <el-form-item>
            <el-input
              v-model="filters.search"
              placeholder="搜索标题或内容"
              :prefix-icon="Search"
              clearable
              @clear="handleSearch"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item>
            <el-select v-model="filters.status" placeholder="状态" clearable @change="handleSearch">
              <el-option label="全部" value="" />
              <el-option label="已发布" value="publish" />
              <el-option label="草稿" value="draft" />
              <el-option label="待审核" value="pending" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-select v-model="filters.category" placeholder="分类" clearable @change="handleSearch">
              <el-option label="全部分类" value="" />
              <el-option
                v-for="cat in categories"
                :key="cat.id"
                :label="cat.name"
                :value="cat.id"
              />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
            <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 文章列表 -->
      <el-table
        :data="posts"
        v-loading="loading"
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="缩略图" width="100">
          <template #default="{ row }">
            <el-image
              v-if="row.featured_image"
              :src="row.featured_image"
              :preview-src-list="[row.featured_image]"
              fit="cover"
              style="width: 60px; height: 60px; border-radius: 4px;"
            />
            <div v-else class="no-image">无图</div>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <el-link type="primary" :underline="false" @click="editPost(row.id)">
              {{ row.title }}
            </el-link>
          </template>
        </el-table-column>
        <el-table-column prop="author" label="作者" width="120" />
        <el-table-column prop="categories" label="分类" width="150">
          <template #default="{ row }">
            <el-tag
              v-for="cat in row.categories"
              :key="cat"
              size="small"
              style="margin-right: 4px;"
            >
              {{ cat }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="统计" width="120">
          <template #default="{ row }">
            <div class="post-stats">
              <span><el-icon><View /></el-icon> {{ row.views || 0 }}</span>
              <span><el-icon><ChatDotRound /></el-icon> {{ row.comments_count || 0 }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="date" label="发布时间" width="180">
          <template #default="{ row }">
            {{ formatDate(row.date) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="editPost(row.id)">
              编辑
            </el-button>
            <el-button link type="primary" size="small" @click="viewPost(row.id)">
              预览
            </el-button>
            <el-button link type="primary" size="small" @click="optimizeWithAI(row)">
              AI优化
            </el-button>
            <el-button link type="danger" size="small" @click="deletePost(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 批量操作 -->
      <div v-if="selectedPosts.length > 0" class="batch-actions">
        <span>已选择 {{ selectedPosts.length }} 项</span>
        <el-button size="small" @click="batchPublish">批量发布</el-button>
        <el-button size="small" @click="batchDraft">批量转为草稿</el-button>
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search, Refresh, View, ChatDotRound } from '@element-plus/icons-vue'
import { postsAPI } from '@/api'
import dayjs from 'dayjs'

const router = useRouter()
const loading = ref(false)

// 筛选条件
const filters = ref({
  search: '',
  status: '',
  category: ''
})

// 分类列表
const categories = ref([])

// 文章列表
const posts = ref([])

// 选中的文章
const selectedPosts = ref([])

// 分页
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0
})

// 加载文章列表
async function loadPosts() {
  loading.value = true
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.per_page,
      search: filters.value.search,
      status: filters.value.status,
      category: filters.value.category
    }
    const data = await postsAPI.getPosts(params)
    posts.value = data.posts || []
    pagination.value.total = data.total || 0
  } catch (error) {
    console.error('加载文章列表失败:', error)
    ElMessage.error('加载文章列表失败')
  } finally {
    loading.value = false
  }
}

// 加载分类列表
async function loadCategories() {
  try {
    const data = await postsAPI.getCategories()
    categories.value = data || []
  } catch (error) {
    console.error('加载分类失败:', error)
  }
}

// 搜索
function handleSearch() {
  pagination.value.page = 1
  loadPosts()
}

// 重置
function handleReset() {
  filters.value = {
    search: '',
    status: '',
    category: ''
  }
  pagination.value.page = 1
  loadPosts()
}

// 选择变化
function handleSelectionChange(selection) {
  selectedPosts.value = selection
}

// 编辑文章
function editPost(id) {
  router.push(`/posts/edit/${id}`)
}

// 预览文章
function viewPost(id) {
  window.open(`/posts/${id}`, '_blank')
}

// AI优化
async function optimizeWithAI(post) {
  try {
    await ElMessageBox.confirm('确定要使用AI优化这篇文章吗?', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    loading.value = true
    await postsAPI.optimizeWithAI(post.id, post.content)
    ElMessage.success('AI优化成功')
    loadPosts()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('AI优化失败:', error)
      ElMessage.error('AI优化失败')
    }
  } finally {
    loading.value = false
  }
}

// 删除文章
async function deletePost(post) {
  try {
    await ElMessageBox.confirm(`确定要删除文章"${post.title}"吗?`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    loading.value = true
    await postsAPI.deletePost(post.id)
    ElMessage.success('删除成功')
    loadPosts()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
      ElMessage.error('删除失败')
    }
  } finally {
    loading.value = false
  }
}

// 批量发布
async function batchPublish() {
  try {
    await ElMessageBox.confirm(`确定要发布选中的 ${selectedPosts.value.length} 篇文章吗?`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    loading.value = true
    const promises = selectedPosts.value.map(post =>
      postsAPI.updatePost(post.id, { status: 'publish' })
    )
    await Promise.all(promises)
    ElMessage.success('批量发布成功')
    loadPosts()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量发布失败:', error)
      ElMessage.error('批量发布失败')
    }
  } finally {
    loading.value = false
  }
}

// 批量转为草稿
async function batchDraft() {
  try {
    await ElMessageBox.confirm(`确定要将选中的 ${selectedPosts.value.length} 篇文章转为草稿吗?`, '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'info'
    })

    loading.value = true
    const promises = selectedPosts.value.map(post =>
      postsAPI.updatePost(post.id, { status: 'draft' })
    )
    await Promise.all(promises)
    ElMessage.success('批量转为草稿成功')
    loadPosts()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量转为草稿失败:', error)
      ElMessage.error('批量转为草稿失败')
    }
  } finally {
    loading.value = false
  }
}

// 批量删除
async function batchDelete() {
  try {
    await ElMessageBox.confirm(`确定要删除选中的 ${selectedPosts.value.length} 篇文章吗? 此操作不可恢复!`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })

    loading.value = true
    const promises = selectedPosts.value.map(post =>
      postsAPI.deletePost(post.id)
    )
    await Promise.all(promises)
    ElMessage.success('批量删除成功')
    loadPosts()
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

// 获取状态类型
function getStatusType(status) {
  const types = {
    publish: 'success',
    draft: 'info',
    pending: 'warning',
    private: 'danger'
  }
  return types[status] || 'info'
}

// 获取状态标签
function getStatusLabel(status) {
  const labels = {
    publish: '已发布',
    draft: '草稿',
    pending: '待审核',
    private: '私密'
  }
  return labels[status] || status
}

onMounted(() => {
  loadPosts()
  loadCategories()
})
</script>

<style lang="scss" scoped>
.posts-container {
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

.no-image {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 60px;
  height: 60px;
  background: #f5f7fa;
  border-radius: 4px;
  color: #909399;
  font-size: 12px;
}

.post-stats {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: #666;

  span {
    display: flex;
    align-items: center;
    gap: 4px;
  }
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

/* 响应式设计 */
@media (max-width: 768px) {
  .posts-container {
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
