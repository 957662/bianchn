<template>
  <div class="setup-wizard-container">
    <el-card v-loading="loading">
      <template #header>
        <div class="wizard-header">
          <span>项目部署配置向导</span>
          <el-tag :type="currentStep === 'finished' ? 'success' : 'info'">
            {{ getStepLabel() }}
          </el-tag>
        </div>
      </template>

      <el-steps :active="currentStepIndex" finish-status="success" align-center>
        <el-step title="环境检测" />
        <el-step title="数据库配置" />
        <el-step title="AI服务配置" />
        <el-step title="CDN配置" />
        <el-step title="邮件配置" />
        <el-step title="完成部署" />
      </el-steps>

      <div class="step-content">
        <!-- 步骤1: 环境检测 -->
        <div v-show="currentStep === 'env-check'" class="step-panel">
          <h3>环境检测</h3>
          <el-descriptions :column="2" border>
            <el-descriptions-item label="PHP版本">
              <el-tag :type="envInfo.php >= '8.1' ? 'success' : 'warning'">
                {{ envInfo.php || '检测中...' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="MySQL版本">
              <el-tag :type="envInfo.mysql >= '8.0' ? 'success' : 'warning'">
                {{ envInfo.mysql || '检测中...' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="Redis状态">
              <el-tag :type="envInfo.redis ? 'success' : 'danger'">
                {{ envInfo.redis ? '已连接' : '未连接' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="WordPress状态">
              <el-tag :type="envInfo.wordpress ? 'success' : 'danger'">
                {{ envInfo.wordpress ? '已安装' : '未安装' }}
              </el-tag>
            </el-descriptions-item>
          </el-descriptions>
          <div class="step-actions">
            <el-button type="primary" @click="checkEnvironment" :loading="checking">
              {{ checking ? '检测中...' : '开始检测' }}
            </el-button>
            <el-button @click="skipTo('db')">跳过</el-button>
          </div>
        </div>

        <!-- 步骤2: 数据库配置 -->
        <div v-show="currentStep === 'db'" class="step-panel">
          <h3>数据库配置</h3>
          <el-form :model="dbConfig" :rules="dbRules" label-width="140px">
            <el-form-item label="数据库主机" prop="host">
              <el-input v-model="dbConfig.host" placeholder="localhost" />
            </el-form-item>
            <el-form-item label="数据库名称" prop="name">
              <el-input v-model="dbConfig.name" placeholder="xiaowu_blog" />
            </el-form-item>
            <el-form-item label="数据库用户名" prop="user">
              <el-input v-model="dbConfig.user" placeholder="xiaowu_user" />
            </el-form-item>
            <el-form-item label="数据库密码" prop="password">
              <el-input
                v-model="dbConfig.password"
                type="password"
                show-password
                placeholder="请输入密码"
              />
            </el-form-item>
            <el-form-item label="测试连接">
              <el-button type="success" @click="testDB" :loading="testingDB">
                {{ testingDB ? '测试中...' : '测试连接' }}
              </el-button>
            </el-form-item>
          </el-form>
          <div class="step-actions">
            <el-button type="primary" @click="saveDB" :loading="savingDB"> 保存并继续 </el-button>
            <el-button @click="skipTo('ai')">跳过</el-button>
          </div>
        </div>

        <!-- 步骤3: AI服务配置 -->
        <div v-show="currentStep === 'ai'" class="step-panel">
          <h3>AI服务配置</h3>
          <el-alert
            title="配置AI服务后，系统将提供文章优化、智能搜索、内容推荐等功能"
            type="info"
            :closable="false"
            show-icon
          />
          <el-form :model="aiConfig" :rules="aiRules" label-width="140px">
            <el-form-item label="AI服务提供商" prop="provider">
              <el-select v-model="aiConfig.provider" placeholder="请选择">
                <el-option label="OpenAI" value="openai" />
                <el-option label="通义千问" value="qwen" />
                <el-option label="文心一言" value="wenxin" />
                <el-option label="Claude" value="claude" />
              </el-select>
            </el-form-item>
            <el-form-item label="API密钥" prop="apiKey">
              <el-input
                v-model="aiConfig.apiKey"
                type="password"
                show-password
                placeholder="请输入API密钥"
              />
              <template #append>
                <el-button @click="showAIKeyHelp">?</el-button>
              </template>
            </el-form-item>
            <el-form-item label="模型名称" prop="model">
              <el-input v-model="aiConfig.model" placeholder="gpt-4" />
            </el-form-item>
            <el-form-item label="最大Token数">
              <el-input-number v-model="aiConfig.maxTokens" :min="100" :max="128000" :step="100" />
            </el-form-item>
            <el-form-item label="温度参数">
              <el-slider
                v-model="aiConfig.temperature"
                :min="0"
                :max="2"
                :step="0.1"
                :marks="{ 0: '精确', 1: '平衡', 2: '创意' }"
              />
              <span style="margin-left: 12px; color: #666">{{ aiConfig.temperature }}</span>
            </el-form-item>
            <el-form-item label="测试连接">
              <el-button type="success" @click="testAI" :loading="testingAI">
                {{ testingAI ? '测试中...' : '测试连接' }}
              </el-button>
            </el-form-item>
          </el-form>
          <div class="step-actions">
            <el-button type="primary" @click="saveAI" :loading="savingAI"> 保存并继续 </el-button>
            <el-button @click="skipTo('cdn')">跳过</el-button>
          </div>
        </div>

        <!-- 步骤4: CDN配置 -->
        <div v-show="currentStep === 'cdn'" class="step-panel">
          <h3>CDN存储配置</h3>
          <el-alert
            title="配置CDN后，静态资源和3D模型将上传到云端存储"
            type="info"
            :closable="false"
            show-icon
          />
          <el-form :model="cdnConfig" :rules="cdnRules" label-width="140px">
            <el-form-item label="CDN提供商" prop="provider">
              <el-select v-model="cdnConfig.provider" placeholder="请选择">
                <el-option label="本地存储" value="local" />
                <el-option label="腾讯云COS" value="tencent" />
                <el-option label="阿里云OSS" value="aliyun" />
                <el-option label="七牛云" value="qiniu" />
              </el-select>
            </el-form-item>
            <template v-if="cdnConfig.provider !== 'local'">
              <el-form-item label="Secret ID" prop="secretId">
                <el-input v-model="cdnConfig.secretId" placeholder="请输入Secret ID" />
              </el-form-item>
              <el-form-item label="Secret Key" prop="secretKey">
                <el-input
                  v-model="cdnConfig.secretKey"
                  type="password"
                  show-password
                  placeholder="请输入Secret Key"
                />
              </el-form-item>
              <el-form-item label="存储桶名称" prop="bucket">
                <el-input v-model="cdnConfig.bucket" placeholder="xiaowu-blog" />
              </el-form-item>
              <el-form-item label="存储区域" prop="region">
                <el-select v-model="cdnConfig.region" placeholder="请选择">
                  <el-option label="华东-上海" value="ap-shanghai" />
                  <el-option label="华北-北京" value="ap-beijing" />
                  <el-option label="华南-广州" value="ap-guangzhou" />
                  <el-option label="西南-成都" value="ap-chengdu" />
                </el-select>
              </el-form-item>
            </template>
          </el-form>
          <div class="step-actions">
            <el-button type="primary" @click="saveCDN" :loading="savingCDN"> 保存并继续 </el-button>
            <el-button @click="skipTo('email')">跳过</el-button>
          </div>
        </div>

        <!-- 步骤5: 邮件配置 -->
        <div v-show="currentStep === 'email'" class="step-panel">
          <h3>邮件服务配置</h3>
          <el-alert
            title="配置邮件服务后，系统将发送通知邮件和用户相关邮件"
            type="info"
            :closable="false"
            show-icon
          />
          <el-form :model="emailConfig" :rules="emailRules" label-width="140px">
            <el-form-item label="SMTP服务器" prop="host">
              <el-input v-model="emailConfig.host" placeholder="smtp.gmail.com" />
            </el-form-item>
            <el-form-item label="SMTP端口" prop="port">
              <el-input-number v-model="emailConfig.port" :min="1" :max="65535" />
            </el-form-item>
            <el-form-item label="加密方式" prop="encryption">
              <el-select v-model="emailConfig.encryption">
                <el-option label="无加密" value="none" />
                <el-option label="SSL" value="ssl" />
                <el-option label="TLS" value="tls" />
              </el-select>
            </el-form-item>
            <el-form-item label="发件人邮箱" prop="fromEmail">
              <el-input
                v-model="emailConfig.fromEmail"
                type="email"
                placeholder="noreply@example.com"
              />
            </el-form-item>
            <el-form-item label="发件人名称" prop="fromName">
              <el-input v-model="emailConfig.fromName" placeholder="小伍同学博客" />
            </el-form-item>
            <el-form-item label="用户名" prop="username">
              <el-input v-model="emailConfig.username" placeholder="your_email@gmail.com" />
            </el-form-item>
            <el-form-item label="密码" prop="password">
              <el-input v-model="emailConfig.password" type="password" show-password />
            </el-form-item>
            <el-form-item label="测试发送">
              <el-button type="success" @click="testEmail" :loading="testingEmail">
                {{ testingEmail ? '发送中...' : '发送测试邮件' }}
              </el-button>
            </el-form-item>
          </el-form>
          <div class="step-actions">
            <el-button type="primary" @click="saveEmail" :loading="savingEmail">
              保存并继续
            </el-button>
            <el-button @click="finishSetup">跳过</el-button>
          </div>
        </div>

        <!-- 步骤6: 完成部署 -->
        <div v-show="currentStep === 'finished'" class="step-panel finished-panel">
          <div class="success-icon">
            <el-icon :size="80" color="#67c23a"><CircleCheck /></el-icon>
          </div>
          <h3>部署配置完成！</h3>
          <p class="success-desc">系统已准备好运行，您可以：</p>
          <div class="success-links">
            <el-button type="primary" size="large" @click="goToAdmin">
              <el-icon><Management /></el-icon>
              进入管理后台
            </el-button>
            <el-button type="success" size="large" @click="goToHome">
              <el-icon><HomeFilled /></el-icon>
              访问前台页面
            </el-button>
          </div>
          <el-divider />
          <h4>配置文件已生成：</h4>
          <div class="config-files">
            <el-card shadow="never">
              <template #header>
                <span>wp-config.php 配置</span>
                <el-button link @click="copyConfig">复制配置</el-button>
              </template>
              <pre class="config-code">{{ generatedConfig.php }}</pre>
            </el-card>
          </div>
        </div>
      </div>
    </el-card>

    <!-- AI密钥帮助对话框 -->
    <el-dialog v-model="aiKeyHelpVisible" title="如何获取AI API密钥" width="600px">
      <div class="ai-help-content">
        <h4>OpenAI</h4>
        <ol>
          <li>
            访问
            <el-link href="https://platform.openai.com/api-keys" target="_blank"
              >https://platform.openai.com/api-keys</el-link
            >
          </li>
          <li>登录或注册账号</li>
          <li>点击"Create new secret key"创建密钥</li>
          <li>复制密钥并粘贴到上方</li>
        </ol>
        <el-divider />
        <h4>通义千问</h4>
        <ol>
          <li>
            访问
            <el-link href="https://dashscope.console.aliyun.com/apiKey" target="_blank"
              >阿里云百炼控制台</el-link
            >
          </li>
          <li>创建API-KEY</li>
          <li>复制密钥并粘贴到上方</li>
        </ol>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { CircleCheck, Management, HomeFilled } from '@element-plus/icons-vue';
import { deploymentAPI } from '@/api';

const loading = ref(false);

// 步骤
const currentStep = ref('env-check');
const currentStepIndex = computed(() => {
  const steps = ['env-check', 'db', 'ai', 'cdn', 'email', 'finished'];
  return steps.indexOf(currentStep.value);
});

// 环境信息
const checking = ref(false);
const envInfo = ref({
  php: null,
  mysql: null,
  redis: null,
  wordpress: null,
});

// 数据库配置
const testingDB = ref(false);
const savingDB = ref(false);
const dbConfig = ref({
  host: 'localhost',
  name: 'xiaowu_blog',
  user: 'xiaowu_user',
  password: '',
});

// AI配置
const testingAI = ref(false);
const savingAI = ref(false);
const aiConfig = ref({
  provider: 'openai',
  apiKey: '',
  model: 'gpt-4',
  maxTokens: 4000,
  temperature: 0.7,
});

// CDN配置
const savingCDN = ref(false);
const cdnConfig = ref({
  provider: 'local',
  secretId: '',
  secretKey: '',
  bucket: 'xiaowu-blog',
  region: 'ap-shanghai',
});

// 邮件配置
const testingEmail = ref(false);
const savingEmail = ref(false);
const emailConfig = ref({
  host: 'smtp.gmail.com',
  port: 587,
  encryption: 'tls',
  fromEmail: '',
  fromName: '小伍同学博客',
  username: '',
  password: '',
});

// AI密钥帮助
const aiKeyHelpVisible = ref(false);

// 表单验证规则
const dbRules = {
  host: [{ required: true, message: '请输入数据库主机', trigger: 'blur' }],
  name: [{ required: true, message: '请输入数据库名称', trigger: 'blur' }],
  user: [{ required: true, message: '请输入数据库用户名', trigger: 'blur' }],
  password: [{ required: true, message: '请输入数据库密码', trigger: 'blur' }],
};

const aiRules = {
  provider: [{ required: true, message: '请选择AI服务提供商', trigger: 'change' }],
  apiKey: [{ required: true, message: '请输入API密钥', trigger: 'blur' }],
  model: [{ required: true, message: '请输入模型名称', trigger: 'blur' }],
};

const cdnRules = {
  provider: [{ required: true, message: '请选择CDN提供商', trigger: 'change' }],
};

const emailRules = {
  host: [{ required: true, message: '请输入SMTP服务器', trigger: 'blur' }],
  port: [{ required: true, message: '请输入SMTP端口', trigger: 'blur' }],
  fromEmail: [{ type: 'email', message: '请输入正确的邮箱格式', trigger: 'blur' }],
  fromName: [{ required: true, message: '请输入发件人名称', trigger: 'blur' }],
};

// 生成的配置
const generatedConfig = ref({});

// 检查环境
async function checkEnvironment() {
  checking.value = true;
  try {
    const result = await deploymentAPI.checkEnvironment();
    envInfo.value = result;
    if (result.php && result.mysql && result.redis && result.wordpress) {
      ElMessage.success('环境检测通过');
      setTimeout(() => skipTo('db'), 1000);
    } else {
      ElMessage.warning('部分环境检测未通过，请检查后继续');
    }
  } catch (error) {
    console.error('环境检测失败:', error);
    ElMessage.error('环境检测失败');
  } finally {
    checking.value = false;
  }
}

// 测试数据库连接
async function testDB() {
  testingDB.value = true;
  try {
    await deploymentAPI.testDB({
      host: dbConfig.value.host,
      name: dbConfig.value.name,
      user: dbConfig.value.user,
      password: dbConfig.value.password,
    });
    ElMessage.success('数据库连接成功');
  } catch (error) {
    console.error('数据库连接失败:', error);
    ElMessage.error('数据库连接失败，请检查配置');
  } finally {
    testingDB.value = false;
  }
}

// 保存数据库配置
async function saveDB() {
  const formRef = { validate: () => Promise.resolve() };
  try {
    await formRef.validate();
    await deploymentAPI.saveConfig('db', dbConfig.value);
    ElMessage.success('数据库配置已保存');
    skipTo('ai');
  } catch (error) {
    ElMessage.error('保存失败');
  }
}

// 测试AI连接
async function testAI() {
  testingAI.value = true;
  try {
    await deploymentAPI.testAI({
      provider: aiConfig.value.provider,
      apiKey: aiConfig.value.apiKey,
      model: aiConfig.value.model,
    });
    ElMessage.success('AI服务连接成功');
  } catch (error) {
    console.error('AI连接测试失败:', error);
    ElMessage.error('AI连接测试失败，请检查API密钥');
  } finally {
    testingAI.value = false;
  }
}

// 保存AI配置
async function saveAI() {
  const formRef = { validate: () => Promise.resolve() };
  try {
    await formRef.validate();
    await deploymentAPI.saveConfig('ai', aiConfig.value);
    ElMessage.success('AI配置已保存');
    skipTo('cdn');
  } catch (error) {
    ElMessage.error('保存失败');
  }
}

// 保存CDN配置
async function saveCDN() {
  savingCDN.value = true;
  try {
    await deploymentAPI.saveConfig('cdn', cdnConfig.value);
    ElMessage.success('CDN配置已保存');
    skipTo('email');
  } catch (error) {
    ElMessage.error('保存失败');
  } finally {
    savingCDN.value = false;
  }
}

// 测试邮件
async function testEmail() {
  testingEmail.value = true;
  try {
    await deploymentAPI.testEmail(emailConfig.value);
    ElMessage.success('测试邮件已发送，请检查收件箱');
  } catch (error) {
    console.error('邮件测试失败:', error);
    ElMessage.error('邮件发送失败，请检查配置');
  } finally {
    testingEmail.value = false;
  }
}

// 保存邮件配置
async function saveEmail() {
  savingEmail.value = true;
  try {
    await deploymentAPI.saveConfig('email', emailConfig.value);
    ElMessage.success('邮件配置已保存');
    finishSetup();
  } catch (error) {
    ElMessage.error('保存失败');
  } finally {
    savingEmail.value = false;
  }
}

// 跳转到指定步骤
function skipTo(step) {
  currentStep.value = step;
}

// 完成设置
async function finishSetup() {
  try {
    const config = await deploymentAPI.generateConfig({
      db: dbConfig.value,
      ai: aiConfig.value,
      cdn: cdnConfig.value,
      email: emailConfig.value,
    });
    generatedConfig.value = config.php;
    currentStep.value = 'finished';
  } catch (error) {
    console.error('生成配置失败:', error);
    ElMessage.error('生成配置失败');
  }
}

// 进入管理后台
function goToAdmin() {
  window.location.href = '/wp-admin/';
}

// 访问前台页面
function goToHome() {
  window.location.href = '/';
}

// 复制配置
function copyConfig() {
  navigator.clipboard.writeText(generatedConfig.value.php);
  ElMessage.success('配置已复制到剪贴板');
}

// 显示AI密钥帮助
function showAIKeyHelp() {
  aiKeyHelpVisible.value = true;
}

// 获取步骤标签
function getStepLabel() {
  const labels = {
    'env-check': '环境检测',
    db: '数据库',
    ai: 'AI服务',
    cdn: 'CDN',
    email: '邮件',
    finished: '已完成',
  };
  return labels[currentStep.value] || '向导';
}

// 检查是否是首次部署
onMounted(() => {
  const isFirstDeploy = localStorage.getItem('xiaowu_first_deploy') !== 'false';
  if (!isFirstDeploy) {
    currentStep.value = 'env-check';
  }
});
</script>

<style lang="scss" scoped>
.setup-wizard-container {
  min-height: 100vh;
  padding: 40px;
  background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
}

:deep(.el-card) {
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.wizard-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 18px;
  font-weight: 600;
}

.step-content {
  margin-top: 40px;
  padding: 40px;
  background: #fff;
  border-radius: 12px;
}

.step-panel {
  min-height: 400px;
}

h3 {
  margin: 0 0 24px 0;
  font-size: 20px;
  font-weight: 600;
  color: #333;
}

.step-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-top: 32px;
  padding-top: 24px;
  border-top: 1px solid #ebeef5;
}

.finished-panel {
  text-align: center;
}

.success-icon {
  margin: 40px 0;
  color: #67c23a;
}

.success-desc {
  font-size: 16px;
  color: #666;
  margin: 24px 0;
}

.success-links {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin: 40px 0;
}

.config-files {
  margin-top: 32px;
  max-width: 800px;
  margin-left: auto;
  margin-right: auto;
}

.config-code {
  background: #f5f7fa;
  padding: 16px;
  border-radius: 8px;
  font-size: 13px;
  line-height: 1.6;
  color: #333;
  max-height: 400px;
  overflow-y: auto;
}

.ai-help-content {
  h4 {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
  }

  ol {
    padding-left: 20px;
    line-height: 2;
  }

  li {
    margin-bottom: 8px;
  }
}
</style>
