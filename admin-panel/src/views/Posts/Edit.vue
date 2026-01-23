<template>
  <div class="post-edit-container">
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
      v-loading="loading"
    >
      <el-card>
        <template #header>
          <div class="card-header">
            <span>{{ isEdit ? '编辑文章' : '写文章' }}</span>
            <div class="header-actions">
              <el-button @click="$router.back()">返回</el-button>
              <el-button type="info" @click="saveDraft">保存草稿</el-button>
              <el-button type="primary" @click="publish">发布</el-button>
            </div>
          </div>
        </template>

        <el-row :gutter="20">
          <el-col :span="18">
            <!-- 标题 -->
            <el-form-item label="文章标题" prop="title">
              <el-input
                v-model="form.title"
                placeholder="请输入文章标题"
                size="large"
                maxlength="200"
                show-word-limit
              />
            </el-form-item>

            <!-- 内容编辑器 -->
            <el-form-item label="文章内容" prop="content">
              <div class="editor-toolbar">
                <el-button-group>
                  <el-tooltip content="AI生成大纲">
                    <el-button :icon="MagicStick" @click="generateOutline">大纲</el-button>
                  </el-tooltip>
                  <el-tooltip content="AI优化内容">
                    <el-button :icon="Cpu" @click="optimizeContent">优化</el-button>
                  </el-tooltip>
                  <el-tooltip content="插入图片">
                    <el-button :icon="Picture" @click="insertImage">图片</el-button>
                  </el-tooltip>
                  <el-tooltip content="插入代码块">
                    <el-button :icon="DocumentCopy" @click="insertCode">代码</el-button>
                  </el-tooltip>
                </el-button-group>
              </div>
              <el-input
                v-model="form.content"
                type="textarea"
                :rows="20"
                placeholder="请输入文章内容，支持Markdown格式"
                class="content-editor"
              />
            </el-form-item>

            <!-- 内容预览 -->
            <el-form-item label="内容预览">
              <el-card class="preview-card">
                <div class="markdown-preview" v-html="renderedContent"></div>
              </el-card>
            </el-form-item>

            <!-- 摘要 -->
            <el-form-item label="文章摘要" prop="excerpt">
              <el-input
                v-model="form.excerpt"
                type="textarea"
                :rows="3"
                placeholder="请输入文章摘要，留空则自动提取"
                maxlength="500"
                show-word-limit
              />
            </el-form-item>
          </el-col>

          <el-col :span="6">
            <!-- 发布设置 -->
            <el-card class="side-card">
              <template #header>发布设置</template>

              <el-form-item label="状态">
                <el-select v-model="form.status" style="width: 100%">
                  <el-option label="已发布" value="publish" />
                  <el-option label="草稿" value="draft" />
                  <el-option label="待审核" value="pending" />
                  <el-option label="私密" value="private" />
                </el-select>
              </el-form-item>

              <el-form-item label="发布时间">
                <el-date-picker
                  v-model="form.date"
                  type="datetime"
                  placeholder="选择发布时间"
                  style="width: 100%"
                  format="YYYY-MM-DD HH:mm"
                />
              </el-form-item>

              <el-form-item label="作者">
                <el-select v-model="form.author_id" style="width: 100%">
                  <el-option
                    v-for="author in authors"
                    :key="author.id"
                    :label="author.name"
                    :value="author.id"
                  />
                </el-select>
              </el-form-item>
            </el-card>

            <!-- 分类和标签 -->
            <el-card class="side-card">
              <template #header>分类和标签</template>

              <el-form-item label="分类">
                <el-select v-model="form.categories" multiple style="width: 100%">
                  <el-option
                    v-for="cat in categories"
                    :key="cat.id"
                    :label="cat.name"
                    :value="cat.id"
                  />
                </el-select>
              </el-form-item>

              <el-form-item label="标签">
                <el-select
                  v-model="form.tags"
                  multiple
                  filterable
                  allow-create
                  style="width: 100%"
                  placeholder="输入标签，按回车添加"
                >
                  <el-option
                    v-for="tag in tags"
                    :key="tag.id"
                    :label="tag.name"
                    :value="tag.id"
                  />
                </el-select>
              </el-form-item>
            </el-card>

            <!-- 特色图片 -->
            <el-card class="side-card">
              <template #header>
                <div class="card-header">
                  <span>特色图片</span>
                  <el-button
                    link
                    type="primary"
                    size="small"
                    :icon="MagicStick"
                    @click="generateImage"
                  >
                    AI生成
                  </el-button>
                </div>
              </template>

              <div class="featured-image">
                <el-image
                  v-if="form.featured_image"
                  :src="form.featured_image"
                  fit="cover"
                  class="image-preview"
                />
                <div v-else class="image-placeholder">
                  <el-icon :size="40"><Picture /></el-icon>
                  <p>暂无图片</p>
                </div>
                <div class="image-actions">
                  <el-upload
                    :action="uploadUrl"
                    :headers="uploadHeaders"
                    :show-file-list="false"
                    :on-success="handleImageSuccess"
                    :before-upload="beforeImageUpload"
                    accept="image/*"
                  >
                    <el-button size="small" type="primary">上传图片</el-button>
                  </el-upload>
                  <el-button
                    v-if="form.featured_image"
                    size="small"
                    type="danger"
                    @click="form.featured_image = ''"
                  >
                    删除
                  </el-button>
                </div>
              </div>
            </el-card>

            <!-- SEO设置 -->
            <el-card class="side-card">
              <template #header>SEO设置</template>

              <el-form-item label="SEO标题">
                <el-input
                  v-model="form.seo_title"
                  placeholder="留空则使用文章标题"
                  maxlength="100"
                />
              </el-form-item>

              <el-form-item label="SEO描述">
                <el-input
                  v-model="form.seo_description"
                  type="textarea"
                  :rows="3"
                  placeholder="留空则使用摘要"
                  maxlength="200"
                />
              </el-form-item>

              <el-form-item label="关键词">
                <el-input
                  v-model="form.seo_keywords"
                  placeholder="用逗号分隔"
                />
              </el-form-item>
            </el-card>
          </el-col>
        </el-row>
      </el-card>
    </el-form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import {
  MagicStick, Cpu, Picture, DocumentCopy
} from '@element-plus/icons-vue'
import { postsAPI, aiAPI, mediaAPI } from '@/api'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js'
import 'highlight.js/styles/github.css'

const route = useRoute()
const router = useRouter()
const formRef = ref(null)
const loading = ref(false)

// 是否编辑模式
const isEdit = computed(() => !!route.params.id)

// Markdown渲染器
const md = new MarkdownIt({
  highlight: function (str, lang) {
    if (lang && hljs.getLanguage(lang)) {
      try {
        return hljs.highlight(str, { language: lang }).value
      } catch (__) {}
    }
    return ''
  }
})

// 表单数据
const form = ref({
  title: '',
  content: '',
  excerpt: '',
  status: 'draft',
  date: new Date(),
  author_id: '',
  categories: [],
  tags: [],
  featured_image: '',
  seo_title: '',
  seo_description: '',
  seo_keywords: ''
})

// 表单验证规则
const rules = {
  title: [
    { required: true, message: '请输入文章标题', trigger: 'blur' },
    { min: 5, message: '标题至少5个字符', trigger: 'blur' }
  ],
  content: [
    { required: true, message: '请输入文章内容', trigger: 'blur' },
    { min: 50, message: '内容至少50个字符', trigger: 'blur' }
  ]
}

// 分类列表
const categories = ref([])

// 标签列表
const tags = ref([])

// 作者列表
const authors = ref([])

// 上传URL
const uploadUrl = computed(() => '/wp-json/wp/v2/media')

// 上传请求头
const uploadHeaders = computed(() => ({
  'Authorization': `Bearer ${localStorage.getItem('wp_token')}`,
  'X-WP-Nonce': localStorage.getItem('wp_nonce')
}))

// 渲染后的内容
const renderedContent = computed(() => {
  if (!form.value.content) return ''
  return md.render(form.value.content)
})

// 加载文章数据
async function loadPost() {
  if (!isEdit.value) return

  loading.value = true
  try {
    const data = await postsAPI.getPost(route.params.id)
    form.value = {
      ...form.value,
      ...data
    }
  } catch (error) {
    console.error('加载文章失败:', error)
    ElMessage.error('加载文章失败')
    router.back()
  } finally {
    loading.value = false
  }
}

// 加载辅助数据
async function loadHelperData() {
  try {
    const [catsData, tagsData, authorsData] = await Promise.all([
      postsAPI.getCategories(),
      postsAPI.getTags(),
      postsAPI.getAuthors()
    ])
    categories.value = catsData || []
    tags.value = tagsData || []
    authors.value = authorsData || []
  } catch (error) {
    console.error('加载辅助数据失败:', error)
  }
}

// 保存草稿
async function saveDraft() {
  form.value.status = 'draft'
  await savePost()
}

// 发布
async function publish() {
  form.value.status = 'publish'
  await savePost()
}

// 保存文章
async function savePost() {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    loading.value = true

    if (isEdit.value) {
      await postsAPI.updatePost(route.params.id, form.value)
      ElMessage.success('更新成功')
    } else {
      const data = await postsAPI.createPost(form.value)
      ElMessage.success('创建成功')
      router.replace(`/posts/edit/${data.id}`)
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('保存失败:', error)
      ElMessage.error('保存失败')
    }
  } finally {
    loading.value = false
  }
}

// AI生成大纲
async function generateOutline() {
  if (!form.value.title) {
    ElMessage.warning('请先输入文章标题')
    return
  }

  loading.value = true
  try {
    const data = await aiAPI.generateOutline(form.value.title)
    form.value.content = data.outline
    ElMessage.success('大纲生成成功')
  } catch (error) {
    console.error('生成大纲失败:', error)
    ElMessage.error('生成大纲失败')
  } finally {
    loading.value = false
  }
}

// AI优化内容
async function optimizeContent() {
  if (!form.value.content) {
    ElMessage.warning('请先输入文章内容')
    return
  }

  loading.value = true
  try {
    const data = await postsAPI.optimizeWithAI(route.params.id, form.value.content)
    form.value.content = data.optimized_content
    ElMessage.success('内容优化成功')
  } catch (error) {
    console.error('优化内容失败:', error)
    ElMessage.error('优化内容失败')
  } finally {
    loading.value = false
  }
}

// AI生成图片
async function generateImage() {
  if (!form.value.title) {
    ElMessage.warning('请先输入文章标题')
    return
  }

  loading.value = true
  try {
    const data = await aiAPI.generateImage(form.value.title)
    form.value.featured_image = data.url
    ElMessage.success('图片生成成功')
  } catch (error) {
    console.error('生成图片失败:', error)
    ElMessage.error('生成图片失败')
  } finally {
    loading.value = false
  }
}

// 插入图片
function insertImage() {
  // TODO: 打开图片选择器
  ElMessage.info('图片插入功能开发中')
}

// 插入代码块
function insertCode() {
  const code = '\n```javascript\n// 在这里输入代码\n```\n'
  form.value.content += code
}

// 图片上传成功
function handleImageSuccess(response) {
  form.value.featured_image = response.source_url
  ElMessage.success('图片上传成功')
}

// 图片上传前验证
function beforeImageUpload(file) {
  const isImage = file.type.startsWith('image/')
  const isLt5M = file.size / 1024 / 1024 < 5

  if (!isImage) {
    ElMessage.error('只能上传图片文件')
    return false
  }
  if (!isLt5M) {
    ElMessage.error('图片大小不能超过 5MB')
    return false
  }
  return true
}

onMounted(() => {
  loadPost()
  loadHelperData()
})
</script>

<style lang="scss" scoped>
.post-edit-container {
  padding: 20px;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.editor-toolbar {
  margin-bottom: 12px;
}

.content-editor {
  font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
  font-size: 14px;
  line-height: 1.6;
}

.preview-card {
  max-height: 500px;
  overflow-y: auto;
}

.markdown-preview {
  padding: 20px;
  line-height: 1.8;

  :deep(h1), :deep(h2), :deep(h3), :deep(h4), :deep(h5), :deep(h6) {
    margin: 20px 0 10px;
    font-weight: 600;
  }

  :deep(p) {
    margin: 10px 0;
  }

  :deep(code) {
    padding: 2px 6px;
    background: #f5f7fa;
    border-radius: 3px;
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 0.9em;
  }

  :deep(pre) {
    padding: 12px;
    background: #f6f8fa;
    border-radius: 6px;
    overflow-x: auto;

    code {
      padding: 0;
      background: none;
    }
  }

  :deep(img) {
    max-width: 100%;
    border-radius: 4px;
  }

  :deep(blockquote) {
    margin: 10px 0;
    padding-left: 16px;
    border-left: 4px solid #ddd;
    color: #666;
  }
}

.side-card {
  margin-bottom: 20px;

  :deep(.el-card__header) {
    padding: 12px 16px;
    font-weight: 600;
  }

  :deep(.el-card__body) {
    padding: 16px;
  }

  .el-form-item {
    margin-bottom: 16px;

    :deep(.el-form-item__label) {
      font-size: 13px;
      padding: 0 0 4px;
    }
  }
}

.featured-image {
  text-align: center;
}

.image-preview {
  width: 100%;
  height: 200px;
  border-radius: 4px;
  margin-bottom: 12px;
}

.image-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 200px;
  background: #f5f7fa;
  border-radius: 4px;
  color: #909399;
  margin-bottom: 12px;

  p {
    margin-top: 8px;
    font-size: 14px;
  }
}

.image-actions {
  display: flex;
  gap: 8px;
  justify-content: center;
}

/* 响应式设计 */
@media (max-width: 1200px) {
  .el-col-6 {
    width: 100%;
    margin-top: 20px;
  }

  .el-col-18 {
    width: 100%;
  }
}
</style>
