<template>
  <div class="gallery-container">
    <el-card>
      <template #header>
        <div class="card-header">
          <span>3D图库</span>
          <el-button type="primary" :icon="Plus" @click="handleUpload"> 上传模型 </el-button>
        </div>
      </template>

      <!-- 搜索和筛选 -->
      <div class="filter-bar">
        <el-form :inline="true" :model="filters">
          <el-form-item>
            <el-input
              v-model="filters.search"
              placeholder="搜索模型名称"
              :prefix-icon="Search"
              clearable
              @clear="handleSearch"
              @keyup.enter="handleSearch"
            />
          </el-form-item>
          <el-form-item>
            <el-select
              v-model="filters.category"
              placeholder="分类"
              clearable
              @change="handleSearch"
            >
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

      <!-- 模型网格 -->
      <div v-loading="loading" class="models-grid">
        <div v-for="model in models" :key="model.id" class="model-card" @click="viewModel(model)">
          <div class="model-thumbnail">
            <el-image v-if="model.thumbnail" :src="model.thumbnail" fit="cover" lazy>
              <template #placeholder>
                <div class="image-placeholder">
                  <el-icon :size="40"><Picture /></el-icon>
                </div>
              </template>
            </el-image>
            <div v-else class="image-placeholder">
              <el-icon :size="40"><Picture /></el-icon>
            </div>
            <el-tag
              v-if="model.status === 'pending'"
              type="warning"
              size="small"
              class="status-tag"
            >
              待审核
            </el-tag>
          </div>
          <div class="model-info">
            <h4 class="model-title">{{ model.title }}</h4>
            <p class="model-description">{{ model.description }}</p>
            <div class="model-meta">
              <span class="format-tag">
                <el-tag size="small" type="info">{{ model.format }}</el-tag>
              </span>
              <span class="view-count">
                <el-icon><View /></el-icon> {{ model.view_count || 0 }}
              </span>
              <span class="download-count">
                <el-icon><Download /></el-icon> {{ model.download_count || 0 }}
              </span>
            </div>
          </div>
        </div>

        <!-- 空状态 -->
        <div v-if="!loading && models.length === 0" class="empty-state">
          <el-empty description="暂无3D模型" />
        </div>
      </div>

      <!-- 分页 -->
      <div class="pagination">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :page-sizes="[12, 24, 48, 96]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSearch"
          @current-change="handleSearch"
        />
      </div>
    </el-card>

    <!-- 上传对话框 -->
    <el-dialog v-model="uploadDialogVisible" title="上传3D模型" width="700px">
      <el-form :model="uploadForm" :rules="uploadRules" label-width="100px">
        <el-form-item label="模型文件" prop="file" required>
          <el-upload
            ref="uploadRef"
            :auto-upload="false"
            :limit="1"
            :on-change="handleFileChange"
            accept=".glb,.gltf,.obj,.fbx"
            drag
          >
            <el-button type="primary" :icon="Upload"> 选择文件 </el-button>
            <template #tip>
              <div class="el-upload__tip">
                支持格式: GLB, GLTF, OBJ, FBX<br />
                最大文件大小: 100MB
              </div>
            </template>
          </el-upload>
          <div v-if="uploadForm.file" class="selected-file">
            <el-icon><Document /></el-icon> {{ uploadForm.file.name }}
          </div>
        </el-form-item>
        <el-form-item label="模型标题" prop="title">
          <el-input v-model="uploadForm.title" placeholder="请输入模型标题" />
        </el-form-item>
        <el-form-item label="模型描述" prop="description">
          <el-input
            v-model="uploadForm.description"
            type="textarea"
            :rows="3"
            placeholder="请输入模型描述"
          />
        </el-form-item>
        <el-form-item label="分类" prop="category">
          <el-select v-model="uploadForm.category" placeholder="请选择分类" style="width: 100%">
            <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="缩略图">
          <el-upload
            :auto-upload="false"
            :limit="1"
            :on-change="handleThumbChange"
            accept="image/*"
          >
            <el-button>选择缩略图</el-button>
          </el-upload>
          <div v-if="uploadForm.thumbnail" class="thumb-preview">
            <el-image :src="uploadForm.thumbnail" style="width: 100px; height: 100px" fit="cover" />
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="uploadDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="uploading" @click="submitUpload">上传</el-button>
      </template>
    </el-dialog>

    <!-- 模型详情对话框 -->
    <el-dialog v-model="detailDialogVisible" :title="currentModel?.title" width="800px">
      <el-tabs v-if="currentModel">
        <el-tab-pane label="预览">
          <div class="model-viewer">
            <div class="viewer-container" ref="viewerContainer">
              <iframe
                v-if="currentModel.model_url"
                :src="currentModel.model_url"
                frameborder="0"
                allowfullscreen
                class="viewer-iframe"
              ></iframe>
            </div>
          </div>
        </el-tab-pane>
        <el-tab-pane label="信息">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="模型ID">{{ currentModel.id }}</el-descriptions-item>
            <el-descriptions-item label="模型名称">{{ currentModel.title }}</el-descriptions-item>
            <el-descriptions-item label="文件格式">{{ currentModel.format }}</el-descriptions-item>
            <el-descriptions-item label="文件大小">{{
              formatFileSize(currentModel.file_size)
            }}</el-descriptions-item>
            <el-descriptions-item label="上传时间">{{
              formatDate(currentModel.created_at)
            }}</el-descriptions-item>
            <el-descriptions-item label="分类">{{
              currentModel.category?.name
            }}</el-descriptions-item>
            <el-descriptions-item label="浏览次数">{{
              currentModel.view_count || 0
            }}</el-descriptions-item>
            <el-descriptions-item label="下载次数">{{
              currentModel.download_count || 0
            }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>
        <el-tab-pane label="设置">
          <el-form :model="viewerConfig" label-width="100px">
            <el-form-item label="自动旋转">
              <el-switch v-model="viewerConfig.auto_rotate" />
            </el-form-item>
            <el-form-item label="灯光模式">
              <el-select v-model="viewerConfig.lighting" style="width: 100%">
                <el-option label="工作室" value="studio" />
                <el-option label="环境" value="environment" />
                <el-option label="定向" value="directional" />
              </el-select>
            </el-form-item>
            <el-form-item label="背景颜色">
              <el-color-picker v-model="viewerConfig.background_color" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="saveViewerConfig">保存设置</el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
      <template #footer>
        <el-button @click="detailDialogVisible = false">关闭</el-button>
        <el-button type="primary" @click="downloadModel">下载模型</el-button>
        <el-button type="warning" @click="editModel">编辑</el-button>
        <el-button type="danger" @click="deleteModel">删除</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  Plus,
  Search,
  Refresh,
  Picture,
  View,
  Download,
  Document,
  Upload,
} from '@element-plus/icons-vue';
import { galleryAPI } from '@/api';
import dayjs from 'dayjs';

const loading = ref(false);
const uploading = ref(false);

// 筛选条件
const filters = ref({
  search: '',
  category: '',
});

// 分类列表
const categories = ref([]);

// 模型列表
const models = ref([]);

// 分页
const pagination = ref({
  page: 1,
  per_page: 24,
  total: 0,
});

// 上传对话框
const uploadDialogVisible = ref(false);
const uploadRef = ref(null);

// 上传表单
const uploadForm = ref({
  file: null,
  title: '',
  description: '',
  category: '',
  thumbnail: '',
});

// 上传表单验证规则
const uploadRules = {
  title: [{ required: true, message: '请输入模型标题', trigger: 'blur' }],
  file: [{ required: true, message: '请选择模型文件', trigger: 'change' }],
};

// 详情对话框
const detailDialogVisible = ref(false);
const currentModel = ref(null);
const viewerContainer = ref(null);

// 查看器配置
const viewerConfig = ref({
  auto_rotate: true,
  lighting: 'studio',
  background_color: '#ffffff',
});

// 加载模型列表
async function loadModels() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.per_page,
      search: filters.value.search,
      category: filters.value.category,
    };
    const data = await galleryAPI.getModels(params);
    models.value = data.models || [];
    pagination.value.total = data.total || 0;
  } catch (error) {
    console.error('加载模型列表失败:', error);
    ElMessage.error('加载模型列表失败');
  } finally {
    loading.value = false;
  }
}

// 加载分类列表
async function loadCategories() {
  try {
    const data = await galleryAPI.getCategories();
    categories.value = data || [];
  } catch (error) {
    console.error('加载分类失败:', error);
  }
}

// 搜索
function handleSearch() {
  pagination.value.page = 1;
  loadModels();
}

// 重置
function handleReset() {
  filters.value = {
    search: '',
    category: '',
  };
  pagination.value.page = 1;
  loadModels();
}

// 上传模型
function handleUpload() {
  uploadForm.value = {
    file: null,
    title: '',
    description: '',
    category: '',
    thumbnail: '',
  };
  uploadDialogVisible.value = true;
}

// 文件变化
function handleFileChange(file) {
  uploadForm.value.file = file.raw;
}

// 缩略图变化
function handleThumbChange(file) {
  uploadForm.value.thumbnail = URL.createObjectURL(file.raw);
}

// 提交上传
async function submitUpload() {
  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('model', uploadForm.value.file);
    formData.append('title', uploadForm.value.title);
    formData.append('description', uploadForm.value.description);
    formData.append('category', uploadForm.value.category);

    await galleryAPI.uploadModel(formData);
    ElMessage.success('上传成功');
    uploadDialogVisible.value = false;
    loadModels();
  } catch (error) {
    console.error('上传失败:', error);
    ElMessage.error('上传失败');
  } finally {
    uploading.value = false;
  }
}

// 查看模型
function viewModel(model) {
  currentModel.value = model;
  viewerConfig.value = model.viewer_config || {
    auto_rotate: true,
    lighting: 'studio',
    background_color: '#ffffff',
  };
  detailDialogVisible.value = true;
}

// 下载模型
function downloadModel() {
  if (!currentModel.value?.model_url) return;
  window.open(currentModel.value.model_url, '_blank');
}

// 编辑模型
function editModel() {
  ElMessage.info('编辑功能开发中');
  // TODO: 实现编辑功能
}

// 删除模型
async function deleteModel() {
  try {
    await ElMessageBox.confirm(`确定要删除模型"${currentModel.value.title}"吗?`, '警告', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
    });

    loading.value = true;
    await galleryAPI.deleteModel(currentModel.value.id);
    ElMessage.success('删除成功');
    detailDialogVisible.value = false;
    loadModels();
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error);
      ElMessage.error('删除失败');
    }
  } finally {
    loading.value = false;
  }
}

// 保存查看器配置
async function saveViewerConfig() {
  try {
    await galleryAPI.updateModel(currentModel.value.id, {
      viewer_config: viewerConfig.value,
    });
    ElMessage.success('设置保存成功');
  } catch (error) {
    console.error('保存设置失败:', error);
    ElMessage.error('保存设置失败');
  }
}

// 格式化文件大小
function formatFileSize(bytes) {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

// 格式化日期
function formatDate(date) {
  return dayjs(date).format('YYYY-MM-DD HH:mm');
}

onMounted(() => {
  loadModels();
  loadCategories();
});
</script>

<style lang="scss" scoped>
.gallery-container {
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

.models-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
  min-height: 400px;
}

.model-card {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  transition: all 0.3s;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }
}

.model-thumbnail {
  position: relative;
  width: 100%;
  height: 200px;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;

  :deep(.el-image) {
    width: 100%;
    height: 100%;
  }
}

.image-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  color: #909399;
}

.status-tag {
  position: absolute;
  top: 10px;
  right: 10px;
}

.model-info {
  padding: 16px;
}

.model-title {
  margin: 0 0 8px 0;
  font-size: 16px;
  font-weight: 600;
  color: #333;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.model-description {
  margin: 0 0 12px 0;
  font-size: 13px;
  color: #666;
  line-height: 1.5;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.model-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  color: #999;

  span {
    display: flex;
    align-items: center;
    gap: 4px;
  }
}

.empty-state {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px;
}

.pagination {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}

.selected-file {
  margin-top: 12px;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #606266;
}

.thumb-preview {
  margin-top: 8px;
}

.model-viewer {
  width: 100%;
  height: 500px;
}

.viewer-container {
  width: 100%;
  height: 100%;
  background: #f0f2f5;
  border-radius: 4px;
  overflow: hidden;
}

.viewer-iframe {
  width: 100%;
  height: 100%;
  border: none;
}

@media (max-width: 768px) {
  .gallery-container {
    padding: 12px;
  }

  .models-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 12px;
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
