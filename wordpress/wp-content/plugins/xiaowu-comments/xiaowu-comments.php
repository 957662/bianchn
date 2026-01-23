<?php
/**
 * Plugin Name: 小伍评论系统
 * Plugin URI: https://github.com/yourusername/xiaowu-comments
 * Description: 增强的WordPress评论系统,支持邮件通知、AI反垃圾、表情包、@提及等功能
 * Version: 1.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xiaowu-comments
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// 插件版本
define('XIAOWU_COMMENTS_VERSION', '1.0.0');

// 插件路径
define('XIAOWU_COMMENTS_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_COMMENTS_URL', plugin_dir_url(__FILE__));

/**
 * 小伍评论系统主类
 */
class Xiaowu_Comments_Plugin
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
        require_once XIAOWU_COMMENTS_DIR . 'includes/class-comment-handler.php';
        require_once XIAOWU_COMMENTS_DIR . 'includes/class-antispam.php';
        require_once XIAOWU_COMMENTS_DIR . 'includes/class-notification.php';
        require_once XIAOWU_COMMENTS_DIR . 'includes/class-emoji.php';
        require_once XIAOWU_COMMENTS_DIR . 'includes/class-mention.php';
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

        // 前端资源
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // 管理后台
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // 评论表单
        add_filter('comment_form_defaults', array($this, 'customize_comment_form'));
        add_action('comment_post', array($this, 'handle_comment_submission'), 10, 3);

        // 评论显示
        add_filter('get_comment_text', array($this, 'process_comment_text'), 10, 3);
        add_filter('comment_class', array($this, 'add_comment_classes'), 10, 5);
    }

    /**
     * 插件激活
     */
    public function activate()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 评论元数据表
        $table_name = $wpdb->prefix . 'xiaowu_comment_meta';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            comment_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY  (meta_id),
            KEY comment_id (comment_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 设置默认配置
        add_option('xiaowu_comments_antispam_enabled', true);
        add_option('xiaowu_comments_notification_enabled', true);
        add_option('xiaowu_comments_emoji_enabled', true);
        add_option('xiaowu_comments_mention_enabled', true);
        add_option('xiaowu_comments_ai_moderation', false);
    }

    /**
     * 插件停用
     */
    public function deactivate()
    {
        wp_cache_flush();
    }

    /**
     * 注册REST API路由
     */
    public function register_rest_routes()
    {
        // 获取评论列表
        register_rest_route('xiaowu-comments/v1', '/comments', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_comments_callback'),
            'permission_callback' => '__return_true'
        ));

        // 提交评论
        register_rest_route('xiaowu-comments/v1', '/comments', array(
            'methods' => 'POST',
            'callback' => array($this, 'post_comment_callback'),
            'permission_callback' => '__return_true'
        ));

        // 点赞评论
        register_rest_route('xiaowu-comments/v1', '/comments/(?P<id>\d+)/like', array(
            'methods' => 'POST',
            'callback' => array($this, 'like_comment_callback'),
            'permission_callback' => '__return_true'
        ));

        // 举报评论
        register_rest_route('xiaowu-comments/v1', '/comments/(?P<id>\d+)/report', array(
            'methods' => 'POST',
            'callback' => array($this, 'report_comment_callback'),
            'permission_callback' => '__return_true'
        ));

        // 删除评论
        register_rest_route('xiaowu-comments/v1', '/comments/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_comment_callback'),
            'permission_callback' => function() {
                return current_user_can('moderate_comments');
            }
        ));
    }

    /**
     * 获取评论列表回调
     */
    public function get_comments_callback($request)
    {
        $post_id = intval($request->get_param('post_id'));
        $parent = intval($request->get_param('parent')) ?: 0;
        $per_page = intval($request->get_param('per_page')) ?: 20;
        $page = intval($request->get_param('page')) ?: 1;

        $args = array(
            'post_id' => $post_id,
            'parent' => $parent,
            'status' => 'approve',
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'comment_date_gmt',
            'order' => 'ASC'
        );

        $comments = get_comments($args);
        $formatted_comments = array();

        foreach ($comments as $comment) {
            $formatted_comments[] = $this->format_comment($comment);
        }

        // 获取总数
        $total_args = array(
            'post_id' => $post_id,
            'parent' => $parent,
            'status' => 'approve',
            'count' => true
        );
        $total = get_comments($total_args);

        return rest_ensure_response(array(
            'success' => true,
            'data' => $formatted_comments,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * 提交评论回调
     */
    public function post_comment_callback($request)
    {
        $post_id = intval($request->get_param('post_id'));
        $content = sanitize_textarea_field($request->get_param('content'));
        $parent = intval($request->get_param('parent')) ?: 0;
        $author_name = sanitize_text_field($request->get_param('author_name'));
        $author_email = sanitize_email($request->get_param('author_email'));
        $author_url = esc_url_raw($request->get_param('author_url'));

        // 验证必填字段
        if (empty($post_id) || empty($content)) {
            return new WP_Error('missing_fields', '缺少必填字段', array('status' => 400));
        }

        // 反垃圾检查
        $antispam = new Xiaowu_Antispam();
        $spam_check = $antispam->check($content, $author_email);

        if (!$spam_check['pass']) {
            return new WP_Error('spam_detected', $spam_check['message'], array('status' => 403));
        }

        // 准备评论数据
        $commentdata = array(
            'comment_post_ID' => $post_id,
            'comment_content' => $content,
            'comment_parent' => $parent,
            'comment_type' => 'comment',
            'comment_approved' => get_option('xiaowu_comments_ai_moderation') ? 0 : 1,
            'user_id' => get_current_user_id()
        );

        // 非登录用户需要提供姓名和邮箱
        if (!is_user_logged_in()) {
            if (empty($author_name) || empty($author_email)) {
                return new WP_Error('auth_required', '请提供姓名和邮箱', array('status' => 400));
            }

            $commentdata['comment_author'] = $author_name;
            $commentdata['comment_author_email'] = $author_email;
            $commentdata['comment_author_url'] = $author_url;
        }

        // 插入评论
        $comment_id = wp_insert_comment($commentdata);

        if (!$comment_id) {
            return new WP_Error('comment_failed', '评论提交失败', array('status' => 500));
        }

        // 发送通知
        if (get_option('xiaowu_comments_notification_enabled')) {
            $notification = new Xiaowu_Comment_Notification();
            $notification->notify_new_comment($comment_id);
        }

        $comment = get_comment($comment_id);

        return rest_ensure_response(array(
            'success' => true,
            'message' => get_option('xiaowu_comments_ai_moderation') ? '评论已提交，等待审核' : '评论已发布',
            'data' => $this->format_comment($comment)
        ));
    }

    /**
     * 点赞评论回调
     */
    public function like_comment_callback($request)
    {
        $comment_id = intval($request->get_param('id'));
        $comment = get_comment($comment_id);

        if (!$comment) {
            return new WP_Error('not_found', '评论不存在', array('status' => 404));
        }

        // 获取当前点赞数
        $likes = intval(get_comment_meta($comment_id, 'likes', true));

        // 检查用户是否已点赞
        $user_ip = $_SERVER['REMOTE_ADDR'];
        $liked_ips = get_comment_meta($comment_id, 'liked_ips', true) ?: array();

        if (in_array($user_ip, $liked_ips)) {
            return new WP_Error('already_liked', '您已点赞过此评论', array('status' => 400));
        }

        // 增加点赞数
        $likes++;
        update_comment_meta($comment_id, 'likes', $likes);

        // 记录点赞IP
        $liked_ips[] = $user_ip;
        update_comment_meta($comment_id, 'liked_ips', $liked_ips);

        return rest_ensure_response(array(
            'success' => true,
            'likes' => $likes
        ));
    }

    /**
     * 举报评论回调
     */
    public function report_comment_callback($request)
    {
        $comment_id = intval($request->get_param('id'));
        $reason = sanitize_text_field($request->get_param('reason'));

        $comment = get_comment($comment_id);
        if (!$comment) {
            return new WP_Error('not_found', '评论不存在', array('status' => 404));
        }

        // 记录举报
        $reports = get_comment_meta($comment_id, 'reports', true) ?: array();
        $reports[] = array(
            'reason' => $reason,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'time' => current_time('mysql')
        );
        update_comment_meta($comment_id, 'reports', $reports);

        // 如果举报次数超过阈值,自动标记为待审核
        if (count($reports) >= 3) {
            wp_set_comment_status($comment_id, 'hold');

            // 通知管理员
            $admin_email = get_option('admin_email');
            wp_mail(
                $admin_email,
                '评论被多次举报',
                "评论 #{$comment_id} 已被举报 " . count($reports) . " 次,已自动标记为待审核。\n\n查看: " . admin_url("comment.php?action=approve&c={$comment_id}")
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => '感谢您的反馈'
        ));
    }

    /**
     * 删除评论回调
     */
    public function delete_comment_callback($request)
    {
        $comment_id = intval($request->get_param('id'));

        $result = wp_delete_comment($comment_id, true);

        if (!$result) {
            return new WP_Error('delete_failed', '删除失败', array('status' => 500));
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => '评论已删除'
        ));
    }

    /**
     * 格式化评论数据
     */
    private function format_comment($comment)
    {
        $likes = intval(get_comment_meta($comment->comment_ID, 'likes', true));
        $mentions = get_comment_meta($comment->comment_ID, 'mentions', true) ?: array();

        return array(
            'id' => $comment->comment_ID,
            'post_id' => $comment->comment_post_ID,
            'parent' => $comment->comment_parent,
            'author' => array(
                'name' => $comment->comment_author,
                'email' => $comment->comment_author_email,
                'url' => $comment->comment_author_url,
                'avatar' => get_avatar_url($comment, array('size' => 48))
            ),
            'content' => apply_filters('comment_text', $comment->comment_content, $comment),
            'date' => $comment->comment_date,
            'date_gmt' => $comment->comment_date_gmt,
            'status' => wp_get_comment_status($comment->comment_ID),
            'likes' => $likes,
            'mentions' => $mentions,
            'is_author' => $comment->user_id == get_current_user_id(),
            'children_count' => get_comments(array(
                'parent' => $comment->comment_ID,
                'count' => true,
                'status' => 'approve'
            ))
        );
    }

    /**
     * 自定义评论表单
     */
    public function customize_comment_form($defaults)
    {
        $defaults['title_reply'] = '发表评论';
        $defaults['label_submit'] = '提交评论';
        $defaults['comment_field'] = '<p class="comment-form-comment">
            <label for="comment">评论内容 <span class="required">*</span></label>
            <textarea id="comment" name="comment" cols="45" rows="8" maxlength="65525" required="required" placeholder="写下你的想法..."></textarea>
        </p>';

        if (get_option('xiaowu_comments_emoji_enabled')) {
            $defaults['comment_field'] .= '<div class="xiaowu-emoji-picker"></div>';
        }

        return $defaults;
    }

    /**
     * 处理评论提交
     */
    public function handle_comment_submission($comment_id, $approved, $commentdata)
    {
        // 处理@提及
        if (get_option('xiaowu_comments_mention_enabled')) {
            $mention = new Xiaowu_Comment_Mention();
            $mention->process($comment_id, $commentdata['comment_content']);
        }
    }

    /**
     * 处理评论文本
     */
    public function process_comment_text($comment_text, $comment, $args)
    {
        // 处理表情
        if (get_option('xiaowu_comments_emoji_enabled')) {
            $emoji = new Xiaowu_Comment_Emoji();
            $comment_text = $emoji->convert($comment_text);
        }

        // 处理@提及
        if (get_option('xiaowu_comments_mention_enabled')) {
            $mention = new Xiaowu_Comment_Mention();
            $comment_text = $mention->format($comment_text, $comment);
        }

        return $comment_text;
    }

    /**
     * 添加评论CSS类
     */
    public function add_comment_classes($classes, $class, $comment_id, $comment, $post_id)
    {
        // 添加作者评论标识
        if ($comment->user_id && get_post_field('post_author', $post_id) == $comment->user_id) {
            $classes[] = 'bypostauthor';
        }

        // 添加管理员评论标识
        if (user_can($comment->user_id, 'manage_options')) {
            $classes[] = 'byadmin';
        }

        return $classes;
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'options-general.php',
            '评论系统设置',
            '评论系统',
            'manage_options',
            'xiaowu-comments',
            array($this, 'admin_page')
        );
    }

    /**
     * 管理页面
     */
    public function admin_page()
    {
        include XIAOWU_COMMENTS_DIR . 'admin/settings-page.php';
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets()
    {
        if (!is_singular() || !comments_open()) {
            return;
        }

        wp_enqueue_style(
            'xiaowu-comments',
            XIAOWU_COMMENTS_URL . 'assets/css/comments.css',
            array(),
            XIAOWU_COMMENTS_VERSION
        );

        wp_enqueue_script(
            'xiaowu-comments',
            XIAOWU_COMMENTS_URL . 'assets/js/comments.js',
            array('jquery'),
            XIAOWU_COMMENTS_VERSION,
            true
        );

        wp_localize_script('xiaowu-comments', 'xiaowuComments', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => rest_url('xiaowu-comments/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id(),
            'emojiEnabled' => get_option('xiaowu_comments_emoji_enabled'),
            'mentionEnabled' => get_option('xiaowu_comments_mention_enabled')
        ));
    }

    /**
     * 加载管理后台资源
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'settings_page_xiaowu-comments') {
            return;
        }

        wp_enqueue_style(
            'xiaowu-comments-admin',
            XIAOWU_COMMENTS_URL . 'assets/css/admin.css',
            array(),
            XIAOWU_COMMENTS_VERSION
        );

        wp_enqueue_script(
            'xiaowu-comments-admin',
            XIAOWU_COMMENTS_URL . 'assets/js/admin.js',
            array('jquery'),
            XIAOWU_COMMENTS_VERSION,
            true
        );
    }
}

/**
 * 初始化插件
 */
function xiaowu_comments_init()
{
    return Xiaowu_Comments_Plugin::get_instance();
}

add_action('plugins_loaded', 'xiaowu_comments_init');
