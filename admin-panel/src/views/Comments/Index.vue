<template>
  <div class="comments-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>评论管理</span>
          <el-badge :value="stats.pending" :hidden="stats.pending === 0">
            <el-button
              type="warning"
              size="small"
              @click="
                filters.status = 'hold';
                handleSearch();
              "
            >
              待审核
            </el-button>
          </el-badge>
        </div>
      </template>

      <!-- 统计卡片 -->
      <el-row :gutter="16" class="stats-row">
        <el-col :span="6">
          <div class="stat-item" @click="filterByStatus('')">
            <span class="stat-label">全部评论</span>
            <span class="stat-value">{{ stats.total }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-item success" @click="filterByStatus('approved')">
            <span class="stat-label">已通过</span>
            <span class="stat-value">{{ stats.approved }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-item warning" @click="filterByStatus('hold')">
            <span class="stat-label">待审核</span>
            <span class="stat-value">{{ stats.pending }}</span>
          </div>
        </el-col>
        <el-col :span="6">
          <div class="stat-item danger" @click="filterByStatus('spam')">
            <span class="stat-label">垃圾评论</span>
            <span class="stat-value">{{ stats.spam }}</span>
          </div>
        </el-col>
      </el-row>

      <!-- 搜索和筛选 -->
      <div class="filter-bar">
        <el-form :inline="true" :model="filters">
          <el-form-item>
            <el-input
              v-model="filters.search"
              placeholder="搜索评论内容或作者"
              :prefix-icon="Search"
              clearable
              @clear="handleSearch"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item>
            <el-select v-model="filters.status" placeholder="状态" clearable @change="handleSearch">
              <el-option label="全部" value="" />
              <el-option label="已通过" value="approved" />
              <el-option label="待审核" value="hold" />
              <el-option label="垃圾评论" value="spam" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
            <el-button :icon="Refresh" @click="handleReset">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 评论列表 -->
      <el-table
        :data="comments"
        v-loading="loading"
        style="width: 100%"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="作者" width="150">
          <template #default="{ row }">
            <div class="author-info">
              <el-avatar :size="32" :src="row.author_avatar">
                {{ row.author_name.charAt(0) }}
              </el-avatar>
              <div class="author-details">
                <p class="author-name">{{ row.author_name }}</p>
                <p class="author-email">{{ row.author_email }}</p>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="评论内容" min-width="300">
          <template #default="{ row }">
            <div class="comment-content">
              <p>{{ row.content }}</p>
              <div class="comment-meta">
                <el-link type="primary" :underline="false" @click="viewPost(row.post_id)">
                  评论于: {{ row.post_title }}
                </el-link>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="AI分析" width="120">
          <template #default="{ row }">
            <div v-if="row.ai_spam_score !== undefined" class="ai-analysis">
              <el-progress
                type="circle"
                :percentage="Math.round(row.ai_spam_score * 100)"
                :width="60"
                :color="getScoreColor(row.ai_spam_score)"
              />
              <p class="score-label">垃圾概率</p>
            </div>
            <el-button v-else link type="primary" size="small" @click="analyzeWithAI(row)">
              AI检测
            </el-button>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="getStatusType(row.status)" size="small">
              {{ getStatusLabel(row.status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="date" label="时间" width="180">
          <template #default="{ row }">
            {{ formatDate(row.date) }}
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.status !== 'approved'"
              link
              type="success"
              size="small"
              @click="approveComment(row)"
            >
              通过
            </el-button>
            <el-button link type="primary" size="small" @click="editComment(row)"> 编辑 </el-button>
            <el-button
              v-if="row.status !== 'spam'"
              link
              type="warning"
              size="small"
              @click="markAsSpam(row)"
            >
              垃圾
            </el-button>
            <el-button link type="danger" size="small" @click="deleteComment(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 批量操作 -->
      <div v-if="selectedComments.length > 0" class="batch-actions">
        <span>已选择 {{ selectedComments.length }} 项</span>
        <el-button size="small" type="success" @click="batchApprove">批量通过</el-button>
        <el-button size="small" type="warning" @click="batchSpam">批量标记为垃圾</el-button>
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

    <!-- 编辑评论对话框 -->
    <el-dialog
      v-model="editDialogVisible"
      title="编辑评论"
      width="600px"
      @close="editingComment = null"
    >
      <el-form v-if="editingComment" :model="editingComment" label-width="100px">
        <el-form-item label="作者姓名">
          <el-input v-model="editingComment.author_name" />
        </el-form-item>
        <el-form-item label="作者邮箱">
          <el-input v-model="editingComment.author_email" />
        </el-form-item>
        <el-form-item label="评论内容">
          <el-input v-model="editingComment.content" type="textarea" :rows="5" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="editingComment.status">
            <el-option label="已通过" value="approved" />
            <el-option label="待审核" value="hold" />
            <el-option label="垃圾评论" value="spam" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveComment">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';
import { commentsAPI } from '@/api';
import dayjs from 'dayjs';

const loading = ref(false);

// 统计数据
const stats = ref({
  total: 0,
  approved: 0,
  pending: 0,
  spam: 0,
});

// 筛选条件
const filters = ref({
  search: '',
  status: '',
});

// 评论列表
const comments = ref([]);

// 选中的评论
const selectedComments = ref([]);

// 分页
const pagination = ref({
  page: 1,
  per_page: 20,
  total: 0,
});

// 编辑对话框
const editDialogVisible = ref(false);
const editingComment = ref(null);

// 加载评论列表
async function loadComments() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.per_page,
      search: filters.value.search,
      status: filters.value.status,
    };
    const data = await commentsAPI.getComments(params);
    comments.value = data.comments || [];
    pagination.value.total = data.total || 0;
  } catch (error) {
    console.error('加载评论列表失败:', error);
    ElMessage.error('加载评论列表失败');
  } finally {
    loading.value = false;
  }
}

// 加载统计数据
async function loadStats() {
  try {
    const data = await commentsAPI.getStats();
    stats.value = data || stats.value;
  } catch (error) {
    console.error('加载统计数据失败:', error);
  }
}

// 按状态筛选
function filterByStatus(status) {
  filters.value.status = status;
  handleSearch();
}

// 搜索
function handleSearch() {
  pagination.value.page = 1;
  loadComments();
}

// 重置
function handleReset() {
  filters.value = {
    search: '',
    status: '',
  };
  pagination.value.page = 1;
  loadComments();
}

// 选择变化
function handleSelectionChange(selection) {
  selectedComments.value = selection;
}

// 查看文章
function viewPost(postId) {
  window.open(`/posts/${postId}`, '_blank');
}

// AI检测
async function analyzeWithAI(comment) {
  loading.value = true;
  try {
    const data = await commentsAPI.analyzeSpam(comment.id);
    comment.ai_spam_score = data.spam_score;
    ElMessage.success('AI分析完成');
  } catch (error) {
    console.error('AI分析失败:', error);
    ElMessage.error('AI分析失败');
  } finally {
    loading.value = false;
  }
}

// 获取分数颜色
function getScoreColor(score) {
  if (score < 0.3) return '#67c23a';
  if (score < 0.7) return '#e6a23c';
  return '#f56c6c';
}

// 通过评论
async function approveComment(comment) {
  loading.value = true;
  try {
    await commentsAPI.updateComment(comment.id, { status: 'approved' });
    ElMessage.success('评论已通过');
    loadComments();
    loadStats();
  } catch (error) {
    console.error('通过评论失败:', error);
    ElMessage.error('通过评论失败');
  } finally {
    loading.value = false;
  }
}

// 编辑评论
function editComment(comment) {
  editingComment.value = { ...comment };
  editDialogVisible.value = true;
}

// 保存评论
async function saveComment() {
  loading.value = true;
  try {
    await commentsAPI.updateComment(editingComment.value.id, editingComment.value);
    ElMessage.success('评论已更新');
    editDialogVisible.value = false;
    loadComments();
  } catch (error) {
    console.error('更新评论失败:', error);
    ElMessage.error('更新评论失败');
  } finally {
    loading.value = false;
  }
}

// 标记为垃圾
async function markAsSpam(comment) {
  loading.value = true;
  try {
    await commentsAPI.updateComment(comment.id, { status: 'spam' });
    ElMessage.success('已标记为垃圾评论');
    loadComments();
    loadStats();
  } catch (error) {
    console.error('标记失败:', error);
    ElMessage.error('标记失败');
  } finally {
    loading.value = false;
  }
}

// 删除评论
async function deleteComment(comment) {
  try {
    await ElMessageBox.confirm('确定要删除这条评论吗?', '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    });

    loading.value = true;
    await commentsAPI.deleteComment(comment.id);
    ElMessage.success('删除成功');
    loadComments();
    loadStats();
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error);
      ElMessage.error('删除失败');
    }
  } finally {
    loading.value = false;
  }
}

// 批量通过
async function batchApprove() {
  loading.value = true;
  try {
    const promises = selectedComments.value.map(comment =>
      commentsAPI.updateComment(comment.id, { status: 'approved' })
    );
    await Promise.all(promises);
    ElMessage.success('批量通过成功');
    loadComments();
    loadStats();
  } catch (error) {
    console.error('批量通过失败:', error);
    ElMessage.error('批量通过失败');
  } finally {
    loading.value = false;
  }
}

// 批量标记为垃圾
async function batchSpam() {
  loading.value = true;
  try {
    const promises = selectedComments.value.map(comment =>
      commentsAPI.updateComment(comment.id, { status: 'spam' })
    );
    await Promise.all(promises);
    ElMessage.success('批量标记成功');
    loadComments();
    loadStats();
  } catch (error) {
    console.error('批量标记失败:', error);
    ElMessage.error('批量标记失败');
  } finally {
    loading.value = false;
  }
}

// 批量删除
async function batchDelete() {
  try {
    await ElMessageBox.confirm(
      `确定要删除选中的 ${selectedComments.value.length} 条评论吗?`,
      '警告',
      {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
      }
    );

    loading.value = true;
    const promises = selectedComments.value.map(comment => commentsAPI.deleteComment(comment.id));
    await Promise.all(promises);
    ElMessage.success('批量删除成功');
    loadComments();
    loadStats();
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量删除失败:', error);
      ElMessage.error('批量删除失败');
    }
  } finally {
    loading.value = false;
  }
}

// 格式化日期
function formatDate(date) {
  return dayjs(date).format('YYYY-MM-DD HH:mm');
}

// 获取状态类型
function getStatusType(status) {
  const types = {
    approved: 'success',
    hold: 'warning',
    spam: 'danger',
  };
  return types[status] || 'info';
}

// 获取状态标签
function getStatusLabel(status) {
  const labels = {
    approved: '已通过',
    hold: '待审核',
    spam: '垃圾评论',
  };
  return labels[status] || status;
}

onMounted(() => {
  loadComments();
  loadStats();
});
</script>

<style lang="scss" scoped>
.comments-container {
  padding: 20px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stats-row {
  margin-bottom: 20px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: #f5f7fa;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s;

  &:hover {
    background: #e4e7ed;
    transform: translateY(-2px);
  }

  &.success {
    background: #f0f9ff;
    &:hover {
      background: #e1f3ff;
    }
  }

  &.warning {
    background: #fdf6ec;
    &:hover {
      background: #faecd8;
    }
  }

  &.danger {
    background: #fef0f0;
    &:hover {
      background: #fde2e2;
    }
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

.filter-bar {
  margin-bottom: 20px;
}

.author-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.author-details {
  flex: 1;
  min-width: 0;

  p {
    margin: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .author-name {
    font-weight: 500;
    font-size: 14px;
  }

  .author-email {
    font-size: 12px;
    color: #909399;
  }
}

.comment-content {
  p {
    margin: 0 0 8px 0;
    line-height: 1.6;
  }

  .comment-meta {
    font-size: 12px;
    color: #909399;
  }
}

.ai-analysis {
  text-align: center;

  .score-label {
    margin-top: 8px;
    font-size: 12px;
    color: #666;
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
  .comments-container {
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
