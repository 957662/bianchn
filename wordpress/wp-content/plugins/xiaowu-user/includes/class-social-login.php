<?php
/**
 * 社交登录类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Social_Login
{
    /**
     * 支持的社交平台
     */
    private $providers = array('wechat', 'qq', 'github', 'google');

    /**
     * 社交登录认证
     */
    public function authenticate($provider, $code, $state = '')
    {
        if (!in_array($provider, $this->providers)) {
            return array(
                'success' => false,
                'message' => '不支持的登录方式'
            );
        }

        // 验证state防止CSRF攻击
        if (!empty($state) && !$this->verify_state($state)) {
            return array(
                'success' => false,
                'message' => '无效的state参数'
            );
        }

        // 根据平台调用相应的认证方法
        $method = "authenticate_{$provider}";
        if (method_exists($this, $method)) {
            return $this->$method($code);
        }

        return array(
            'success' => false,
            'message' => '登录方式暂未实现'
        );
    }

    /**
     * 微信登录
     */
    private function authenticate_wechat($code)
    {
        $app_id = get_option('xiaowu_user_wechat_app_id');
        $app_secret = get_option('xiaowu_user_wechat_app_secret');

        if (empty($app_id) || empty($app_secret)) {
            return array(
                'success' => false,
                'message' => '微信登录未配置'
            );
        }

        // 获取access_token
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$app_id}&secret={$app_secret}&code={$code}&grant_type=authorization_code";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => '获取微信access_token失败'
            );
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['errcode'])) {
            return array(
                'success' => false,
                'message' => '微信登录失败: ' . ($data['errmsg'] ?? '')
            );
        }

        $access_token = $data['access_token'];
        $openid = $data['openid'];

        // 获取用户信息
        $user_info_url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}";
        $user_response = wp_remote_get($user_info_url);
        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        return $this->process_social_login('wechat', $openid, $user_data, $data);
    }

    /**
     * QQ登录
     */
    private function authenticate_qq($code)
    {
        $app_id = get_option('xiaowu_user_qq_app_id');
        $app_key = get_option('xiaowu_user_qq_app_key');
        $redirect_uri = get_option('xiaowu_user_qq_redirect_uri');

        if (empty($app_id) || empty($app_key)) {
            return array(
                'success' => false,
                'message' => 'QQ登录未配置'
            );
        }

        // 获取access_token
        $url = "https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&client_id={$app_id}&client_secret={$app_key}&code={$code}&redirect_uri={$redirect_uri}";
        $response = wp_remote_get($url);
        $body = wp_remote_retrieve_body($response);

        parse_str($body, $params);
        if (!isset($params['access_token'])) {
            return array(
                'success' => false,
                'message' => 'QQ登录失败'
            );
        }

        $access_token = $params['access_token'];

        // 获取openid
        $openid_url = "https://graph.qq.com/oauth2.0/me?access_token={$access_token}";
        $openid_response = wp_remote_get($openid_url);
        $openid_body = wp_remote_retrieve_body($openid_response);

        preg_match('/openid":"([^"]+)"/', $openid_body, $matches);
        $openid = $matches[1] ?? '';

        if (empty($openid)) {
            return array(
                'success' => false,
                'message' => '获取QQ openid失败'
            );
        }

        // 获取用户信息
        $user_info_url = "https://graph.qq.com/user/get_user_info?access_token={$access_token}&oauth_consumer_key={$app_id}&openid={$openid}";
        $user_response = wp_remote_get($user_info_url);
        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        return $this->process_social_login('qq', $openid, $user_data, array('access_token' => $access_token));
    }

    /**
     * GitHub登录
     */
    private function authenticate_github($code)
    {
        $client_id = get_option('xiaowu_user_github_client_id');
        $client_secret = get_option('xiaowu_user_github_client_secret');

        if (empty($client_id) || empty($client_secret)) {
            return array(
                'success' => false,
                'message' => 'GitHub登录未配置'
            );
        }

        // 获取access_token
        $url = "https://github.com/login/oauth/access_token";
        $response = wp_remote_post($url, array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code
            ),
            'headers' => array('Accept' => 'application/json')
        ));

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['access_token'])) {
            return array(
                'success' => false,
                'message' => 'GitHub登录失败'
            );
        }

        $access_token = $data['access_token'];

        // 获取用户信息
        $user_response = wp_remote_get('https://api.github.com/user', array(
            'headers' => array(
                'Authorization' => 'token ' . $access_token,
                'User-Agent' => 'Xiaowu-Blog'
            )
        ));

        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        return $this->process_social_login('github', $user_data['id'], $user_data, $data);
    }

    /**
     * Google登录
     */
    private function authenticate_google($code)
    {
        $client_id = get_option('xiaowu_user_google_client_id');
        $client_secret = get_option('xiaowu_user_google_client_secret');
        $redirect_uri = get_option('xiaowu_user_google_redirect_uri');

        if (empty($client_id) || empty($client_secret)) {
            return array(
                'success' => false,
                'message' => 'Google登录未配置'
            );
        }

        // 获取access_token
        $url = "https://oauth2.googleapis.com/token";
        $response = wp_remote_post($url, array(
            'body' => array(
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirect_uri
            )
        ));

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($data['access_token'])) {
            return array(
                'success' => false,
                'message' => 'Google登录失败'
            );
        }

        $access_token = $data['access_token'];

        // 获取用户信息
        $user_response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token)
        ));

        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);

        return $this->process_social_login('google', $user_data['id'], $user_data, $data);
    }

    /**
     * 处理社交登录
     */
    private function process_social_login($provider, $provider_user_id, $user_data, $token_data)
    {
        // 查找是否已绑定账号
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_social_accounts';

        $social_account = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE provider = %s AND provider_user_id = %s",
            $provider,
            $provider_user_id
        ));

        if ($social_account) {
            // 已绑定，直接登录
            $user = get_userdata($social_account->user_id);

            if (!$user) {
                return array(
                    'success' => false,
                    'message' => '关联的用户不存在'
                );
            }

            // 更新token
            $wpdb->update(
                $table_name,
                array(
                    'access_token' => $token_data['access_token'] ?? '',
                    'refresh_token' => $token_data['refresh_token'] ?? '',
                    'expires_at' => isset($token_data['expires_in']) ? date('Y-m-d H:i:s', time() + $token_data['expires_in']) : null
                ),
                array('id' => $social_account->id)
            );

            // 登录用户
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);

            return array(
                'success' => true,
                'message' => '登录成功',
                'data' => array(
                    'user_id' => $user->ID,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name
                )
            );
        } else {
            // 未绑定，创建新用户
            return $this->create_user_from_social($provider, $provider_user_id, $user_data, $token_data);
        }
    }

    /**
     * 从社交账号创建用户
     */
    private function create_user_from_social($provider, $provider_user_id, $user_data, $token_data)
    {
        // 生成用户名
        $username = $this->generate_username($provider, $user_data);

        // 生成随机密码
        $password = wp_generate_password(12, true, true);

        // 提取邮箱
        $email = $user_data['email'] ?? '';

        // 如果邮箱为空或已存在，生成临时邮箱
        if (empty($email) || email_exists($email)) {
            $email = $username . '@' . $provider . '.local';
        }

        // 创建用户
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return array(
                'success' => false,
                'message' => '创建用户失败: ' . $user_id->get_error_message()
            );
        }

        // 更新显示名称和头像
        $display_name = $this->extract_display_name($provider, $user_data);
        $avatar_url = $this->extract_avatar_url($provider, $user_data);

        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name
        ));

        // 创建用户资料
        global $wpdb;
        $profile_table = $wpdb->prefix . 'xiaowu_user_profiles';
        $wpdb->insert($profile_table, array(
            'user_id' => $user_id,
            'avatar_url' => $avatar_url
        ));

        // 绑定社交账号
        $social_table = $wpdb->prefix . 'xiaowu_social_accounts';
        $wpdb->insert($social_table, array(
            'user_id' => $user_id,
            'provider' => $provider,
            'provider_user_id' => $provider_user_id,
            'access_token' => $token_data['access_token'] ?? '',
            'refresh_token' => $token_data['refresh_token'] ?? '',
            'expires_at' => isset($token_data['expires_in']) ? date('Y-m-d H:i:s', time() + $token_data['expires_in']) : null
        ));

        // 邮箱已验证
        update_user_meta($user_id, 'email_verified', true);

        // 初始化用户等级
        $level_table = $wpdb->prefix . 'xiaowu_user_levels';
        $wpdb->insert($level_table, array(
            'user_id' => $user_id,
            'level' => 1,
            'experience' => 0,
            'points' => 0
        ));

        // 登录用户
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return array(
            'success' => true,
            'message' => '注册并登录成功',
            'data' => array(
                'user_id' => $user_id,
                'username' => $username,
                'display_name' => $display_name
            )
        );
    }

    /**
     * 生成用户名
     */
    private function generate_username($provider, $user_data)
    {
        $base_username = $provider . '_user_' . wp_rand(1000, 9999);

        if (isset($user_data['login'])) {
            $base_username = sanitize_user($user_data['login']);
        } elseif (isset($user_data['nickname'])) {
            $base_username = sanitize_user($user_data['nickname']);
        } elseif (isset($user_data['name'])) {
            $base_username = sanitize_user($user_data['name']);
        }

        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * 提取显示名称
     */
    private function extract_display_name($provider, $user_data)
    {
        if (isset($user_data['nickname'])) {
            return $user_data['nickname'];
        } elseif (isset($user_data['name'])) {
            return $user_data['name'];
        } elseif (isset($user_data['login'])) {
            return $user_data['login'];
        }
        return $provider . ' User';
    }

    /**
     * 提取头像URL
     */
    private function extract_avatar_url($provider, $user_data)
    {
        $fields = array('headimgurl', 'figureurl_qq_2', 'avatar_url', 'picture');

        foreach ($fields as $field) {
            if (isset($user_data[$field]) && !empty($user_data[$field])) {
                return $user_data[$field];
            }
        }

        return '';
    }

    /**
     * 生成state参数
     */
    public function generate_state()
    {
        $state = wp_generate_password(32, false);
        set_transient('social_login_state_' . $state, true, 600); // 10分钟有效
        return $state;
    }

    /**
     * 验证state参数
     */
    private function verify_state($state)
    {
        $valid = get_transient('social_login_state_' . $state);
        if ($valid) {
            delete_transient('social_login_state_' . $state);
            return true;
        }
        return false;
    }

    /**
     * 解绑社交账号
     */
    public function unbind($user_id, $provider)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_social_accounts';

        $deleted = $wpdb->delete($table_name, array(
            'user_id' => $user_id,
            'provider' => $provider
        ));

        if ($deleted) {
            return array(
                'success' => true,
                'message' => '解绑成功'
            );
        } else {
            return array(
                'success' => false,
                'message' => '解绑失败'
            );
        }
    }

    /**
     * 获取用户绑定的社交账号
     */
    public function get_bindings($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_social_accounts';

        $bindings = $wpdb->get_results($wpdb->prepare(
            "SELECT provider, provider_user_id, created_at FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        return array(
            'success' => true,
            'data' => $bindings
        );
    }
}
