<?php
/**
 * 搜索索引器类
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Search_Indexer
{
    private $index_table;

    public function __construct()
    {
        global $wpdb;
        $this->index_table = $wpdb->prefix . 'xiaowu_search_index';
    }

    /**
     * 创建索引
     */
    public function create_index()
    {
        // 数据库表已在插件激活时创建
        return array(
            'success' => true,
            'message' => '索引已创建'
        );
    }

    /**
     * 重建所有索引
     */
    public function reindex_all()
    {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => '权限不足'));
        }

        // 清空现有索引
        $this->clear_index();

        $stats = array(
            'posts' => 0,
            'comments' => 0,
            'users' => 0
        );

        // 索引文章
        if (get_option('xiaowu_search_index_posts', true)) {
            $stats['posts'] = $this->index_all_posts();
        }

        // 索引评论
        if (get_option('xiaowu_search_index_comments', true)) {
            $stats['comments'] = $this->index_all_comments();
        }

        // 索引用户
        if (get_option('xiaowu_search_index_users', true)) {
            $stats['users'] = $this->index_all_users();
        }

        wp_send_json_success(array(
            'message' => '索引重建完成',
            'stats' => $stats
        ));
    }

    /**
     * 清空索引
     */
    private function clear_index()
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->index_table}");
    }

    /**
     * 索引单篇文章
     */
    public function index_post($post_id, $post = null)
    {
        if (!$post) {
            $post = get_post($post_id);
        }

        if (!$post || $post->post_type !== 'post') {
            return;
        }

        // 只索引已发布的文章
        if ($post->post_status !== 'publish') {
            $this->delete_post_index($post_id);
            return;
        }

        global $wpdb;

        $author = get_user_by('ID', $post->post_author);
        $content = $this->extract_content($post->post_content);
        $excerpt = !empty($post->post_excerpt) ? $post->post_excerpt : wp_trim_words($content, 50);

        $metadata = json_encode(array(
            'categories' => wp_get_post_categories($post_id, array('fields' => 'names')),
            'tags' => wp_get_post_tags($post_id, array('fields' => 'names')),
            'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
            'views' => get_post_meta($post_id, 'views_count', true) ?: 0
        ));

        $data = array(
            'object_id' => $post_id,
            'object_type' => 'post',
            'title' => $post->post_title,
            'content' => $content,
            'excerpt' => $excerpt,
            'author_id' => $post->post_author,
            'author_name' => $author ? $author->display_name : '',
            'created_at' => $post->post_date,
            'modified_at' => $post->post_modified,
            'status' => $post->post_status,
            'metadata' => $metadata
        );

        // 检查是否已存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->index_table} WHERE object_id = %d AND object_type = 'post'",
            $post_id
        ));

        if ($existing) {
            $wpdb->update($this->index_table, $data, array('id' => $existing));
        } else {
            $wpdb->insert($this->index_table, $data);
        }
    }

    /**
     * 删除文章索引
     */
    public function delete_post_index($post_id)
    {
        global $wpdb;
        $wpdb->delete($this->index_table, array(
            'object_id' => $post_id,
            'object_type' => 'post'
        ));
    }

    /**
     * 索引所有文章
     */
    private function index_all_posts()
    {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $posts = get_posts($args);
        $count = 0;

        foreach ($posts as $post_id) {
            $this->index_post($post_id);
            $count++;
        }

        return $count;
    }

    /**
     * 索引单条评论
     */
    public function index_comment($comment_id, $comment = null)
    {
        if (!$comment) {
            $comment = get_comment($comment_id);
        }

        if (!$comment) {
            return;
        }

        // 只索引已批准的评论
        if ($comment->comment_approved !== '1') {
            $this->delete_comment_index($comment_id);
            return;
        }

        global $wpdb;

        $post = get_post($comment->comment_post_ID);
        $title = '评论: ' . ($post ? $post->post_title : '');

        $metadata = json_encode(array(
            'post_id' => $comment->comment_post_ID,
            'post_title' => $post ? $post->post_title : '',
            'parent_comment' => $comment->comment_parent
        ));

        $data = array(
            'object_id' => $comment_id,
            'object_type' => 'comment',
            'title' => $title,
            'content' => $comment->comment_content,
            'excerpt' => wp_trim_words($comment->comment_content, 20),
            'author_id' => $comment->user_id ?: null,
            'author_name' => $comment->comment_author,
            'created_at' => $comment->comment_date,
            'modified_at' => $comment->comment_date,
            'status' => 'approved',
            'metadata' => $metadata
        );

        // 检查是否已存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->index_table} WHERE object_id = %d AND object_type = 'comment'",
            $comment_id
        ));

        if ($existing) {
            $wpdb->update($this->index_table, $data, array('id' => $existing));
        } else {
            $wpdb->insert($this->index_table, $data);
        }
    }

    /**
     * 删除评论索引
     */
    public function delete_comment_index($comment_id)
    {
        global $wpdb;
        $wpdb->delete($this->index_table, array(
            'object_id' => $comment_id,
            'object_type' => 'comment'
        ));
    }

    /**
     * 索引所有评论
     */
    private function index_all_comments()
    {
        $args = array(
            'status' => 'approve',
            'number' => 0
        );

        $comments = get_comments($args);
        $count = 0;

        foreach ($comments as $comment) {
            $this->index_comment($comment->comment_ID, $comment);
            $count++;
        }

        return $count;
    }

    /**
     * 索引单个用户
     */
    public function index_user($user_id)
    {
        $user = get_user_by('ID', $user_id);

        if (!$user) {
            return;
        }

        global $wpdb;

        $bio = get_user_meta($user_id, 'description', true);
        $content = $user->display_name . ' ' . $user->user_login . ' ' . $user->user_email . ' ' . $bio;

        $metadata = json_encode(array(
            'username' => $user->user_login,
            'email' => $user->user_email,
            'url' => $user->user_url,
            'posts_count' => count_user_posts($user_id),
            'avatar' => get_avatar_url($user_id)
        ));

        $data = array(
            'object_id' => $user_id,
            'object_type' => 'user',
            'title' => $user->display_name,
            'content' => $content,
            'excerpt' => $bio,
            'author_id' => null,
            'author_name' => null,
            'created_at' => $user->user_registered,
            'modified_at' => $user->user_registered,
            'status' => 'active',
            'metadata' => $metadata
        );

        // 检查是否已存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->index_table} WHERE object_id = %d AND object_type = 'user'",
            $user_id
        ));

        if ($existing) {
            $wpdb->update($this->index_table, $data, array('id' => $existing));
        } else {
            $wpdb->insert($this->index_table, $data);
        }
    }

    /**
     * 索引所有用户
     */
    private function index_all_users()
    {
        $users = get_users(array('fields' => 'ID'));
        $count = 0;

        foreach ($users as $user_id) {
            $this->index_user($user_id);
            $count++;
        }

        return $count;
    }

    /**
     * 提取内容
     */
    private function extract_content($content)
    {
        // 移除短代码
        $content = strip_shortcodes($content);

        // 移除HTML标签
        $content = wp_strip_all_tags($content);

        // 移除多余空格和换行
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }

    /**
     * 优化索引
     */
    public function optimize_index()
    {
        global $wpdb;

        // 删除已删除对象的索引
        $this->clean_orphaned_indexes();

        // 优化表
        $wpdb->query("OPTIMIZE TABLE {$this->index_table}");

        return array(
            'success' => true,
            'message' => '索引已优化'
        );
    }

    /**
     * 清理孤立索引
     */
    private function clean_orphaned_indexes()
    {
        global $wpdb;

        // 清理已删除的文章索引
        $wpdb->query(
            "DELETE si FROM {$this->index_table} si
             LEFT JOIN {$wpdb->posts} p ON si.object_id = p.ID
             WHERE si.object_type = 'post' AND p.ID IS NULL"
        );

        // 清理已删除的评论索引
        $wpdb->query(
            "DELETE si FROM {$this->index_table} si
             LEFT JOIN {$wpdb->comments} c ON si.object_id = c.comment_ID
             WHERE si.object_type = 'comment' AND c.comment_ID IS NULL"
        );

        // 清理已删除的用户索引
        $wpdb->query(
            "DELETE si FROM {$this->index_table} si
             LEFT JOIN {$wpdb->users} u ON si.object_id = u.ID
             WHERE si.object_type = 'user' AND u.ID IS NULL"
        );
    }

    /**
     * 获取索引统计
     */
    public function get_stats()
    {
        global $wpdb;

        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->index_table}"),
            'posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->index_table} WHERE object_type = 'post'"),
            'comments' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->index_table} WHERE object_type = 'comment'"),
            'users' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->index_table} WHERE object_type = 'user'"),
            'last_indexed' => $wpdb->get_var("SELECT MAX(modified_at) FROM {$this->index_table}")
        );

        return array(
            'success' => true,
            'data' => $stats
        );
    }

    /**
     * 批量索引（用于AJAX分批处理）
     */
    public function batch_index($type, $offset = 0, $limit = 50)
    {
        $count = 0;

        switch ($type) {
            case 'posts':
                $args = array(
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => $limit,
                    'offset' => $offset,
                    'fields' => 'ids'
                );

                $posts = get_posts($args);
                foreach ($posts as $post_id) {
                    $this->index_post($post_id);
                    $count++;
                }
                break;

            case 'comments':
                $args = array(
                    'status' => 'approve',
                    'number' => $limit,
                    'offset' => $offset
                );

                $comments = get_comments($args);
                foreach ($comments as $comment) {
                    $this->index_comment($comment->comment_ID, $comment);
                    $count++;
                }
                break;

            case 'users':
                $args = array(
                    'fields' => 'ID',
                    'number' => $limit,
                    'offset' => $offset
                );

                $users = get_users($args);
                foreach ($users as $user_id) {
                    $this->index_user($user_id);
                    $count++;
                }
                break;
        }

        return array(
            'success' => true,
            'data' => array(
                'indexed' => $count,
                'has_more' => $count === $limit
            )
        );
    }
}
