<?php
/**
 * Plugin Name: 小伍部署向导
 * Plugin URI: https://github.com/957662/bianchn
 * Description: 部署配置向导，提供Web界面配置系统环境变量
 * Version: 2.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xiaowu-deployment
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
  exit;
}

// 插件版本
define('XIAOWU_DEPLOY_VERSION', '1.0.0');

// 插件路径
define('XIAOWU_DEPLOY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_DEPLOY_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 小伍部署向导插件主类
 */
class Xiaowu_Deployment_Wizard_Plugin
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
  }

  /**
   * 初始化钩子
   */
  private function init_hooks()
  {
    // 插件激活
    register_activation_hook(__FILE__, array($this, 'activate'));

    // REST API路由
    add_action('rest_api_init', array($this, 'register_rest_routes'));
  }

  /**
   * 插件激活
   */
  public function activate()
  {
    // 创建配置存储表
    $this->create_config_table();
  }

  /**
   * 创建配置表
   */
  private function create_config_table()
  {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'xiaowu_deployment_config';
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      config_key varchar(100) NOT NULL,
      config_value longtext,
      config_group varchar(50) DEFAULT 'general',
      is_encrypted tinyint(1) DEFAULT 0,
      updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY config_key (config_key),
      KEY config_group (config_group)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  /**
   * 注册REST API路由
   */
  public function register_rest_routes()
  {
    // 检查环境
    register_rest_route('xiaowu/v1', '/deployment/environment', array(
      'methods' => 'GET',
      'callback' => array($this, 'check_environment'),
      'permission_callback' => '__return_true'
    ));

    // 测试数据库连接
    register_rest_route('xiaowu/v1', '/deployment/test-db', array(
      'methods' => 'POST',
      'callback' => array($this, 'test_db'),
      'permission_callback' => '__return_true'
    ));

    // 测试AI连接
    register_rest_route('xiaowu/v1', '/deployment/test-ai', array(
      'methods' => 'POST',
      'callback' => array($this, 'test_ai'),
      'permission_callback' => '__return_true'
    ));

    // 测试邮件
    register_rest_route('xiaowu/v1', '/deployment/test-email', array(
      'methods' => 'POST',
      'callback' => array($this, 'test_email'),
      'permission_callback' => '__return_true'
    ));

    // 保存配置
    register_rest_route('xiaowu/v1', '/deployment/save/(?P<type>[a-z-]+)', array(
      'methods' => 'POST',
      'callback' => array($this, 'save_config'),
      'permission_callback' => '__return_true'
    ));

    // 生成配置文件
    register_rest_route('xiaowu/v1', '/deployment/generate', array(
      'methods' => 'POST',
      'callback' => array($this, 'generate_config'),
      'permission_callback' => '__return_true'
    ));

    // 应用配置
    register_rest_route('xiaowu/v1', '/deployment/apply', array(
      'methods' => 'POST',
      'callback' => array($this, 'apply_config'),
      'permission_callback' => '__return_true'
    ));

    // 标记部署完成
    register_rest_route('xiaowu/v1', '/deployment/complete', array(
      'methods' => 'POST',
      'callback' => array($this, 'mark_completed'),
      'permission_callback' => '__return_true'
    ));

    // 获取已保存的配置
    register_rest_route('xiaowu/v1', '/deployment/config', array(
      'methods' => 'GET',
      'callback' => array($this, 'get_saved_config'),
      'permission_callback' => '__return_true'
    ));
  }

  /**
   * 检查环境
   */
  public function check_environment($request)
  {
    $result = array(
      'php' => phpversion(),
      'mysql' => $this->get_mysql_version(),
      'redis' => $this->check_redis_connection(),
      'wordpress' => $this->check_wordpress_installation(),
      'extensions' => $this->check_php_extensions()
    );

    return rest_ensure_response($result, 200);
  }

  /**
   * 获取MySQL版本
   */
  private function get_mysql_version()
  {
    global $wpdb;
    $version = $wpdb->get_var('SELECT VERSION()');
    return $version;
  }

  /**
   * 检查Redis连接
   */
  private function check_redis_connection()
  {
    if (!extension_loaded('redis')) {
      return false;
    }

    try {
      $redis = new Redis();
      $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
      $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
      $connected = $redis->connect($host, $port);
      $redis->close();
      return $connected;
    } catch (Exception $e) {
      return false;
    }
  }

  /**
   * 检查WordPress安装
   */
  private function check_wordpress_installation()
  {
    return defined('DB_NAME') && defined('DB_USER');
  }

  /**
   * 检查PHP扩展
   */
  private function check_php_extensions()
  {
    $required = array('curl', 'mbstring', 'mysqli', 'json');
    $missing = array();

    foreach ($required as $ext) {
      if (!extension_loaded($ext)) {
        $missing[] = $ext;
      }
    }

    return array(
      'required' => $required,
      'missing' => $missing,
      'all_present' => empty($missing)
    );
  }

  /**
   * 测试数据库连接
   */
  public function test_db($request)
  {
    $params = $request->get_json_params();

    $host = sanitize_text_field($params['host'] ?? 'localhost');
    $name = sanitize_text_field($params['name'] ?? '');
    $user = sanitize_text_field($params['user'] ?? '');
    $password = $params['password'] ?? '';

    $connection = mysqli_connect($host, $user, $password);

    if (!$connection) {
      return new WP_Error(
        'db_connection_failed',
        '数据库连接失败: ' . mysqli_connect_error(),
        array('status' => 400)
      );
    }

    $selected = mysqli_select_db($connection, $name);
    mysqli_close($connection);

    if (!$selected) {
      return new WP_Error(
        'db_not_found',
        '数据库不存在或无权限访问',
        array('status' => 400)
      );
    }

    return rest_ensure_response(
      array('success' => true, 'message' => '数据库连接成功'),
      200
    );
  }

  /**
   * 测试AI连接
   */
  public function test_ai($request)
  {
    $params = $request->get_json_params();

    $provider = sanitize_text_field($params['provider'] ?? 'openai');
    $apiKey = $params['apiKey'] ?? '';
    $model = sanitize_text_field($params['model'] ?? '');

    // 这里调用实际的AI服务进行测试
    $result = $this->test_ai_connection($provider, $apiKey, $model);

    if (is_wp_error($result)) {
      return $result;
    }

    return rest_ensure_response(
      array('success' => true, 'message' => 'AI服务连接成功'),
      200
    );
  }

  /**
   * 测试AI连接（简化版）
   */
  private function test_ai_connection($provider, $apiKey, $model)
  {
    // 实际项目中这里会调用对应的AI API
    // 这里返回成功用于演示
    return true;
  }

  /**
   * 测试邮件
   */
  public function test_email($request)
  {
    $params = $request->get_json_params();

    $to = get_option('admin_email');
    $subject = '小伍博客 - 邮件服务测试';
    $message = '这是一封测试邮件，如果您收到此邮件，说明邮件服务配置正确。';

    $sent = wp_mail($to, $subject, $message);

    if ($sent) {
      return rest_ensure_response(
        array('success' => true, 'message' => '测试邮件已发送'),
        200
      );
    } else {
      return new WP_Error(
        'email_send_failed',
        '邮件发送失败',
        array('status' => 500)
      );
    }
  }

  /**
   * 保存配置
   */
  public function save_config($request)
  {
    $type = $request->get_param('type');
    $data = $request->get_json_params();

    switch ($type) {
      case 'db':
        $this->save_config_value('db_host', $data['host']);
        $this->save_config_value('db_name', $data['name']);
        $this->save_config_value('db_user', $data['user']);
        $this->save_config_value('db_password', $data['password'], true);
        break;

      case 'ai':
        $this->save_config_value('ai_provider', $data['provider']);
        $this->save_config_value('ai_api_key', $data['apiKey'], true);
        $this->save_config_value('ai_model', $data['model']);
        $this->save_config_value('ai_max_tokens', $data['maxTokens']);
        $this->save_config_value('ai_temperature', $data['temperature']);
        break;

      case 'cdn':
        $this->save_config_value('cdn_provider', $data['provider']);
        $this->save_config_value('cdn_secret_id', $data['secretId'], true);
        $this->save_config_value('cdn_secret_key', $data['secretKey'], true);
        $this->save_config_value('cdn_bucket', $data['bucket']);
        $this->save_config_value('cdn_region', $data['region']);
        break;

      case 'email':
        $this->save_config_value('smtp_host', $data['host']);
        $this->save_config_value('smtp_port', $data['port']);
        $this->save_config_value('smtp_encryption', $data['encryption']);
        $this->save_config_value('smtp_from_email', $data['fromEmail']);
        $this->save_config_value('smtp_from_name', $data['fromName']);
        $this->save_config_value('smtp_username', $data['username']);
        $this->save_config_value('smtp_password', $data['password'], true);
        break;
    }

    return rest_ensure_response(
      array('success' => true, 'message' => '配置已保存'),
      200
    );
  }

  /**
   * 保存配置值
   */
  private function save_config_value($key, $value, $is_encrypted = false)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . 'xiaowu_deployment_config';

    // 检查是否已存在
    $exists = $wpdb->get_var(
      $wpdb->prepare("SELECT id FROM $table_name WHERE config_key = %s", $key)
    );

    if ($exists) {
      $wpdb->update(
        $table_name,
        array(
          'config_value' => $value,
          'is_encrypted' => $is_encrypted ? 1 : 0,
          'updated_at' => current_time('mysql')
        ),
        array('config_key' => $key)
      );
    } else {
      $wpdb->insert(
        $table_name,
        array(
          'config_key' => $key,
          'config_value' => $value,
          'is_encrypted' => $is_encrypted ? 1 : 0
        )
      );
    }
  }

  /**
   * 生成配置文件
   */
  public function generate_config($request)
  {
    $data = $request->get_json_params();

    $php_config = $this->generate_php_config($data);

    return rest_ensure_response(
      array('php' => $php_config),
      200
    );
  }

  /**
   * 生成PHP配置文件内容
   */
  private function generate_php_config($data)
  {
    $db = $data['db'] ?? array();
    $ai = $data['ai'] ?? array();
    $cdn = $data['cdn'] ?? array();
    $email = $data['email'] ?? array();

    // 从数据库获取已保存的配置
    global $wpdb;
    $table_name = $wpdb->prefix . 'xiaowu_deployment_config';

    $configs = $wpdb->get_results(
      "SELECT config_key, config_value FROM $table_name",
      ARRAY_A
    );

    $config_map = array();
    foreach ($configs as $config) {
      $config_map[$config->config_key] = $config->config_value;
    }

    // 使用保存的配置
    $db_host = $db['host'] ?? $config_map['db_host'] ?? 'localhost';
    $db_name = $db['name'] ?? $config_map['db_name'] ?? 'xiaowu_blog';
    $db_user = $db['user'] ?? $config_map['db_user'] ?? 'xiaowu_user';
    $db_password = $db['password'] ?? $config_map['db_password'] ?? '';

    $ai_provider = $ai['provider'] ?? $config_map['ai_provider'] ?? 'openai';
    $ai_api_key = $ai['apiKey'] ?? $config_map['ai_api_key'] ?? '';
    $ai_model = $ai['model'] ?? $config_map['ai_model'] ?? 'gpt-4';
    $ai_max_tokens = $ai['maxTokens'] ?? $config_map['ai_max_tokens'] ?? '4000';
    $ai_temperature = $ai['temperature'] ?? $config_map['ai_temperature'] ?? '0.7';

    $cdn_provider = $cdn['provider'] ?? $config_map['cdn_provider'] ?? 'local';
    $cdn_secret_id = $cdn['secretId'] ?? $config_map['cdn_secret_id'] ?? '';
    $cdn_secret_key = $cdn['secretKey'] ?? $config_map['cdn_secret_key'] ?? '';
    $cdn_bucket = $cdn['bucket'] ?? $config_map['cdn_bucket'] ?? 'xiaowu-blog';
    $cdn_region = $cdn['region'] ?? $config_map['cdn_region'] ?? 'ap-shanghai';

    $smtp_host = $email['host'] ?? $config_map['smtp_host'] ?? 'smtp.gmail.com';
    $smtp_port = $email['port'] ?? $config_map['smtp_port'] ?? '587';
    $smtp_encryption = $email['encryption'] ?? $config_map['smtp_encryption'] ?? 'tls';
    $smtp_from_email = $email['fromEmail'] ?? $config_map['smtp_from_email'] ?? 'noreply@example.com';
    $smtp_from_name = $email['fromName'] ?? $config_map['smtp_from_name'] ?? '小伍同学博客';
    $smtp_username = $email['username'] ?? $config_map['smtp_username'] ?? '';
    $smtp_password = $email['password'] ?? $config_map['smtp_password'] ?? '';

    // 生成唯一密钥
    $auth_keys = array(
      'AUTH_KEY' => wp_generate_password(64),
      'SECURE_AUTH_KEY' => wp_generate_password(64),
      'LOGGED_IN_KEY' => wp_generate_password(64),
      'NONCE_KEY' => wp_generate_password(64),
      'AUTH_SALT' => wp_generate_password(64),
      'SECURE_AUTH_SALT' => wp_generate_password(64),
      'LOGGED_IN_SALT' => wp_generate_password(64),
      'NONCE_SALT' => wp_generate_password(64)
    );

    ob_start();
    ?>
<?php
/**
 * WordPress基础配置文件
 *
 * 本文件由部署向导自动生成，请勿手动修改
 *
 * @package WordPress
 */

// 数据库配置
define('DB_NAME', '<?php echo esc_js($db_name); ?>');
define('DB_USER', '<?php echo esc_js($db_user); ?>');
define('DB_PASSWORD', '<?php echo esc_js($db_password); ?>');
define('DB_HOST', '<?php echo esc_js($db_host); ?>');
define('DB_CHARSET', 'utf8mb4');

// 身份验证密钥
define('AUTH_KEY',         '<?php echo $auth_keys['AUTH_KEY']; ?>');
define('SECURE_AUTH_KEY',  '<?php echo $auth_keys['SECURE_AUTH_KEY']; ?>');
define('LOGGED_IN_KEY',    '<?php echo $auth_keys['LOGGED_IN_KEY']; ?>');
define('NONCE_KEY',        '<?php echo $auth_keys['NONCE_KEY']; ?>');
define('AUTH_SALT',        '<?php echo $auth_keys['AUTH_SALT']; ?>');
define('SECURE_AUTH_SALT', '<?php echo $auth_keys['SECURE_AUTH_SALT']; ?>');
define('LOGGED_IN_SALT',   '<?php echo $auth_keys['LOGGED_IN_SALT']; ?>');
define('NONCE_SALT',       '<?php echo $auth_keys['NONCE_SALT']; ?>');

// WordPress 数据表前缀
$table_prefix = 'wp_';

// AI服务配置
define('XIAOWU_AI_PROVIDER', '<?php echo esc_js($ai_provider); ?>');
define('XIAOWU_AI_API_KEY', '<?php echo esc_js($ai_api_key); ?>');
define('XIAOWU_AI_MODEL', '<?php echo esc_js($ai_model); ?>');
define('XIAOWU_AI_MAX_TOKENS', <?php echo intval($ai_max_tokens); ?>);
define('XIAOWU_AI_TEMPERATURE', <?php echo floatval($ai_temperature); ?>);

// CDN配置
define('XIAOWU_CDN_PROVIDER', '<?php echo esc_js($cdn_provider); ?>');
define('XIAOWU_CDN_SECRET_ID', '<?php echo esc_js($cdn_secret_id); ?>');
define('XIAOWU_CDN_SECRET_KEY', '<?php echo esc_js($cdn_secret_key); ?>');
define('XIAOWU_CDN_BUCKET', '<?php echo esc_js($cdn_bucket); ?>');
define('XIAOWU_CDN_REGION', '<?php echo esc_js($cdn_region); ?>');

// SMTP配置
define('XIAOWU_SMTP_HOST', '<?php echo esc_js($smtp_host); ?>');
define('XIAOWU_SMTP_PORT', <?php echo intval($smtp_port); ?>);
define('XIAOWU_SMTP_ENCRYPTION', '<?php echo esc_js($smtp_encryption); ?>');
define('XIAOWU_SMTP_FROM_EMAIL', '<?php echo esc_js($smtp_from_email); ?>');
define('XIAOWU_SMTP_FROM_NAME', '<?php echo esc_js($smtp_from_name); ?>');
define('XIAOWU_SMTP_USERNAME', '<?php echo esc_js($smtp_username); ?>');
define('XIAOWU_SMTP_PASSWORD', '<?php echo esc_js($smtp_password); ?>');

/** 绝对路径 */
if (!defined('ABSPATH')) {
  define('ABSPATH', dirname(__FILE__) . '/');
}

/** 设置WordPress变量和包含文件 */
require_once ABSPATH . 'wp-settings.php';
<?php
    $content = ob_get_clean();
    return $content;
  }

  /**
   * 应用配置
   */
  public function apply_config($request)
  {
    // 生成wp-config.php文件
    $data = $request->get_json_params();
    $php_config = $this->generate_php_config($data);

    // 写入wp-config.php
    $config_file = ABSPATH . 'wp-config.php';
    $result = file_put_contents($config_file, $php_config, LOCK_EX);

    if ($result === false) {
      return new WP_Error(
        'config_write_failed',
        '无法写入配置文件，请检查文件权限',
        array('status' => 500)
      );
    }

    return rest_ensure_response(
      array('success' => true, 'message' => '配置已应用'),
      200
    );
  }

  /**
   * 标记部署完成
   */
  public function mark_completed($request)
  {
    // 标记已完成
    update_option('xiaowu_deployment_completed', current_time('mysql'));
    update_option('xiaowu_first_deploy', 'false');

    return rest_ensure_response(
      array('success' => true, 'message' => '部署已完成'),
      200
    );
  }

  /**
   * 获取已保存的配置
   */
  public function get_saved_config($request)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . 'xiaowu_deployment_config';

    $configs = $wpdb->get_results(
      "SELECT config_key, config_value, is_encrypted FROM $table_name",
      ARRAY_A
    );

    $result = array(
      'db' => array(),
      'ai' => array(),
      'cdn' => array(),
      'email' => array()
    );

    foreach ($configs as $config) {
      $value = $config->is_encrypted ? '***' : $config->config_value;

      if (strpos($config->config_key, 'db_') === 0) {
        $result['db'][substr($config->config_key, 3)] = $value;
      } elseif (strpos($config->config_key, 'ai_') === 0) {
        $result['ai'][substr($config->config_key, 3)] = $value;
      } elseif (strpos($config->config_key, 'cdn_') === 0) {
        $result['cdn'][substr($config->config_key, 4)] = $value;
      } elseif (strpos($config->config_key, 'smtp_') === 0) {
        $result['email'][substr($config->config_key, 5)] = $value;
      }
    }

    return rest_ensure_response($result, 200);
  }
}

// 初始化插件
Xiaowu_Deployment_Wizard_Plugin::get_instance();
