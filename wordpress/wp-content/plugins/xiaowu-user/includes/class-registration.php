<?php
/**
 * 用户注册类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_User_Registration
{
    /**
     * 用户注册
     */
    public function register($username, $email, $password, $display_name = '')
    {
        // 检查是否启用注册
        if (!get_option('xiaowu_user_enable_registration', true)) {
            return array(
                'success' => false,
                'message' => '注册功能已关闭'
            );
        }

        // 验证输入
        $validation = $this->validate_registration($username, $email, $password);
        if (!$validation['success']) {
            return $validation;
        }

        // 创建用户
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return array(
                'success' => false,
                'message' => $user_id->get_error_message()
            );
        }

        // 更新显示名称
        if (!empty($display_name)) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $display_name
            ));
        }

        // 设置默认角色
        $default_role = get_option('xiaowu_user_default_role', 'subscriber');
        $user = new WP_User($user_id);
        $user->set_role($default_role);

        // 初始化用户资料
        $this->init_user_profile($user_id);

        // 初始化用户等级
        $this->init_user_level($user_id);

        // 发送验证邮件
        if (get_option('xiaowu_user_require_email_verification', true)) {
            $this->send_verification_email($user_id);
            $message = '注册成功,请查收验证邮件';
        } else {
            update_user_meta($user_id, 'email_verified', true);
            $message = '注册成功';
        }

        // 记录注册活动
        $this->record_registration_activity($user_id);

        return array(
            'success' => true,
            'message' => $message,
            'data' => array(
                'user_id' => $user_id,
                'username' => $username,
                'email' => $email
            )
        );
    }

    /**
     * 验证注册信息
     */
    private function validate_registration($username, $email, $password)
    {
        $errors = array();

        // 验证用户名
        if (empty($username)) {
            $errors[] = '用户名不能为空';
        } elseif (!validate_username($username)) {
            $errors[] = '用户名格式不正确';
        } elseif (username_exists($username)) {
            $errors[] = '用户名已存在';
        } elseif (strlen($username) < 3) {
            $errors[] = '用户名至少3个字符';
        } elseif (strlen($username) > 20) {
            $errors[] = '用户名最多20个字符';
        }

        // 验证邮箱
        if (empty($email)) {
            $errors[] = '邮箱不能为空';
        } elseif (!is_email($email)) {
            $errors[] = '邮箱格式不正确';
        } elseif (email_exists($email)) {
            $errors[] = '邮箱已被注册';
        }

        // 验证密码
        if (empty($password)) {
            $errors[] = '密码不能为空';
        } elseif (strlen($password) < 6) {
            $errors[] = '密码至少6个字符';
        }

        // 检查密码强度
        $auth = new Xiaowu_User_Auth();
        $strength = $auth->validate_password_strength($password);
        if (!$strength['is_strong']) {
            $errors = array_merge($errors, $strength['messages']);
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => implode('; ', $errors),
                'errors' => $errors
            );
        }

        return array('success' => true);
    }

    /**
     * 初始化用户资料
     */
    private function init_user_profile($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'avatar_url' => get_avatar_url($user_id),
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * 初始化用户等级
     */
    private function init_user_level($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'level' => 1,
            'experience' => 0,
            'points' => 0,
            'badges' => json_encode(array()),
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * 发送验证邮件
     */
    public function send_verification_email($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // 生成验证令牌
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, 'email_verification_token', $token);
        update_user_meta($user_id, 'email_verification_token_time', time());

        // 构建验证链接
        $verify_url = add_query_arg(array(
            'action' => 'verify_email',
            'token' => $token
        ), home_url());

        // 发送邮件
        $subject = '[' . get_bloginfo('name') . '] 请验证您的邮箱';

        $message = "您好 {$user->display_name},\n\n";
        $message .= "感谢您注册 " . get_bloginfo('name') . "！\n\n";
        $message .= "请点击下面的链接验证您的邮箱：\n";
        $message .= $verify_url . "\n\n";
        $message .= "此链接将在24小时后失效。\n\n";
        $message .= "如果您没有注册账号，请忽略此邮件。\n\n";
        $message .= "祝好！\n";
        $message .= get_bloginfo('name') . " 团队";

        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * 验证邮箱
     */
    public function verify_email($token)
    {
        if (empty($token)) {
            return array(
                'success' => false,
                'message' => '验证令牌不能为空'
            );
        }

        // 查找用户
        $users = get_users(array(
            'meta_key' => 'email_verification_token',
            'meta_value' => $token,
            'number' => 1
        ));

        if (empty($users)) {
            return array(
                'success' => false,
                'message' => '无效的验证令牌'
            );
        }

        $user = $users[0];

        // 检查令牌是否过期(24小时)
        $token_time = get_user_meta($user->ID, 'email_verification_token_time', true);
        if (time() - $token_time > 24 * 60 * 60) {
            return array(
                'success' => false,
                'message' => '验证令牌已过期，请重新发送验证邮件'
            );
        }

        // 标记邮箱已验证
        update_user_meta($user->ID, 'email_verified', true);
        delete_user_meta($user->ID, 'email_verification_token');
        delete_user_meta($user->ID, 'email_verification_token_time');

        // 奖励注册积分
        $user_level = new Xiaowu_User_Level();
        $user_level->add_experience($user->ID, 10, '完成邮箱验证');

        return array(
            'success' => true,
            'message' => '邮箱验证成功',
            'data' => array(
                'user_id' => $user->ID,
                'username' => $user->user_login
            )
        );
    }

    /**
     * 重新发送验证邮件
     */
    public function resend_verification_email($email)
    {
        $user = get_user_by('email', $email);

        if (!$user) {
            return array(
                'success' => false,
                'message' => '用户不存在'
            );
        }

        // 检查是否已验证
        $email_verified = get_user_meta($user->ID, 'email_verified', true);
        if ($email_verified) {
            return array(
                'success' => false,
                'message' => '邮箱已验证，无需重复验证'
            );
        }

        // 检查发送频率限制(5分钟内只能发送一次)
        $last_sent = get_user_meta($user->ID, 'verification_email_last_sent', true);
        if ($last_sent && (time() - $last_sent) < 300) {
            return array(
                'success' => false,
                'message' => '发送过于频繁，请5分钟后再试'
            );
        }

        // 发送验证邮件
        $sent = $this->send_verification_email($user->ID);

        if ($sent) {
            update_user_meta($user->ID, 'verification_email_last_sent', time());
            return array(
                'success' => true,
                'message' => '验证邮件已重新发送'
            );
        } else {
            return array(
                'success' => false,
                'message' => '邮件发送失败，请稍后再试'
            );
        }
    }

    /**
     * 记录注册活动
     */
    private function record_registration_activity($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_activities';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'action' => 'register',
            'object_type' => 'user',
            'object_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * 检查用户名是否可用
     */
    public function check_username_availability($username)
    {
        if (empty($username)) {
            return array(
                'available' => false,
                'message' => '用户名不能为空'
            );
        }

        if (!validate_username($username)) {
            return array(
                'available' => false,
                'message' => '用户名格式不正确'
            );
        }

        if (username_exists($username)) {
            return array(
                'available' => false,
                'message' => '用户名已存在'
            );
        }

        return array(
            'available' => true,
            'message' => '用户名可用'
        );
    }

    /**
     * 检查邮箱是否可用
     */
    public function check_email_availability($email)
    {
        if (empty($email)) {
            return array(
                'available' => false,
                'message' => '邮箱不能为空'
            );
        }

        if (!is_email($email)) {
            return array(
                'available' => false,
                'message' => '邮箱格式不正确'
            );
        }

        if (email_exists($email)) {
            return array(
                'available' => false,
                'message' => '邮箱已被注册'
            );
        }

        return array(
            'available' => true,
            'message' => '邮箱可用'
        );
    }

    /**
     * 获取注册统计
     */
    public function get_registration_stats($days = 30)
    {
        global $wpdb;

        $date_query = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s",
            $date_query
        ));

        // 按日统计
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(user_registered) as date, COUNT(*) as count
            FROM {$wpdb->users}
            WHERE user_registered >= %s
            GROUP BY DATE(user_registered)
            ORDER BY date ASC",
            $date_query
        ), ARRAY_A);

        return array(
            'total' => intval($total),
            'daily' => $daily_stats
        );
    }
}
