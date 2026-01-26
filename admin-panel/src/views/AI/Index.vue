<template>
  <div class="ai-container">
    <el-card>
      <template #header>
        <span>AI设置</span>
      </template>

      <!-- AI服务商配置 -->
      <div class="section">
        <h3 class="section-title">
          <el-icon><Setting /></el-icon>
          AI服务商配置
        </h3>
        <el-form :model="aiSettings" label-width="140px">
          <el-form-item label="服务商">
            <el-select v-model="aiSettings.provider" style="width: 300px">
              <el-option label="OpenAI" value="openai" />
              <el-option label="通义千问" value="qwen" />
              <el-option label="文心一言" value="wenxin" />
              <el-option label="Claude" value="claude" />
            </el-select>
          </el-form-item>
          <el-form-item label="API密钥">
            <el-input
              v-model="aiSettings.api_key"
              type="password"
              show-password
              placeholder="请输入API密钥"
              style="width: 400px"
            />
            <el-button
              link
              type="primary"
              :icon="Refresh"
              style="margin-left: 12px"
              @click="testConnection"
              :loading="testing"
            >
              测试连接
            </el-button>
          </el-form-item>
          <el-form-item label="模型名称">
            <el-input
              v-model="aiSettings.model"
              placeholder="例如: gpt-4, qwen-max"
              style="width: 300px"
            />
          </el-form-item>
          <el-form-item label="最大Token">
            <el-input-number v-model="aiSettings.max_tokens" :min="100" :max="128000" :step="100" />
          </el-form-item>
          <el-form-item label="温度参数">
            <el-slider
              v-model="aiSettings.temperature"
              :min="0"
              :max="2"
              :step="0.1"
              :marks="{ 0: '精确', 1: '平衡', 2: '创意' }"
              style="width: 300px"
            />
          </el-form-item>
        </el-form>
      </div>

      <!-- 功能开关 -->
      <div class="section">
        <h3 class="section-title">
          <el-icon><Switch /></el-icon>
          功能开关
        </h3>
        <el-form :model="featureConfig" label-width="180px">
          <el-form-item label="文章优化">
            <el-switch v-model="featureConfig.article_optimization" />
            <span style="margin-left: 12px; color: #999; font-size: 13px">
              使用AI优化文章内容
            </span>
          </el-form-item>
          <el-form-item label="智能搜索">
            <el-switch v-model="featureConfig.smart_search" />
            <span style="margin-left: 12px; color: #999; font-size: 13px">
              启用语义搜索和智能推荐
            </span>
          </el-form-item>
          <el-form-item label="内容推荐">
            <el-switch v-model="featureConfig.recommendation" />
            <span style="margin-left: 12px; color: #999; font-size: 13px">
              为用户提供个性化内容推荐
            </span>
          </el-form-item>
          <el-form-item label="代码生成">
            <el-switch v-model="featureConfig.code_generation" />
            <span style="margin-left: 12px; color: #999; font-size: 13px">
              生成代码片段和功能代码
            </span>
          </el-form-item>
          <el-form-item label="联网搜索">
            <el-switch v-model="featureConfig.web_search" />
            <span style="margin-left: 12px; color: #999; font-size: 13px">
              搜索网络获取最新信息
            </span>
          </el-form-item>
          <el-form-item label="图像生成">
            <el-switch v-model="featureConfig.image_generation" />
            <span style="margin-left: 12px; color: #999; font-size: 13px">
              AI生成文章配图和封面
            </span>
          </el-form-item>
        </el-form>
      </div>

      <!-- 使用统计 -->
      <div class="section">
        <h3 class="section-title">
          <el-icon><DataLine /></el-icon>
          使用统计
        </h3>
        <el-row :gutter="20">
          <el-col :span="6">
            <div class="usage-card">
              <div class="usage-icon">
                <el-icon :size="24"><ChatLineSquare /></el-icon>
              </div>
              <div class="usage-info">
                <p class="usage-label">今日请求</p>
                <h4 class="usage-value">{{ usage.today_requests }}</h4>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="usage-card">
              <div class="usage-icon">
                <el-icon :size="24"><Coin /></el-icon>
              </div>
              <div class="usage-info">
                <p class="usage-label">今日Token</p>
                <h4 class="usage-value">{{ usage.today_tokens.toLocaleString() }}</h4>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="usage-card">
              <div class="usage-icon">
                <el-icon :size="24"><Clock /></el-icon>
              </div>
              <div class="usage-info">
                <p class="usage-label">平均耗时</p>
                <h4 class="usage-value">{{ usage.avg_time }}ms</h4>
              </div>
            </div>
          </el-col>
          <el-col :span="6">
            <div class="usage-card">
              <div class="usage-icon">
                <el-icon :size="24"><CircleCheck /></el-icon>
              </div>
              <div class="usage-info">
                <p class="usage-label">成功率</p>
                <h4 class="usage-value">{{ usage.success_rate }}%</h4>
              </div>
            </div>
          </el-col>
        </el-row>

        <!-- 使用趋势图 -->
        <el-card class="trend-card" shadow="never">
          <v-chart class="chart" :option="usageChartOption" autoresize />
        </el-card>
      </div>

      <!-- 最近任务 -->
      <div class="section">
        <h3 class="section-title">
          <el-icon><List /></el-icon>
          最近任务
        </h3>
        <el-table :data="recentTasks" style="width: 100%">
          <el-table-column prop="id" label="ID" width="80" />
          <el-table-column prop="type" label="类型" width="120">
            <template #default="{ row }">
              <el-tag :type="getTaskTypeColor(row.type)" size="small">
                {{ getTaskTypeLabel(row.type) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="status" label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="getStatusType(row.status)" size="small">
                {{ getStatusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="tokens_used" label="Token消耗" width="120" />
          <el-table-column prop="cost" label="费用" width="100">
            <template #default="{ row }"> ${{ row.cost?.toFixed(4) || '0.0000' }} </template>
          </el-table-column>
          <el-table-column prop="created_at" label="创建时间" width="180">
            <template #default="{ row }">
              {{ formatDate(row.created_at) }}
            </template>
          </el-table-column>
          <el-table-column label="操作" width="100">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="viewTaskDetail(row)">
                详情
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- 保存按钮 -->
      <div class="save-actions">
        <el-button type="primary" size="large" :loading="saving" @click="saveSettings">
          保存设置
        </el-button>
        <el-button size="large" @click="resetSettings"> 重置默认 </el-button>
      </div>
    </el-card>

    <!-- 任务详情对话框 -->
    <el-dialog v-model="taskDetailVisible" title="任务详情" width="700px">
      <el-descriptions v-if="currentTask" :column="2" border>
        <el-descriptions-item label="任务ID">{{ currentTask.id }}</el-descriptions-item>
        <el-descriptions-item label="任务类型">{{
          getTaskTypeLabel(currentTask.type)
        }}</el-descriptions-item>
        <el-descriptions-item label="任务状态">{{
          getStatusLabel(currentTask.status)
        }}</el-descriptions-item>
        <el-descriptions-item label="输入内容">
          <div class="task-input">{{ currentTask.input }}</div>
        </el-descriptions-item>
        <el-descriptions-item label="Token消耗">{{ currentTask.tokens_used }}</el-descriptions-item>
        <el-descriptions-item label="费用"
          >${{ currentTask.cost?.toFixed(4) || '0.0000' }}</el-descriptions-item
        >
        <el-descriptions-item label="创建时间">{{
          formatDate(currentTask.created_at)
        }}</el-descriptions-item>
        <el-descriptions-item label="完成时间" v-if="currentTask.completed_at">
          {{ formatDate(currentTask.completed_at) }}
        </el-descriptions-item>
      </el-descriptions>
      <div v-if="currentTask.error" class="task-error">
        <p class="error-label">错误信息:</p>
        <pre class="error-content">{{ currentTask.error }}</pre>
      </div>
      <template #footer>
        <el-button @click="taskDetailVisible = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
  Setting,
  Switch,
  DataLine,
  Refresh,
  ChatLineSquare,
  Coin,
  Clock,
  CircleCheck,
  List,
} from '@element-plus/icons-vue';
import { aiAPI } from '@/api';
import dayjs from 'dayjs';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { LineChart } from 'echarts/charts';
import {
  TitleComponent,
  TooltipComponent,
  LegendComponent,
  GridComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';

// 注册ECharts组件
use([CanvasRenderer, LineChart, TitleComponent, TooltipComponent, LegendComponent, GridComponent]);

const testing = ref(false);
const saving = ref(false);
const taskDetailVisible = ref(false);
const currentTask = ref(null);

// AI设置
const aiSettings = ref({
  provider: 'openai',
  api_key: '',
  model: 'gpt-4',
  max_tokens: 4000,
  temperature: 0.7,
});

// 功能配置
const featureConfig = ref({
  article_optimization: true,
  smart_search: true,
  recommendation: true,
  code_generation: true,
  web_search: true,
  image_generation: true,
});

// 使用统计
const usage = ref({
  today_requests: 0,
  today_tokens: 0,
  avg_time: 0,
  success_rate: 0,
});

// 最近任务
const recentTasks = ref([]);

// 使用趋势数据
const usageData = ref({
  dates: [],
  requests: [],
  tokens: [],
});

// 使用趋势图表配置
const usageChartOption = computed(() => ({
  tooltip: {
    trigger: 'axis',
    axisPointer: {
      type: 'shadow',
    },
  },
  legend: {
    data: ['请求数', 'Token数'],
  },
  grid: {
    left: '3%',
    right: '4%',
    bottom: '3%',
    containLabel: true,
  },
  xAxis: {
    type: 'category',
    data: usageData.value.dates,
    boundaryGap: false,
  },
  yAxis: [
    {
      type: 'value',
      name: '请求数',
      position: 'left',
    },
    {
      type: 'value',
      name: 'Token数',
      position: 'right',
    },
  ],
  series: [
    {
      name: '请求数',
      type: 'line',
      smooth: true,
      data: usageData.value.requests,
      itemStyle: {
        color: '#667eea',
      },
    },
    {
      name: 'Token数',
      type: 'line',
      smooth: true,
      data: usageData.value.tokens,
      itemStyle: {
        color: '#f093fb',
      },
    },
  ],
}));

// 加载AI设置
async function loadSettings() {
  try {
    const data = await aiAPI.getSettings();
    if (data) {
      aiSettings.value = {
        provider: data.provider || 'openai',
        api_key: data.api_key || '',
        model: data.model || 'gpt-4',
        max_tokens: data.max_tokens || 4000,
        temperature: data.temperature || 0.7,
      };
      featureConfig.value = data.features || featureConfig.value;
    }
  } catch (error) {
    console.error('加载AI设置失败:', error);
  }
}

// 加载使用统计
async function loadUsage() {
  try {
    const data = await aiAPI.getUsage();
    usage.value = {
      today_requests: data.today_requests || 0,
      today_tokens: data.today_tokens || 0,
      avg_time: data.avg_time || 0,
      success_rate: data.success_rate || 0,
    };
    // 加载趋势数据
    loadUsageTrend();
  } catch (error) {
    console.error('加载使用统计失败:', error);
  }
}

// 加载使用趋势
async function loadUsageTrend() {
  try {
    const data = await aiAPI.getUsageTrend(7);
    usageData.value = {
      dates: data.dates || [],
      requests: data.requests || [],
      tokens: data.tokens || [],
    };
  } catch (error) {
    console.error('加载使用趋势失败:', error);
  }
}

// 加载最近任务
async function loadRecentTasks() {
  try {
    const data = await aiAPI.getTasks(20);
    recentTasks.value = data || [];
  } catch (error) {
    console.error('加载最近任务失败:', error);
  }
}

// 测试连接
async function testConnection() {
  testing.value = true;
  try {
    await aiAPI.testConnection(aiSettings.value.provider, aiSettings.value.api_key);
    ElMessage.success('连接测试成功');
  } catch (error) {
    console.error('连接测试失败:', error);
    ElMessage.error('连接测试失败，请检查API密钥');
  } finally {
    testing.value = false;
  }
}

// 保存设置
async function saveSettings() {
  saving.value = true;
  try {
    await aiAPI.saveSettings({
      ...aiSettings.value,
      features: featureConfig.value,
    });
    ElMessage.success('设置保存成功');
  } catch (error) {
    console.error('保存设置失败:', error);
    ElMessage.error('保存设置失败');
  } finally {
    saving.value = false;
  }
}

// 重置设置
function resetSettings() {
  aiSettings.value = {
    provider: 'openai',
    api_key: '',
    model: 'gpt-4',
    max_tokens: 4000,
    temperature: 0.7,
  };
  ElMessage.info('已重置为默认设置，请点击保存按钮应用');
}

// 查看任务详情
function viewTaskDetail(task) {
  currentTask.value = task;
  taskDetailVisible.value = true;
}

// 获取任务类型标签
function getTaskTypeLabel(type) {
  const labels = {
    optimize_article: '文章优化',
    generate_outline: '生成大纲',
    generate_image: '生成图片',
    search: '智能搜索',
    recommend: '内容推荐',
    generate_code: '代码生成',
    web_search: '联网搜索',
  };
  return labels[type] || type;
}

// 获取任务类型颜色
function getTaskTypeColor(type) {
  const colors = {
    optimize_article: 'primary',
    generate_outline: 'success',
    generate_image: 'warning',
    search: 'info',
    recommend: 'primary',
    generate_code: 'success',
    web_search: 'warning',
  };
  return colors[type] || '';
}

// 获取状态标签
function getStatusLabel(status) {
  const labels = {
    queued: '排队中',
    processing: '处理中',
    completed: '已完成',
    failed: '失败',
  };
  return labels[status] || status;
}

// 获取状态类型
function getStatusType(status) {
  const types = {
    queued: 'info',
    processing: 'warning',
    completed: 'success',
    failed: 'danger',
  };
  return types[status] || 'info';
}

// 格式化日期
function formatDate(date) {
  if (!date) return '-';
  return dayjs(date).format('YYYY-MM-DD HH:mm:ss');
}

onMounted(() => {
  loadSettings();
  loadUsage();
  loadRecentTasks();
});
</script>

<style lang="scss" scoped>
.ai-container {
  padding: 20px;
}

.section {
  margin-top: 32px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 0 20px 0;
  font-size: 16px;
  font-weight: 600;
  color: #333;
}

.usage-card {
  display: flex;
  align-items: center;
  padding: 20px;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
}

.usage-icon {
  width: 48px;
  height: 48px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
  margin-right: 16px;
}

.usage-info {
  flex: 1;
}

.usage-label {
  margin: 0 0 4px 0;
  font-size: 13px;
  color: #666;
}

.usage-value {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #333;
}

.trend-card {
  margin-top: 20px;

  :deep(.el-card__body) {
    padding: 20px;
    height: 300px;
  }
}

.chart {
  width: 100%;
  height: 260px;
}

.task-input {
  max-height: 200px;
  overflow-y: auto;
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  font-size: 13px;
  font-family: 'Courier New', monospace;
}

.task-error {
  margin-top: 16px;
  padding: 16px;
  background: #fef0f0;
  border-radius: 4px;
}

.error-label {
  margin: 0 0 8px 0;
  font-size: 14px;
  font-weight: 600;
  color: #f56c6c;
}

.error-content {
  margin: 0;
  max-height: 300px;
  overflow-y: auto;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  color: #f56c6c;
}

.save-actions {
  margin-top: 40px;
  padding-top: 20px;
  border-top: 1px solid #ebeef5;
  display: flex;
  gap: 12px;
  justify-content: center;
}

@media (max-width: 768px) {
  .ai-container {
    padding: 12px;
  }

  .usage-card {
    padding: 16px;
  }

  .save-actions {
    flex-direction: column;

    .el-button {
      width: 100%;
    }
  }
}
</style>
