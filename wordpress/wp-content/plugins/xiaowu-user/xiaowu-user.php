<?php
/**
 * Plugin Name: 小伍用户管理
 * Plugin URI: https://github.com/yourusername/xiaowu-user
 * Description: 用户管理插件，提供用户资料、社交登录、用户等级、私信系统、关注/粉丝功能
 * Version: 1.0.0
 * Author: 小伍同学
 * Author URI: https://xiaowu.blog
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: xiaowu-user
 * Domain Path: /languages
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 插件版本
define('XIAOWU_USER_VERSION', '1.0.0');

// 插件路径
define('XIAOWU_USER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XIAOWU_USER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 小伍用户管理插件主类
 */
class Xiaowu_User_Plugin
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
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-auth.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-registration.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-password-reset.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-profile.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-social-login.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-user-level.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-private-message.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-follow.php';
        require_once XIAOWU_USER_PLUGIN_DIR . 'includes/class-user-stats.php';
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

        // 用户登录后操作
        add_action('wp_login', array($this, 'on_user_login'), 10, 2);
    }

    /**
     * 插件激活
     */
    public function activate()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 用户扩展信息表
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            avatar_url varchar(500),
            bio text,
            location varchar(100),
            website varchar(255),
            birthday date,
            gender varchar(10),
            phone varchar(20),
            wechat varchar(50),
            qq varchar(20),
            github varchar(100),
            custom_fields longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // 用户等级表
        $table_levels = $wpdb->prefix . 'xiaowu_user_levels';
        $sql_levels = "CREATE TABLE IF NOT EXISTS $table_levels (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            level int(11) DEFAULT 1,
            experience int(11) DEFAULT 0,
            points int(11) DEFAULT 0,
            badges longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY level (level)
        ) $charset_collate;";

        // 私信表
        $table_messages = $wpdb->prefix . 'xiaowu_private_messages';
        $sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            receiver_id bigint(20) NOT NULL,
            subject varchar(255),
            content longtext NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY receiver_id (receiver_id),
            KEY is_read (is_read)
        ) $charset_collate;";

        // 关注关系表
        $table_follows = $wpdb->prefix . 'xiaowu_user_follows';
        $sql_follows = "CREATE TABLE IF NOT EXISTS $table_follows (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            follower_id bigint(20) NOT NULL,
            following_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY follower_following (follower_id, following_id),
            KEY follower_id (follower_id),
            KEY following_id (following_id)
        ) $charset_collate;";

        // 社交账号绑定表
        $table_social = $wpdb->prefix . 'xiaowu_social_accounts';
        $sql_social = "CREATE TABLE IF NOT EXISTS $table_social (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            provider varchar(50) NOT NULL,
            provider_user_id varchar(255) NOT NULL,
            access_token varchar(500),
            refresh_token varchar(500),
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_user (provider, provider_user_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // 用户活动记录表
        $table_activities = $wpdb->prefix . 'xiaowu_user_activities';
        $sql_activities = "CREATE TABLE IF NOT EXISTS $table_activities (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50),
            object_id bigint(20),
            metadata longtext,
            ip_address varchar(45),
            user_agent varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_levels);
        dbDelta($sql_messages);
        dbDelta($sql_follows);
        dbDelta($sql_social);
        dbDelta($sql_activities);

        // 设置默认配置
        add_option('xiaowu_user_enable_registration', true);
        add_option('xiaowu_user_require_email_verification', true);
        add_option('xiaowu_user_enable_social_login', true);
        add_option('xiaowu_user_default_role', 'subscriber');
        add_option('xiaowu_user_enable_private_message', true);
        add_option('xiaowu_user_enable_follow', true);
        add_option('xiaowu_user_enable_user_level', true);

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
        // 用户注册
        register_rest_route('xiaowu-user/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_callback'),
            'permission_callback' => '__return_true'
        ));

        // 用户登录
        register_rest_route('xiaowu-user/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login_callback'),
            'permission_callback' => '__return_true'
        ));

        // 用户登出
        register_rest_route('xiaowu-user/v1', '/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 邮箱验证
        register_rest_route('xiaowu-user/v1', '/verify-email', array(
            'methods' => 'GET',
            'callback' => array($this, 'verify_email_callback'),
            'permission_callback' => '__return_true'
        ));

        // 密码重置请求
        register_rest_route('xiaowu-user/v1', '/reset-password', array(
            'methods' => 'POST',
            'callback' => array($this, 'reset_password_request_callback'),
            'permission_callback' => '__return_true'
        ));

        // 密码重置确认
        register_rest_route('xiaowu-user/v1', '/reset-password/confirm', array(
            'methods' => 'POST',
            'callback' => array($this, 'reset_password_confirm_callback'),
            'permission_callback' => '__return_true'
        ));

        // 获取用户资料
        register_rest_route('xiaowu-user/v1', '/profile/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_profile_callback'),
            'permission_callback' => '__return_true'
        ));

        // 更新用户资料
        register_rest_route('xiaowu-user/v1', '/profile', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_profile_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 上传头像
        register_rest_route('xiaowu-user/v1', '/avatar', array(
            'methods' => 'POST',
            'callback' => array($this, 'upload_avatar_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 社交登录
        register_rest_route('xiaowu-user/v1', '/social-login/(?P<provider>[a-zA-Z0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'social_login_callback'),
            'permission_callback' => '__return_true'
        ));

        // 获取用户等级
        register_rest_route('xiaowu-user/v1', '/level/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_level_callback'),
            'permission_callback' => '__return_true'
        ));

        // 发送私信
        register_rest_route('xiaowu-user/v1', '/messages', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_message_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 获取私信列表
        register_rest_route('xiaowu-user/v1', '/messages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_messages_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 标记私信已读
        register_rest_route('xiaowu-user/v1', '/messages/(?P<id>\d+)/read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_message_read_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 关注用户
        register_rest_route('xiaowu-user/v1', '/follow/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'follow_user_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 取消关注
        register_rest_route('xiaowu-user/v1', '/unfollow/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'unfollow_user_callback'),
            'permission_callback' => 'is_user_logged_in'
        ));

        // 获取关注列表
        register_rest_route('xiaowu-user/v1', '/following/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_following_callback'),
            'permission_callback' => '__return_true'
        ));

        // 获取粉丝列表
        register_rest_route('xiaowu-user/v1', '/followers/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_followers_callback'),
            'permission_callback' => '__return_true'
        ));

        // 获取用户统计
        register_rest_route('xiaowu-user/v1', '/stats/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats_callback'),
            'permission_callback' => '__return_true'
        ));
    }

    /**
     * 注册回调
     */
    public function register_callback($request)
    {
        $username = sanitize_text_field($request->get_param('username'));
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');
        $display_name = sanitize_text_field($request->get_param('display_name'));

        $registration = new Xiaowu_User_Registration();
        return rest_ensure_response($registration->register($username, $email, $password, $display_name));
    }

    /**
     * 登录回调
     */
    public function login_callback($request)
    {
        $username = sanitize_text_field($request->get_param('username'));
        $password = $request->get_param('password');
        $remember = filter_var($request->get_param('remember'), FILTER_VALIDATE_BOOLEAN);

        $auth = new Xiaowu_User_Auth();
        return rest_ensure_response($auth->login($username, $password, $remember));
    }

    /**
     * 登出回调
     */
    public function logout_callback($request)
    {
        $auth = new Xiaowu_User_Auth();
        return rest_ensure_response($auth->logout());
    }

    /**
     * 邮箱验证回调
     */
    public function verify_email_callback($request)
    {
        $token = sanitize_text_field($request->get_param('token'));

        $registration = new Xiaowu_User_Registration();
        return rest_ensure_response($registration->verify_email($token));
    }

    /**
     * 密码重置请求回调
     */
    public function reset_password_request_callback($request)
    {
        $email = sanitize_email($request->get_param('email'));

        $password_reset = new Xiaowu_Password_Reset();
        return rest_ensure_response($password_reset->request_reset($email));
    }

    /**
     * 密码重置确认回调
     */
    public function reset_password_confirm_callback($request)
    {
        $token = sanitize_text_field($request->get_param('token'));
        $password = $request->get_param('password');

        $password_reset = new Xiaowu_Password_Reset();
        return rest_ensure_response($password_reset->confirm_reset($token, $password));
    }

    /**
     * 获取用户资料回调
     */
    public function get_profile_callback($request)
    {
        $user_id = intval($request->get_param('id'));

        $profile = new Xiaowu_User_Profile();
        return rest_ensure_response($profile->get($user_id));
    }

    /**
     * 更新用户资料回调
     */
    public function update_profile_callback($request)
    {
        $user_id = get_current_user_id();
        $data = array(
            'display_name' => sanitize_text_field($request->get_param('display_name')),
            'bio' => sanitize_textarea_field($request->get_param('bio')),
            'location' => sanitize_text_field($request->get_param('location')),
            'website' => esc_url($request->get_param('website')),
            'birthday' => sanitize_text_field($request->get_param('birthday')),
            'gender' => sanitize_text_field($request->get_param('gender')),
            'phone' => sanitize_text_field($request->get_param('phone')),
            'wechat' => sanitize_text_field($request->get_param('wechat')),
            'qq' => sanitize_text_field($request->get_param('qq')),
            'github' => sanitize_text_field($request->get_param('github'))
        );

        $profile = new Xiaowu_User_Profile();
        return rest_ensure_response($profile->update($user_id, $data));
    }

    /**
     * 上传头像回调
     */
    public function upload_avatar_callback($request)
    {
        $user_id = get_current_user_id();
        $files = $request->get_file_params();

        if (empty($files['avatar'])) {
            return new WP_Error('no_file', '未上传文件', array('status' => 400));
        }

        $profile = new Xiaowu_User_Profile();
        return rest_ensure_response($profile->upload_avatar($user_id, $files['avatar']));
    }

    /**
     * 社交登录回调
     */
    public function social_login_callback($request)
    {
        $provider = sanitize_text_field($request->get_param('provider'));
        $code = sanitize_text_field($request->get_param('code'));
        $state = sanitize_text_field($request->get_param('state'));

        $social_login = new Xiaowu_Social_Login();
        return rest_ensure_response($social_login->authenticate($provider, $code, $state));
    }

    /**
     * 获取用户等级回调
     */
    public function get_level_callback($request)
    {
        $user_id = intval($request->get_param('id'));

        $user_level = new Xiaowu_User_Level();
        return rest_ensure_response($user_level->get($user_id));
    }

    /**
     * 发送私信回调
     */
    public function send_message_callback($request)
    {
        $sender_id = get_current_user_id();
        $receiver_id = intval($request->get_param('receiver_id'));
        $subject = sanitize_text_field($request->get_param('subject'));
        $content = sanitize_textarea_field($request->get_param('content'));

        $pm = new Xiaowu_Private_Message();
        return rest_ensure_response($pm->send($sender_id, $receiver_id, $subject, $content));
    }

    /**
     * 获取私信列表回调
     */
    public function get_messages_callback($request)
    {
        $user_id = get_current_user_id();
        $type = sanitize_text_field($request->get_param('type')) ?: 'inbox';
        $page = intval($request->get_param('page')) ?: 1;
        $per_page = intval($request->get_param('per_page')) ?: 20;

        $pm = new Xiaowu_Private_Message();
        return rest_ensure_response($pm->get_messages($user_id, $type, $page, $per_page));
    }

    /**
     * 标记私信已读回调
     */
    public function mark_message_read_callback($request)
    {
        $message_id = intval($request->get_param('id'));
        $user_id = get_current_user_id();

        $pm = new Xiaowu_Private_Message();
        return rest_ensure_response($pm->mark_read($message_id, $user_id));
    }

    /**
     * 关注用户回调
     */
    public function follow_user_callback($request)
    {
        $follower_id = get_current_user_id();
        $following_id = intval($request->get_param('id'));

        $follow = new Xiaowu_User_Follow();
        return rest_ensure_response($follow->follow($follower_id, $following_id));
    }

    /**
     * 取消关注回调
     */
    public function unfollow_user_callback($request)
    {
        $follower_id = get_current_user_id();
        $following_id = intval($request->get_param('id'));

        $follow = new Xiaowu_User_Follow();
        return rest_ensure_response($follow->unfollow($follower_id, $following_id));
    }

    /**
     * 获取关注列表回调
     */
    public function get_following_callback($request)
    {
        $user_id = intval($request->get_param('id'));
        $page = intval($request->get_param('page')) ?: 1;
        $per_page = intval($request->get_param('per_page')) ?: 20;

        $follow = new Xiaowu_User_Follow();
        return rest_ensure_response($follow->get_following($user_id, $page, $per_page));
    }

    /**
     * 获取粉丝列表回调
     */
    public function get_followers_callback($request)
    {
        $user_id = intval($request->get_param('id'));
        $page = intval($request->get_param('page')) ?: 1;
        $per_page = intval($request->get_param('per_page')) ?: 20;

        $follow = new Xiaowu_User_Follow();
        return rest_ensure_response($follow->get_followers($user_id, $page, $per_page));
    }

    /**
     * 获取用户统计回调
     */
    public function get_stats_callback($request)
    {
        $user_id = intval($request->get_param('id'));

        $stats = new Xiaowu_User_Stats();
        return rest_ensure_response($stats->get($user_id));
    }

    /**
     * 用户登录后操作
     */
    public function on_user_login($user_login, $user)
    {
        // 记录登录时间
        update_user_meta($user->ID, 'last_login', current_time('mysql'));

        // 记录登录IP
        update_user_meta($user->ID, 'last_login_ip', $_SERVER['REMOTE_ADDR']);

        // 增加登录次数
        $login_count = intval(get_user_meta($user->ID, 'login_count', true));
        update_user_meta($user->ID, 'login_count', $login_count + 1);

        // 记录活动
        $stats = new Xiaowu_User_Stats();
        $stats->record_activity($user->ID, 'login');
    }

    /**
     * 添加管理菜单
     */
    public function add_admin_menu()
    {
        add_menu_page(
            '小伍用户管理',
            '小伍用户',
            'manage_options',
            'xiaowu-user',
            array($this, 'admin_page'),
            'dashicons-admin-users',
            25
        );

        add_submenu_page(
            'xiaowu-user',
            '用户设置',
            '设置',
            'manage_options',
            'xiaowu-user',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'xiaowu-user',
            '用户统计',
            '统计',
            'manage_options',
            'xiaowu-user-stats',
            array($this, 'stats_page')
        );
    }

    /**
     * 管理页面
     */
    public function admin_page()
    {
        include XIAOWU_USER_PLUGIN_DIR . 'admin/settings-page.php';
    }

    /**
     * 统计页面
     */
    public function stats_page()
    {
        include XIAOWU_USER_PLUGIN_DIR . 'admin/stats-page.php';
    }

    /**
     * 加载管理后台资源
     */
    public function enqueue_admin_assets($hook)
    {
        if (strpos($hook, 'xiaowu-user') === false) {
            return;
        }

        wp_enqueue_style(
            'xiaowu-user-admin',
            XIAOWU_USER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            XIAOWU_USER_VERSION
        );

        wp_enqueue_script(
            'xiaowu-user-admin',
            XIAOWU_USER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            XIAOWU_USER_VERSION,
            true
        );

        wp_localize_script('xiaowu-user-admin', 'xiaowuUser', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xiaowu_user_nonce'),
            'apiUrl' => rest_url('xiaowu-user/v1')
        ));
    }

    /**
     * 加载前端资源
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'xiaowu-user-frontend',
            XIAOWU_USER_PLUGIN_URL . 'assets/css/user.css',
            array(),
            XIAOWU_USER_VERSION
        );

        wp_enqueue_script(
            'xiaowu-user-frontend',
            XIAOWU_USER_PLUGIN_URL . 'assets/js/user.js',
            array('jquery'),
            XIAOWU_USER_VERSION,
            true
        );

        wp_localize_script('xiaowu-user-frontend', 'xiaowuUser', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('xiaowu_user_nonce'),
            'apiUrl' => rest_url('xiaowu-user/v1'),
            'isLoggedIn' => is_user_logged_in(),
            'currentUserId' => get_current_user_id()
        ));
    }
}

/**
 * 初始化插件
 */
function xiaowu_user_init()
{
    return Xiaowu_User_Plugin::get_instance();
}

// 启动插件
add_action('plugins_loaded', 'xiaowu_user_init');
