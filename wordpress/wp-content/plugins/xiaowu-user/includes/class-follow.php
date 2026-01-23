<?php
/**
 * 关注系统类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_User_Follow
{
    /**
     * 关注用户
     */
    public function follow($follower_id, $following_id)
    {
        // 验证
        if ($follower_id === $following_id) {
            return array(
                'success' => false,
                'message' => '不能关注自己'
            );
        }

        // 检查用户是否存在
        $following_user = get_userdata($following_id);
        if (!$following_user) {
            return array(
                'success' => false,
                'message' => '用户不存在'
            );
        }

        // 检查是否已关注
        if ($this->is_following($follower_id, $following_id)) {
            return array(
                'success' => false,
                'message' => '已经关注该用户'
            );
        }

        // 插入关注记录
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        $result = $wpdb->insert($table_name, array(
            'follower_id' => $follower_id,
            'following_id' => $following_id,
            'created_at' => current_time('mysql')
        ));

        if (!$result) {
            return array(
                'success' => false,
                'message' => '关注失败'
            );
        }

        // 发送通知
        $this->send_follow_notification($following_id, $follower_id);

        // 记录活动
        $this->log_activity($follower_id, 'user_followed', $following_id);

        // 奖励经验值
        $user_level = new Xiaowu_User_Level();
        $user_level->add_experience($follower_id, 5, 'follow_user');

        return array(
            'success' => true,
            'message' => '关注成功'
        );
    }

    /**
     * 取消关注
     */
    public function unfollow($follower_id, $following_id)
    {
        if (!$this->is_following($follower_id, $following_id)) {
            return array(
                'success' => false,
                'message' => '未关注该用户'
            );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        $deleted = $wpdb->delete($table_name, array(
            'follower_id' => $follower_id,
            'following_id' => $following_id
        ));

        if (!$deleted) {
            return array(
                'success' => false,
                'message' => '取消关注失败'
            );
        }

        // 记录活动
        $this->log_activity($follower_id, 'user_unfollowed', $following_id);

        return array(
            'success' => true,
            'message' => '已取消关注'
        );
    }

    /**
     * 检查是否关注
     */
    public function is_following($follower_id, $following_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE follower_id = %d AND following_id = %d",
            $follower_id,
            $following_id
        ));

        return $count > 0;
    }

    /**
     * 获取关注列表
     */
    public function get_following($user_id, $page = 1, $per_page = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT f.following_id, f.created_at, u.user_login, u.display_name
             FROM $table_name f
             JOIN {$wpdb->users} u ON f.following_id = u.ID
             WHERE f.follower_id = %d
             ORDER BY f.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $following = array();
        foreach ($results as $row) {
            // 获取用户资料
            $profile_table = $wpdb->prefix . 'xiaowu_user_profiles';
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $row['following_id']
            ), ARRAY_A);

            $following[] = array(
                'user_id' => $row['following_id'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'avatar' => $profile['avatar_url'] ?? get_avatar_url($row['following_id']),
                'bio' => $profile['bio'] ?? '',
                'followed_at' => $row['created_at']
            );
        }

        return array(
            'success' => true,
            'data' => $following,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page
            )
        );
    }

    /**
     * 获取粉丝列表
     */
    public function get_followers($user_id, $page = 1, $per_page = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT f.follower_id, f.created_at, u.user_login, u.display_name
             FROM $table_name f
             JOIN {$wpdb->users} u ON f.follower_id = u.ID
             WHERE f.following_id = %d
             ORDER BY f.created_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $followers = array();
        foreach ($results as $row) {
            // 获取用户资料
            $profile_table = $wpdb->prefix . 'xiaowu_user_profiles';
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $row['follower_id']
            ), ARRAY_A);

            $followers[] = array(
                'user_id' => $row['follower_id'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'avatar' => $profile['avatar_url'] ?? get_avatar_url($row['follower_id']),
                'bio' => $profile['bio'] ?? '',
                'followed_at' => $row['created_at']
            );
        }

        return array(
            'success' => true,
            'data' => $followers,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page
            )
        );
    }

    /**
     * 获取关注统计
     */
    public function get_stats($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        // 关注数
        $following_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE follower_id = %d",
            $user_id
        ));

        // 粉丝数
        $followers_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE following_id = %d",
            $user_id
        ));

        return array(
            'following_count' => (int)$following_count,
            'followers_count' => (int)$followers_count
        );
    }

    /**
     * 获取互相关注的用户
     */
    public function get_mutual_following($user_id, $page = 1, $per_page = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';
        $offset = ($page - 1) * $per_page;

        // 查询互相关注
        $sql = "
            SELECT f1.following_id AS user_id, u.user_login, u.display_name
            FROM $table_name f1
            JOIN $table_name f2 ON f1.following_id = f2.follower_id
                                AND f1.follower_id = f2.following_id
            JOIN {$wpdb->users} u ON f1.following_id = u.ID
            WHERE f1.follower_id = %d
            LIMIT %d OFFSET %d
        ";

        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $user_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $mutual = array();
        foreach ($results as $row) {
            // 获取用户资料
            $profile_table = $wpdb->prefix . 'xiaowu_user_profiles';
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $row['user_id']
            ), ARRAY_A);

            $mutual[] = array(
                'user_id' => $row['user_id'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'avatar' => $profile['avatar_url'] ?? get_avatar_url($row['user_id']),
                'bio' => $profile['bio'] ?? ''
            );
        }

        return array(
            'success' => true,
            'data' => $mutual,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page
            )
        );
    }

    /**
     * 批量检查关注状态
     */
    public function check_following_batch($follower_id, $user_ids)
    {
        if (empty($user_ids) || !is_array($user_ids)) {
            return array(
                'success' => false,
                'message' => '无效的用户ID列表'
            );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT following_id FROM $table_name
             WHERE follower_id = %d AND following_id IN ($placeholders)",
            array_merge(array($follower_id), $user_ids)
        );

        $results = $wpdb->get_col($query);

        $status = array();
        foreach ($user_ids as $user_id) {
            $status[$user_id] = in_array($user_id, $results);
        }

        return array(
            'success' => true,
            'data' => $status
        );
    }

    /**
     * 获取推荐关注
     */
    public function get_recommended($user_id, $limit = 10)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        // 获取已关注的用户ID
        $following_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT following_id FROM $table_name WHERE follower_id = %d",
            $user_id
        ));

        $following_ids[] = $user_id; // 排除自己

        // 查找关注者的关注（二度关注）
        $placeholders = implode(',', array_fill(0, count($following_ids), '%d'));
        $sql = "
            SELECT f.following_id, COUNT(*) AS common_count, u.user_login, u.display_name
            FROM $table_name f
            JOIN {$wpdb->users} u ON f.following_id = u.ID
            WHERE f.follower_id IN (
                SELECT following_id FROM $table_name WHERE follower_id = %d
            )
            AND f.following_id NOT IN ($placeholders)
            GROUP BY f.following_id
            ORDER BY common_count DESC
            LIMIT %d
        ";

        $params = array_merge(array($user_id), $following_ids, array($limit));
        $results = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        $recommended = array();
        foreach ($results as $row) {
            // 获取用户资料
            $profile_table = $wpdb->prefix . 'xiaowu_user_profiles';
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $profile_table WHERE user_id = %d",
                $row['following_id']
            ), ARRAY_A);

            // 获取粉丝数
            $followers_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE following_id = %d",
                $row['following_id']
            ));

            $recommended[] = array(
                'user_id' => $row['following_id'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'avatar' => $profile['avatar_url'] ?? get_avatar_url($row['following_id']),
                'bio' => $profile['bio'] ?? '',
                'followers_count' => (int)$followers_count,
                'common_following' => (int)$row['common_count']
            );
        }

        return array(
            'success' => true,
            'data' => $recommended
        );
    }

    /**
     * 获取最新关注的用户动态
     */
    public function get_following_activities($user_id, $page = 1, $per_page = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';
        $activities_table = $wpdb->prefix . 'xiaowu_user_activities';
        $offset = ($page - 1) * $per_page;

        $sql = "
            SELECT a.*, u.user_login, u.display_name
            FROM $activities_table a
            JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE a.user_id IN (
                SELECT following_id FROM $table_name WHERE follower_id = %d
            )
            ORDER BY a.created_at DESC
            LIMIT %d OFFSET %d
        ";

        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $user_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $activities = array();
        foreach ($results as $row) {
            $activities[] = array(
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'avatar' => get_avatar_url($row['user_id']),
                'action' => $row['action'],
                'object_type' => $row['object_type'],
                'object_id' => $row['object_id'],
                'metadata' => json_decode($row['metadata'], true),
                'created_at' => $row['created_at']
            );
        }

        return array(
            'success' => true,
            'data' => $activities,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page
            )
        );
    }

    /**
     * 发送关注通知
     */
    private function send_follow_notification($to_user_id, $follower_id)
    {
        $to_user = get_userdata($to_user_id);
        $follower = get_userdata($follower_id);

        if (!$to_user || !$follower) {
            return;
        }

        // 检查用户是否开启了关注通知
        $notification_enabled = get_user_meta($to_user_id, 'follow_notification_enabled', true);
        if ($notification_enabled === '0') {
            return;
        }

        $subject = '[' . get_bloginfo('name') . '] 您有新的粉丝';
        $message = "您好 {$to_user->display_name},\n\n";
        $message .= "{$follower->display_name} 关注了您。\n\n";
        $message .= "查看Ta的主页: " . home_url('/user/' . $follower->user_login) . "\n\n";
        $message .= "祝好!\n";
        $message .= get_bloginfo('name') . " 团队";

        wp_mail($to_user->user_email, $subject, $message);
    }

    /**
     * 记录活动
     */
    private function log_activity($user_id, $action, $target_user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_activities';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'action' => $action,
            'object_type' => 'user',
            'object_id' => $target_user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * 移除粉丝
     */
    public function remove_follower($user_id, $follower_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        $deleted = $wpdb->delete($table_name, array(
            'follower_id' => $follower_id,
            'following_id' => $user_id
        ));

        if (!$deleted) {
            return array(
                'success' => false,
                'message' => '移除失败'
            );
        }

        return array(
            'success' => true,
            'message' => '已移除粉丝'
        );
    }
}
