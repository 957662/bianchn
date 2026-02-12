<?php
/**
 * 云盘管理器
 *
 * 管理所有云盘提供商
 *
 * @package Xiaowu_Cloud
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
  exit;
}

class Xiaowu_Cloud_Drive_Manager
{
  /**
   * 获取指定的云盘提供商实例
   */
  public function get_provider($provider)
  {
    $provider_class_map = array(
      'aliyun' => 'Xiaowu_Cloud_Drive_Provider_Aliyun',
      'baidu' => 'Xiaowu_Cloud_Drive_Provider_Baidu',
      'onedrive' => 'Xiaowu_Cloud_Drive_Provider_OneDrive'
    );

    $class_name = $provider_class_map[$provider] ?? null;

    if (!$class_name) {
      return null;
    }

    $provider_file = XIAOWU_CLOUD_PLUGIN_DIR . "includes/providers/class-{$provider}-drive.php";

    if (!file_exists($provider_file)) {
      return null;
    }

    require_once $provider_file;
    return new $class_name($this->get_provider_config($provider));
  }

  /**
   * 获取提供商配置
   */
  private function get_provider_config($provider)
  {
    return array(
      'app_id' => get_option("xiaowu_cloud_{$provider}_app_id", ''),
      'app_secret' => get_option("xiaowu_cloud_{$provider}_app_secret", ''),
      'access_token' => get_option("xiaowu_cloud_{$provider}_access_token", ''),
      'refresh_token' => get_option("xiaowu_cloud_{$provider}_refresh_token", '')
    );
  }

  /**
   * 获取所有支持的提供商
   */
  public function get_all_providers()
  {
    return array(
      'aliyun' => '阿里云盘',
      'baidu' => '百度网盘',
      'onedrive' => 'OneDrive'
    );
  }
}
