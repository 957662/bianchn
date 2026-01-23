<?php
/**
 * 私信系统类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Private_Message
{
    /**
     * 发送私信
     */
    public function send($from_user_id, $to_user_id, $content, $parent_id = 0)
    {
        // 验证输入
        if (empty($content)) {
            return array(
                'success' => false,
                'message' => '消息内容不能为空'
            );
        }

        if ($from_user_id === $to_user_id) {
            return array(
                'success' => false,
                'message' => '不能给自己发送消息'
            );
        }

        // 检查接收用户是否存在
        $to_user = get_userdata($to_user_id);
        if (!$to_user) {
            return array(
                'success' => false,
                'message' => '接收用户不存在'
            );
        }

        // 检查是否被对方屏蔽
        if ($this->is_blocked($from_user_id, $to_user_id)) {
            return array(
                'success' => false,
                'message' => '无法发送消息'
            );
        }

        // 检查发送频率限制
        if (!$this->check_rate_limit($from_user_id)) {
            return array(
                'success' => false,
                'message' => '发送过于频繁，请稍后再试'
            );
        }

        // 内容过滤和验证
        $content = wp_kses_post($content);
        if (strlen($content) > 5000) {
            return array(
                'success' => false,
                'message' => '消息内容过长(最多5000字符)'
            );
        }

        // 插入消息
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';

        $result = $wpdb->insert($table_name, array(
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'content' => $content,
            'parent_id' => $parent_id,
            'is_read' => 0,
            'created_at' => current_time('mysql')
        ));

        if (!$result) {
            return array(
                'success' => false,
                'message' => '发送失败'
            );
        }

        $message_id = $wpdb->insert_id;

        // 发送通知
        $this->send_notification($to_user_id, $from_user_id, $message_id);

        // 记录活动
        $this->log_activity($from_user_id, 'message_sent', $message_id, $to_user_id);

        return array(
            'success' => true,
            'message' => '发送成功',
            'data' => array(
                'message_id' => $message_id
            )
        );
    }

    /**
     * 获取对话列表
     */
    public function get_conversations($user_id, $page = 1, $per_page = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';
        $offset = ($page - 1) * $per_page;

        // 获取最近的对话
        $sql = "
            SELECT
                CASE
                    WHEN from_user_id = %d THEN to_user_id
                    ELSE from_user_id
                END AS other_user_id,
                MAX(created_at) AS last_message_time,
                SUM(CASE WHEN to_user_id = %d AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
            FROM $table_name
            WHERE from_user_id = %d OR to_user_id = %d
            GROUP BY other_user_id
            ORDER BY last_message_time DESC
            LIMIT %d OFFSET %d
        ";

        $results = $wpdb->get_results($wpdb->prepare(
            $sql,
            $user_id,
            $user_id,
            $user_id,
            $user_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $conversations = array();
        foreach ($results as $row) {
            $other_user = get_userdata($row['other_user_id']);
            if (!$other_user) {
                continue;
            }

            // 获取最后一条消息
            $last_message = $this->get_last_message($user_id, $row['other_user_id']);

            $conversations[] = array(
                'user_id' => $row['other_user_id'],
                'username' => $other_user->user_login,
                'display_name' => $other_user->display_name,
                'avatar' => get_avatar_url($row['other_user_id']),
                'last_message' => $last_message,
                'last_message_time' => $row['last_message_time'],
                'unread_count' => (int)$row['unread_count']
            );
        }

        return array(
            'success' => true,
            'data' => $conversations,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page
            )
        );
    }

    /**
     * 获取与某用户的消息列表
     */
    public function get_messages($user_id, $other_user_id, $page = 1, $per_page = 50)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE (from_user_id = %d AND to_user_id = %d)
                OR (from_user_id = %d AND to_user_id = %d)
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $other_user_id,
            $other_user_id,
            $user_id,
            $per_page,
            $offset
        ), ARRAY_A);

        $messages = array();
        foreach ($results as $row) {
            $from_user = get_userdata($row['from_user_id']);

            $messages[] = array(
                'id' => $row['id'],
                'from_user_id' => $row['from_user_id'],
                'from_username' => $from_user->user_login,
                'from_display_name' => $from_user->display_name,
                'from_avatar' => get_avatar_url($row['from_user_id']),
                'content' => $row['content'],
                'is_read' => (bool)$row['is_read'],
                'created_at' => $row['created_at'],
                'is_mine' => $row['from_user_id'] === $user_id
            );
        }

        // 标记为已读
        $this->mark_as_read($user_id, $other_user_id);

        return array(
            'success' => true,
            'data' => array_reverse($messages),
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page
            )
        );
    }

    /**
     * 标记消息为已读
     */
    public function mark_as_read($user_id, $from_user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';

        $wpdb->update(
            $table_name,
            array('is_read' => 1),
            array(
                'to_user_id' => $user_id,
                'from_user_id' => $from_user_id
            )
        );

        return array(
            'success' => true,
            'message' => '已标记为已读'
        );
    }

    /**
     * 删除消息
     */
    public function delete($user_id, $message_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';

        // 检查消息是否属于当前用户
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $message_id
        ), ARRAY_A);

        if (!$message) {
            return array(
                'success' => false,
                'message' => '消息不存在'
            );
        }

        if ($message['from_user_id'] !== $user_id && $message['to_user_id'] !== $user_id) {
            return array(
                'success' => false,
                'message' => '无权删除此消息'
            );
        }

        // 软删除：标记为已删除
        $field = $message['from_user_id'] === $user_id ? 'deleted_by_sender' : 'deleted_by_receiver';

        $wpdb->update(
            $table_name,
            array($field => 1),
            array('id' => $message_id)
        );

        return array(
            'success' => true,
            'message' => '删除成功'
        );
    }

    /**
     * 获取未读消息数
     */
    public function get_unread_count($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE to_user_id = %d AND is_read = 0",
            $user_id
        ));

        return array(
            'success' => true,
            'data' => array(
                'unread_count' => (int)$count
            )
        );
    }

    /**
     * 获取最后一条消息
     */
    private function get_last_message($user_id, $other_user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';

        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE (from_user_id = %d AND to_user_id = %d)
                OR (from_user_id = %d AND to_user_id = %d)
             ORDER BY created_at DESC
             LIMIT 1",
            $user_id,
            $other_user_id,
            $other_user_id,
            $user_id
        ), ARRAY_A);

        if (!$message) {
            return '';
        }

        // 截断长消息
        $content = strip_tags($message['content']);
        if (strlen($content) > 50) {
            $content = mb_substr($content, 0, 50) . '...';
        }

        return $content;
    }

    /**
     * 检查是否被屏蔽
     */
    private function is_blocked($from_user_id, $to_user_id)
    {
        $blocked_users = get_user_meta($to_user_id, 'blocked_users', true);
        if (!is_array($blocked_users)) {
            return false;
        }

        return in_array($from_user_id, $blocked_users);
    }

    /**
     * 屏蔽用户
     */
    public function block_user($user_id, $blocked_user_id)
    {
        $blocked_users = get_user_meta($user_id, 'blocked_users', true);
        if (!is_array($blocked_users)) {
            $blocked_users = array();
        }

        if (in_array($blocked_user_id, $blocked_users)) {
            return array(
                'success' => false,
                'message' => '已经屏蔽该用户'
            );
        }

        $blocked_users[] = $blocked_user_id;
        update_user_meta($user_id, 'blocked_users', $blocked_users);

        return array(
            'success' => true,
            'message' => '屏蔽成功'
        );
    }

    /**
     * 取消屏蔽用户
     */
    public function unblock_user($user_id, $blocked_user_id)
    {
        $blocked_users = get_user_meta($user_id, 'blocked_users', true);
        if (!is_array($blocked_users)) {
            return array(
                'success' => false,
                'message' => '未屏蔽该用户'
            );
        }

        $key = array_search($blocked_user_id, $blocked_users);
        if ($key === false) {
            return array(
                'success' => false,
                'message' => '未屏蔽该用户'
            );
        }

        unset($blocked_users[$key]);
        update_user_meta($user_id, 'blocked_users', array_values($blocked_users));

        return array(
            'success' => true,
            'message' => '取消屏蔽成功'
        );
    }

    /**
     * 检查发送频率限制
     */
    private function check_rate_limit($user_id)
    {
        $key = 'message_rate_limit_' . $user_id;
        $sent_count = get_transient($key);

        if ($sent_count === false) {
            set_transient($key, 1, 60); // 1分钟
            return true;
        }

        if ($sent_count >= 10) {
            return false;
        }

        set_transient($key, $sent_count + 1, 60);
        return true;
    }

    /**
     * 发送通知
     */
    private function send_notification($to_user_id, $from_user_id, $message_id)
    {
        $to_user = get_userdata($to_user_id);
        $from_user = get_userdata($from_user_id);

        if (!$to_user || !$from_user) {
            return;
        }

        // 检查用户是否开启了私信通知
        $notification_enabled = get_user_meta($to_user_id, 'message_notification_enabled', true);
        if ($notification_enabled === '0') {
            return;
        }

        $subject = '[' . get_bloginfo('name') . '] 您收到了新私信';
        $message = "您好 {$to_user->display_name},\n\n";
        $message .= "{$from_user->display_name} 给您发送了新私信。\n\n";
        $message .= "请登录查看: " . home_url('/messages') . "\n\n";
        $message .= "祝好!\n";
        $message .= get_bloginfo('name') . " 团队";

        wp_mail($to_user->user_email, $subject, $message);
    }

    /**
     * 记录活动
     */
    private function log_activity($user_id, $action, $message_id, $other_user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_activities';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'action' => $action,
            'object_type' => 'message',
            'object_id' => $message_id,
            'metadata' => json_encode(array('other_user_id' => $other_user_id)),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * 搜索可发送消息的用户
     */
    public function search_users($search, $current_user_id, $limit = 10)
    {
        global $wpdb;

        $search = '%' . $wpdb->esc_like($search) . '%';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, user_login, display_name
             FROM {$wpdb->users}
             WHERE (user_login LIKE %s OR display_name LIKE %s)
               AND ID != %d
             LIMIT %d",
            $search,
            $search,
            $current_user_id,
            $limit
        ), ARRAY_A);

        $users = array();
        foreach ($results as $row) {
            $users[] = array(
                'user_id' => $row['ID'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'avatar' => get_avatar_url($row['ID'])
            );
        }

        return array(
            'success' => true,
            'data' => $users
        );
    }
}
