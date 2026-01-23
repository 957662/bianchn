<?php
/**
 * 用户认证类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_User_Auth
{
    /**
     * 用户登录
     */
    public function login($username, $password, $remember = false)
    {
        // 验证输入
        if (empty($username) || empty($password)) {
            return array(
                'success' => false,
                'message' => '用户名和密码不能为空'
            );
        }

        // 尝试登录
        $credentials = array(
            'user_login' => $username,
            'user_password' => $password,
            'remember' => $remember
        );

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            return array(
                'success' => false,
                'message' => $user->get_error_message()
            );
        }

        // 检查邮箱是否已验证
        if (get_option('xiaowu_user_require_email_verification')) {
            $email_verified = get_user_meta($user->ID, 'email_verified', true);
            if (!$email_verified) {
                wp_logout();
                return array(
                    'success' => false,
                    'message' => '请先验证邮箱后再登录',
                    'code' => 'email_not_verified'
                );
            }
        }

        // 生成JWT令牌(可选)
        $token = $this->generate_jwt_token($user);

        return array(
            'success' => true,
            'message' => '登录成功',
            'data' => array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID),
                'token' => $token
            )
        );
    }

    /**
     * 用户登出
     */
    public function logout()
    {
        wp_logout();

        return array(
            'success' => true,
            'message' => '登出成功'
        );
    }

    /**
     * 检查用户是否登录
     */
    public function check_login()
    {
        if (!is_user_logged_in()) {
            return array(
                'success' => false,
                'message' => '用户未登录'
            );
        }

        $user = wp_get_current_user();

        return array(
            'success' => true,
            'data' => array(
                'user_id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID)
            )
        );
    }

    /**
     * 验证密码强度
     */
    public function validate_password_strength($password)
    {
        $strength = 0;
        $messages = array();

        // 长度检查
        if (strlen($password) < 8) {
            $messages[] = '密码长度至少8个字符';
        } else {
            $strength++;
        }

        // 包含数字
        if (preg_match('/\d/', $password)) {
            $strength++;
        } else {
            $messages[] = '密码应包含数字';
        }

        // 包含小写字母
        if (preg_match('/[a-z]/', $password)) {
            $strength++;
        } else {
            $messages[] = '密码应包含小写字母';
        }

        // 包含大写字母
        if (preg_match('/[A-Z]/', $password)) {
            $strength++;
        } else {
            $messages[] = '密码应包含大写字母';
        }

        // 包含特殊字符
        if (preg_match('/[^a-zA-Z\d]/', $password)) {
            $strength++;
        }

        $is_strong = $strength >= 3;

        return array(
            'is_strong' => $is_strong,
            'strength' => $strength,
            'messages' => $messages
        );
    }

    /**
     * 生成JWT令牌
     */
    private function generate_jwt_token($user)
    {
        // 简单的JWT实现(生产环境应使用专业JWT库)
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
        $payload = json_encode(array(
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'exp' => time() + (7 * 24 * 60 * 60) // 7天过期
        ));

        $base64UrlHeader = $this->base64url_encode($header);
        $base64UrlPayload = $this->base64url_encode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, $this->get_jwt_secret(), true);
        $base64UrlSignature = $this->base64url_encode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * 验证JWT令牌
     */
    public function verify_jwt_token($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $valid_signature = hash_hmac('sha256', $header . '.' . $payload, $this->get_jwt_secret(), true);
        $valid_signature = $this->base64url_encode($valid_signature);

        if ($signature !== $valid_signature) {
            return false;
        }

        $payload_data = json_decode($this->base64url_decode($payload), true);

        // 检查是否过期
        if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
            return false;
        }

        return $payload_data;
    }

    /**
     * Base64 URL编码
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL解码
     */
    private function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * 获取JWT密钥
     */
    private function get_jwt_secret()
    {
        $secret = get_option('xiaowu_user_jwt_secret');
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('xiaowu_user_jwt_secret', $secret);
        }
        return $secret;
    }

    /**
     * 限制登录尝试
     */
    public function check_login_attempts($username)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'login_attempts_' . md5($ip . $username);
        $attempts = get_transient($key);

        if ($attempts === false) {
            $attempts = 0;
        }

        $max_attempts = 5;
        $lockout_duration = 15 * 60; // 15分钟

        if ($attempts >= $max_attempts) {
            return array(
                'allowed' => false,
                'message' => '登录尝试次数过多，请15分钟后再试',
                'remaining' => 0
            );
        }

        return array(
            'allowed' => true,
            'remaining' => $max_attempts - $attempts
        );
    }

    /**
     * 记录登录失败
     */
    public function record_login_failure($username)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'login_attempts_' . md5($ip . $username);
        $attempts = get_transient($key);

        if ($attempts === false) {
            $attempts = 0;
        }

        $attempts++;
        set_transient($key, $attempts, 15 * 60); // 15分钟过期
    }

    /**
     * 清除登录尝试记录
     */
    public function clear_login_attempts($username)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'login_attempts_' . md5($ip . $username);
        delete_transient($key);
    }

    /**
     * 双因素认证 - 发送验证码
     */
    public function send_2fa_code($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // 生成6位数字验证码
        $code = wp_rand(100000, 999999);

        // 保存验证码(5分钟有效)
        set_transient('2fa_code_' . $user_id, $code, 5 * 60);

        // 发送邮件
        $subject = '[' . get_bloginfo('name') . '] 登录验证码';
        $message = "您的登录验证码是: {$code}\n\n验证码将在5分钟后失效。";

        return wp_mail($user->user_email, $subject, $message);
    }

    /**
     * 双因素认证 - 验证验证码
     */
    public function verify_2fa_code($user_id, $code)
    {
        $stored_code = get_transient('2fa_code_' . $user_id);

        if ($stored_code === false) {
            return array(
                'success' => false,
                'message' => '验证码已过期'
            );
        }

        if ($code != $stored_code) {
            return array(
                'success' => false,
                'message' => '验证码错误'
            );
        }

        // 验证成功，删除验证码
        delete_transient('2fa_code_' . $user_id);

        return array(
            'success' => true,
            'message' => '验证成功'
        );
    }
}
