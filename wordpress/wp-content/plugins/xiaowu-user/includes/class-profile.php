<?php
/**
 * 用户资料类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_User_Profile
{
    /**
     * 获取用户资料
     */
    public function get($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return array(
                'success' => false,
                'message' => '用户不存在'
            );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';

        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$profile) {
            // 如果资料不存在，创建默认资料
            $wpdb->insert($table_name, array(
                'user_id' => $user_id,
                'avatar_url' => get_avatar_url($user_id)
            ));
            $profile = array('user_id' => $user_id);
        }

        // 获取用户等级
        $user_level = new Xiaowu_User_Level();
        $level_data = $user_level->get($user_id);

        // 获取关注统计
        $follow = new Xiaowu_User_Follow();
        $follow_stats = $follow->get_stats($user_id);

        $data = array(
            'user_id' => $user_id,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'avatar' => $profile['avatar_url'] ?? get_avatar_url($user_id),
            'bio' => $profile['bio'] ?? '',
            'location' => $profile['location'] ?? '',
            'website' => $profile['website'] ?? '',
            'birthday' => $profile['birthday'] ?? '',
            'gender' => $profile['gender'] ?? '',
            'phone' => $profile['phone'] ?? '',
            'wechat' => $profile['wechat'] ?? '',
            'qq' => $profile['qq'] ?? '',
            'github' => $profile['github'] ?? '',
            'registered' => $user->user_registered,
            'level' => $level_data['data']['level'] ?? 1,
            'experience' => $level_data['data']['experience'] ?? 0,
            'points' => $level_data['data']['points'] ?? 0,
            'following_count' => $follow_stats['following_count'],
            'followers_count' => $follow_stats['followers_count']
        );

        return array(
            'success' => true,
            'data' => $data
        );
    }

    /**
     * 更新用户资料
     */
    public function update($user_id, $data)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return array(
                'success' => false,
                'message' => '用户不存在'
            );
        }

        // 更新WordPress用户信息
        if (isset($data['display_name'])) {
            wp_update_user(array(
                'ID' => $user_id,
                'display_name' => $data['display_name']
            ));
        }

        // 更新扩展资料
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';

        $profile_data = array();
        $allowed_fields = array('bio', 'location', 'website', 'birthday', 'gender', 'phone', 'wechat', 'qq', 'github');

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $profile_data[$field] = $data[$field];
            }
        }

        if (!empty($profile_data)) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE user_id = %d",
                $user_id
            ));

            if ($exists) {
                $wpdb->update($table_name, $profile_data, array('user_id' => $user_id));
            } else {
                $profile_data['user_id'] = $user_id;
                $wpdb->insert($table_name, $profile_data);
            }
        }

        return array(
            'success' => true,
            'message' => '资料更新成功'
        );
    }

    /**
     * 上传头像
     */
    public function upload_avatar($user_id, $file)
    {
        // 验证文件类型
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            return array(
                'success' => false,
                'message' => '不支持的文件类型，只允许JPG, PNG, GIF格式'
            );
        }

        // 验证文件大小(最大2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return array(
                'success' => false,
                'message' => '文件大小超过限制(最大2MB)'
            );
        }

        // 使用WordPress媒体上传
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            return array(
                'success' => false,
                'message' => $attachment_id->get_error_message()
            );
        }

        // 获取图片URL
        $avatar_url = wp_get_attachment_url($attachment_id);

        // 更新用户资料
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';

        $wpdb->update(
            $table_name,
            array('avatar_url' => $avatar_url),
            array('user_id' => $user_id)
        );

        return array(
            'success' => true,
            'message' => '头像上传成功',
            'data' => array(
                'avatar_url' => $avatar_url,
                'attachment_id' => $attachment_id
            )
        );
    }

    /**
     * 更新自定义字段
     */
    public function update_custom_fields($user_id, $custom_fields)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';

        $wpdb->update(
            $table_name,
            array('custom_fields' => json_encode($custom_fields)),
            array('user_id' => $user_id)
        );

        return array(
            'success' => true,
            'message' => '自定义字段更新成功'
        );
    }

    /**
     * 获取自定义字段
     */
    public function get_custom_fields($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_profiles';

        $custom_fields = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_fields FROM $table_name WHERE user_id = %d",
            $user_id
        ));

        return json_decode($custom_fields, true) ?: array();
    }
}
