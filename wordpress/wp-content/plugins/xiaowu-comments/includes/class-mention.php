<?php
/**
 * @提及功能类
 *
 * @package Xiaowu_Comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Comment_Mention
{
    /**
     * 处理评论中的@提及
     */
    public function process($comment_id, $content)
    {
        if (!get_option('xiaowu_comments_mention_enabled', true)) {
            return;
        }

        // 提取所有@提及
        preg_match_all('/@([a-zA-Z0-9_\x{4e00}-\x{9fa5}]+)/u', $content, $matches);

        if (empty($matches[1])) {
            return;
        }

        $mentions = array();
        $valid_mentions = array();

        foreach ($matches[1] as $username) {
            // 检查用户是否存在
            $user = get_user_by('login', $username);

            if (!$user) {
                // 尝试通过昵称查找
                $user = $this->get_user_by_display_name($username);
            }

            if ($user) {
                $mentions[] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email
                );

                $valid_mentions[$username] = $user;
            }
        }

        // 保存提及信息
        if (!empty($mentions)) {
            update_comment_meta($comment_id, 'mentions', $mentions);
        }

        return $mentions;
    }

    /**
     * 格式化评论文本中的@提及
     */
    public function format($text, $comment = null)
    {
        if (!get_option('xiaowu_comments_mention_enabled', true)) {
            return $text;
        }

        // 替换@提及为链接
        $text = preg_replace_callback(
            '/@([a-zA-Z0-9_\x{4e00}-\x{9fa5}]+)/u',
            function($matches) {
                $username = $matches[1];
                $user = get_user_by('login', $username);

                if (!$user) {
                    $user = $this->get_user_by_display_name($username);
                }

                if ($user) {
                    $user_link = get_author_posts_url($user->ID);
                    return '<a href="' . esc_url($user_link) . '" class="xiaowu-mention" data-user-id="' . $user->ID . '">@' . esc_html($user->display_name) . '</a>';
                }

                return $matches[0];
            },
            $text
        );

        return $text;
    }

    /**
     * 通过显示名称查找用户
     */
    private function get_user_by_display_name($display_name)
    {
        global $wpdb;

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->users WHERE display_name = %s LIMIT 1",
            $display_name
        ));

        if ($user_id) {
            return get_user_by('id', $user_id);
        }

        return false;
    }

    /**
     * 获取可以@的用户列表
     */
    public function get_mentionable_users($search = '', $limit = 10)
    {
        $args = array(
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );

        if (!empty($search)) {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'display_name', 'user_nicename');
        }

        $users = get_users($args);

        $results = array();
        foreach ($users as $user) {
            $results[] = array(
                'id' => $user->ID,
                'username' => $user->user_login,
                'display_name' => $user->display_name,
                'avatar' => get_avatar_url($user->ID, array('size' => 32))
            );
        }

        return $results;
    }

    /**
     * 搜索评论者
     */
    public function search_commenters($search, $post_id = 0, $limit = 10)
    {
        global $wpdb;

        $where = "WHERE comment_approved = '1'";

        if ($post_id > 0) {
            $where .= $wpdb->prepare(" AND comment_post_ID = %d", $post_id);
        }

        if (!empty($search)) {
            $where .= $wpdb->prepare(" AND comment_author LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }

        $query = "
            SELECT DISTINCT comment_author, comment_author_email
            FROM $wpdb->comments
            $where
            ORDER BY comment_author
            LIMIT %d
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $limit));

        $commenters = array();
        foreach ($results as $result) {
            $commenters[] = array(
                'name' => $result->comment_author,
                'email' => $result->comment_author_email,
                'avatar' => get_avatar_url($result->comment_author_email, array('size' => 32))
            );
        }

        return $commenters;
    }

    /**
     * 获取用户被提及次数
     */
    public function get_mention_count($user_id)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
            FROM $wpdb->commentmeta
            WHERE meta_key = 'mentions'
            AND meta_value LIKE %s",
            '%\"id\":' . $user_id . '%'
        ));

        return intval($count);
    }

    /**
     * 获取用户的提及历史
     */
    public function get_user_mentions($user_id, $limit = 20)
    {
        global $wpdb;

        $comment_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT comment_id
            FROM $wpdb->commentmeta
            WHERE meta_key = 'mentions'
            AND meta_value LIKE %s
            ORDER BY meta_id DESC
            LIMIT %d",
            '%\"id\":' . $user_id . '%',
            $limit
        ));

        if (empty($comment_ids)) {
            return array();
        }

        $comments = get_comments(array(
            'comment__in' => $comment_ids,
            'status' => 'approve',
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC'
        ));

        return $comments;
    }

    /**
     * 渲染@提及自动完成
     */
    public function render_autocomplete()
    {
        if (!get_option('xiaowu_comments_mention_enabled', true)) {
            return '';
        }

        ob_start();
        ?>
        <div class="xiaowu-mention-autocomplete" style="display: none;">
            <ul class="xiaowu-mention-list"></ul>
        </div>
        <?php
        return ob_get_clean();
    }
}
