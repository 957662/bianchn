<?php
/**
 * 搜索建议类
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Search_Suggestions
{
    /**
     * 获取搜索建议（AJAX）
     */
    public function get_suggestions()
    {
        $query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;

        if (empty($query) || mb_strlen($query) < 2) {
            wp_send_json_success(array('suggestions' => array()));
            return;
        }

        $suggestions = $this->get_suggestions_for_query($query, $limit);

        wp_send_json_success($suggestions);
    }

    /**
     * 获取查询建议
     */
    public function get_suggestions_for_query($query, $limit = 5)
    {
        $suggestions = array();

        // 1. 基于历史搜索的建议
        $history_suggestions = $this->get_history_suggestions($query, $limit);
        $suggestions = array_merge($suggestions, $history_suggestions);

        // 2. 基于热门搜索的建议
        if (count($suggestions) < $limit) {
            $popular_suggestions = $this->get_popular_suggestions($query, $limit - count($suggestions));
            $suggestions = array_merge($suggestions, $popular_suggestions);
        }

        // 3. 基于内容标题的建议
        if (count($suggestions) < $limit) {
            $content_suggestions = $this->get_content_suggestions($query, $limit - count($suggestions));
            $suggestions = array_merge($suggestions, $content_suggestions);
        }

        // 4. 基于标签和分类的建议
        if (count($suggestions) < $limit) {
            $taxonomy_suggestions = $this->get_taxonomy_suggestions($query, $limit - count($suggestions));
            $suggestions = array_merge($suggestions, $taxonomy_suggestions);
        }

        // 去重
        $suggestions = array_unique($suggestions, SORT_REGULAR);

        // 限制数量
        $suggestions = array_slice($suggestions, 0, $limit);

        return array(
            'query' => $query,
            'suggestions' => $suggestions
        );
    }

    /**
     * 基于历史搜索的建议
     */
    private function get_history_suggestions($query, $limit)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT query, COUNT(*) as frequency
             FROM $table_name
             WHERE query LIKE %s
               AND query != %s
             GROUP BY query
             ORDER BY frequency DESC, search_time DESC
             LIMIT %d",
            '%' . $wpdb->esc_like($query) . '%',
            $query,
            $limit
        ), ARRAY_A);

        $suggestions = array();
        foreach ($results as $row) {
            $suggestions[] = array(
                'text' => $row['query'],
                'type' => 'history',
                'frequency' => intval($row['frequency'])
            );
        }

        return $suggestions;
    }

    /**
     * 基于热门搜索的建议
     */
    private function get_popular_suggestions($query, $limit)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_popular_searches';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT query, search_count
             FROM $table_name
             WHERE query LIKE %s
               AND query != %s
             ORDER BY search_count DESC
             LIMIT %d",
            '%' . $wpdb->esc_like($query) . '%',
            $query,
            $limit
        ), ARRAY_A);

        $suggestions = array();
        foreach ($results as $row) {
            $suggestions[] = array(
                'text' => $row['query'],
                'type' => 'popular',
                'count' => intval($row['search_count'])
            );
        }

        return $suggestions;
    }

    /**
     * 基于内容标题的建议
     */
    private function get_content_suggestions($query, $limit)
    {
        global $wpdb;
        $index_table = $wpdb->prefix . 'xiaowu_search_index';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT title, object_type
             FROM $index_table
             WHERE title LIKE %s
               AND status IN ('publish', 'approved', 'active')
             ORDER BY modified_at DESC
             LIMIT %d",
            '%' . $wpdb->esc_like($query) . '%',
            $limit
        ), ARRAY_A);

        $suggestions = array();
        foreach ($results as $row) {
            $suggestions[] = array(
                'text' => $row['title'],
                'type' => 'content',
                'content_type' => $row['object_type']
            );
        }

        return $suggestions;
    }

    /**
     * 基于标签和分类的建议
     */
    private function get_taxonomy_suggestions($query, $limit)
    {
        $suggestions = array();

        // 搜索分类
        $categories = get_terms(array(
            'taxonomy' => 'category',
            'hide_empty' => true,
            'search' => $query,
            'number' => $limit
        ));

        foreach ($categories as $cat) {
            $suggestions[] = array(
                'text' => $cat->name,
                'type' => 'category',
                'count' => $cat->count
            );
        }

        // 搜索标签
        if (count($suggestions) < $limit) {
            $tags = get_terms(array(
                'taxonomy' => 'post_tag',
                'hide_empty' => true,
                'search' => $query,
                'number' => $limit - count($suggestions)
            ));

            foreach ($tags as $tag) {
                $suggestions[] = array(
                    'text' => $tag->name,
                    'type' => 'tag',
                    'count' => $tag->count
                );
            }
        }

        return $suggestions;
    }

    /**
     * 获取相关搜索
     */
    public function get_related_searches($query, $limit = 5)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        // 查找同时搜索过当前关键词和其他关键词的用户
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT h2.query, COUNT(*) as frequency
             FROM $table_name h1
             JOIN $table_name h2 ON h1.user_id = h2.user_id
             WHERE h1.query = %s
               AND h2.query != %s
               AND h2.user_id IS NOT NULL
             GROUP BY h2.query
             ORDER BY frequency DESC
             LIMIT %d",
            $query,
            $query,
            $limit
        ), ARRAY_A);

        $related = array();
        foreach ($results as $row) {
            $related[] = array(
                'query' => $row['query'],
                'frequency' => intval($row['frequency'])
            );
        }

        return array(
            'success' => true,
            'data' => $related
        );
    }

    /**
     * 获取查询自动完成
     */
    public function get_autocomplete($query, $limit = 10)
    {
        if (mb_strlen($query) < 2) {
            return array('success' => false, 'message' => '查询太短');
        }

        global $wpdb;

        // 从多个来源获取建议
        $sources = array();

        // 1. 文章标题
        $sources['posts'] = $wpdb->get_results($wpdb->prepare(
            "SELECT post_title as text, 'post' as type, ID as id
             FROM {$wpdb->posts}
             WHERE post_title LIKE %s
               AND post_status = 'publish'
               AND post_type = 'post'
             ORDER BY post_date DESC
             LIMIT 5",
            '%' . $wpdb->esc_like($query) . '%'
        ), ARRAY_A);

        // 2. 热门搜索
        $popular_table = $wpdb->prefix . 'xiaowu_popular_searches';
        $sources['popular'] = $wpdb->get_results($wpdb->prepare(
            "SELECT query as text, 'search' as type, search_count as count
             FROM $popular_table
             WHERE query LIKE %s
             ORDER BY search_count DESC
             LIMIT 3",
            '%' . $wpdb->esc_like($query) . '%'
        ), ARRAY_A);

        // 3. 分类和标签
        $terms = get_terms(array(
            'taxonomy' => array('category', 'post_tag'),
            'search' => $query,
            'hide_empty' => true,
            'number' => 2
        ));

        $sources['terms'] = array();
        foreach ($terms as $term) {
            $sources['terms'][] = array(
                'text' => $term->name,
                'type' => $term->taxonomy,
                'count' => $term->count
            );
        }

        // 合并所有建议
        $all_suggestions = array();
        foreach ($sources as $source) {
            $all_suggestions = array_merge($all_suggestions, $source);
        }

        // 限制数量
        $all_suggestions = array_slice($all_suggestions, 0, $limit);

        return array(
            'success' => true,
            'data' => $all_suggestions
        );
    }

    /**
     * 纠错建议
     */
    public function get_spelling_suggestions($query)
    {
        // 简单的拼写纠错：查找相似的热门搜索
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_popular_searches';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT query, search_count
             FROM $table_name
             WHERE SOUNDEX(query) = SOUNDEX(%s)
                OR query LIKE %s
             ORDER BY search_count DESC
             LIMIT 3",
            $query,
            '%' . $wpdb->esc_like(substr($query, 0, 3)) . '%'
        ), ARRAY_A);

        $suggestions = array();
        foreach ($results as $row) {
            // 计算编辑距离
            $distance = levenshtein($query, $row['query']);
            if ($distance > 0 && $distance <= 3) {
                $suggestions[] = array(
                    'suggestion' => $row['query'],
                    'confidence' => 1 - ($distance / max(mb_strlen($query), mb_strlen($row['query'])))
                );
            }
        }

        return array(
            'success' => true,
            'data' => $suggestions
        );
    }
}
