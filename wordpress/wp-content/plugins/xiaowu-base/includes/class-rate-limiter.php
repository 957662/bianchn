<?php
/**
 * 速率限制器类
 * 用于防止 API 滥用和 DDoS 攻击
 * 
 * @package xiaowu-base
 */

namespace XiaowuBase\Security;

if (!defined('ABSPATH')) {
    exit;
}

class RateLimiter {
    /**
     * 公开 API 请求限制（每分钟）
     */
    const LIMIT_REQUESTS = 100;

    /**
     * 认证用户请求限制（每分钟）
     */
    const LIMIT_AUTH_REQUESTS = 1000;

    /**
     * 时间窗口（秒）
     */
    const LIMIT_WINDOW = 60;

    /**
     * 尝试登录限制（每分钟）
     */
    const LIMIT_LOGIN_ATTEMPTS = 5;

    /**
     * Redis 实例
     *
     * @var \Redis
     */
    private $redis = null;

    /**
     * 构造函数
     */
    public function __construct() {
        try {
            // 检查 Redis 扩展是否可用
            if (!extension_loaded('redis')) {
                $this->redis = null;
                return;
            }

            $this->redis = new \Redis();
            // 连接 Redis 服务器
            $connected = @$this->redis->connect('127.0.0.1', 6379, 2);
            if (!$connected) {
                $this->redis = null;
                error_log('XiaowuBase: Redis 连接失败 - 将使用数据库备份方案');
            }
        } catch (\Exception $e) {
            $this->redis = null;
            error_log('XiaowuBase: Redis 连接异常 - ' . $e->getMessage());
        }
    }

    /**
     * 检查请求是否超过限流
     *
     * @param string  $identifier   标识符（IP地址或用户ID）
     * @param bool    $authenticated 是否为认证用户
     * @param bool    $is_login      是否为登录请求
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int, 'limit' => int]
     */
    public function check_limit($identifier, $authenticated = false, $is_login = false) {
        // 确定限制参数
        if ($is_login) {
            $limit = self::LIMIT_LOGIN_ATTEMPTS;
            $window = self::LIMIT_WINDOW;
        } else {
            $limit = $authenticated ? self::LIMIT_AUTH_REQUESTS : self::LIMIT_REQUESTS;
            $window = self::LIMIT_WINDOW;
        }

        $key = "rate_limit:{$identifier}";

        if ($this->redis) {
            return $this->check_limit_redis($key, $limit, $window);
        } else {
            return $this->check_limit_database($key, $limit, $window);
        }
    }

    /**
     * 使用 Redis 检查限流
     *
     * @param string $key    缓存键
     * @param int    $limit  限制数
     * @param int    $window 时间窗口（秒）
     * @return array 检查结果
     */
    private function check_limit_redis($key, $limit, $window) {
        try {
            $current = $this->redis->incr($key);

            // 第一次请求时设置过期时间
            if ($current === 1) {
                $this->redis->expire($key, $window);
            }

            $ttl = $this->redis->ttl($key);
            if ($ttl <= 0) {
                $ttl = $window;
            }

            $remaining = max(0, $limit - $current);
            $reset_at = time() + $ttl;

            return [
                'allowed'   => $current <= $limit,
                'remaining' => $remaining,
                'reset_at'  => $reset_at,
                'limit'     => $limit,
            ];
        } catch (\Exception $e) {
            error_log('XiaowuBase: Redis 检查限流异常 - ' . $e->getMessage());
            // 降级到数据库方案
            return $this->check_limit_database($key, $limit, $window);
        }
    }

    /**
     * 使用数据库检查限流（降级方案）
     *
     * @param string $key    缓存键
     * @param int    $limit  限制数
     * @param int    $window 时间窗口（秒）
     * @return array 检查结果
     */
    private function check_limit_database($key, $limit, $window) {
        global $wpdb;

        $current_time = time();
        $window_start = $current_time - $window;

        // 获取时间窗口内的请求数
        $table_name = $wpdb->prefix . 'xiaowu_rate_limits';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE identifier = %s AND timestamp > %d",
            $key,
            $window_start
        ));

        $current = intval($count) + 1;

        // 记录新请求
        $wpdb->insert(
            $table_name,
            [
                'identifier' => $key,
                'timestamp'  => $current_time,
            ]
        );

        // 清理过期数据
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %d",
            $window_start
        ));

        $remaining = max(0, $limit - $current);
        $reset_at = $current_time + $window;

        return [
            'allowed'   => $current <= $limit,
            'remaining' => $remaining,
            'reset_at'  => $reset_at,
            'limit'     => $limit,
        ];
    }

    /**
     * 获取客户端 IP 地址
     *
     * @return string
     */
    public function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 检查 Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // 检查代理
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        }
        // 检查其他代理
        elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * 验证 IP 地址格式
     *
     * @param string $ip IP 地址
     * @return bool
     */
    public function is_valid_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 应用速率限制响应头
     *
     * @param array $check_result 检查结果
     * @return void
     */
    public function apply_headers($check_result) {
        header('X-RateLimit-Limit: ' . intval($check_result['limit']));
        header('X-RateLimit-Remaining: ' . intval($check_result['remaining']));
        header('X-RateLimit-Reset: ' . intval($check_result['reset_at']));
    }

    /**
     * 是否应该跳过限流检查
     *
     * @param \WP_REST_Request $request REST 请求对象
     * @return bool
     */
    public function should_skip_rate_limit($request) {
        // 获取当前用户
        $user_id = get_current_user_id();

        // 管理员跳过限流检查
        if ($user_id && user_can($user_id, 'manage_options')) {
            return true;
        }

        // 特定路由可以跳过（例如心跳检测）
        $skip_routes = [
            '/wp-json/wp/v2/users/me',
            '/wp-json/wp-site-health/v1/health-check/wp-version-check-info',
        ];

        $route = $request->get_route();
        foreach ($skip_routes as $skip_route) {
            if (strpos($route, $skip_route) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取速率限制状态
     *
     * @param string $identifier 标识符
     * @return array
     */
    public function get_status($identifier) {
        $key = "rate_limit:{$identifier}";

        if ($this->redis) {
            try {
                $current = $this->redis->get($key);
                $ttl = $this->redis->ttl($key);

                return [
                    'current' => intval($current ?? 0),
                    'ttl'     => $ttl > 0 ? $ttl : 0,
                ];
            } catch (\Exception $e) {
                error_log('XiaowuBase: Redis 获取状态异常 - ' . $e->getMessage());
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_rate_limits';
        $window_start = time() - self::LIMIT_WINDOW;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE identifier = %s AND timestamp > %d",
            $key,
            $window_start
        ));

        return [
            'current' => intval($count ?? 0),
            'ttl'     => self::LIMIT_WINDOW,
        ];
    }

    /**
     * 重置限流计数
     *
     * @param string $identifier 标识符
     * @return bool
     */
    public function reset_limit($identifier) {
        $key = "rate_limit:{$identifier}";

        if ($this->redis) {
            try {
                $this->redis->del($key);
                return true;
            } catch (\Exception $e) {
                error_log('XiaowuBase: Redis 重置限流异常 - ' . $e->getMessage());
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_rate_limits';
        $wpdb->delete($table_name, ['identifier' => $key]);

        return true;
    }
}
