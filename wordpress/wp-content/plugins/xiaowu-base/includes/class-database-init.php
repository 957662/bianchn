<?php
/**
 * 数据库初始化
 * 为速率限制功能创建必要的数据库表
 *
 * @package xiaowu-base
 */

namespace XiaowuBase;

if (!defined('ABSPATH')) {
    exit;
}

class DatabaseInit {
    /**
     * 创建速率限制表
     *
     * @return void
     */
    public static function create_rate_limit_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'xiaowu_rate_limits';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            identifier VARCHAR(100) NOT NULL,
            timestamp BIGINT(20) NOT NULL,
            PRIMARY KEY (id),
            KEY identifier (identifier),
            KEY timestamp (timestamp)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // 记录表创建日志
        if ($wpdb->last_error) {
            error_log('XiaowuBase: 创建速率限制表失败 - ' . $wpdb->last_error);
        } else {
            error_log('XiaowuBase: 速率限制表创建成功');
        }
    }

    /**
     * 创建日志表
     *
     * @return void
     */
    public static function create_security_log_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'xiaowu_security_logs';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_id BIGINT(20) UNSIGNED,
            message TEXT,
            metadata LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($wpdb->last_error) {
            error_log('XiaowuBase: 创建安全日志表失败 - ' . $wpdb->last_error);
        } else {
            error_log('XiaowuBase: 安全日志表创建成功');
        }
    }

    /**
     * 创建所有必要的表
     *
     * @return void
     */
    public static function create_all_tables() {
        self::create_rate_limit_table();
        self::create_security_log_table();
    }

    /**
     * 清理过期数据
     *
     * @return void
     */
    public static function cleanup_expired_data() {
        global $wpdb;

        // 清理超过 1 小时的限流记录
        $table_name = $wpdb->prefix . 'xiaowu_rate_limits';
        $cutoff_time = time() - 3600;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %d",
            $cutoff_time
        ));

        // 清理超过 30 天的安全日志
        $log_table_name = $wpdb->prefix . 'xiaowu_security_logs';
        $wpdb->query(
            "DELETE FROM {$log_table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }
}

// 插件激活时创建表
register_activation_hook(
    dirname(__DIR__) . '/xiaowu-base.php',
    [__CLASS__, 'create_all_tables']
);

// 定时清理过期数据
if (!wp_next_scheduled('xiaowu_cleanup_expired_data')) {
    wp_schedule_event(time(), 'hourly', 'xiaowu_cleanup_expired_data');
}
add_action('xiaowu_cleanup_expired_data', [__CLASS__, 'cleanup_expired_data']);
