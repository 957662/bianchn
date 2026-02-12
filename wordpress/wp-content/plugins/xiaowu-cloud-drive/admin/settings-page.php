<?php
/**
 * 云盘设置页面
 *
 * @package Xiaowu_Cloud
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
  exit;
}

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'aliyun';
?>

<div class="wrap">
  <h1>☁️ 云盘设置</h1>

  <h2 class="nav-tab-wrapper">
    <a href="?page=xiaowu-cloud-settings&tab=aliyun" class="nav-tab <?php echo $current_tab === 'aliyun' ? 'nav-tab-active' : ''; ?>">
      阿里云盘
    </a>
    <a href="?page=xiaowu-cloud-settings&tab=baidu" class="nav-tab <?php echo $current_tab === 'baidu' ? 'nav-tab-active' : ''; ?>">
      百度网盘
    </a>
    <a href="?page=xiaowu-cloud-settings&tab=onedrive" class="nav-tab <?php echo $current_tab === 'onedrive' ? 'nav-tab-active' : ''; ?>">
      OneDrive
    </a>
    <a href="?page=xiaowu-cloud-settings&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
      通用设置
    </a>
  </h2>

  <?php if ($current_tab === 'aliyun'): ?>
    <?php require_once XIAOWU_CLOUD_PLUGIN_DIR . 'admin/tabs/aliyun-settings.php'; ?>
  <?php elseif ($current_tab === 'baidu'): ?>
    <?php require_once XIAOWU_CLOUD_PLUGIN_DIR . 'admin/tabs/baidu-settings.php'; ?>
  <?php elseif ($current_tab === 'onedrive'): ?>
    <?php require_once XIAOWU_CLOUD_PLUGIN_DIR . 'admin/tabs/onedrive-settings.php'; ?>
  <?php elseif ($current_tab === 'settings'): ?>
    <?php require_once XIAOWU_CLOUD_PLUGIN_DIR . 'admin/tabs/general-settings.php'; ?>
  <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
  $('.xiaowu-cloud-test-btn').on('click', function() {
    const btn = $(this);
    const provider = btn.data('provider');

    btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> 测试中...');

    $.ajax({
      url: '/wp-json/xiaowu/v1/cloud/test',
      method: 'POST',
      data: {
        provider: provider
      },
      success: function(response) {
        if (response.success) {
          btn.html('<span class="dashicons dashicons-yes"></span> 获取授权');
          if (response.auth_url) {
            window.open(response.auth_url, '_blank');
          }
        } else {
          btn.prop('disabled', false).html('测试连接');
          alert('测试失败: ' + response.message);
        }
      },
      error: function() {
        btn.prop('disabled', false).html('测试连接');
        alert('测试失败，请重试');
      }
    });
  });
});
</script>

<style>
.spin {
  animation: spin 1s linear infinite;
  display: inline-block;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
