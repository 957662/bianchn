<?php
/**
 * Plugin Name: 小伍3D图库
 * Plugin URI: https://github.com/yourusername/xiaowu-3d-gallery
 * Description: 3D图库插件，提供3D模型上传、展示、管理等功能，基于Three.js实现
 * Version: 2.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xiaowu-3d-gallery
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 插件版本
define('XIAOWU_3D_VERSION', '1.0.0');

// 插件路径
define('XIAOWU_3D_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_3D_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 小伍3D图库插件主类
 */
class Xiaowu_3D_Gallery_Plugin
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
        require_once XIAOWU_3D_PLUGIN_DIR . 'includes/model-post-type.php';
        require_once XIAOWU_3D_PLUGIN_DIR . 'includes/model-uploader.php';
        require_once XIAOWU_3D_PLUGIN_DIR . 'includes/cdn-manager.php';
        require_once XIAOWU_3D_PLUGIN_DIR . 'includes/thumbnail-generator.php';
        require_once XIAOWU_3D_PLUGIN_DIR . 'includes/viewer.php';
    }

    /**
     * 初始化钩子
     */
    private function init_hooks()
    {
        // 插件激活和停用
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // 初始化
        add_action('init', array($this, 'init'));

        // REST API路由
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // 管理后台
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // 前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // 短代码
        add_shortcode('xiaowu_3d_viewer', array($this, 'viewer_shortcode'));
        add_shortcode('xiaowu_3d_gallery', array($this, 'gallery_shortcode'));
    }

    /**
     * 插件激活
     */
    public function activate()
    {
        // 注册自定义文章类型
        $this->register_post_types();

        // 创建必要的数据库表
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 模型查看记录表
        $table_name = $wpdb->prefix . 'xiaowu_model_views';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            model_id bigint(20) NOT NULL,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent varchar(255),
            viewed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY model_id (model_id),
            KEY user_id (user_id),
            KEY viewed_at (viewed_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 插件停用
     */
    public function deactivate()
    {
        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 初始化
     */
    public function init()
    {
        $this->register_post_types();
        $this->register_taxonomies();
    }

    /**
     * 注册自定义文章类型
     */
    private function register_post_types()
    {
        $model_post_type = new Xiaowu_Model_Post_Type();
        $model_post_type->register();
    }

    /**
     * 注册分类法
     */
    private function register_taxonomies()
    {
        // 模型分类
        register_taxonomy('model_category', 'xiaowu_3d_model', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => '模型分类',
                'singular_name' => '模型分类',
                'search_items' => '搜索分类',
                'all_items' => '所有分类',
                'parent_item' => '父级分类',
                'parent_item_colon' => '父级分类:',
                'edit_item' => '编辑分类',
                'update_item' => '更新分类',
                'add_new_item' => '添加新分类',
                'new_item_name' => '新分类名称',
                'menu_name' => '分类',
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'model-category'),
        ));

        // 模型标签
        register_taxonomy('model_tag', 'xiaowu_3d_model', array(
            'hierarchical' => false,
            'labels' => array(
                'name' => '模型标签',
                'singular_name' => '模型标签',
                'search_items' => '搜索标签',
                'popular_items' => '热门标签',
                'all_items' => '所有标签',
                'edit_item' => '编辑标签',
                'update_item' => '更新标签',
                'add_new_item' => '添加新标签',
                'new_item_name' => '新标签名称',
                'menu_name' => '标签',
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'model-tag'),
        ));
    }

    /**
     * 注册REST API路由
     */
    public function register_rest_routes()
    {
        // 模型列表
        register_rest_route('xiaowu-3d/v1', '/models', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_models_callback'),
            'permission_callback' => '__return_true'
        ));

        // 单个模型
        register_rest_route('xiaowu-3d/v1', '/models/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_model_callback'),
            'permission_callback' => '__return_true'
        ));

        // 上传模型
        register_rest_route('xiaowu-3d/v1', '/models/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_model_callback'),
            'permission_callback' => function() {
                return current_user_can('upload_files');
            }
        ));

        // 更新模型
        register_rest_route('xiaowu-3d/v1', '/models/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_model_callback'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));

        // 删除模型
        register_rest_route('xiaowu-3d/v1', '/models/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_model_callback'),
            'permission_callback' => function() {
                return current_user_can('delete_posts');
            }
        ));

        // 记录查看
        register_rest_route('xiaowu-3d/v1', '/models/(?P<id>\d+)/view', array(
            'methods' => 'POST',
            'callback' => array($this, 'record_view_callback'),
            'permission_callback' => '__return_true'
        ));

        // 模型统计
        register_rest_route('xiaowu-3d/v1', '/models/(?P<id>\d+)/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_model_stats_callback'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * 获取模型列表回调
     */
    public function get_models_callback($request)
    {
        $page = intval($request->get_param('page')) ?: 1;
        $per_page = intval($request->get_param('per_page')) ?: 12;
        $category = sanitize_text_field($request->get_param('category'));
        $search = sanitize_text_field($request->get_param('search'));
        $orderby = sanitize_text_field($request->get_param('orderby')) ?: 'date';
        $order = sanitize_text_field($request->get_param('order')) ?: 'DESC';

        $args = array(
            'post_type' => 'xiaowu_3d_model',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => $orderby,
            'order' => $order
        );

        if ($category) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'model_category',
                    'field' => 'slug',
                    'terms' => $category
                )
            );
        }

        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $models = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $model_id = get_the_ID();
                $models[] = $this->format_model_data($model_id);
            }
            wp_reset_postdata();
        }

        return rest_ensure_response(array(
            'success' => true,
            'models' => $models,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages
        ));
    }

    /**
     * 获取单个模型回调
     */
    public function get_model_callback($request)
    {
        $model_id = intval($request['id']);
        $post = get_post($model_id);

        if (!$post || $post->post_type !== 'xiaowu_3d_model') {
            return new WP_Error('not_found', '模型不存在', array('status' => 404));
        }

        return rest_ensure_response(array(
            'success' => true,
            'model' => $this->format_model_data($model_id)
        ));
    }

    /**
     * 上传模型回调
     */
    public function upload_model_callback($request)
    {
        $uploader = new Xiaowu_Model_Uploader();

        $title = sanitize_text_field($request->get_param('title'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $category = sanitize_text_field($request->get_param('category'));
        $tags = $request->get_param('tags');

        $result = $uploader->upload($_FILES['file'], array(
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'tags' => $tags
        ));

        if (!$result['success']) {
            return new WP_Error('upload_failed', $result['error'], array('status' => 400));
        }

        return rest_ensure_response($result);
    }

    /**
     * 更新模型回调
     */
    public function update_model_callback($request)
    {
        $model_id = intval($request['id']);
        $post = get_post($model_id);

        if (!$post || $post->post_type !== 'xiaowu_3d_model') {
            return new WP_Error('not_found', '模型不存在', array('status' => 404));
        }

        if (!current_user_can('edit_post', $model_id)) {
            return new WP_Error('forbidden', '无权限编辑', array('status' => 403));
        }

        $json = $request->get_json_params();

        // 更新文章信息
        if (isset($json['title'])) {
            wp_update_post(array(
                'ID' => $model_id,
                'post_title' => sanitize_text_field($json['title'])
            ));
        }

        if (isset($json['description'])) {
            wp_update_post(array(
                'ID' => $model_id,
                'post_content' => sanitize_textarea_field($json['description'])
            ));
        }

        // 更新查看器配置
        if (isset($json['viewer_config'])) {
            update_post_meta($model_id, '_viewer_config', $json['viewer_config']);
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => '模型已更新',
            'model' => $this->format_model_data($model_id)
        ));
    }

    /**
     * 删除模型回调
     */
    public function delete_model_callback($request)
    {
        $model_id = intval($request['id']);
        $post = get_post($model_id);

        if (!$post || $post->post_type !== 'xiaowu_3d_model') {
            return new WP_Error('not_found', '模型不存在', array('status' => 404));
        }

        if (!current_user_can('delete_post', $model_id)) {
            return new WP_Error('forbidden', '无权限删除', array('status' => 403));
        }

        // 删除CDN文件
        $file_url = get_post_meta($model_id, '_model_file_url', true);
        $thumbnail_url = get_post_meta($model_id, '_model_thumbnail_url', true);

        $cdn_manager = new Xiaowu_CDN_Manager();
        if ($file_url) {
            $cdn_manager->delete($file_url);
        }
        if ($thumbnail_url) {
            $cdn_manager->delete($thumbnail_url);
        }

        // 删除文章
        wp_delete_post($model_id, true);

        return rest_ensure_response(array(
            'success' => true,
            'message' => '模型已删除'
        ));
    }

    /**
     * 记录查看回调
     */
    public function record_view_callback($request)
    {
        $model_id = intval($request['id']);
        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_model_views';

        $wpdb->insert(
            $table_name,
            array(
                'model_id' => $model_id,
                'user_id' => $user_id ?: null,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ),
            array('%d', '%d', '%s', '%s')
        );

        // 更新查看计数
        $view_count = get_post_meta($model_id, '_view_count', true) ?: 0;
        update_post_meta($model_id, '_view_count', $view_count + 1);

        return rest_ensure_response(array(
            'success' => true,
            'view_count' => $view_count + 1
        ));
    }

    /**
     * 获取模型统计回调
     */
    public function get_model_stats_callback($request)
    {
        $model_id = intval($request['id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_model_views';

        // 总查看数
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE model_id = %d",
            $model_id
        ));

        // 今日查看数
        $today_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE model_id = %d AND DATE(viewed_at) = CURDATE()",
            $model_id
        ));

        // 本周查看数
        $week_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE model_id = %d AND YEARWEEK(viewed_at) = YEARWEEK(NOW())",
            $model_id
        ));

        // 独立访客数
        $unique_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) FROM $table_name WHERE model_id = %d",
            $model_id
        ));

        return rest_ensure_response(array(
            'success' => true,
            'stats' => array(
                'total_views' => intval($total_views),
                'today_views' => intval($today_views),
                'week_views' => intval($week_views),
                'unique_visitors' => intval($unique_visitors)
            )
        ));
    }

    /**
     * 格式化模型数据
     */
    private function format_model_data($model_id)
    {
        return array(
            'id' => $model_id,
            'title' => get_the_title($model_id),
            'description' => get_post_field('post_content', $model_id),
            'excerpt' => get_the_excerpt($model_id),
            'file_url' => get_post_meta($model_id, '_model_file_url', true),
            'file_format' => get_post_meta($model_id, '_model_file_format', true),
            'file_size' => get_post_meta($model_id, '_model_file_size', true),
            'thumbnail_url' => get_post_meta($model_id, '_model_thumbnail_url', true) ?: get_the_post_thumbnail_url($model_id, 'medium'),
            'cdn_url' => get_post_meta($model_id, '_model_cdn_url', true),
            'viewer_config' => get_post_meta($model_id, '_viewer_config', true),
            'model_metadata' => get_post_meta($model_id, '_model_metadata', true),
            'view_count' => get_post_meta($model_id, '_view_count', true) ?: 0,
            'download_count' => get_post_meta($model_id, '_download_count', true) ?: 0,
            'categories' => wp_get_post_terms($model_id, 'model_category', array('fields' => 'names')),
            'tags' => wp_get_post_terms($model_id, 'model_tag', array('fields' => 'names')),
            'author' => get_the_author_meta('display_name', get_post_field('post_author', $model_id)),
            'author_id' => get_post_field('post_author', $model_id),
            'date' => get_the_date('c', $model_id),
            'modified' => get_the_modified_date('c', $model_id),
            'status' => get_post_status($model_id),
            'permalink' => get_permalink($model_id)
        );
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu()
    {
        add_menu_page(
            '3D图库',
            '3D图库',
            'manage_options',
            'xiaowu-3d-gallery',
            array($this, 'admin_page'),
            'dashicons-images-alt2',
            31
        );

        add_submenu_page(
            'xiaowu-3d-gallery',
            '所有模型',
            '所有模型',
            'manage_options',
            'edit.php?post_type=xiaowu_3d_model'
        );

        add_submenu_page(
            'xiaowu-3d-gallery',
            '上传模型',
            '上传模型',
            'manage_options',
            'xiaowu-3d-upload',
            array($this, 'upload_page')
        );

        add_submenu_page(
            'xiaowu-3d-gallery',
            '查看器配置',
            '查看器配置',
            'manage_options',
            'xiaowu-3d-viewer-config',
            array($this, 'viewer_config_page')
        );
    }

    /**
     * 管理页面
     */
    public function admin_page()
    {
        include XIAOWU_3D_PLUGIN_DIR . 'admin/model-list.php';
    }

    /**
     * 上传页面
     */
    public function upload_page()
    {
        include XIAOWU_3D_PLUGIN_DIR . 'admin/model-edit.php';
    }

    /**
     * 查看器配置页面
     */
    public function viewer_config_page()
    {
        include XIAOWU_3D_PLUGIN_DIR . 'admin/viewer-config.php';
    }

    /**
     * 加载管理后台资源
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'xiaowu-3d') === false && get_post_type() !== 'xiaowu_3d_model') {
            return;
        }

        wp_enqueue_style(
            'xiaowu-3d-admin',
            XIAOWU_3D_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            XIAOWU_3D_VERSION
        );

        wp_enqueue_script(
            'xiaowu-3d-admin',
            XIAOWU_3D_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            XIAOWU_3D_VERSION,
            true
        );

        wp_localize_script('xiaowu-3d-admin', 'xiaowu3D', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xiaowu_3d_nonce'),
            'apiUrl' => rest_url('xiaowu-3d/v1')
        ));
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'xiaowu-3d-viewer',
            XIAOWU_3D_PLUGIN_URL . 'assets/css/viewer.css',
            array(),
            XIAOWU_3D_VERSION
        );

        // Three.js和加载器
        wp_enqueue_script(
            'three-js',
            XIAOWU_3D_PLUGIN_URL . 'assets/js/three.min.js',
            array(),
            '0.150.0',
            true
        );

        wp_enqueue_script(
            'gltf-loader',
            XIAOWU_3D_PLUGIN_URL . 'assets/js/GLTFLoader.js',
            array('three-js'),
            '0.150.0',
            true
        );

        wp_enqueue_script(
            'xiaowu-3d-viewer',
            XIAOWU_3D_PLUGIN_URL . 'assets/js/viewer.js',
            array('three-js', 'gltf-loader'),
            XIAOWU_3D_VERSION,
            true
        );

        wp_localize_script('xiaowu-3d-viewer', 'xiaowu3DConfig', array(
            'apiUrl' => rest_url('xiaowu-3d/v1'),
            'assetsUrl' => XIAOWU_3D_PLUGIN_URL . 'assets/'
        ));
    }

    /**
     * 查看器短代码
     */
    public function viewer_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'id' => 0,
            'width' => '100%',
            'height' => '500px',
            'auto_rotate' => 'true'
        ), $atts);

        $model_id = intval($atts['id']);
        if (!$model_id) {
            return '<p>请指定模型ID</p>';
        }

        $viewer = new Xiaowu_3D_Viewer();
        return $viewer->render($model_id, $atts);
    }

    /**
     * 图库短代码
     */
    public function gallery_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'category' => '',
            'limit' => 12,
            'columns' => 3,
            'orderby' => 'date',
            'order' => 'DESC'
        ), $atts);

        ob_start();
        include XIAOWU_3D_PLUGIN_DIR . 'templates/model-grid.php';
        return ob_get_clean();
    }
}

/**
 * 初始化插件
 */
function xiaowu_3d_gallery_init()
{
    return Xiaowu_3D_Gallery_Plugin::get_instance();
}

// 启动插件
add_action('plugins_loaded', 'xiaowu_3d_gallery_init');
