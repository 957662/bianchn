<?php
/**
 * 搜索引擎核心类
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Search_Engine
{
    private $min_word_length;
    private $enable_fuzzy;
    private $enable_synonyms;

    public function __construct()
    {
        $this->min_word_length = get_option('xiaowu_search_min_word_length', 2);
        $this->enable_fuzzy = get_option('xiaowu_search_enable_fuzzy', true);
        $this->enable_synonyms = get_option('xiaowu_search_enable_synonyms', true);
    }

    /**
     * 执行搜索
     */
    public function search($query, $args = array())
    {
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'type' => 'all', // all, post, comment, user
            'order_by' => 'relevance', // relevance, date, views
            'filters' => array(),
            'enable_ai' => get_option('xiaowu_search_enable_ai', true)
        );

        $args = wp_parse_args($args, $defaults);

        // 验证查询
        if (empty($query) || mb_strlen($query) < $this->min_word_length) {
            return array(
                'success' => false,
                'message' => '搜索关键词太短'
            );
        }

        // 记录搜索历史
        $this->log_search($query);

        // 预处理查询
        $processed_query = $this->preprocess_query($query);

        // 执行数据库搜索
        $results = $this->database_search($processed_query, $args);

        // AI增强搜索
        if ($args['enable_ai'] && class_exists('Xiaowu_AI_Search')) {
            $ai_search = new Xiaowu_AI_Search();
            $results = $ai_search->enhance_results($query, $results);
        }

        // 记录点击统计
        $this->update_popular_searches($query, count($results));

        return array(
            'success' => true,
            'data' => array(
                'query' => $query,
                'results' => $results,
                'total' => count($results),
                'page' => $args['page'],
                'per_page' => $args['per_page'],
                'total_pages' => ceil(count($results) / $args['per_page'])
            )
        );
    }

    /**
     * 预处理查询
     */
    private function preprocess_query($query)
    {
        // 去除多余空格
        $query = trim(preg_replace('/\s+/', ' ', $query));

        // 转换为小写
        $query = mb_strtolower($query);

        // 移除特殊字符
        $query = preg_replace('/[^\w\s\u4e00-\u9fa5]/u', '', $query);

        // 分词
        $terms = $this->tokenize($query);

        // 同义词扩展
        if ($this->enable_synonyms) {
            $terms = $this->expand_synonyms($terms);
        }

        return array(
            'original' => $query,
            'processed' => implode(' ', $terms),
            'terms' => $terms
        );
    }

    /**
     * 分词
     */
    private function tokenize($query)
    {
        // 简单分词：按空格分割
        $terms = explode(' ', $query);

        // 过滤短词
        $terms = array_filter($terms, function ($term) {
            return mb_strlen($term) >= $this->min_word_length;
        });

        // 中文分词（简单实现，实际项目可使用专业分词库）
        $chinese_terms = array();
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $query)) {
            // 提取中文字符
            preg_match_all('/[\x{4e00}-\x{9fa5}]+/u', $query, $matches);
            if (!empty($matches[0])) {
                $chinese_terms = $matches[0];
            }
        }

        return array_unique(array_merge($terms, $chinese_terms));
    }

    /**
     * 同义词扩展
     */
    private function expand_synonyms($terms)
    {
        $synonyms = $this->get_synonyms();
        $expanded = $terms;

        foreach ($terms as $term) {
            if (isset($synonyms[$term])) {
                $expanded = array_merge($expanded, $synonyms[$term]);
            }
        }

        return array_unique($expanded);
    }

    /**
     * 获取同义词库
     */
    private function get_synonyms()
    {
        return array(
            'php' => array('PHP', 'php7', 'php8'),
            'js' => array('javascript', 'JavaScript', 'JS'),
            'wp' => array('wordpress', 'WordPress', 'WP'),
            '教程' => array('指南', '入门', '学习'),
            '问题' => array('错误', 'bug', '故障'),
        );
    }

    /**
     * 数据库搜索
     */
    private function database_search($processed_query, $args)
    {
        global $wpdb;
        $results = array();

        // 搜索文章
        if (in_array($args['type'], array('all', 'post'))) {
            $posts = $this->search_posts($processed_query, $args);
            $results = array_merge($results, $posts);
        }

        // 搜索评论
        if (in_array($args['type'], array('all', 'comment'))) {
            $comments = $this->search_comments($processed_query, $args);
            $results = array_merge($results, $comments);
        }

        // 搜索用户
        if (in_array($args['type'], array('all', 'user'))) {
            $users = $this->search_users($processed_query, $args);
            $results = array_merge($results, $users);
        }

        // 排序
        $results = $this->sort_results($results, $args['order_by']);

        // 分页
        $offset = ($args['page'] - 1) * $args['per_page'];
        $results = array_slice($results, $offset, $args['per_page']);

        return $results;
    }

    /**
     * 搜索文章
     */
    private function search_posts($processed_query, $args)
    {
        global $wpdb;
        $index_table = $wpdb->prefix . 'xiaowu_search_index';
        $query_string = $processed_query['processed'];

        $sql = $wpdb->prepare(
            "SELECT object_id as id, title, excerpt as content, author_name, created_at, modified_at,
                    MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE) as relevance
             FROM $index_table
             WHERE object_type = 'post'
               AND status = 'publish'
               AND MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
             ORDER BY relevance DESC
             LIMIT 100",
            $query_string,
            $query_string
        );

        $posts = $wpdb->get_results($sql, ARRAY_A);

        $results = array();
        foreach ($posts as $post) {
            $post_obj = get_post($post['id']);
            if (!$post_obj) {
                continue;
            }

            $results[] = array(
                'id' => $post['id'],
                'type' => 'post',
                'title' => $post['title'],
                'content' => $this->highlight_terms($post['content'], $processed_query['terms']),
                'excerpt' => wp_trim_words($post['content'], 30),
                'author' => $post['author_name'],
                'date' => $post['created_at'],
                'modified' => $post['modified_at'],
                'url' => get_permalink($post['id']),
                'relevance' => floatval($post['relevance']),
                'thumbnail' => get_the_post_thumbnail_url($post['id'], 'medium'),
                'categories' => wp_get_post_categories($post['id'], array('fields' => 'names')),
                'tags' => wp_get_post_tags($post['id'], array('fields' => 'names'))
            );
        }

        return $results;
    }

    /**
     * 搜索评论
     */
    private function search_comments($processed_query, $args)
    {
        global $wpdb;
        $index_table = $wpdb->prefix . 'xiaowu_search_index';
        $query_string = $processed_query['processed'];

        $sql = $wpdb->prepare(
            "SELECT object_id as id, title, content, author_name, created_at,
                    MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE) as relevance
             FROM $index_table
             WHERE object_type = 'comment'
               AND status = 'approved'
               AND MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
             ORDER BY relevance DESC
             LIMIT 50",
            $query_string,
            $query_string
        );

        $comments = $wpdb->get_results($sql, ARRAY_A);

        $results = array();
        foreach ($comments as $comment) {
            $comment_obj = get_comment($comment['id']);
            if (!$comment_obj) {
                continue;
            }

            $post = get_post($comment_obj->comment_post_ID);

            $results[] = array(
                'id' => $comment['id'],
                'type' => 'comment',
                'title' => '评论: ' . $post->post_title,
                'content' => $this->highlight_terms($comment['content'], $processed_query['terms']),
                'excerpt' => wp_trim_words($comment['content'], 20),
                'author' => $comment['author_name'],
                'date' => $comment['created_at'],
                'url' => get_comment_link($comment['id']),
                'relevance' => floatval($comment['relevance']),
                'post_title' => $post->post_title,
                'post_url' => get_permalink($post->ID)
            );
        }

        return $results;
    }

    /**
     * 搜索用户
     */
    private function search_users($processed_query, $args)
    {
        global $wpdb;
        $index_table = $wpdb->prefix . 'xiaowu_search_index';
        $query_string = $processed_query['processed'];

        $sql = $wpdb->prepare(
            "SELECT object_id as id, title, content, created_at,
                    MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE) as relevance
             FROM $index_table
             WHERE object_type = 'user'
               AND MATCH(title, content, excerpt) AGAINST(%s IN BOOLEAN MODE)
             ORDER BY relevance DESC
             LIMIT 20",
            $query_string,
            $query_string
        );

        $users = $wpdb->get_results($sql, ARRAY_A);

        $results = array();
        foreach ($users as $user) {
            $user_obj = get_user_by('ID', $user['id']);
            if (!$user_obj) {
                continue;
            }

            $results[] = array(
                'id' => $user['id'],
                'type' => 'user',
                'title' => $user['title'],
                'content' => $this->highlight_terms($user['content'], $processed_query['terms']),
                'excerpt' => wp_trim_words($user['content'], 20),
                'username' => $user_obj->user_login,
                'display_name' => $user_obj->display_name,
                'date' => $user['created_at'],
                'url' => get_author_posts_url($user['id']),
                'relevance' => floatval($user['relevance']),
                'avatar' => get_avatar_url($user['id']),
                'posts_count' => count_user_posts($user['id'])
            );
        }

        return $results;
    }

    /**
     * 高亮搜索词
     */
    private function highlight_terms($text, $terms)
    {
        foreach ($terms as $term) {
            $text = preg_replace(
                '/(' . preg_quote($term, '/') . ')/iu',
                '<mark>$1</mark>',
                $text
            );
        }

        return $text;
    }

    /**
     * 排序结果
     */
    private function sort_results($results, $order_by)
    {
        switch ($order_by) {
            case 'date':
                usort($results, function ($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });
                break;

            case 'views':
                usort($results, function ($a, $b) {
                    $views_a = get_post_meta($a['id'], 'views_count', true) ?: 0;
                    $views_b = get_post_meta($b['id'], 'views_count', true) ?: 0;
                    return $views_b - $views_a;
                });
                break;

            case 'relevance':
            default:
                usort($results, function ($a, $b) {
                    return $b['relevance'] <=> $a['relevance'];
                });
                break;
        }

        return $results;
    }

    /**
     * 记录搜索历史
     */
    private function log_search($query)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $wpdb->insert($table_name, array(
            'user_id' => get_current_user_id() ?: null,
            'query' => $query,
            'search_time' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ));
    }

    /**
     * 更新热门搜索
     */
    private function update_popular_searches($query, $results_count)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_popular_searches';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE query = %s",
            $query
        ));

        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'search_count' => $existing->search_count + 1,
                    'last_searched' => current_time('mysql')
                ),
                array('id' => $existing->id)
            );
        } else {
            $wpdb->insert($table_name, array(
                'query' => $query,
                'search_count' => 1,
                'last_searched' => current_time('mysql')
            ));
        }
    }

    /**
     * 获取客户端IP
     */
    private function get_client_ip()
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return sanitize_text_field($ip);
    }

    /**
     * 获取热门搜索
     */
    public function get_popular_searches($limit = 10)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_popular_searches';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT query, search_count FROM $table_name
             ORDER BY search_count DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return array(
            'success' => true,
            'data' => $results
        );
    }

    /**
     * 获取用户搜索历史
     */
    public function get_user_history($user_id = null, $limit = 10)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array('success' => false, 'message' => '用户未登录');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT query, search_time FROM $table_name
             WHERE user_id = %d
             ORDER BY search_time DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);

        return array(
            'success' => true,
            'data' => $results
        );
    }

    /**
     * 清除用户搜索历史
     */
    public function clear_user_history($user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return array('success' => false, 'message' => '用户未登录');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $wpdb->delete($table_name, array('user_id' => $user_id));

        return array(
            'success' => true,
            'message' => '搜索历史已清除'
        );
    }
}
