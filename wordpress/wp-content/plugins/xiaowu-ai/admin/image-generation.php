<?php
/**
 * AI生图页面
 *
 * @package Xiaowu_AI
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
  exit;
}

$current_provider = get_option('xiaowu_ai_default_provider', 'openai');
?>

<div class="wrap xiaowu-ai-image-gen">
  <h1>
    <span class="dashicons dashicons-format-image"></span>
    AI 图像生成
  </h1>

  <div class="xiaowu-ai-image-gen-container">
    <div class="xiaowu-ai-image-gen-sidebar">
      <h2>配置</h2>

      <table class="form-table">
        <tr>
          <th><label for="image-provider">提供商</label></th>
          <td>
            <select id="image-provider">
              <option value="openai" <?php selected($current_provider, 'openai'); ?>>OpenAI (DALL-E)</option>
              <option value="qianwen" <?php selected($current_provider, 'qianwen'); ?>>通义千问</option>
              <option value="custom">自定义</option>
            </select>
          </td>
        </tr>
        <tr>
          <th><label for="image-size">尺寸</label></th>
          <td>
            <select id="image-size">
              <option value="1024x1024">1024 x 1024 (默认)</option>
              <option value="1792x1024">1792 x 1024 (横向)</option>
              <option value="1024x1792">1024 x 1792 (纵向)</option>
            </select>
          </td>
        </tr>
        <tr>
          <th><label for="image-count">数量</label></th>
          <td>
            <input type="number" id="image-count" value="1" min="1" max="4">
          </td>
        </tr>
        <tr id="custom-model-row" style="display:none;">
          <th><label for="image-model">模型</label></th>
          <td>
            <input type="text" id="image-model" class="regular-text" placeholder="dall-e-3">
          </td>
        </tr>
      </table>

      <h3>API配置</h3>
      <table class="form-table">
        <tr>
          <th><label for="image-api-endpoint">API端点</label></th>
          <td>
            <input type="text" id="image-api-endpoint" class="regular-text large-text" placeholder="https://api.openai.com/v1/images/generations">
          </td>
        </tr>
        <tr>
          <th><label for="image-api-key">API密钥</label></th>
          <td>
            <input type="password" id="image-api-key" class="regular-text large-text">
            <p class="description">留空使用已配置的密钥</p>
          </td>
        </tr>
      </table>
    </div>

    <div class="xiaowu-ai-image-gen-main">
      <h2>生成图像</h2>

      <div class="xiaowu-prompt-input">
        <label for="image-prompt">提示词：</label>
        <textarea id="image-prompt" rows="4" class="large-text" placeholder="描述您想要生成的图像，例如：一只可爱的猫咪在花园里玩毛线球"></textarea>

        <div class="xiaowu-prompt-suggestions">
          <h4>示例提示词：</h4>
          <div class="xiaowu-suggestions">
            <button type="button" class="button suggestion-btn" data-prompt="A beautiful sunset over mountains with a lake reflection">山湖日落</button>
            <button type="button" class="button suggestion-btn" data-prompt="A futuristic city with flying cars and neon lights at night">未来城市夜景</button>
            <button type="button" class="button suggestion-btn" data-prompt="A cute robot character with big eyes, digital art style">可爱机器人</button>
            <button type="button" class="button suggestion-btn" data-prompt="Abstract geometric patterns with vibrant colors, modern art">抽象几何图案</button>
          </div>
        </div>

        <button type="button" id="generate-image-btn" class="button button-primary button-large">
          <span class="dashicons dashicons-admin-media"></span>
          生成图像
        </button>

        <div id="generation-status" style="margin-top: 20px;"></div>
      </div>

      <div class="xiaowu-generated-images" id="generated-images">
        <h2>生成的图像</h2>
        <div id="images-container" class="xiaowu-images-grid">
          <p class="xiaowu-placeholder">生成的图像将显示在这里</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
jQuery(document).ready(function($) {
  // 提供商选择
  $('#image-provider').on('change', function() {
    if ($(this).val() === 'custom') {
      $('#custom-model-row').show();
    } else {
      $('#custom-model-row').hide();
    }
  });

  // 使用示例提示词
  $('.suggestion-btn').on('click', function() {
    const prompt = $(this).data('prompt');
    $('#image-prompt').val(prompt).focus();
  });

  // 生成图像
  $('#generate-image-btn').on('click', function() {
    const prompt = $('#image-prompt').val();
    const provider = $('#image-provider').val();
    const size = $('#image-size').val();
    const count = $('#image-count').val();
    const model = $('#image-model').val();
    const endpoint = $('#image-api-endpoint').val();
    const apiKey = $('#image-api-key').val();

    if (!prompt.trim()) {
      alert('请输入提示词');
      return;
    }

    const $btn = $(this);
    const $status = $('#generation-status');

    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> 生成中...');
    $status.html('<div class="notice notice-info inline">正在生成图像，请稍候...</div>');

    const data = {
      prompt: prompt,
      provider: provider,
      size: size,
      n: parseInt(count),
      model: model || 'dall-e-3',
      endpoint: endpoint || '',
      api_key: apiKey || ''
    };

    $.ajax({
      url: '/wp-json/xiaowu/v1/ai/generate-image',
      method: 'POST',
      data: data,
      success: function(response) {
        if (response.success) {
          $status.html('<div class="notice notice-success inline">✓ 图像生成成功！</div>');
          displayImages(response.images);
        } else {
          $status.html('<div class="notice notice-error inline">✗ ' + response.error + '</div>');
        }
      },
      error: function(xhr) {
        const error = xhr.responseJSON?.message || '生成失败，请重试';
        $status.html('<div class="notice notice-error inline">✗ ' + error + '</div>');
      },
      complete: function() {
        $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-media"></span> 生成图像');
      }
    });
  });

  // 显示生成的图像
  function displayImages(images) {
    const $container = $('#images-container');
    $container.empty();

    if (!images || images.length === 0) {
      $container.html('<p>没有生成的图像</p>');
      return;
    }

    images.forEach(function(image) {
      const url = image.url || image;
      const $img = $('<div class="xiaowu-image-item">');

      $img.append('<img src="' + url + '" alt="Generated Image">');
      $img.append('<div class="xiaowu-image-actions">' +
        '<a href="' + url + '" target="_blank" class="button button-small">查看大图</a>' +
        '<button type="button" class="button button-small xiaowu-save-to-media" data-url="' + url + '">保存到媒体库</button>' +
        '</div>');

      $container.append($img);
    });

    // 绑定保存到媒体库按钮
    $('.xiaowu-save-to-media').on('click', function() {
      const url = $(this).data('url');
      saveToMedia(url);
    });
  }

  // 保存到媒体库
  function saveToMedia(url) {
    const $btn = $(this);
    $btn.prop('disabled', true).text('保存中...');

    $.ajax({
      url: '/wp-json/xiaowu/v1/ai/save-image',
      method: 'POST',
      data: { url: url },
      success: function(response) {
        if (response.success) {
          $btn.text('已保存');
          alert('图像已保存到媒体库');
        } else {
          $btn.prop('disabled', false).text('保存到媒体库');
          alert('保存失败: ' + response.error);
        }
      },
      error: function() {
        $btn.prop('disabled', false).text('保存到媒体库');
        alert('保存失败，请重试');
      }
    });
  }
});

function selected(provider, value) {
  return provider === value;
}
</script>

<style>
.xiaowu-ai-image-gen-container {
  display: flex;
  gap: 20px;
  margin-top: 20px;
}

.xiaowu-ai-image-gen-sidebar {
  flex: 0 0 300px;
}

.xiaowu-ai-image-gen-main {
  flex: 1;
}

.xiaowu-prompt-input {
  background: #fff;
  padding: 20px;
  border: 1px solid #ddd;
  border-radius: 5px;
  margin-bottom: 20px;
}

.xiaowu-prompt-input textarea {
  width: 100%;
  margin: 10px 0;
}

.xiaowu-prompt-suggestions {
  margin-top: 15px;
}

.xiaowu-prompt-suggestions h4 {
  margin-bottom: 10px;
}

.xiaowu-suggestions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.xiaowu-suggestions button {
  margin: 0;
}

.xiaowu-generated-images {
  background: #fff;
  padding: 20px;
  border: 1px solid #ddd;
  border-radius: 5px;
}

.xiaowu-images-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
  margin-top: 15px;
}

.xiaowu-image-item {
  border: 1px solid #ddd;
  border-radius: 5px;
  overflow: hidden;
}

.xiaowu-image-item img {
  width: 100%;
  height: auto;
  display: block;
}

.xiaowu-image-actions {
  padding: 10px;
  display: flex;
  gap: 8px;
  background: #f9f9f9;
}

.xiaowu-image-actions button,
.xiaowu-image-actions a {
  flex: 1;
  text-align: center;
}

@media (max-width: 1024px) {
  .xiaowu-ai-image-gen-container {
    flex-direction: column;
  }

  .xiaowu-ai-image-gen-sidebar {
    flex: none;
    width: 100%;
  }
}
</style>
