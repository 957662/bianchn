<?php
/**
 * Plugin Name: 小伍AI服务
 * Plugin URI: https://github.com/yourusername/xiaowu-ai
 * Description: AI服务插件，提供文章优化、智能搜索、内容推荐、代码生成、联网搜索等功能
 * Version: 2.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xiaowu-ai
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 插件版本
define('XIAOWU_AI_VERSION', '1.0.0');

// 插件路径
define('XIAOWU_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_AI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 小伍AI插件主类
 */
class Xiaowu_AI_Plugin
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * 加载依赖文件
     */
    private function load_dependencies()
    {
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-ai-service.php';
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-article-optimizer.php';
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-smart-search.php';
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-recommendation.php';
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-code-generator.php';
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-web-search.php';
        require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-cache-manager.php';
    }

    /**
     * 初始化钩子
     */
    private function init_hooks()
    {
        // 插件激活和停用
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // REST API路由
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // 管理后台
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // 前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * 插件激活
     */
    public function activate()
    {
        // 创建必要的数据库表
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // AI任务表
        $table_name = $wpdb->prefix . 'xiaowu_ai_tasks';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            input longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            result longtext,
            error text,
            tokens_used int(11) DEFAULT 0,
            cost decimal(10,4) DEFAULT 0.0000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime,
            user_id bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 设置默认配置
        add_option('xiaowu_ai_provider', 'openai');
        add_option('xiaowu_ai_model', 'gpt-4');
        add_option('xiaowu_ai_max_tokens', 4000);
        add_option('xiaowu_ai_temperature', 0.7);

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 插件停用
     */
    public function deactivate()
    {
        // 清除缓存
        wp_cache_flush();

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 注册REST API路由
     */
    public function register_rest_routes()
    {
        // 文章优化API
        register_rest_route('xiaowu-ai/v1', '/optimize-article', array(
            'methods' => 'POST',
            'callback' => array($this, 'optimize_article_callback'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        // 智能搜索API
        register_rest_route('xiaowu-search/v1', '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_callback'),
            'permission_callback' => '__return_true'
        ));

        // 内容推荐API
        register_rest_route('xiaowu-ai/v1', '/recommend', array(
            'methods' => 'POST',
            'callback' => array($this, 'recommend_callback'),
            'permission_callback' => '__return_true'
        ));

        // 代码生成API
        register_rest_route('xiaowu-ai/v1', '/generate-code', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_code_callback'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        // 联网搜索API
        register_rest_route('xiaowu-ai/v1', '/web-search', array(
            'methods' => 'POST',
            'callback' => array($this, 'web_search_callback'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        // 图像生成API
        register_rest_route('xiaowu-ai/v1', '/generate-image', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_image_callback'),
            'permission_callback' => function() {
                return current_user_can('upload_files');
            }
        ));

        // 配置API
        register_rest_route('xiaowu-ai/v1', '/config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_config_callback'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));

        // 保存图像到媒体库API
        register_rest_route('xiaowu/v1', '/ai/save-image', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_image_callback'),
            'permission_callback' => function() {
                return current_user_can('upload_files');
            }
        ));
    }

    /**
     * 文章优化回调
     */
    public function optimize_article_callback($request)
    {
        $title = sanitize_text_field($request->get_param('title'));
        $content = wp_kses_post($request->get_param('content'));
        $type = sanitize_text_field($request->get_param('type'));
        $language = sanitize_text_field($request->get_param('language')) ?: 'zh-CN';

        $optimizer = new Xiaowu_Article_Optimizer();
        $result = $optimizer->optimize($title, $content, $type, $language);

        return rest_ensure_response($result);
    }

    /**
     * 搜索回调
     */
    public function search_callback($request)
    {
        $query = sanitize_text_field($request->get_param('q'));
        $limit = intval($request->get_param('limit')) ?: 10;
        $type = sanitize_text_field($request->get_param('type')) ?: 'all';
        $semantic = filter_var($request->get_param('semantic'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        $search = new Xiaowu_Smart_Search();
        $result = $search->search($query, $limit, $type, $semantic);

        return rest_ensure_response($result);
    }

    /**
     * 推荐回调
     */
    public function recommend_callback($request)
    {
        $user_id = intval($request->get_param('user_id'));
        $context = $request->get_param('context');
        $limit = intval($request->get_param('limit')) ?: 5;

        $recommendation = new Xiaowu_Recommendation();
        $result = $recommendation->recommend($user_id, $context, $limit);

        return rest_ensure_response($result);
    }

    /**
     * 代码生成回调
     */
    public function generate_code_callback($request)
    {
        $description = sanitize_textarea_field($request->get_param('description'));
        $language = sanitize_text_field($request->get_param('language')) ?: 'php';
        $framework = sanitize_text_field($request->get_param('framework')) ?: 'wordpress';
        $context = $request->get_param('context');

        $generator = new Xiaowu_Code_Generator();
        $result = $generator->generate($description, $language, $framework, $context);

        return rest_ensure_response($result);
    }

    /**
     * 联网搜索回调
     */
    public function web_search_callback($request)
    {
        $query = sanitize_text_field($request->get_param('query'));
        $num_results = intval($request->get_param('num_results')) ?: 5;
        $language = sanitize_text_field($request->get_param('language')) ?: 'zh-CN';

        $web_search = new Xiaowu_Web_Search();
        $result = $web_search->search($query, $num_results, $language);

        return rest_ensure_response($result);
    }

    /**
     * 图像生成回调
     */
    public function generate_image_callback($request)
    {
        $prompt = sanitize_textarea_field($request->get_param('prompt'));
        $style = sanitize_text_field($request->get_param('style')) ?: 'icon';
        $size = sanitize_text_field($request->get_param('size')) ?: '512x512';
        $format = sanitize_text_field($request->get_param('format')) ?: 'png';
        $num_images = intval($request->get_param('num_images')) ?: 1;

        $ai_service = new Xiaowu_AI_Service();
        $result = $ai_service->generate_image($prompt, $style, $size, $format, $num_images);

        return rest_ensure_response($result);
    }

    /**
     * 获取配置回调
     */
    public function get_config_callback()
    {
        $config = array(
            'provider' => get_option('xiaowu_ai_provider'),
            'model' => get_option('xiaowu_ai_model'),
            'api_key_configured' => !empty(get_option('xiaowu_ai_api_key')),
            'features' => array(
                'article_optimization' => true,
                'smart_search' => true,
                'recommendation' => true,
                'code_generation' => true,
                'web_search' => true,
                'image_generation' => true
            )
        );

        return rest_ensure_response(array('success' => true, 'data' => $config));
    }

    /**
     * 更新配置回调
     */
    public function update_config_callback($request)
    {
        $provider = sanitize_text_field($request->get_param('provider'));
        $api_key = sanitize_text_field($request->get_param('api_key'));
        $model = sanitize_text_field($request->get_param('model'));
        $custom_prompts = $request->get_param('custom_prompts');

        update_option('xiaowu_ai_provider', $provider);
        if (!empty($api_key)) {
            update_option('xiaowu_ai_api_key', $api_key);
        }
        update_option('xiaowu_ai_model', $model);
        if ($custom_prompts) {
            update_option('xiaowu_ai_custom_prompts', $custom_prompts);
        }

        return rest_ensure_response(array('success' => true, 'message' => '配置已更新'));
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu()
    {
        add_menu_page(
            '小伍AI服务',
            '小伍AI',
            'manage_options',
            'xiaowu-ai',
            array($this, 'admin_page'),
            'dashicons-heart',
            30
        );

        add_submenu_page(
            'xiaowu-ai',
            'AI配置',
            '配置',
            'manage_options',
            'xiaowu-ai',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'xiaowu-ai',
            'AI统计',
            '统计',
            'manage_options',
            'xiaowu-ai-stats',
            array($this, 'stats_page')
        );

        add_submenu_page(
            'xiaowu-ai',
            'AI生图',
            '生图',
            'upload_files',
            'xiaowu-ai-image-gen',
            array($this, 'image_gen_page')
        );
    }

    /**
     * 管理页面
     */
    public function admin_page()
    {
        include XIAOWU_AI_PLUGIN_DIR . 'admin/settings-page.php';
    }

    /**
     * 统计页面
     */
    public function stats_page()
    {
        include XIAOWU_AI_PLUGIN_DIR . 'admin/stats-page.php';
    }

    /**
     * AI生图页面
     */
    public function image_gen_page()
    {
        include XIAOWU_AI_PLUGIN_DIR . 'admin/image-generation.php';
    }

    /**
     * 保存图像到媒体库回调
     */
    public function save_image_callback($request)
    {
        $url = esc_url_raw($request->get_param('url'));

        if (empty($url)) {
            return new WP_Error('invalid_url', '无效的图像URL', array('status' => 400));
        }

        // 下载图像
        $tmp_file = download_url($url);

        if (is_wp_error($tmp_file)) {
            return $tmp_file;
        }

        // 获取文件扩展名
        $extension = 'png';
        $headers = wp_remote_head($url);
        if (!is_wp_error($headers) && isset($headers['content-type'])) {
            $content_type = $headers['content-type'];
            if (strpos($content_type, 'jpeg') !== false || strpos($content_type, 'jpg') !== false) {
                $extension = 'jpg';
            } elseif (strpos($content_type, 'png') !== false) {
                $extension = 'png';
            } elseif (strpos($content_type, 'webp') !== false) {
                $extension = 'webp';
            }
        }

        // 生成文件名
        $filename = 'ai-generated-' . time() . '-' . rand(100, 999) . '.' . $extension;

        // 附加到媒体库
        $attachment_id = media_handle_sideload(array(
            'tmp_name' => $tmp_file,
            'name' => $filename
        ));

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // 更新附件元数据
        wp_update_post(array(
            'ID' => $attachment_id,
            'post_title' => 'AI Generated Image',
            'post_excerpt' => 'Generated by Xiaowu AI',
            'post_content' => ''
        ));

        // 添加分类
        wp_set_object_terms($attachment_id, 'ai-generated', 'category');

        return rest_ensure_response(array(
            'success' => true,
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        ));
    }

    /**
     * 加载管理后台资源
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'xiaowu-ai') === false) {
            return;
        }

        wp_enqueue_style(
            'xiaowu-ai-admin',
            XIAOWU_AI_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            XIAOWU_AI_VERSION
        );

        wp_enqueue_script(
            'xiaowu-ai-admin',
            XIAOWU_AI_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            XIAOWU_AI_VERSION,
            true
        );

        wp_localize_script('xiaowu-ai-admin', 'xiaowuAI', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xiaowu_ai_nonce'),
            'apiUrl' => rest_url('xiaowu-ai/v1')
        ));
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets()
    {
        // 预留前端资源加载
    }
}

/**
 * 初始化插件
 */
function xiaowu_ai_init()
{
    return Xiaowu_AI_Plugin::get_instance();
}

// 启动插件
add_action('plugins_loaded', 'xiaowu_ai_init');

/**
 * AJAX处理函数
 */

// 清空缓存
add_action('wp_ajax_xiaowu_clear_cache', 'xiaowu_ajax_clear_cache');
function xiaowu_ajax_clear_cache()
{
    check_ajax_referer('xiaowu_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('权限不足');
    }

    $cache_manager = new Xiaowu_Cache_Manager();
    $cache_manager->flush_group('xiaowu_ai');
    $cache_manager->flush_group('xiaowu_search');

    wp_send_json_success('缓存已清空');
}

// 获取任务详情
add_action('wp_ajax_xiaowu_get_task_detail', 'xiaowu_ajax_get_task_detail');
function xiaowu_ajax_get_task_detail()
{
    check_ajax_referer('xiaowu_ai_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('权限不足');
    }

    $task_id = intval($_POST['task_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'xiaowu_ai_tasks';
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $task_id), ARRAY_A);

    if (!$task) {
        wp_send_json_error('任务不存在');
    }

    wp_send_json_success($task);
}
