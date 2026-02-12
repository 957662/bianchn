<?php
/**
 * 阿里云盘提供商
 *
 * @package Xiaowu_Cloud
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
  exit;
}

class Xiaowu_Cloud_Drive_Provider_Aliyun
{
  private $access_token;
  private $drive_id;
  private $api_base = 'https://openapi.alipan.com/adrive/v1.0';

  public function __construct($config)
  {
    $this->access_token = $config['access_token'] ?? '';
    $this->drive_id = get_option('xiaowu_cloud_aliyun_drive_id', '');
  }

  /**
   * 获取授权URL
   */
  public function authorize()
  {
    $app_id = get_option('xiaowu_cloud_aliyun_app_id', '');
    $redirect_uri = admin_url('admin.php?page=xiaowu-cloud-drive&auth=aliyun');

    $auth_url = add_query_arg(array(
      'client_id' => $app_id,
      'redirect_uri' => $redirect_uri,
      'scope' => 'user:base,file:all:read,file:all:write',
      'response_type' => 'code'
    ), 'https://open.alipan.com/oauth/authorize');

    return $auth_url;
  }

  /**
   * 获取访问令牌
   */
  public function get_access_token($code)
  {
    $app_id = get_option('xiaowu_cloud_aliyun_app_id', '');
    $app_secret = get_option('xiaowu_cloud_aliyun_app_secret', '');

    $response = wp_remote_post('https://open.alipan.com/oauth/access_token', array(
      'body' => json_encode(array(
        'client_id' => $app_id,
        'client_secret' => $app_secret,
        'grant_type' => 'authorization_code',
        'code' => $code
      ))
    ));

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['access_token'])) {
      update_option('xiaowu_cloud_aliyun_access_token', $body['access_token']);
      update_option('xiaowu_cloud_aliyun_refresh_token', $body['refresh_token']);
      $this->access_token = $body['access_token'];

      // 获取drive_id
      $this->get_drive_id();

      return true;
    }

    return false;
  }

  /**
   * 获取drive_id
   */
  private function get_drive_id()
  {
    $response = wp_remote_get($this->api_base . '/user/getDriveInfo', array(
      'headers' => array(
        'Authorization' => 'Bearer ' . $this->access_token
      )
    ));

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['default_drive_id'])) {
      $this->drive_id = $body['default_drive_id'];
      update_option('xiaowu_cloud_aliyun_drive_id', $this->drive_id);
    }
  }

  /**
   * 获取文件列表
   */
  public function get_file_list($parent_file_id = 'root')
  {
    $response = wp_remote_get($this->api_base . '/openFile/list', array(
      'headers' => array(
        'Authorization' => 'Bearer ' . $this->access_token
      ),
      'body' => json_encode(array(
        'drive_id' => $this->drive_id,
        'parent_file_id' => $parent_file_id,
        'limit' => 100
      ))
    ));

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['items'] ?? array();
  }

  /**
   * 上传文件
   */
  public function upload_file($file_path, $remote_filename = null)
  {
    if (!$remote_filename) {
      $remote_filename = basename($file_path);
    }

    // 1. 创建上传会话
    $response = wp_remote_post($this->api_base . '/openFile/create', array(
      'headers' => array(
        'Authorization' => 'Bearer ' . $this->access_token,
        'Content-Type' => 'application/json'
      ),
      'body' => json_encode(array(
        'drive_id' => $this->drive_id,
        'parent_file_id' => 'root',
        'name' => $remote_filename,
        'type' => 'file',
        'check_name_mode' => 'overwrite'
      ))
    ));

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['file_id'])) {
      return array(
        'success' => false,
        'error' => '创建文件失败'
      );
    }

    $file_id = $body['file_id'];
    $upload_url = $body['upload_url'];

    // 2. 上传文件内容
    $response = wp_remote_put($upload_url, array(
      'body' => file_get_contents($file_path),
      'headers' => array(
        'Content-Type' => 'application/octet-stream'
      )
    ));

    if (is_wp_error($response)) {
      return array(
        'success' => false,
        'error' => '上传失败'
      );
    }

    // 3. 完成上传
    wp_remote_post($this->api_base . '/openFile/complete', array(
      'headers' => array(
        'Authorization' => 'Bearer ' . $this->access_token,
        'Content-Type' => 'application/json'
      ),
      'body' => json_encode(array(
        'drive_id' => $this->drive_id,
        'file_id' => $file_id
      ))
    ));

    return array(
      'success' => true,
      'file_id' => $file_id,
      'url' => "https://www.alipan.com/drive/file/{$file_id}"
    );
  }

  /**
   * 下载文件
   */
  public function download_file($file_id)
  {
    $response = wp_remote_get($this->api_base . '/openFile/getDownloadUrl', array(
      'headers' => array(
        'Authorization' => 'Bearer ' . $this->access_token,
        'Content-Type' => 'application/json'
      ),
      'body' => json_encode(array(
        'drive_id' => $this->drive_id,
        'file_id' => $file_id
      ))
    ));

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return $body['url'] ?? false;
  }
}
