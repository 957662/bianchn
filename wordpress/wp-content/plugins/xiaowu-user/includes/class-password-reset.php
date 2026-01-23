<?php
/**
 * 密码重置类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Password_Reset
{
    /**
     * 请求密码重置
     */
    public function request_reset($email)
    {
        if (empty($email)) {
            return array(
                'success' => false,
                'message' => '邮箱不能为空'
            );
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            // 为了安全，即使用户不存在也返回成功消息
            return array(
                'success' => true,
                'message' => '如果该邮箱已注册，您将收到重置密码的链接'
            );
        }

        // 检查发送频率限制(5分钟内只能发送一次)
        $last_sent = get_user_meta($user->ID, 'password_reset_last_sent', true);
        if ($last_sent && (time() - $last_sent) < 300) {
            return array(
                'success' => false,
                'message' => '发送过于频繁，请5分钟后再试'
            );
        }

        // 生成重置令牌
        $token = wp_generate_password(32, false);
        update_user_meta($user->ID, 'password_reset_token', $token);
        update_user_meta($user->ID, 'password_reset_token_time', time());
        update_user_meta($user->ID, 'password_reset_last_sent', time());

        // 构建重置链接
        $reset_url = add_query_arg(array(
            'action' => 'reset_password',
            'token' => $token
        ), home_url());

        // 发送邮件
        $subject = '[' . get_bloginfo('name') . '] 重置您的密码';

        $message = "您好 {$user->display_name},\n\n";
        $message .= "我们收到了重置您密码的请求。\n\n";
        $message .= "请点击下面的链接重置密码：\n";
        $message .= $reset_url . "\n\n";
        $message .= "此链接将在1小时后失效。\n\n";
        $message .= "如果您没有请求重置密码，请忽略此邮件。\n\n";
        $message .= "祝好！\n";
        $message .= get_bloginfo('name') . " 团队";

        $sent = wp_mail($user->user_email, $subject, $message);

        if ($sent) {
            return array(
                'success' => true,
                'message' => '密码重置链接已发送到您的邮箱'
            );
        } else {
            return array(
                'success' => false,
                'message' => '邮件发送失败，请稍后再试'
            );
        }
    }

    /**
     * 确认密码重置
     */
    public function confirm_reset($token, $new_password)
    {
        if (empty($token)) {
            return array(
                'success' => false,
                'message' => '重置令牌不能为空'
            );
        }

        if (empty($new_password)) {
            return array(
                'success' => false,
                'message' => '新密码不能为空'
            );
        }

        // 验证密码强度
        $auth = new Xiaowu_User_Auth();
        $strength = $auth->validate_password_strength($new_password);
        if (!$strength['is_strong']) {
            return array(
                'success' => false,
                'message' => '密码强度不足：' . implode('; ', $strength['messages'])
            );
        }

        // 查找用户
        $users = get_users(array(
            'meta_key' => 'password_reset_token',
            'meta_value' => $token,
            'number' => 1
        ));

        if (empty($users)) {
            return array(
                'success' => false,
                'message' => '无效的重置令牌'
            );
        }

        $user = $users[0];

        // 检查令牌是否过期(1小时)
        $token_time = get_user_meta($user->ID, 'password_reset_token_time', true);
        if (time() - $token_time > 3600) {
            return array(
                'success' => false,
                'message' => '重置令牌已过期，请重新申请'
            );
        }

        // 重置密码
        wp_set_password($new_password, $user->ID);

        // 清除令牌
        delete_user_meta($user->ID, 'password_reset_token');
        delete_user_meta($user->ID, 'password_reset_token_time');

        // 记录活动
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_activities';
        $wpdb->insert($table_name, array(
            'user_id' => $user->ID,
            'action' => 'password_reset',
            'object_type' => 'user',
            'object_id' => $user->ID,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        ));

        // 发送通知邮件
        $this->send_password_changed_notification($user);

        return array(
            'success' => true,
            'message' => '密码重置成功'
        );
    }

    /**
     * 发送密码已更改通知
     */
    private function send_password_changed_notification($user)
    {
        $subject = '[' . get_bloginfo('name') . '] 您的密码已更改';

        $message = "您好 {$user->display_name},\n\n";
        $message .= "您的密码已成功更改。\n\n";
        $message .= "如果这不是您本人的操作，请立即联系管理员。\n\n";
        $message .= "祝好！\n";
        $message .= get_bloginfo('name') . " 团队";

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * 验证重置令牌
     */
    public function validate_token($token)
    {
        if (empty($token)) {
            return array(
                'valid' => false,
                'message' => '令牌不能为空'
            );
        }

        $users = get_users(array(
            'meta_key' => 'password_reset_token',
            'meta_value' => $token,
            'number' => 1
        ));

        if (empty($users)) {
            return array(
                'valid' => false,
                'message' => '无效的令牌'
            );
        }

        $user = $users[0];
        $token_time = get_user_meta($user->ID, 'password_reset_token_time', true);

        if (time() - $token_time > 3600) {
            return array(
                'valid' => false,
                'message' => '令牌已过期'
            );
        }

        return array(
            'valid' => true,
            'user_id' => $user->ID,
            'username' => $user->user_login
        );
    }
}
