<?php
/**
 * Plugin Name: 小伍智能搜索
 * Plugin URI: https://xiaowu.com/plugins/xiaowu-search
 * Description: 为小伍博客提供智能搜索功能，支持全文搜索、AI增强搜索、搜索建议等
 * Version: 2.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.com
 * License: GPL v2 or later
 * Text Domain: xiaowu-search
 */

if (!defined('ABSPATH')) {
    exit;
}

// 插件常量
define('XIAOWU_SEARCH_VERSION', '1.0.0');
define('XIAOWU_SEARCH_PATH', plugin_dir_path(__FILE__));
define('XIAOWU_SEARCH_URL', plugin_dir_url(__FILE__));

// 加载核心类
require_once XIAOWU_SEARCH_PATH . 'includes/class-search-engine.php';
require_once XIAOWU_SEARCH_PATH . 'includes/class-search-indexer.php';
require_once XIAOWU_SEARCH_PATH . 'includes/class-search-api.php';
require_once XIAOWU_SEARCH_PATH . 'includes/class-search-suggestions.php';
require_once XIAOWU_SEARCH_PATH . 'includes/class-ai-search.php';
require_once XIAOWU_SEARCH_PATH . 'includes/class-search-analytics.php';
require_once XIAOWU_SEARCH_PATH . 'admin/settings-page.php';

class Xiaowu_Search
{
    private static $instance = null;
    private $search_engine;
    private $indexer;
    private $suggestions;
    private $ai_search;
    private $analytics;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_classes();
        $this->init_hooks();
    }

    private function init_classes()
    {
        $this->search_engine = new Xiaowu_Search_Engine();
        $this->indexer = new Xiaowu_Search_Indexer();
        $this->suggestions = new Xiaowu_Search_Suggestions();
        $this->ai_search = new Xiaowu_AI_Search();
        $this->analytics = new Xiaowu_Search_Analytics();
    }

    private function init_hooks()
    {
        // 插件激活/停用
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // 管理后台
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // 前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // REST API
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // 搜索短代码
        add_shortcode('xiaowu_search', array($this, 'search_shortcode'));
        add_shortcode('xiaowu_search_results', array($this, 'search_results_shortcode'));

        // 替换默认WordPress搜索
        if (get_option('xiaowu_search_replace_default', true)) {
            add_filter('posts_search', array($this, 'replace_default_search'), 10, 2);
            add_action('pre_get_posts', array($this, 'modify_search_query'));
        }

        // 内容索引钩子
        add_action('save_post', array($this->indexer, 'index_post'), 10, 2);
        add_action('delete_post', array($this->indexer, 'delete_post_index'));
        add_action('comment_post', array($this->indexer, 'index_comment'), 10, 2);
        add_action('delete_comment', array($this->indexer, 'delete_comment_index'));
        add_action('profile_update', array($this->indexer, 'index_user'));

        // AJAX钩子
        add_action('wp_ajax_xiaowu_search_suggestions', array($this->suggestions, 'get_suggestions'));
        add_action('wp_ajax_nopriv_xiaowu_search_suggestions', array($this->suggestions, 'get_suggestions'));
        add_action('wp_ajax_xiaowu_reindex_content', array($this->indexer, 'reindex_all'));

        // 定时任务
        add_action('xiaowu_search_daily_optimization', array($this->indexer, 'optimize_index'));
    }

    /**
     * 插件激活
     */
    public function activate()
    {
        // 创建数据库表
        $this->create_tables();

        // 创建索引
        $this->indexer->create_index();

        // 设置默认选项
        $this->set_default_options();

        // 注册定时任务
        if (!wp_next_scheduled('xiaowu_search_daily_optimization')) {
            wp_schedule_event(time(), 'daily', 'xiaowu_search_daily_optimization');
        }

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 插件停用
     */
    public function deactivate()
    {
        // 清除定时任务
        wp_clear_scheduled_hook('xiaowu_search_daily_optimization');

        // 刷新重写规则
        flush_rewrite_rules();
    }

    /**
     * 创建数据库表
     */
    private function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 搜索历史表
        $search_history_table = $wpdb->prefix . 'xiaowu_search_history';
        $sql1 = "CREATE TABLE IF NOT EXISTS $search_history_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            query varchar(255) NOT NULL,
            results_count int(11) DEFAULT 0,
            clicked_result_id bigint(20) UNSIGNED DEFAULT NULL,
            clicked_result_type varchar(50) DEFAULT NULL,
            search_time datetime NOT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY query (query),
            KEY search_time (search_time)
        ) $charset_collate;";

        // 热门搜索表
        $popular_searches_table = $wpdb->prefix . 'xiaowu_popular_searches';
        $sql2 = "CREATE TABLE IF NOT EXISTS $popular_searches_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            query varchar(255) NOT NULL,
            search_count int(11) DEFAULT 1,
            last_searched datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY query (query),
            KEY search_count (search_count)
        ) $charset_collate;";

        // 搜索索引表
        $search_index_table = $wpdb->prefix . 'xiaowu_search_index';
        $sql3 = "CREATE TABLE IF NOT EXISTS $search_index_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            object_id bigint(20) UNSIGNED NOT NULL,
            object_type varchar(50) NOT NULL,
            title text NOT NULL,
            content longtext NOT NULL,
            excerpt text DEFAULT NULL,
            author_id bigint(20) UNSIGNED DEFAULT NULL,
            author_name varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            modified_at datetime NOT NULL,
            status varchar(20) DEFAULT 'publish',
            relevance_score float DEFAULT 0,
            metadata text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY object_id (object_id, object_type),
            KEY author_id (author_id),
            KEY status (status),
            FULLTEXT KEY search_fulltext (title, content, excerpt)
        ) $charset_collate ENGINE=InnoDB;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
    }

    /**
     * 设置默认选项
     */
    private function set_default_options()
    {
        $defaults = array(
            'xiaowu_search_replace_default' => true,
            'xiaowu_search_enable_ai' => true,
            'xiaowu_search_enable_suggestions' => true,
            'xiaowu_search_results_per_page' => 20,
            'xiaowu_search_min_word_length' => 2,
            'xiaowu_search_enable_fuzzy' => true,
            'xiaowu_search_enable_synonyms' => true,
            'xiaowu_search_index_posts' => true,
            'xiaowu_search_index_pages' => true,
            'xiaowu_search_index_comments' => true,
            'xiaowu_search_index_users' => true,
            'xiaowu_search_enable_analytics' => true,
        );

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu()
    {
        add_menu_page(
            '智能搜索',
            '智能搜索',
            'manage_options',
            'xiaowu-search',
            array($this, 'render_admin_page'),
            'dashicons-search',
            30
        );

        add_submenu_page(
            'xiaowu-search',
            '搜索设置',
            '设置',
            'manage_options',
            'xiaowu-search',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'xiaowu-search',
            '搜索分析',
            '搜索分析',
            'manage_options',
            'xiaowu-search-analytics',
            array($this, 'render_analytics_page')
        );

        add_submenu_page(
            'xiaowu-search',
            '索引管理',
            '索引管理',
            'manage_options',
            'xiaowu-search-indexing',
            array($this, 'render_indexing_page')
        );
    }

    /**
     * 渲染管理页面
     */
    public function render_admin_page()
    {
        include XIAOWU_SEARCH_PATH . 'admin/settings-page.php';
    }

    /**
     * 渲染分析页面
     */
    public function render_analytics_page()
    {
        include XIAOWU_SEARCH_PATH . 'admin/analytics-page.php';
    }

    /**
     * 渲染索引页面
     */
    public function render_indexing_page()
    {
        include XIAOWU_SEARCH_PATH . 'admin/indexing-page.php';
    }

    /**
     * 加载管理后台资源
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'xiaowu-search') === false) {
            return;
        }

        wp_enqueue_style(
            'xiaowu-search-admin',
            XIAOWU_SEARCH_URL . 'assets/css/admin.css',
            array(),
            XIAOWU_SEARCH_VERSION
        );

        wp_enqueue_script(
            'xiaowu-search-admin',
            XIAOWU_SEARCH_URL . 'assets/js/admin.js',
            array('jquery', 'wp-api'),
            XIAOWU_SEARCH_VERSION,
            true
        );

        wp_localize_script('xiaowu-search-admin', 'xiaowuSearchAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('xiaowu-search/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'restNonce' => wp_create_nonce('wp_rest')
        ));
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'xiaowu-search',
            XIAOWU_SEARCH_URL . 'assets/css/search.css',
            array(),
            XIAOWU_SEARCH_VERSION
        );

        wp_enqueue_script(
            'xiaowu-search',
            XIAOWU_SEARCH_URL . 'assets/js/search.js',
            array('jquery', 'wp-api'),
            XIAOWU_SEARCH_VERSION,
            true
        );

        wp_localize_script('xiaowu-search', 'xiaowuSearch', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('xiaowu-search/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'enableAI' => get_option('xiaowu_search_enable_ai', true),
            'enableSuggestions' => get_option('xiaowu_search_enable_suggestions', true),
            'minWordLength' => get_option('xiaowu_search_min_word_length', 2)
        ));
    }

    /**
     * 注册REST API路由
     */
    public function register_rest_routes()
    {
        $api = new Xiaowu_Search_API();
        $api->register_routes();
    }

    /**
     * 搜索短代码
     */
    public function search_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'placeholder' => '搜索文章、评论、用户...',
            'button_text' => '搜索',
            'show_suggestions' => 'true',
            'show_popular' => 'true'
        ), $atts);

        ob_start();
        include XIAOWU_SEARCH_PATH . 'templates/search-form.php';
        return ob_get_clean();
    }

    /**
     * 搜索结果短代码
     */
    public function search_results_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'query' => get_query_var('s'),
            'per_page' => get_option('xiaowu_search_results_per_page', 20),
            'show_filters' => 'true'
        ), $atts);

        if (empty($atts['query'])) {
            return '<p>请输入搜索关键词</p>';
        }

        $results = $this->search_engine->search($atts['query'], array(
            'per_page' => $atts['per_page'],
            'page' => get_query_var('paged', 1)
        ));

        ob_start();
        include XIAOWU_SEARCH_PATH . 'templates/search-results.php';
        return ob_get_clean();
    }

    /**
     * 替换默认WordPress搜索
     */
    public function replace_default_search($search, $query)
    {
        if (!is_search() || !$query->is_main_query()) {
            return $search;
        }

        // 使用自定义搜索引擎
        return '';
    }

    /**
     * 修改搜索查询
     */
    public function modify_search_query($query)
    {
        if (!is_search() || !$query->is_main_query()) {
            return;
        }

        $search_query = get_query_var('s');
        if (empty($search_query)) {
            return;
        }

        // 使用自定义搜索引擎
        $results = $this->search_engine->search($search_query, array(
            'per_page' => $query->get('posts_per_page'),
            'page' => $query->get('paged', 1)
        ));

        // 设置查询结果
        if ($results['success'] && !empty($results['data']['results'])) {
            $post_ids = array();
            foreach ($results['data']['results'] as $result) {
                if ($result['type'] === 'post') {
                    $post_ids[] = $result['id'];
                }
            }

            if (!empty($post_ids)) {
                $query->set('post__in', $post_ids);
                $query->set('orderby', 'post__in');
            }
        }
    }

    /**
     * 获取搜索引擎实例
     */
    public function get_search_engine()
    {
        return $this->search_engine;
    }

    /**
     * 获取索引器实例
     */
    public function get_indexer()
    {
        return $this->indexer;
    }

    /**
     * 获取分析器实例
     */
    public function get_analytics()
    {
        return $this->analytics;
    }
}

// 初始化插件
function xiaowu_search()
{
    return Xiaowu_Search::get_instance();
}

xiaowu_search();
