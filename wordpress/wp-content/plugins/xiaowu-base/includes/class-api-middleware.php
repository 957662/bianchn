<?php
/**
 * API 中间件类
 * 处理 REST API 请求的速率限制等中间件功能
 *
 * @package xiaowu-base
 */

namespace XiaowuBase;

if (!defined('ABSPATH')) {
    exit;
}

class APIMiddleware {
    /**
     * 速率限制器实例
     *
     * @var Security\RateLimiter
     */
    private $rate_limiter;

    /**
     * 构造函数
     */
    public function __construct() {
        $this->rate_limiter = new Security\RateLimiter();
        
        // 注册钩子
        add_filter('rest_pre_dispatch', [$this, 'check_rate_limit'], 10, 3);
        add_filter('rest_post_dispatch', [$this, 'apply_rate_limit_headers'], 10, 3);
        
        // 处理预检请求
        add_filter('rest_pre_options', [$this, 'handle_preflight'], 10, 2);
    }

    /**
     * 检查速率限制
     *
     * @param mixed              $response REST 响应
     * @param \WP_REST_Server    $server   REST 服务器实例
     * @param \WP_REST_Request   $request  REST 请求对象
     * @return mixed WP_Error 或原始响应
     */
    public function check_rate_limit($response, $server, $request) {
        // 跳过某些请求
        if ($this->rate_limiter->should_skip_rate_limit($request)) {
            return $response;
        }

        // 获取请求标识符
        $ip = $this->rate_limiter->get_client_ip();
        $user_id = get_current_user_id();

        // 验证 IP 地址
        if (!$this->rate_limiter->is_valid_ip($ip)) {
            return $response;
        }

        // 认证用户使用用户 ID，匿名用户使用 IP
        $identifier = $user_id ? "user:{$user_id}" : "ip:{$ip}";
        $authenticated = $user_id > 0;

        // 检查是否为登录请求
        $is_login = strpos($request->get_route(), '/authenticate') !== false;

        // 执行限流检查
        $check = $this->rate_limiter->check_limit($identifier, $authenticated, $is_login);

        // 存储检查结果供后期钩子使用
        $request->set_param('_rate_limit_check', $check);

        // 超过限制时返回 429 错误
        if (!$check['allowed']) {
            return new \WP_Error(
                'rest_rate_limit_exceeded',
                '请求过于频繁，请稍后再试',
                ['status' => 429]
            );
        }

        return $response;
    }

    /**
     * 应用速率限制响应头
     *
     * @param mixed              $response REST 响应
     * @param \WP_REST_Server    $server   REST 服务器实例
     * @param \WP_REST_Request   $request  REST 请求对象
     * @return mixed
     */
    public function apply_rate_limit_headers($response, $server, $request) {
        // 获取存储的检查结果
        $check = $request->get_param('_rate_limit_check');

        if (is_array($check)) {
            $this->rate_limiter->apply_headers($check);
        }

        return $response;
    }

    /**
     * 处理预检请求（OPTIONS）
     *
     * @param mixed           $response 响应
     * @param \WP_REST_Request $request  请求对象
     * @return mixed
     */
    public function handle_preflight($response, $request) {
        // 允许所有 OPTIONS 请求通过
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }

        return $response;
    }

    /**
     * 获取限流状态 REST 端点
     *
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response
     */
    public function get_rate_limit_status($request) {
        $ip = $this->rate_limiter->get_client_ip();
        $user_id = get_current_user_id();

        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                '权限不足',
                ['status' => 403]
            );
        }

        $identifier = $user_id ? "user:{$user_id}" : "ip:{$ip}";
        $status = $this->rate_limiter->get_status($identifier);

        return rest_ensure_response($status);
    }

    /**
     * 重置限流计数 REST 端点
     *
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response
     */
    public function reset_rate_limit($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                '权限不足',
                ['status' => 403]
            );
        }

        $identifier = $request->get_param('identifier');
        if (!$identifier) {
            $ip = $this->rate_limiter->get_client_ip();
            $user_id = get_current_user_id();
            $identifier = $user_id ? "user:{$user_id}" : "ip:{$ip}";
        }

        $this->rate_limiter->reset_limit($identifier);

        return rest_ensure_response([
            'success'    => true,
            'message'    => '限流计数已重置',
            'identifier' => $identifier,
        ]);
    }

    /**
     * 注册 REST 路由
     *
     * @return void
     */
    public function register_rest_routes() {
        // 获取限流状态
        register_rest_route(
            'xiaowu/v1',
            '/rate-limit-status',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_rate_limit_status'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ]
        );

        // 重置限流计数
        register_rest_route(
            'xiaowu/v1',
            '/rate-limit-reset',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'reset_rate_limit'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ]
        );
    }
}

// 初始化中间件
$middleware = new APIMiddleware();
$middleware->register_rest_routes();
add_action('rest_api_init', [$middleware, 'register_rest_routes']);
