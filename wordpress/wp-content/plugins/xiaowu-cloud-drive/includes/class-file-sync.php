<?php
/**
 * 文件同步类
 *
 * 处理WordPress媒体库与云盘的文件同步
 *
 * @package Xiaowu_Cloud
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
  exit;
}

class Xiaowu_Cloud_File_Sync
{
  private $drive_manager;

  public function __construct()
  {
    $this->drive_manager = new Xiaowu_Cloud_Drive_Manager();
  }

  /**
   * 同步媒体到云盘
   */
  public function sync_media_to_cloud($media_id)
  {
    $file_path = get_attached_file($media_id);

    if (!$file_path || !file_exists($file_path)) {
      return array(
        'success' => false,
        'error' => '文件不存在'
      );
    }

    $provider = get_option('xiaowu_cloud_sync_provider', 'aliyun');
    $drive = $this->drive_manager->get_provider($provider);

    if (!$drive) {
      return array(
        'success' => false,
        'error' => '无效的云盘提供商'
      );
    }

    $result = $drive->upload_file($file_path);

    if ($result['success']) {
      // 保存云盘文件ID
      update_post_meta($media_id, '_cloud_file_id', $result['file_id']);
      update_post_meta($media_id, '_cloud_provider', $provider);
      update_post_meta($media_id, '_cloud_url', $result['url']);
    }

    return $result;
  }

  /**
   * 从云盘下载文件
   */
  public function download_from_cloud($media_id)
  {
    $file_id = get_post_meta($media_id, '_cloud_file_id', true);
    $provider = get_post_meta($media_id, '_cloud_provider', true);

    if (!$file_id || !$provider) {
      return array(
        'success' => false,
        'error' => '文件未同步到云盘'
      );
    }

    $drive = $this->drive_manager->get_provider($provider);

    if (!$drive) {
      return array(
        'success' => false,
        'error' => '无效的云盘提供商'
      );
    }

    $download_url = $drive->download_file($file_id);

    if (!$download_url) {
      return array(
        'success' => false,
        'error' => '获取下载URL失败'
      );
    }

    // 下载文件
    $tmp_file = download_url($download_url);

    if (is_wp_error($tmp_file)) {
      return array(
        'success' => false,
        'error' => $tmp_file->get_error_message()
      );
    }

    // 附加到媒体库
    $attachment_id = media_handle_sideload(array(
      'tmp_name' => $tmp_file,
      'name' => wp_basename($download_url)
    ));

    if (is_wp_error($attachment_id)) {
      return array(
        'success' => false,
        'error' => $attachment_id->get_error_message()
      );
    }

    return array(
      'success' => true,
      'attachment_id' => $attachment_id
    );
  }

  /**
   * 批量同步
   */
  public function batch_sync($media_ids = array())
  {
    if (empty($media_ids)) {
      // 获取最近的媒体文件
      $media_ids = get_posts(array(
        'post_type' => 'attachment',
        'posts_per_page' => 10,
        'fields' => 'ids',
        'post_status' => 'any'
      ));
    }

    $results = array(
      'success' => 0,
      'failed' => 0,
      'errors' => array()
    );

    foreach ($media_ids as $media_id) {
      $result = $this->sync_media_to_cloud($media_id);

      if ($result['success']) {
        $results['success']++;
      } else {
        $results['failed']++;
        $results['errors'][] = array(
          'media_id' => $media_id,
          'error' => $result['error']
        );
      }
    }

    return $results;
  }
}
