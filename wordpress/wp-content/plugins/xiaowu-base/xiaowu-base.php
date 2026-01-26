<?php
/**
 * Plugin Name: xiaowu-base
 * Description: 小伍博客基础服务插件 - 提供速率限制、安全认证等基础功能
 * Version: 1.0.0
 * Author: xiaowu
 * License: GPL v2 or later
 * Text Domain: xiaowu-base
 * Domain Path: /languages
 *
 * @package xiaowu-base
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XIAOWU_BASE_VERSION', '1.0.0');
define('XIAOWU_BASE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_BASE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 自动加载类文件
 */
spl_autoload_register(function ($class) {
    // 检查类是否在我们的命名空间内
    if (strpos($class, 'XiaowuBase\\') !== 0) {
        return;
    }

    // 移除命名空间前缀
    $relative_class = substr($class, strlen('XiaowuBase\\'));

    // 将类名转换为文件路径
    $file = XIAOWU_BASE_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php';

    // 如果文件存在，加载它
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * 插件启动函数
 */
function xiaowu_base_init() {
    // 加载翻译文件
    load_plugin_textdomain(
        'xiaowu-base',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );

    // 初始化数据库
    require_once XIAOWU_BASE_PLUGIN_DIR . 'includes/class-database-init.php';

    // 初始化速率限制器
    if (class_exists('XiaowuBase\\Security\\RateLimiter')) {
        // 速率限制器会在需要时创建实例
    }

    // 初始化 API 中间件
    if (class_exists('XiaowuBase\\APIMiddleware')) {
        new \XiaowuBase\APIMiddleware();
    }

    // 初始化 CORS 管理器
    if (class_exists('XiaowuBase\\Security\\CORSManager')) {
        $cors_manager = new \XiaowuBase\Security\CORSManager();
        $cors_manager->register_rest_routes();
    }

    // 初始化输入验证系统
    if (class_exists('XiaowuBase\\Security\\InputValidator')) {
        // 输入验证器会在需要时创建实例
    }

    // 初始化 SQL 注入防护
    if (class_exists('XiaowuBase\\Security\\SQLInjectionProtection')) {
        // SQL 注入防护会在需要时创建实例
    }

    // 初始化 XSS 防护
    if (class_exists('XiaowuBase\\Security\\XSSProtection')) {
        // XSS 防护会在需要时创建实例
    }
}

add_action('plugins_loaded', 'xiaowu_base_init');

/**
 * 插件激活钩子
 */
function xiaowu_base_activate() {
    require_once XIAOWU_BASE_PLUGIN_DIR . 'includes/class-database-init.php';
    \XiaowuBase\DatabaseInit::create_all_tables();
}

register_activation_hook(__FILE__, 'xiaowu_base_activate');

/**
 * 插件停用钩子
 */
function xiaowu_base_deactivate() {
    // 可选：清理缓存或日志
    wp_clear_scheduled_hook('xiaowu_cleanup_expired_data');
}

register_deactivation_hook(__FILE__, 'xiaowu_base_deactivate');

/**
 * 获取速率限制器实例的辅助函数
 *
 * @return \XiaowuBase\Security\RateLimiter
 */
function xiaowu_get_rate_limiter() {
    static $rate_limiter = null;

    if ($rate_limiter === null) {
        $rate_limiter = new \XiaowuBase\Security\RateLimiter();
    }

    return $rate_limiter;
}

/**
 * 检查 IP 是否在黑名单中
 * 
 * @param string $ip IP 地址
 * @return bool
 */
function xiaowu_is_ip_blacklisted($ip) {
    $blacklist = get_option('xiaowu_ip_blacklist', []);
    return is_array($blacklist) && in_array($ip, $blacklist, true);
}

/**
 * 添加 IP 到黑名单
 *
 * @param string $ip IP 地址
 * @param string $reason 黑名单原因
 * @param int    $duration 持续时间（秒），0 表示永久
 * @return bool
 */
function xiaowu_blacklist_ip($ip, $reason = '', $duration = 0) {
    $blacklist = get_option('xiaowu_ip_blacklist', []);
    if (!is_array($blacklist)) {
        $blacklist = [];
    }

    if (!in_array($ip, $blacklist, true)) {
        $blacklist[] = $ip;
        update_option('xiaowu_ip_blacklist', $blacklist);
    }

    // 记录黑名单原因
    if ($reason) {
        $log_key = 'xiaowu_blacklist_log_' . md5($ip);
        set_transient($log_key, [
            'reason'  => $reason,
            'time'    => current_time('mysql'),
            'duration' => $duration,
        ], $duration ?: 30 * DAY_IN_SECONDS);
    }

    return true;
}

/**
 * 从黑名单中移除 IP
 *
 * @param string $ip IP 地址
 * @return bool
 */
function xiaowu_unblacklist_ip($ip) {
    $blacklist = get_option('xiaowu_ip_blacklist', []);
    if (!is_array($blacklist)) {
        return false;
    }

    $key = array_search($ip, $blacklist, true);
    if ($key !== false) {
        unset($blacklist[$key]);
        update_option('xiaowu_ip_blacklist', array_values($blacklist));
        return true;
    }

    return false;
}

/**
 * 记录安全事件
 *
 * @param string $event_type 事件类型
 * @param string $ip_address IP 地址
 * @param string $message    事件消息
 * @param array  $metadata   元数据
 * @return void
 */
function xiaowu_log_security_event($event_type, $ip_address, $message = '', $metadata = []) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'xiaowu_security_logs';
    $user_id = get_current_user_id();

    $wpdb->insert(
        $table_name,
        [
            'event_type'  => $event_type,
            'ip_address'  => $ip_address,
            'user_id'     => $user_id ?: null,
            'message'     => $message,
            'metadata'    => wp_json_encode($metadata),
        ]
    );
}

/**
 * 获取安全事件日志
 *
 * @param array $args 查询参数
 * @return array
 */
function xiaowu_get_security_logs($args = []) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'xiaowu_security_logs';

    $limit = isset($args['limit']) ? intval($args['limit']) : 50;
    $offset = isset($args['offset']) ? intval($args['offset']) : 0;
    $event_type = isset($args['event_type']) ? sanitize_text_field($args['event_type']) : '';

    $query = "SELECT * FROM {$table_name}";
    $where = [];

    if ($event_type) {
        $where[] = $wpdb->prepare('event_type = %s', $event_type);
    }

    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }

    $query .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
    $results = $wpdb->get_results($wpdb->prepare($query, $limit, $offset));

    return $results ?: [];
}

/**
 * 获取输入验证器实例的辅助函数
 *
 * @param array $input 用户输入的数据
 * @return \XiaowuBase\Security\InputValidator
 */
function xiaowu_get_input_validator($input = []) {
    return new \XiaowuBase\Security\InputValidator($input);
}

/**
 * 获取 SQL 注入防护实例的辅助函数
 *
 * @return \XiaowuBase\Security\SQLInjectionProtection
 */
function xiaowu_get_sql_protection() {
    static $sql_protection = null;

    if ($sql_protection === null) {
        $sql_protection = new \XiaowuBase\Security\SQLInjectionProtection();
    }

    return $sql_protection;
}

/**
 * 获取 XSS 防护实例的辅助函数
 *
 * @return \XiaowuBase\Security\XSSProtection
 */
function xiaowu_get_xss_protection() {
    static $xss_protection = null;

    if ($xss_protection === null) {
        $xss_protection = new \XiaowuBase\Security\XSSProtection();
    }

    return $xss_protection;
}

/**
 * 安全地执行数据库查询
 *
 * @param string $query SQL 查询字符串
 * @param mixed  $args  查询参数
 * @return array|null
 */
function xiaowu_safe_query($query, $args = []) {
    return xiaowu_get_sql_protection()->safe_query($query, $args);
}

/**
 * 安全地执行单行查询
 *
 * @param string $query SQL 查询字符串
 * @param mixed  $args  查询参数
 * @return object|null
 */
function xiaowu_safe_query_row($query, $args = []) {
    return xiaowu_get_sql_protection()->safe_query_row($query, $args);
}

/**
 * 安全地执行单值查询
 *
 * @param string $query SQL 查询字符串
 * @param mixed  $args  查询参数
 * @return mixed|null
 */
function xiaowu_safe_query_var($query, $args = []) {
    return xiaowu_get_sql_protection()->safe_query_var($query, $args);
}

/**
 * 转义 HTML 输出
 *
 * @param string $data 数据
 * @return string
 */
function xiaowu_escape_html($data) {
    return xiaowu_get_xss_protection()->escape_html($data);
}

/**
 * 转义 HTML 属性
 *
 * @param string $data 数据
 * @return string
 */
function xiaowu_escape_attr($data) {
    return xiaowu_get_xss_protection()->escape_attr($data);
}

/**
 * 转义 JavaScript
 *
 * @param mixed $data 数据
 * @return string
 */
function xiaowu_escape_js($data) {
    return xiaowu_get_xss_protection()->escape_js($data);
}

/**
 * 转义 URL
 *
 * @param string $url URL
 * @return string
 */
function xiaowu_escape_url($url) {
    return xiaowu_get_xss_protection()->escape_url($url);
}

/**
 * 净化 HTML 内容
 *
 * @param string $html HTML 内容
 * @return string
 */
function xiaowu_sanitize_html($html) {
    return xiaowu_get_xss_protection()->sanitize_html($html);
}

/**
 * 检测 XSS 攻击
 *
 * @param string $data 数据
 * @return bool
 */
function xiaowu_detect_xss($data) {
    return xiaowu_get_xss_protection()->detect_xss($data);
}
