<template>
  <div class="dashboard-container">
    <el-row :gutter="20">
      <!-- 统计卡片 -->
      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <el-icon :size="32"><Document /></el-icon>
          </div>
          <div class="stat-content">
            <p class="stat-title">文章总数</p>
            <h2 class="stat-value">{{ stats.posts }}</h2>
            <p class="stat-trend">
              <span :class="['trend', stats.postsGrowth >= 0 ? 'up' : 'down']">
                <el-icon><CaretTop v-if="stats.postsGrowth >= 0" /><CaretBottom v-else /></el-icon>
                {{ Math.abs(stats.postsGrowth) }}%
              </span>
              <span class="trend-label">较上月</span>
            </p>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <el-icon :size="32"><ChatDotRound /></el-icon>
          </div>
          <div class="stat-content">
            <p class="stat-title">评论总数</p>
            <h2 class="stat-value">{{ stats.comments }}</h2>
            <p class="stat-trend">
              <span :class="['trend', stats.commentsGrowth >= 0 ? 'up' : 'down']">
                <el-icon><CaretTop v-if="stats.commentsGrowth >= 0" /><CaretBottom v-else /></el-icon>
                {{ Math.abs(stats.commentsGrowth) }}%
              </span>
              <span class="trend-label">较上月</span>
            </p>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <el-icon :size="32"><User /></el-icon>
          </div>
          <div class="stat-content">
            <p class="stat-title">用户总数</p>
            <h2 class="stat-value">{{ stats.users }}</h2>
            <p class="stat-trend">
              <span :class="['trend', stats.usersGrowth >= 0 ? 'up' : 'down']">
                <el-icon><CaretTop v-if="stats.usersGrowth >= 0" /><CaretBottom v-else /></el-icon>
                {{ Math.abs(stats.usersGrowth) }}%
              </span>
              <span class="trend-label">较上月</span>
            </p>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :sm="12" :lg="6">
        <div class="stat-card">
          <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <el-icon :size="32"><View /></el-icon>
          </div>
          <div class="stat-content">
            <p class="stat-title">浏览量</p>
            <h2 class="stat-value">{{ formatNumber(stats.views) }}</h2>
            <p class="stat-trend">
              <span :class="['trend', stats.viewsGrowth >= 0 ? 'up' : 'down']">
                <el-icon><CaretTop v-if="stats.viewsGrowth >= 0" /><CaretBottom v-else /></el-icon>
                {{ Math.abs(stats.viewsGrowth) }}%
              </span>
              <span class="trend-label">较上月</span>
            </p>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <!-- 访问量趋势图 -->
      <el-col :xs="24" :lg="16">
        <el-card class="chart-card">
          <template #header>
            <div class="card-header">
              <span>访问量趋势</span>
              <el-radio-group v-model="visitsPeriod" size="small" @change="loadVisitsData">
                <el-radio-button label="week">最近7天</el-radio-button>
                <el-radio-button label="month">最近30天</el-radio-button>
              </el-radio-group>
            </div>
          </template>
          <v-chart class="chart" :option="visitsChartOption" :loading="chartsLoading" autoresize />
        </el-card>
      </el-col>

      <!-- 内容分布 -->
      <el-col :xs="24" :lg="8">
        <el-card class="chart-card">
          <template #header>
            <span>内容分布</span>
          </template>
          <v-chart class="chart" :option="contentChartOption" :loading="chartsLoading" autoresize />
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" style="margin-top: 20px;">
      <!-- 最新文章 -->
      <el-col :xs="24" :lg="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>最新文章</span>
              <el-link type="primary" :underline="false" @click="$router.push('/posts')">查看全部</el-link>
            </div>
          </template>
          <el-table :data="recentPosts" style="width: 100%" v-loading="loading">
            <el-table-column prop="title" label="标题" min-width="200">
              <template #default="{ row }">
                <el-link type="primary" :underline="false" @click="editPost(row.id)">
                  {{ row.title }}
                </el-link>
              </template>
            </el-table-column>
            <el-table-column prop="author" label="作者" width="100" />
            <el-table-column prop="date" label="发布时间" width="180">
              <template #default="{ row }">
                {{ formatDate(row.date) }}
              </template>
            </el-table-column>
            <el-table-column prop="status" label="状态" width="80">
              <template #default="{ row }">
                <el-tag :type="getStatusType(row.status)" size="small">
                  {{ getStatusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>

      <!-- 最新评论 -->
      <el-col :xs="24" :lg="12">
        <el-card>
          <template #header>
            <div class="card-header">
              <span>最新评论</span>
              <el-link type="primary" :underline="false" @click="$router.push('/comments')">查看全部</el-link>
            </div>
          </template>
          <el-table :data="recentComments" style="width: 100%" v-loading="loading">
            <el-table-column prop="author" label="作者" width="100" />
            <el-table-column prop="content" label="内容" min-width="200" show-overflow-tooltip />
            <el-table-column prop="post_title" label="文章" width="150" show-overflow-tooltip />
            <el-table-column prop="date" label="时间" width="180">
              <template #default="{ row }">
                {{ formatDate(row.date) }}
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <!-- 快捷操作 -->
    <el-row :gutter="20" style="margin-top: 20px;">
      <el-col :span="24">
        <el-card>
          <template #header>
            <span>快捷操作</span>
          </template>
          <div class="quick-actions">
            <el-button type="primary" :icon="EditPen" @click="$router.push('/posts/create')">
              写文章
            </el-button>
            <el-button :icon="Picture" @click="$router.push('/gallery')">
              上传3D模型
            </el-button>
            <el-button :icon="Setting" @click="$router.push('/ai')">
              AI设置
            </el-button>
            <el-button :icon="Search" @click="$router.push('/search')">
              搜索管理
            </el-button>
            <el-button :icon="UserFilled" @click="$router.push('/users')">
              用户管理
            </el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  Document, ChatDotRound, User, View, CaretTop, CaretBottom,
  EditPen, Picture, Setting, Search, UserFilled
} from '@element-plus/icons-vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { LineChart, PieChart } from 'echarts/charts'
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent
} from 'echarts/components'
import { statsAPI, postsAPI, commentsAPI } from '@/api'
import dayjs from 'dayjs'

// 注册ECharts组件
use([
  CanvasRenderer,
  LineChart,
  PieChart,
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent
])

const router = useRouter()
const loading = ref(false)
const chartsLoading = ref(false)
const visitsPeriod = ref('week')

// 统计数据
const stats = ref({
  posts: 0,
  comments: 0,
  users: 0,
  views: 0,
  postsGrowth: 0,
  commentsGrowth: 0,
  usersGrowth: 0,
  viewsGrowth: 0
})

// 最新文章
const recentPosts = ref([])

// 最新评论
const recentComments = ref([])

// 访问量数据
const visitsData = ref({
  dates: [],
  visits: []
})

// 内容分布数据
const contentData = ref({
  published: 0,
  draft: 0,
  pending: 0
})

// 访问量图表配置
const visitsChartOption = computed(() => ({
  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'shadow'
    }
  },
  grid: {
    left: '3%',
    right: '4%',
    bottom: '3%',
    containLabel: true
  },
  xAxis: {
    type: 'category',
    data: visitsData.value.dates,
    boundaryGap: false
  },
  yAxis: {
    type: 'value'
  },
  series: [
    {
      name: '访问量',
      type: 'line',
      smooth: true,
      areaStyle: {
        color: {
          type: 'linear',
          x: 0,
          y: 0,
          x2: 0,
          y2: 1,
          colorStops: [
            { offset: 0, color: 'rgba(102, 126, 234, 0.6)' },
            { offset: 1, color: 'rgba(102, 126, 234, 0.1)' }
          ]
        }
      },
      lineStyle: {
        color: '#667eea',
        width: 3
      },
      itemStyle: {
        color: '#667eea'
      },
      data: visitsData.value.visits
    }
  ]
}))

// 内容分布图表配置
const contentChartOption = computed(() => ({
  tooltip: {
    trigger: 'item',
    formatter: '{b}: {c} ({d}%)'
  },
  legend: {
    orient: 'vertical',
    left: 'left'
  },
  series: [
    {
      type: 'pie',
      radius: ['40%', '70%'],
      avoidLabelOverlap: false,
      itemStyle: {
        borderRadius: 10,
        borderColor: '#fff',
        borderWidth: 2
      },
      label: {
        show: true,
        formatter: '{b}: {d}%'
      },
      data: [
        { value: contentData.value.published, name: '已发布', itemStyle: { color: '#67c23a' } },
        { value: contentData.value.draft, name: '草稿', itemStyle: { color: '#909399' } },
        { value: contentData.value.pending, name: '待审核', itemStyle: { color: '#e6a23c' } }
      ]
    }
  ]
}))

// 加载仪表盘数据
async function loadDashboardData() {
  loading.value = true
  try {
    const data = await statsAPI.getDashboard()
    stats.value = data.stats || stats.value
    recentPosts.value = (data.recent_posts || []).slice(0, 5)
    recentComments.value = (data.recent_comments || []).slice(0, 5)
    contentData.value = data.content_stats || contentData.value
  } catch (error) {
    console.error('加载仪表盘数据失败:', error)
    ElMessage.error('加载数据失败')
  } finally {
    loading.value = false
  }
}

// 加载访问量数据
async function loadVisitsData() {
  chartsLoading.value = true
  try {
    const data = await statsAPI.getVisits({ period: visitsPeriod.value })
    visitsData.value = data
  } catch (error) {
    console.error('加载访问量数据失败:', error)
  } finally {
    chartsLoading.value = false
  }
}

// 格式化数字
function formatNumber(num) {
  if (num >= 10000) {
    return (num / 10000).toFixed(1) + 'w'
  }
  if (num >= 1000) {
    return (num / 1000).toFixed(1) + 'k'
  }
  return num
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

// 编辑文章
function editPost(id) {
  router.push(`/posts/edit/${id}`)
}

onMounted(() => {
  loadDashboardData()
  loadVisitsData()
})
</script>

<style lang="scss" scoped>
.dashboard-container {
  padding: 20px;
}

.stat-card {
  display: flex;
  align-items: center;
  padding: 24px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.05);
  transition: all 0.3s;
  cursor: pointer;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px 0 rgba(0, 0, 0, 0.1);
  }
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 12px;
  color: #fff;
  margin-right: 20px;
}

.stat-content {
  flex: 1;
}

.stat-title {
  margin: 0 0 8px 0;
  font-size: 14px;
  color: #666;
}

.stat-value {
  margin: 0 0 8px 0;
  font-size: 28px;
  font-weight: 600;
  color: #333;
}

.stat-trend {
  margin: 0;
  font-size: 12px;
  color: #999;
}

.trend {
  display: inline-flex;
  align-items: center;
  margin-right: 8px;

  &.up {
    color: #67c23a;
  }

  &.down {
    color: #f56c6c;
  }
}

.chart-card {
  :deep(.el-card__body) {
    padding: 20px;
    height: 350px;
  }
}

.chart {
  width: 100%;
  height: 300px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.quick-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

/* 响应式设计 */
@media (max-width: 768px) {
  .dashboard-container {
    padding: 12px;
  }

  .stat-card {
    padding: 16px;
  }

  .stat-icon {
    width: 48px;
    height: 48px;
    margin-right: 12px;
  }

  .stat-value {
    font-size: 24px;
  }

  .quick-actions {
    .el-button {
      flex: 1;
      min-width: calc(50% - 6px);
    }
  }
}
</style>
