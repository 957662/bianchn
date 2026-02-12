<?php
/**
 * Plugin Name: 小伍云盘集成
 * Plugin URI: https://github.com/957662/bianchn
 * Description: 集成阿里云盘、百度网盘、OneDrive等云存储服务
 * Version: 2.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xiaowu-cloud-drive
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
  exit;
}

// 插件版本
define('XIAOWU_CLOUD_VERSION', '2.0.0');

// 插件路径
define('XIAOWU_CLOUD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_CLOUD_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 小伍云盘集成插件主类
 */
class Xiaowu_Cloud_Drive_Plugin
{
  /**
   * 单例实例
   */
  private static $instance = null;

  /**
   * 获取单例实例
   */
  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * 构造函数
   */
  private function __construct()
  {
    $this->init_hooks();
    $this->includes();
  }

  /**
   * 初始化钩子
   */
  private function init_hooks()
  {
    // 插件激活
    register_activation_hook(__FILE__, array($this, 'activate'));

    // 加载文本域
    add_action('plugins_loaded', array($this, 'load_textdomain'));

    // 管理菜单
    add_action('admin_menu', array($this, 'add_admin_menu'));

    // REST API路由
    add_action('rest_api_init', array($this, 'register_rest_routes'));

    // 媒体上传到云盘
    add_filter('wp_handle_upload', array($this, 'upload_to_cloud'), 10, 2);
  }

  /**
   * 加载必需文件
   */
  private function includes()
  {
    require_once XIAOWU_CLOUD_PLUGIN_DIR . 'includes/class-drive-manager.php';
    require_once XIAOWU_CLOUD_PLUGIN_DIR . 'includes/class-file-sync.php';
  }

  /**
   * 加载文本域
   */
  public function load_textdomain()
  {
    load_plugin_textdomain('xiaowu-cloud-drive', false, dirname(plugin_basename(__FILE__)) . '/languages');
  }

  /**
   * 插件激活
   */
  public function activate()
  {
    // 创建数据表
    $this->create_tables();

    // 设置默认选项
    update_option('xiaowu_cloud_sync_provider', 'aliyun');
    update_option('xiaowu_cloud_auto_sync', true);
  }

  /**
   * 创建数据表
   */
  private function create_tables()
  {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // 云盘文件记录表
    $table_name = $wpdb->prefix . 'xiaowu_cloud_files';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      attachment_id bigint(20) DEFAULT NULL,
      provider varchar(50) NOT NULL,
      file_id varchar(255) NOT NULL,
      file_name varchar(255) NOT NULL,
      file_size bigint(20) DEFAULT 0,
      file_url text,
      sync_status varchar(20) DEFAULT 'synced',
      synced_at datetime DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY attachment_id (attachment_id),
      KEY provider (provider),
      KEY file_id (file_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  /**
   * 添加管理菜单
   */
  public function add_admin_menu()
  {
    add_menu_page(
      '云盘管理',
      '云盘',
      'manage_options',
      'xiaowu-cloud-drive',
      array($this, 'render_admin_page'),
      'dashicons-cloud',
      20
    );

    add_submenu_page(
      'xiaowu-cloud-drive',
      '云盘设置',
      '设置',
      'manage_options',
      'xiaowu-cloud-settings',
      array($this, 'render_settings_page')
    );
  }

  /**
   * 渲染管理页面
   */
  public function render_admin_page()
  {
    require_once XIAOWU_CLOUD_PLUGIN_DIR . 'admin/file-browser.php';
  }

  /**
   * 渲染设置页面
   */
  public function render_settings_page()
  {
    require_once XIAOWU_CLOUD_PLUGIN_DIR . 'admin/settings-page.php';
  }

  /**
   * 注册REST API路由
   */
  public function register_rest_routes()
  {
    // 获取云盘文件列表
    register_rest_route('xiaowu/v1', '/cloud/files', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_files'),
      'permission_callback' => function() {
        return current_user_can('manage_options');
      }
    ));

    // 上传文件到云盘
    register_rest_route('xiaowu/v1', '/cloud/upload', array(
      'methods' => 'POST',
      'callback' => array($this, 'upload_file'),
      'permission_callback' => function() {
        return current_user_can('upload_files');
      }
    ));

    // 从云盘下载文件
    register_rest_route('xiaowu/v1', '/cloud/download', array(
      'methods' => 'POST',
      'callback' => array($this, 'download_file'),
      'permission_callback' => function() {
        return current_user_can('upload_files');
      }
    ));

    // 测试云盘连接
    register_rest_route('xiaowu/v1', '/cloud/test', array(
      'methods' => 'POST',
      'callback' => array($this, 'test_connection'),
      'permission_callback' => function() {
        return current_user_can('manage_options');
      }
    ));
  }

  /**
   * 获取云盘文件列表
   */
  public function get_files($request)
  {
    $provider = $request->get_param('provider') ?: get_option('xiaowu_cloud_sync_provider', 'aliyun');
    $parent_id = $request->get_param('parent_id') ?: 'root';

    $manager = new Xiaowu_Cloud_Drive_Manager();
    $drive = $manager->get_provider($provider);

    if (!$drive) {
      return new WP_Error('invalid_provider', '无效的云盘提供商', array('status' => 400));
    }

    $files = $drive->get_file_list($parent_id);

    return rest_ensure_response($files);
  }

  /**
   * 上传文件到云盘
   */
  public function upload_file($request)
  {
    $file_id = $request->get_param('file_id');
    $provider = $request->get_param('provider') ?: get_option('xiaowu_cloud_sync_provider', 'aliyun');

    $file_path = get_attached_file($file_id);

    if (!$file_path || !file_exists($file_path)) {
      return new WP_Error('file_not_found', '文件不存在', array('status' => 404));
    }

    $manager = new Xiaowu_Cloud_Drive_Manager();
    $drive = $manager->get_provider($provider);

    if (!$drive) {
      return new WP_Error('invalid_provider', '无效的云盘提供商', array('status' => 400));
    }

    $result = $drive->upload_file($file_path);

    if ($result['success']) {
      // 记录同步信息
      $this->save_file_record($file_id, $provider, $result['file_id'], $result['url']);
    }

    return rest_ensure_response($result);
  }

  /**
   * 从云盘下载文件
   */
  public function download_file($request)
  {
    $cloud_file_id = $request->get_param('cloud_file_id');
    $provider = $request->get_param('provider') ?: get_option('xiaowu_cloud_sync_provider', 'aliyun');

    $manager = new Xiaowu_Cloud_Drive_Manager();
    $drive = $manager->get_provider($provider);

    if (!$drive) {
      return new WP_Error('invalid_provider', '无效的云盘提供商', array('status' => 400));
    }

    $download_url = $drive->download_file($cloud_file_id);

    if (!$download_url) {
      return new WP_Error('download_failed', '下载失败', array('status' => 500));
    }

    // 下载并保存到媒体库
    $tmp_file = download_url($download_url);

    if (is_wp_error($tmp_file)) {
      return $tmp_file;
    }

    // 附加到媒体库
    $attachment_id = media_handle_sideload(array(
      'tmp_name' => $tmp_file,
      'name' => basename($download_url)
    ));

    return rest_ensure_response(array(
      'success' => true,
      'attachment_id' => $attachment_id
    ));
  }

  /**
   * 测试云盘连接
   */
  public function test_connection($request)
  {
    $provider = $request->get_param('provider') ?: get_option('xiaowu_cloud_sync_provider', 'aliyun');
    $config = $request->get_json_params();

    // 保存配置
    if (!empty($config['app_id'])) {
      update_option("xiaowu_cloud_{$provider}_app_id", $config['app_id']);
    }
    if (!empty($config['app_secret'])) {
      update_option("xiaowu_cloud_{$provider}_app_secret", $config['app_secret']);
    }

    $manager = new Xiaowu_Cloud_Drive_Manager();
    $drive = $manager->get_provider($provider);

    if (!$drive) {
      return new WP_Error('invalid_provider', '无效的云盘提供商', array('status' => 400));
    }

    // 获取授权URL
    $auth_url = $drive->authorize();

    return rest_ensure_response(array(
      'success' => true,
      'auth_url' => $auth_url,
      'message' => '请访问授权URL完成认证'
    ));
  }

  /**
   * 上传媒体到云盘
   */
  public function upload_to_cloud($upload, $context)
  {
    // 检查是否启用自动同步
    if (!get_option('xiaowu_cloud_auto_sync', true)) {
      return $upload;
    }

    // 只同步图片和文档
    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'application/pdf');
    if (!in_array($upload['type'], $allowed_types)) {
      return $upload;
    }

    $attachment_id = attachment_url_to_postid($upload['url']);
    if (!$attachment_id) {
      return $upload;
    }

    $sync = new Xiaowu_Cloud_File_Sync();
    $sync->sync_media_to_cloud($attachment_id);

    return $upload;
  }

  /**
   * 保存文件记录
   */
  private function save_file_record($attachment_id, $provider, $cloud_file_id, $cloud_url)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . 'xiaowu_cloud_files';

    $wpdb->replace(
      $table_name,
      array(
        'attachment_id' => $attachment_id,
        'provider' => $provider,
        'file_id' => $cloud_file_id,
        'file_url' => $cloud_url,
        'sync_status' => 'synced',
        'synced_at' => current_time('mysql')
      ),
      array('%d', '%s', '%s', '%s', '%s', '%s')
    );
  }
}

// 初始化插件
Xiaowu_Cloud_Drive_Plugin::get_instance();
