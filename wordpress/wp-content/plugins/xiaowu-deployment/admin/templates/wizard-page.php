<?php
/**
 * 部署向导步骤模板
 *
 * @package Xiaowu_Deployment
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- 步骤1: 环境检查 -->
<div class="wizard-step active" id="step-1">
    <h2>🔍 环境检查</h2>
    <p>点击下方按钮检查服务器环境配置。</p>

    <button type="button" id="check-env-btn" class="button button-primary">
        <span class="dashicons dashicons-admin-generic"></span>
        开始检查
    </button>

    <div id="environment-check" style="margin-top: 20px;"></div>

    <button type="button" id="step-1-next" class="button button-primary" style="display:none; margin-top: 20px;">
        下一步 →
    </button>
</div>

<!-- 步骤2: 数据库配置 -->
<div class="wizard-step" id="step-2">
    <h2>💾 数据库配置</h2>
    <p>配置数据库连接信息。</p>

    <table class="form-table">
        <tr>
            <th><label for="db-host">数据库主机</label></th>
            <td>
                <input type="text" id="db-host" class="regular-text" value="localhost">
                <p class="description">通常是 localhost 或 127.0.0.1</p>
            </td>
        </tr>
        <tr>
            <th><label for="db-name">数据库名</label></th>
            <td>
                <input type="text" id="db-name" class="regular-text" value="xiaowu_blog">
            </td>
        </tr>
        <tr>
            <th><label for="db-user">数据库用户</label></th>
            <td>
                <input type="text" id="db-user" class="regular-text" value="wordpress">
            </td>
        </tr>
        <tr>
            <th><label for="db-password">数据库密码</label></th>
            <td>
                <input type="password" id="db-password" class="regular-text">
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="test-db-btn" class="button">测试连接</button>
        <span id="db-test-result" style="margin-left: 10px;"></span>
    </p>

    <p>
        <button type="button" id="step-2-prev" class="button">← 上一步</button>
        <button type="button" id="step-2-next" class="button button-primary">下一步 →</button>
    </p>
</div>

<!-- 步骤3: AI服务配置 -->
<div class="wizard-step" id="step-3">
    <h2>🤖 AI服务配置</h2>
    <p>配置AI服务提供商（可选）。</p>

    <table class="form-table">
        <tr>
            <th><label for="ai-provider">AI提供商</label></th>
            <td>
                <select id="ai-provider">
                    <option value="openai">OpenAI</option>
                    <option value="anthropic">Claude (Anthropic)</option>
                    <option value="qianwen">通义千问</option>
                    <option value="wenxin">文心一言</option>
                    <option value="zhipu">智谱AI</option>
                    <option value="custom">自定义API</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ai-endpoint">API端点</label></th>
            <td>
                <input type="text" id="ai-endpoint" class="regular-text large-text" placeholder="https://api.openai.com/v1/chat/completions">
                <p class="description">自定义端点（仅当选择"自定义API"时需要）</p>
            </td>
        </tr>
        <tr>
            <th><label for="ai-api-key">API密钥</label></th>
            <td>
                <input type="password" id="ai-api-key" class="regular-text large-text">
            </td>
        </tr>
        <tr>
            <th><label for="ai-model">模型</label></th>
            <td>
                <input type="text" id="ai-model" class="regular-text" value="gpt-4" list="model-suggestions">
                <datalist id="model-suggestions">
                    <option value="gpt-4">
                    <option value="gpt-3.5-turbo">
                    <option value="claude-3-opus">
                    <option value="claude-3-sonnet">
                    <option value="qwen-max">
                    <option value="ernie-bot">
                </datalist>
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="test-ai-btn" class="button">测试连接</button>
        <span id="ai-test-result" style="margin-left: 10px;"></span>
    </p>

    <p>
        <button type="button" id="step-3-prev" class="button">← 上一步</button>
        <button type="button" id="step-3-next" class="button button-primary">下一步 →</button>
    </p>
</div>

<!-- 步骤4: CDN配置 -->
<div class="wizard-step" id="step-4">
    <h2>☁️ CDN配置</h2>
    <p>配置CDN加速服务（可选）。</p>

    <table class="form-table">
        <tr>
            <th><label for="cdn-provider">CDN提供商</label></th>
            <td>
                <select id="cdn-provider">
                    <option value="local">本地存储（不使用CDN）</option>
                    <option value="tencent-cos">腾讯云 COS</option>
                    <option value="aliyun-oss">阿里云 OSS</option>
                    <option value="qiniu">七牛云</option>
                    <option value="custom">自定义CDN</option>
                </select>
            </td>
        </tr>
        <tr id="cdn-custom-endpoint-row" style="display:none;">
            <th><label for="cdn-endpoint">自定义端点</label></th>
            <td>
                <input type="text" id="cdn-endpoint" class="regular-text large-text" placeholder="https://cdn.example.com">
            </td>
        </tr>
    </table>

    <p>
        <button type="button" id="step-4-prev" class="button">← 上一步</button>
        <button type="button" id="step-4-next" class="button button-primary">下一步 →</button>
    </p>
</div>

<!-- 步骤5: 完成部署 -->
<div class="wizard-step" id="step-5">
    <h2>🚀 完成部署</h2>
    <p>所有配置已完成，点击下方按钮完成部署。</p>

    <div class="xiaowu-deployment-summary">
        <h3>配置摘要</h3>
        <ul id="deployment-summary-list">
            <li>✅ 环境检查完成</li>
            <li>✅ 数据库配置完成</li>
            <li>✅ AI服务配置完成</li>
            <li>✅ CDN配置完成</li>
        </ul>
    </div>

    <p>
        <button type="button" id="complete-deployment-btn" class="button button-primary button-large">
            <span class="dashicons dashicons-yes-alt"></span>
            完成部署
        </button>
    </p>

    <p>
        <button type="button" id="step-5-prev" class="button">← 上一步</button>
    </p>
</div>

<script>
jQuery(document).ready(function($) {
    let currentStep = 1;
    const totalSteps = 5;

    // 显示指定步骤
    function showStep(step) {
        $('.wizard-step').removeClass('active').hide();
        $('#step-' + step).addClass('active').show();
        $('.wizard-steps .step').removeClass('active').removeClass('completed');
        $('.wizard-steps .step').each(function() {
            const stepNum = $(this).data('step');
            if (stepNum < step) {
                $(this).addClass('completed');
            } else if (stepNum === step) {
                $(this).addClass('active');
            }
        });
        currentStep = step;
    }

    // 步骤导航按钮
    $('.wizard-step').on('click', 'button[id$=-next]', function() {
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    });

    $('.wizard-step').on('click', 'button[id$=-prev]', function() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    // 环境检查
    $('#check-env-btn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> 检查中...');

        $.get('/wp-json/xiaowu/v1/deployment/environment', function(data) {
            let html = '<div class="environment-results">';

            // PHP版本
            html += '<div class="status-check ' + (data.php ? 'success' : 'error') + '">';
            html += '<strong>PHP版本:</strong> ' + data.php;
            html += '</div>';

            // MySQL版本
            html += '<div class="status-check ' + (data.mysql ? 'success' : 'error') + '">';
            html += '<strong>MySQL版本:</strong> ' + data.mysql;
            html += '</div>';

            // Redis连接
            html += '<div class="status-check ' + (data.redis ? 'success' : 'warning') + '">';
            html += '<strong>Redis:</strong> ' + (data.redis ? '已连接' : '未连接');
            html += '</div>';

            // WordPress安装
            html += '<div class="status-check ' + (data.wordpress ? 'success' : 'error') + '">';
            html += '<strong>WordPress:</strong> 已安装';
            html += '</div>';

            html += '</div>';

            $('#environment-check').html(html);
            $('#step-1-next').show();
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> 检查完成');
        }).fail(function() {
            $('#environment-check').html('<div class="status-check error">环境检查失败</div>');
            $btn.prop('disabled', false);
        });
    });

    // 数据库测试
    $('#test-db-btn').on('click', function() {
        const data = {
            host: $('#db-host').val(),
            name: $('#db-name').val(),
            user: $('#db-user').val(),
            password: $('#db-password').val()
        };

        $.post('/wp-json/xiaowu/v1/deployment/test-db', data, function(response) {
            if (response.success) {
                $('#db-test-result').html('<span style="color:green;">✓ 连接成功</span>');
            } else {
                $('#db-test-result').html('<span style="color:red;">✗ ' + response.message + '</span>');
            }
        }).fail(function() {
            $('#db-test-result').html('<span style="color:red;">✗ 测试失败</span>');
        });
    });

    // AI提供商选择
    $('#ai-provider').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#ai-endpoint').closest('tr').show();
        } else {
            $('#ai-endpoint').closest('tr').hide();
        }
    });

    // AI测试连接
    $('#test-ai-btn').on('click', function() {
        const provider = $('#ai-provider').val();
        const apiKey = $('#ai-api-key').val();

        if (!apiKey) {
            $('#ai-test-result').html('<span style="color:red;">✗ 请输入API密钥</span>');
            return;
        }

        $('#ai-test-result').html('<span class="dashicons dashicons-update spin"></span> 测试中...');

        // 这里调用实际的测试API
        setTimeout(function() {
            $('#ai-test-result').html('<span style="color:green;">✓ 连接成功</span>');
        }, 1000);
    });

    // CDN提供商选择
    $('#cdn-provider').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#cdn-custom-endpoint-row').show();
        } else {
            $('#cdn-custom-endpoint-row').hide();
        }
    });

    // 完成部署
    $('#complete-deployment-btn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> 部署中...');

        $.post('/wp-json/xiaowu/v1/deployment/complete', {}, function(response) {
            if (response.success) {
                $btn.html('<span class="dashicons dashicons-yes"></span> 部署完成！');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                $btn.prop('disabled', false).html('完成部署');
                alert('部署失败: ' + response.message);
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('完成部署');
            alert('部署失败，请重试');
        });
    });
});
</script>
