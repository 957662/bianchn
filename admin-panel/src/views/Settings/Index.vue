<template>
  <div class="settings-container">
    <el-card>
      <template #header>
        <span>系统设置</span>
      </template>

      <el-tabs v-model="activeTab">
        <!-- 基本设置 -->
        <el-tab-pane label="基本设置" name="basic">
          <el-form :model="basicSettings" label-width="150px">
            <el-form-item label="站点名称">
              <el-input v-model="basicSettings.site_name" style="width: 400px;" />
            </el-form-item>
            <el-form-item label="站点描述">
              <el-input
                v-model="basicSettings.site_description"
                type="textarea"
                :rows="3"
                style="width: 400px;"
              />
            </el-form-item>
            <el-form-item label="站点URL">
              <el-input v-model="basicSettings.site_url" style="width: 400px;" />
            </el-form-item>
            <el-form-item label="首页标题">
              <el-input v-model="basicSettings.home_title" style="width: 400px;" />
            </el-form-item>
            <el-form-item label="每页文章数">
              <el-input-number
                v-model="basicSettings.posts_per_page"
                :min="1"
                :max="50"
              />
            </el-form-item>
            <el-form-item label="时区设置">
              <el-select v-model="basicSettings.timezone" style="width: 400px;">
                <el-option label="北京时间 (UTC+8)" value="Asia/Shanghai" />
                <el-option label="上海 (UTC+8)" value="Asia/Shanghai" />
                <el-option label="东京 (UTC+9)" value="Asia/Tokyo" />
                <el-option label="纽约 (UTC-5)" value="America/New_York" />
                <el-option label="伦敦 (UTC+0)" value="Europe/London" />
              </el-select>
            </el-form-item>
            <el-form-item label="语言设置">
              <el-select v-model="basicSettings.language" style="width: 400px;">
                <el-option label="简体中文" value="zh-CN" />
                <el-option label="繁體中文" value="zh-TW" />
                <el-option label="English" value="en-US" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="saving" @click="saveBasicSettings">
                保存设置
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 评论设置 -->
        <el-tab-pane label="评论设置" name="comments">
          <el-form :model="commentSettings" label-width="150px">
            <el-form-item label="启用评论">
              <el-switch v-model="commentSettings.enabled" />
            </el-form-item>
            <el-form-item label="评论审核">
              <el-switch v-model="commentSettings.moderation" />
              <span style="margin-left: 12px; color: #999;">新评论需审核后显示</span>
            </el-form-item>
            <el-form-item label="登录才能评论">
              <el-switch v-model="commentSettings.require_login" />
            </el-form-item>
            <el-form-item label="评论分页">
              <el-input-number
                v-model="commentSettings.per_page"
                :min="5"
                :max="100"
              />
            </el-form-item>
            <el-form-item label="表情过滤">
              <el-switch v-model="commentSettings.emoji_filter" />
              <span style="margin-left: 12px; color: #999;">自动过滤纯表情评论</span>
            </el-form-item>
            <el-form-item label="敏感词过滤">
              <el-input
                v-model="commentSettings.sensitive_words"
                type="textarea"
                :rows="3"
                placeholder="多个敏感词用逗号分隔"
                style="width: 400px;"
              />
            </el-form-item>
            <el-form-item label="黑名单">
              <el-input
                v-model="commentSettings.blacklist"
                type="textarea"
                :rows="3"
                placeholder="每行一个IP地址或邮箱"
                style="width: 400px;"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="saving" @click="saveCommentSettings">
                保存设置
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 媒体设置 -->
        <el-tab-pane label="媒体设置" name="media">
          <el-form :model="mediaSettings" label-width="150px">
            <el-form-item label="最大上传大小">
              <el-input-number
                v-model="mediaSettings.max_upload_size"
                :min="1"
                :max="100"
              />
              <span style="margin-left: 12px; color: #999;">MB</span>
            </el-form-item>
            <el-form-item label="允许的文件类型">
              <el-checkbox-group v-model="mediaSettings.allowed_types">
                <el-checkbox label="jpg,jpeg">JPG/JPEG</el-checkbox>
                <el-checkbox label="png">PNG</el-checkbox>
                <el-checkbox label="gif">GIF</el-checkbox>
                <el-checkbox label="webp">WebP</el-checkbox>
                <el-checkbox label="svg">SVG</el-checkbox>
                <el-checkbox label="pdf">PDF</el-checkbox>
                <el-checkbox label="doc,docx">Word</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
            <el-form-item label="自动压缩图片">
              <el-switch v-model="mediaSettings.auto_compress" />
            </el-form-item>
            <el-form-item label="生成缩略图">
              <el-switch v-model="mediaSettings.generate_thumbnails" />
            </el-form-item>
            <el-form-item label="CDN设置">
              <el-select v-model="mediaSettings.cdn_provider" style="width: 300px;">
                <el-option label="本地存储" value="local" />
                <el-option label="腾讯云COS" value="tencent" />
                <el-option label="阿里云OSS" value="aliyun" />
                <el-option label="七牛云" value="qiniu" />
              </el-select>
              <el-button
                link
                type="primary"
                style="margin-left: 12px;"
                @click="configureCDN"
              >
                配置CDN
              </el-button>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="saving" @click="saveMediaSettings">
                保存设置
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 性能设置 -->
        <el-tab-pane label="性能设置" name="performance">
          <el-form :model="performanceSettings" label-width="150px">
            <el-form-item label="启用缓存">
              <el-switch v-model="performanceSettings.cache_enabled" />
            </el-form-item>
            <el-form-item label="缓存类型">
              <el-select v-model="performanceSettings.cache_type" style="width: 300px;">
                <el-option label="文件缓存" value="file" />
                <el-option label="Redis缓存" value="redis" />
                <el-option label="Memcached" value="memcached" />
              </el-select>
            </el-form-item>
            <el-form-item label="缓存时间">
              <el-input-number
                v-model="performanceSettings.cache_time"
                :min="0"
                :max="86400"
              />
              <span style="margin-left: 12px; color: #999;">秒</span>
            </el-form-item>
            <el-form-item label="Gzip压缩">
              <el-switch v-model="performanceSettings.gzip_enabled" />
              <span style="margin-left: 12px; color: #999;">压缩静态资源</span>
            </el-form-item>
            <el-form-item label="合并CSS/JS">
              <el-switch v-model="performanceSettings.minify_enabled" />
              <span style="margin-left: 12px; color: #999;">合并并压缩资源</span>
            </el-form-item>
            <el-form-item label="延迟加载">
              <el-switch v-model="performanceSettings.lazy_load" />
              <span style="margin-left: 12px; color: #999;">图片延迟加载</span>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="saving" @click="savePerformanceSettings">
                保存设置
              </el-button>
              <el-button :icon="Delete" type="warning" @click="clearCache">
                清除缓存
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 安全设置 -->
        <el-tab-pane label="安全设置" name="security">
          <el-form :model="securitySettings" label-width="150px">
            <el-form-item label="启用登录验证码">
              <el-switch v-model="securitySettings.login_captcha" />
            </el-form-item>
            <el-form-item label="登录失败限制">
              <el-input-number
                v-model="securitySettings.max_login_attempts"
                :min="3"
                :max="10"
              />
              <span style="margin-left: 12px; color: #999;">次失败后锁定</span>
            </el-form-item>
            <el-form-item label="锁定时间">
              <el-input-number
                v-model="securitySettings.lockout_time"
                :min="5"
                :max="60"
              />
              <span style="margin-left: 12px; color: #999;">分钟</span>
            </el-form-item>
            <el-form-item label="强制HTTPS">
              <el-switch v-model="securitySettings.force_https" />
            </el-form-item>
            <el-form-item label="CSRF保护">
              <el-switch v-model="securitySettings.csrf_protection" />
            </el-form-item>
            <el-form-item label="SQL注入防护">
              <el-switch v-model="securitySettings.sql_injection_protection" />
            </el-form-item>
            <el-form-item label="XSS防护">
              <el-switch v-model="securitySettings.xss_protection" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="saving" @click="saveSecuritySettings">
                保存设置
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 邮件设置 -->
        <el-tab-pane label="邮件设置" name="email">
          <el-form :model="emailSettings" label-width="150px">
            <el-form-item label="启用邮件">
              <el-switch v-model="emailSettings.enabled" />
            </el-form-item>
            <el-form-item label="SMTP服务器">
              <el-input v-model="emailSettings.smtp_host" placeholder="smtp.example.com" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="SMTP端口">
              <el-input-number v-model="emailSettings.smtp_port" :min="1" :max="65535" />
            </el-form-item>
            <el-form-item label="加密方式">
              <el-select v-model="emailSettings.encryption" style="width: 200px;">
                <el-option label="无" value="none" />
                <el-option label="SSL" value="ssl" />
                <el-option label="TLS" value="tls" />
              </el-select>
            </el-form-item>
            <el-form-item label="发件人邮箱">
              <el-input v-model="emailSettings.from_email" type="email" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="发件人名称">
              <el-input v-model="emailSettings.from_name" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="用户名">
              <el-input v-model="emailSettings.username" style="width: 300px;" />
            </el-form-item>
            <el-form-item label="密码">
              <el-input
                v-model="emailSettings.password"
                type="password"
                show-password
                style="width: 300px;"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" :loading="saving" @click="testEmail">
                发送测试邮件
              </el-button>
              <el-button type="primary" :loading="saving" @click="saveEmailSettings">
                保存设置
              </el-button>
            </el-form-item>
          </el-form>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'

const activeTab = ref('basic')
const saving = ref(false)

// 基本设置
const basicSettings = ref({
  site_name: '小伍同学博客',
  site_description: '分享技术、学习与思考',
  site_url: window.location.origin,
  home_title: '小伍同学 - 技术博客',
  posts_per_page: 10,
  timezone: 'Asia/Shanghai',
  language: 'zh-CN'
})

// 评论设置
const commentSettings = ref({
  enabled: true,
  moderation: true,
  require_login: false,
  per_page: 10,
  emoji_filter: false,
  sensitive_words: '',
  blacklist: ''
})

// 媒体设置
const mediaSettings = ref({
  max_upload_size: 10,
  allowed_types: ['jpg,jpeg', 'png', 'gif'],
  auto_compress: true,
  generate_thumbnails: true,
  cdn_provider: 'local'
})

// 性能设置
const performanceSettings = ref({
  cache_enabled: true,
  cache_type: 'file',
  cache_time: 3600,
  gzip_enabled: true,
  minify_enabled: true,
  lazy_load: true
})

// 安全设置
const securitySettings = ref({
  login_captcha: false,
  max_login_attempts: 5,
  lockout_time: 15,
  force_https: false,
  csrf_protection: true,
  sql_injection_protection: true,
  xss_protection: true
})

// 邮件设置
const emailSettings = ref({
  enabled: true,
  smtp_host: 'smtp.gmail.com',
  smtp_port: 587,
  encryption: 'tls',
  from_email: 'noreply@example.com',
  from_name: '小伍同学博客',
  username: '',
  password: ''
})

// 加载设置
async function loadSettings() {
  // TODO: 从API加载设置
  ElMessage.info('设置加载中...')
}

// 保存基本设置
async function saveBasicSettings() {
  saving.value = true
  try {
    // TODO: 调用API保存
    await new Promise(resolve => setTimeout(resolve, 500))
    ElMessage.success('基本设置保存成功')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 保存评论设置
async function saveCommentSettings() {
  saving.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 500))
    ElMessage.success('评论设置保存成功')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 保存媒体设置
async function saveMediaSettings() {
  saving.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 500))
    ElMessage.success('媒体设置保存成功')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 保存性能设置
async function savePerformanceSettings() {
  saving.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 500))
    ElMessage.success('性能设置保存成功')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 清除缓存
async function clearCache() {
  try {
    await ElMessageBox.confirm('确定要清除所有缓存吗?', '提示', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning'
    })
    saving.value = true
    // TODO: 调用清除缓存API
    await new Promise(resolve => setTimeout(resolve, 1000))
    ElMessage.success('缓存已清除')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('清除缓存失败:', error)
      ElMessage.error('清除缓存失败')
    }
  } finally {
    saving.value = false
  }
}

// 保存安全设置
async function saveSecuritySettings() {
  saving.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 500))
    ElMessage.success('安全设置保存成功')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

// 配置CDN
function configureCDN() {
  ElMessage.info('CDN配置功能开发中')
  // TODO: 打开CDN配置对话框
}

// 发送测试邮件
async function testEmail() {
  try {
    await ElMessageBox.prompt('请输入测试邮件地址:', '发送测试邮件', {
      confirmButtonText: '发送',
      cancelButtonText: '取消',
      inputPattern: /[^@]+@[^@]+\.[^@]+/,
      inputErrorMessage: '请输入正确的邮箱地址'
    })
    saving.value = true
    // TODO: 调用发送测试邮件API
    await new Promise(resolve => setTimeout(resolve, 2000))
    ElMessage.success('测试邮件已发送，请检查收件箱')
  } catch (error) {
    if (error !== 'cancel') {
      console.error('发送测试邮件失败:', error)
    }
  } finally {
    saving.value = false
  }
}

// 保存邮件设置
async function saveEmailSettings() {
  saving.value = true
  try {
    await new Promise(resolve => setTimeout(resolve, 500))
    ElMessage.success('邮件设置保存成功')
  } catch (error) {
    console.error('保存失败:', error)
    ElMessage.error('保存失败')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>

<style lang="scss" scoped>
.settings-container {
  padding: 20px;
}

@media (max-width: 768px) {
  .settings-container {
    padding: 12px;
  }
}
</style>
