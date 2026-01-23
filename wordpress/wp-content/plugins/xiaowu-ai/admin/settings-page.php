<?php
/**
 * AI配置页面
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

// 保存配置
if (isset($_POST['xiaowu_ai_save']) && check_admin_referer('xiaowu_ai_settings')) {
    update_option('xiaowu_ai_provider', sanitize_text_field($_POST['provider']));
    update_option('xiaowu_ai_api_key', sanitize_text_field($_POST['api_key']));
    update_option('xiaowu_ai_model', sanitize_text_field($_POST['model']));
    update_option('xiaowu_ai_max_tokens', intval($_POST['max_tokens']));
    update_option('xiaowu_ai_temperature', floatval($_POST['temperature']));
    update_option('xiaowu_img_gen_api_key', sanitize_text_field($_POST['img_gen_api_key']));
    update_option('xiaowu_serpapi_key', sanitize_text_field($_POST['serpapi_key']));

    echo '<div class="notice notice-success"><p>配置已保存！</p></div>';
}

// 获取当前配置
$provider = get_option('xiaowu_ai_provider', 'openai');
$api_key = get_option('xiaowu_ai_api_key', '');
$model = get_option('xiaowu_ai_model', 'gpt-4');
$max_tokens = get_option('xiaowu_ai_max_tokens', 4000);
$temperature = get_option('xiaowu_ai_temperature', 0.7);
$img_gen_api_key = get_option('xiaowu_img_gen_api_key', '');
$serpapi_key = get_option('xiaowu_serpapi_key', '');

// 获取缓存统计
$cache_manager = new Xiaowu_Cache_Manager();
$cache_stats = $cache_manager->get_stats();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="xiaowu-ai-settings">
        <form method="post" action="">
            <?php wp_nonce_field('xiaowu_ai_settings'); ?>

            <table class="form-table">
                <tr>
                    <th colspan="2">
                        <h2>AI服务配置</h2>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="provider">AI提供商</label>
                    </th>
                    <td>
                        <select name="provider" id="provider" class="regular-text">
                            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI</option>
                            <option value="qianwen" <?php selected($provider, 'qianwen'); ?>>通义千问</option>
                            <option value="claude" <?php selected($provider, 'claude'); ?>>Claude</option>
                        </select>
                        <p class="description">选择您要使用的AI服务提供商</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="api_key">API密钥</label>
                    </th>
                    <td>
                        <input type="password" name="api_key" id="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                        <p class="description">您的AI服务API密钥</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="model">模型</label>
                    </th>
                    <td>
                        <input type="text" name="model" id="model" value="<?php echo esc_attr($model); ?>" class="regular-text" />
                        <p class="description">AI模型名称（如：gpt-4, gpt-3.5-turbo, qwen-max）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="max_tokens">最大令牌数</label>
                    </th>
                    <td>
                        <input type="number" name="max_tokens" id="max_tokens" value="<?php echo esc_attr($max_tokens); ?>" class="small-text" />
                        <p class="description">AI响应的最大令牌数（建议：2000-4000）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="temperature">温度</label>
                    </th>
                    <td>
                        <input type="number" name="temperature" id="temperature" value="<?php echo esc_attr($temperature); ?>" step="0.1" min="0" max="2" class="small-text" />
                        <p class="description">控制AI输出的随机性（0-2，推荐：0.7）</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2">
                        <h2>图像生成配置</h2>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="img_gen_api_key">图像生成API密钥</label>
                    </th>
                    <td>
                        <input type="password" name="img_gen_api_key" id="img_gen_api_key" value="<?php echo esc_attr($img_gen_api_key); ?>" class="regular-text" />
                        <p class="description">DALL-E或其他图像生成服务的API密钥</p>
                    </td>
                </tr>

                <tr>
                    <th colspan="2">
                        <h2>联网搜索配置</h2>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="serpapi_key">SerpAPI密钥</label>
                    </th>
                    <td>
                        <input type="password" name="serpapi_key" id="serpapi_key" value="<?php echo esc_attr($serpapi_key); ?>" class="regular-text" />
                        <p class="description">用于联网搜索功能的SerpAPI密钥</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="xiaowu_ai_save" class="button button-primary" value="保存配置" />
            </p>
        </form>

        <hr />

        <h2>缓存状态</h2>
        <table class="widefat">
            <tr>
                <th>缓存类型</th>
                <td><?php echo esc_html($cache_stats['type']); ?></td>
            </tr>
            <?php if (isset($cache_stats['redis'])): ?>
            <tr>
                <th>Redis连接</th>
                <td><?php echo $cache_stats['redis']['connected'] ? '已连接' : '未连接'; ?></td>
            </tr>
            <?php if ($cache_stats['redis']['connected']): ?>
            <tr>
                <th>内存使用</th>
                <td><?php echo esc_html($cache_stats['redis']['used_memory']); ?></td>
            </tr>
            <tr>
                <th>键数量</th>
                <td><?php echo esc_html($cache_stats['redis']['total_keys']); ?></td>
            </tr>
            <tr>
                <th>Redis版本</th>
                <td><?php echo esc_html($cache_stats['redis']['version']); ?></td>
            </tr>
            <?php endif; ?>
            <?php endif; ?>
        </table>

        <hr />

        <h2>API测试</h2>
        <div class="xiaowu-ai-test">
            <p>
                <button type="button" id="test-ai-connection" class="button">测试AI连接</button>
                <button type="button" id="clear-cache" class="button">清空缓存</button>
            </p>
            <div id="test-result" style="margin-top: 10px;"></div>
        </div>
    </div>
</div>

<style>
.xiaowu-ai-settings {
    max-width: 800px;
}

.xiaowu-ai-settings h2 {
    margin-top: 20px;
    padding-bottom: 5px;
    border-bottom: 1px solid #ccc;
}

.xiaowu-ai-test {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
}

#test-result {
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: none;
}

#test-result.show {
    display: block;
}

#test-result.success {
    border-color: #46b450;
    background: #ecf7ed;
}

#test-result.error {
    border-color: #dc3232;
    background: #f9e9e9;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 测试AI连接
    $('#test-ai-connection').on('click', function() {
        var button = $(this);
        var resultDiv = $('#test-result');

        button.prop('disabled', true).text('测试中...');
        resultDiv.removeClass('success error').hide();

        $.ajax({
            url: xiaowuAI.apiUrl + '/config',
            method: 'GET',
            headers: {
                'X-WP-Nonce': xiaowuAI.nonce
            },
            success: function(response) {
                resultDiv
                    .addClass('success show')
                    .html('<strong>成功！</strong> AI服务配置正常。');
            },
            error: function(xhr) {
                resultDiv
                    .addClass('error show')
                    .html('<strong>失败！</strong> ' + (xhr.responseJSON ? xhr.responseJSON.message : '无法连接到AI服务'));
            },
            complete: function() {
                button.prop('disabled', false).text('测试AI连接');
            }
        });
    });

    // 清空缓存
    $('#clear-cache').on('click', function() {
        if (!confirm('确定要清空所有缓存吗？')) {
            return;
        }

        var button = $(this);
        var resultDiv = $('#test-result');

        button.prop('disabled', true).text('清空中...');
        resultDiv.removeClass('success error').hide();

        $.ajax({
            url: xiaowuAI.ajaxUrl,
            method: 'POST',
            data: {
                action: 'xiaowu_clear_cache',
                nonce: xiaowuAI.nonce
            },
            success: function(response) {
                resultDiv
                    .addClass('success show')
                    .html('<strong>成功！</strong> 缓存已清空。');
            },
            error: function(xhr) {
                resultDiv
                    .addClass('error show')
                    .html('<strong>失败！</strong> 清空缓存失败。');
            },
            complete: function() {
                button.prop('disabled', false).text('清空缓存');
            }
        });
    });
});
</script>
