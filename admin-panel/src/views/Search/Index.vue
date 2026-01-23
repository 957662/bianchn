<template>
  <div class="search-container">
    <el-card>
      <template #header>
        <span>搜索管理</span>
      </template>

      <!-- 统计卡片 -->
      <el-row :gutter="16" class="stats-row">
        <el-col :span="6">
          <div class="stat-item">
            <span class="stat-label">总搜索量</span>
            <span class="stat-value">{{ stats.total_searches }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-item success">
            <span class="stat-label">有结果</span>
            <span class="stat-value">{{ stats.with_results }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-item warning">
            <span class="stat-label">无结果</span>
            <span class="stat-value">{{ stats.no_results }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-item danger">
            <span class="stat-label">平均耗时</span>
            <span class="stat-value">{{ stats.avg_time }}ms</span>
          </div>
        </el-col>
      </el-row>

      <!-- 热门搜索 -->
      <div class="section">
        <h3 class="section-title">热门搜索词</h3>
        <div class="popular-tags">
          <el-tag
            v-for="item in popularSearches"
            :key="item.keyword"
            :type="getTagType(item.count)"
            size="large"
            class="popular-tag"
            @click="searchKeyword(item.keyword)"
          >
            {{ item.keyword }} ({{ item.count }})
          </el-tag>
        </div>
      </div>

      <!-- 搜索历史 -->
      <div class="section">
        <h3 class="section-title">最近搜索</h3>
        <el-table :data="recentSearches" style="width: 100%">
          <el-table-column prop="keyword" label="搜索词" />
          <el-table-column prop="results_count" label="结果数" width="100" />
          <el-table-column prop="user" label="用户" width="120" />
          <el-table-column prop="created_at" label="时间" width="180">
            <template #default="{ row }">
              {{ formatDate(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="searchKeyword(row.keyword)">
                再次搜索
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- 索引管理 -->
      <div class="section">
        <h3 class="section-title">索引管理</h3>
        <div class="index-actions">
          <el-card>
            <div class="index-info">
              <div class="index-stat">
                <span class="label">索引状态</span>
                <el-tag :type="indexInfo.status === 'ready' ? 'success' : 'warning'">
                  {{ indexInfo.status === 'ready' ? '就绪' : '构建中' }}
                </el-tag>
              </div>
              <div class="index-stat">
                <span class="label">文章索引</span>
                <span class="value">{{ indexInfo.posts_indexed }}</span>
              </div>
              <div class="index-stat">
                <span class="label">评论索引</span>
                <span class="value">{{ indexInfo.comments_indexed }}</span>
              </div>
              <div class="index-stat">
                <span class="label">最后更新</span>
                <span class="value">{{ formatDate(indexInfo.last_updated) }}</span>
              </div>
            </div>
            <div class="index-buttons">
              <el-button type="primary" :icon="Refresh" :loading="indexing" @click="rebuildIndex">
                重建索引
              </el-button>
              <el-button type="success" :icon="MagicStick" :loading="optimizing" @click="optimizeIndex">
                优化索引
              </el-button>
            </div>
          </el-card>
        </div>
      </div>

      <!-- 搜索配置 -->
      <div class="section">
        <h3 class="section-title">搜索配置</h3>
        <el-form :model="searchConfig" label-width="120px">
          <el-form-item label="启用AI增强">
            <el-switch v-model="searchConfig.ai_enabled" />
          </el-form-item>
          <el-form-item label="语义搜索">
            <el-switch v-model="searchConfig.semantic_search" />
          </el-form-item>
          <el-form-item label="模糊匹配">
            <el-switch v-model="searchConfig.fuzzy_match" />
          </el-form-item>
          <el-form-item label="结果数量">
            <el-input-number v-model="searchConfig.max_results" :min="5" :max="100" />
          </el-form-item>
          <el-form-item label="缓存时间">
            <el-input-number v-model="searchConfig.cache_ttl" :min="0" :max="3600" />
            <span style="margin-left: 8px; color: #999;">秒</span>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="saveConfig">保存配置</el-button>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh, MagicStick } from '@element-plus/icons-vue'
import { searchAPI } from '@/api'
import dayjs from 'dayjs'

const indexing = ref(false)
const optimizing = ref(false)

// 统计数据
const stats = ref({
  total_searches: 0,
  with_results: 0,
  no_results: 0,
  avg_time: 0
})

// 热门搜索
const popularSearches = ref([])

// 最近搜索
const recentSearches = ref([])

// 索引信息
const indexInfo = ref({
  status: 'ready',
  posts_indexed: 0,
  comments_indexed: 0,
  last_updated: null
})

// 搜索配置
const searchConfig = ref({
  ai_enabled: true,
  semantic_search: true,
  fuzzy_match: true,
  max_results: 20,
  cache_ttl: 300
})

// 加载统计数据
async function loadStats() {
  try {
    const data = await searchAPI.getStats(30)
    stats.value = data || stats.value
  } catch (error) {
    console.error('加载统计数据失败:', error)
  }
}

// 加载热门搜索
async function loadPopularSearches() {
  try {
    const data = await searchAPI.getPopular(10)
    popularSearches.value = data || []
  } catch (error) {
    console.error('加载热门搜索失败:', error)
  }
}

// 加载最近搜索
async function loadRecentSearches() {
  try {
    const data = await searchAPI.getRecentSearches(20)
    recentSearches.value = data || []
  } catch (error) {
    console.error('加载最近搜索失败:', error)
  }
}

// 重建索引
async function rebuildIndex() {
  indexing.value = true
  try {
    await searchAPI.rebuildIndex('all')
    ElMessage.success('索引重建任务已启动')
    loadIndexInfo()
  } catch (error) {
    console.error('重建索引失败:', error)
    ElMessage.error('重建索引失败')
  } finally {
    indexing.value = false
  }
}

// 优化索引
async function optimizeIndex() {
  optimizing.value = true
  try {
    await searchAPI.optimizeIndex()
    ElMessage.success('索引优化成功')
    loadIndexInfo()
  } catch (error) {
    console.error('优化索引失败:', error)
    ElMessage.error('优化索引失败')
  } finally {
    optimizing.value = false
  }
}

// 保存配置
async function saveConfig() {
  try {
    await searchAPI.saveConfig(searchConfig.value)
    ElMessage.success('配置保存成功')
  } catch (error) {
    console.error('保存配置失败:', error)
    ElMessage.error('保存配置失败')
  }
}

// 搜索关键词
function searchKeyword(keyword) {
  // TODO: 跳转到前台搜索
  console.log('搜索:', keyword)
  ElMessage.info(`搜索功能: ${keyword}`)
}

// 获取标签类型
function getTagType(count) {
  if (count >= 50) return 'danger'
  if (count >= 20) return 'warning'
  return ''
}

// 格式化日期
function formatDate(date) {
  if (!date) return '-'
  return dayjs(date).format('YYYY-MM-DD HH:mm')
}

// 加载索引信息
function loadIndexInfo() {
  // TODO: 调用API获取索引信息
  indexInfo.value = {
    status: 'ready',
    posts_indexed: Math.floor(Math.random() * 1000),
    comments_indexed: Math.floor(Math.random() * 500),
    last_updated: new Date()
  }
}

onMounted(() => {
  loadStats()
  loadPopularSearches()
  loadRecentSearches()
  loadIndexInfo()
})
</script>

<style lang="scss" scoped>
.search-container {
  padding: 20px;
}

.stats-row {
  margin-bottom: 24px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;

  &.success {
    background: #f0f9ff;
  }

  &.warning {
    background: #fdf6ec;
  }

  &.danger {
    background: #fef0f0;
  }
}

.stat-label {
  font-size: 14px;
  color: #666;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 24px;
  font-weight: 600;
  color: #333;
}

.section {
  margin-top: 32px;
}

.section-title {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #333;
}

.popular-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.popular-tag {
  cursor: pointer;
  transition: all 0.3s;

  &:hover {
    transform: scale(1.1);
  }
}

.index-actions {
  .index-info {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 20px;
  }

  .index-stat {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .label {
    font-size: 13px;
    color: #666;
  }

  .value {
    font-size: 14px;
    font-weight: 500;
    color: #333;
  }

  .index-buttons {
    display: flex;
    gap: 12px;
  }
}

@media (max-width: 768px) {
  .search-container {
    padding: 12px;
  }

  .stats-row {
    :deep(.el-col) {
      margin-bottom: 12px;
    }
  }

  .index-info {
    grid-template-columns: 1fr;
  }
}
</style>
