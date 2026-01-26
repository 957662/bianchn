<?php
/**
 * CORS 管理类
 * 处理跨域资源共享 (CORS) 安全配置
 *
 * @package xiaowu-base
 */

namespace XiaowuBase\Security;

if (!defined('ABSPATH')) {
    exit;
}

class CORSManager {
    /**
     * 允许的来源列表
     *
     * @var array
     */
    private $allowed_origins = [];

    /**
     * 构造函数
     */
    public function __construct() {
        $this->load_allowed_origins();
        
        // 注册 WordPress 钩子
        add_action('rest_pre_serve_request', [$this, 'handle_cors_headers'], 10);
        add_action('rest_pre_options', [$this, 'handle_preflight_request'], 10, 2);
    }

    /**
     * 加载允许的来源列表
     *
     * @return void
     */
    private function load_allowed_origins() {
        // 默认允许的来源
        $default_origins = [
            'http://localhost:3000',          // 本地开发 Vue
            'http://localhost:5173',          // Vite 开发服务器
            'http://localhost:8080',          // WordPress 本地
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'https://monkeycode-ai.online',
            'https://www.monkeycode-ai.online',
        ];

        // 从数据库读取自定义来源
        $custom_origins = get_option('xiaowu_cors_allowed_origins', []);
        if (!is_array($custom_origins)) {
            $custom_origins = [];
        }

        // 合并默认和自定义来源
        $this->allowed_origins = array_unique(
            array_merge($default_origins, $custom_origins)
        );

        // 允许通过过滤器自定义来源
        $this->allowed_origins = apply_filters(
            'xiaowu_cors_allowed_origins',
            $this->allowed_origins
        );
    }

    /**
     * 获取允许的来源列表
     *
     * @return array
     */
    public function get_allowed_origins() {
        return $this->allowed_origins;
    }

    /**
     * 检查来源是否被允许
     *
     * @param string $origin 来源 URL
     * @return bool
     */
    public function is_origin_allowed($origin) {
        $allowed = $this->get_allowed_origins();
        return in_array($origin, $allowed, true);
    }

    /**
     * 添加来源到白名单
     *
     * @param string $origin 来源 URL
     * @return bool
     */
    public function add_allowed_origin($origin) {
        if (!filter_var($origin, FILTER_VALIDATE_URL)) {
            return false;
        }

        $origins = get_option('xiaowu_cors_allowed_origins', []);
        if (!is_array($origins)) {
            $origins = [];
        }

        if (!in_array($origin, $origins, true)) {
            $origins[] = $origin;
            update_option('xiaowu_cors_allowed_origins', $origins);
            $this->load_allowed_origins();
        }

        return true;
    }

    /**
     * 移除来源白名单
     *
     * @param string $origin 来源 URL
     * @return bool
     */
    public function remove_allowed_origin($origin) {
        $origins = get_option('xiaowu_cors_allowed_origins', []);
        if (!is_array($origins)) {
            return false;
        }

        $key = array_search($origin, $origins, true);
        if ($key !== false) {
            unset($origins[$key]);
            update_option('xiaowu_cors_allowed_origins', array_values($origins));
            $this->load_allowed_origins();
            return true;
        }

        return false;
    }

    /**
     * 处理 CORS 请求头
     *
     * @param bool $served 是否已服务
     * @return bool
     */
    public function handle_cors_headers($served) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) 
            ? sanitize_text_field($_SERVER['HTTP_ORIGIN']) 
            : '';

        if (empty($origin)) {
            return $served;
        }

        // 验证来源
        if ($this->is_origin_allowed($origin)) {
            // 应用 CORS 响应头
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Expose-Headers: Content-Type, Authorization, X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset');
            header('Access-Control-Max-Age: 3600');
            header('Vary: Origin');
        } else {
            // 不被允许的来源，只设置 Vary 头
            header('Vary: Origin');
        }

        return $served;
    }

    /**
     * 处理预检请求 (OPTIONS)
     *
     * @param mixed            $response 响应对象
     * @param \WP_REST_Request $request  请求对象
     * @return mixed
     */
    public function handle_preflight_request($response, $request) {
        if ($request->get_method() !== 'OPTIONS') {
            return $response;
        }

        $origin = isset($_SERVER['HTTP_ORIGIN']) 
            ? sanitize_text_field($_SERVER['HTTP_ORIGIN']) 
            : '';

        if (empty($origin)) {
            return $response;
        }

        // 验证预检请求的来源
        if ($this->is_origin_allowed($origin)) {
            // 获取客户端请求的方法和头
            $request_method = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])
                ? sanitize_text_field($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])
                : 'GET';

            $request_headers = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])
                ? sanitize_text_field($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])
                : 'Content-Type';

            // 验证请求的方法
            $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
            if (!in_array(strtoupper($request_method), $allowed_methods, true)) {
                return new \WP_Error(
                    'rest_forbidden',
                    '请求方法不被允许',
                    ['status' => 403]
                );
            }

            // 应用预检响应头
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: ' . $request_headers);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 3600');
            header('Vary: Origin');

            return true;
        }

        return $response;
    }

    /**
     * 获取 CORS 配置状态 (REST 端点)
     *
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response
     */
    public function get_cors_status($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                '权限不足',
                ['status' => 403]
            );
        }

        $current_origin = isset($_SERVER['HTTP_ORIGIN'])
            ? sanitize_text_field($_SERVER['HTTP_ORIGIN'])
            : '';

        return rest_ensure_response([
            'current_origin'    => $current_origin,
            'is_allowed'        => $this->is_origin_allowed($current_origin),
            'allowed_origins'   => $this->get_allowed_origins(),
            'supported_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'],
            'max_age'           => 3600,
        ]);
    }

    /**
     * 添加来源到白名单 (REST 端点)
     *
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response
     */
    public function add_cors_origin($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                '权限不足',
                ['status' => 403]
            );
        }

        $origin = $request->get_param('origin');
        if (!$origin) {
            return new \WP_Error(
                'rest_missing_param',
                '缺少参数: origin',
                ['status' => 400]
            );
        }

        if (!filter_var($origin, FILTER_VALIDATE_URL)) {
            return new \WP_Error(
                'rest_invalid_param',
                '无效的 URL 格式',
                ['status' => 400]
            );
        }

        if (!$this->add_allowed_origin($origin)) {
            return new \WP_Error(
                'rest_failed',
                '添加来源失败',
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'message' => '来源已添加到白名单',
            'origin'  => $origin,
        ]);
    }

    /**
     * 移除来源 (REST 端点)
     *
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response
     */
    public function remove_cors_origin($request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                '权限不足',
                ['status' => 403]
            );
        }

        $origin = $request->get_param('origin');
        if (!$origin) {
            return new \WP_Error(
                'rest_missing_param',
                '缺少参数: origin',
                ['status' => 400]
            );
        }

        if (!$this->remove_allowed_origin($origin)) {
            return new \WP_Error(
                'rest_failed',
                '移除来源失败或来源不存在',
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success' => true,
            'message' => '来源已从白名单移除',
            'origin'  => $origin,
        ]);
    }

    /**
     * 注册 REST 路由
     *
     * @return void
     */
    public function register_rest_routes() {
        // 获取 CORS 配置状态
        register_rest_route(
            'xiaowu/v1',
            '/cors-status',
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_cors_status'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ]
        );

        // 添加来源到白名单
        register_rest_route(
            'xiaowu/v1',
            '/cors-add-origin',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'add_cors_origin'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
                'args'                => [
                    'origin' => [
                        'type'     => 'string',
                        'required' => true,
                        'validate_callback' => function($param) {
                            return filter_var($param, FILTER_VALIDATE_URL);
                        },
                    ],
                ],
            ]
        );

        // 移除来源
        register_rest_route(
            'xiaowu/v1',
            '/cors-remove-origin',
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'remove_cors_origin'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
                'args'                => [
                    'origin' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                ],
            ]
        );
    }
}
