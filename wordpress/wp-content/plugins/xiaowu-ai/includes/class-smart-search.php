<?php
/**
 * 智能搜索类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Smart_Search 类
 */
class Xiaowu_Smart_Search
{
    /**
     * AI服务实例
     */
    private $ai_service;

    /**
     * 缓存管理器
     */
    private $cache_manager;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->ai_service = new Xiaowu_AI_Service();
        $this->cache_manager = new Xiaowu_Cache_Manager();
    }

    /**
     * 智能搜索
     */
    public function search($query, $limit = 10, $type = 'all', $semantic = true)
    {
        // 检查缓存
        $cache_key = 'xiaowu_search_' . md5($query . $limit . $type . ($semantic ? '1' : '0'));
        $cached_result = $this->cache_manager->get($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // 如果启用语义搜索，生成查询向量
        $query_embedding = null;
        if ($semantic) {
            $embedding_result = $this->ai_service->generate_embedding($query);
            if ($embedding_result['success']) {
                $query_embedding = $embedding_result['embedding'];
            }
        }

        // 执行搜索
        $results = array();

        if ($type === 'all' || $type === 'posts') {
            $posts = $this->search_posts($query, $limit, $query_embedding);
            $results['posts'] = $posts;
        }

        if ($type === 'all' || $type === 'pages') {
            $pages = $this->search_pages($query, $limit, $query_embedding);
            $results['pages'] = $pages;
        }

        if ($type === 'all' || $type === 'models') {
            $models = $this->search_3d_models($query, $limit, $query_embedding);
            $results['models'] = $models;
        }

        // 如果启用语义搜索，使用AI重新排序结果
        if ($semantic && !empty($query_embedding)) {
            $results = $this->rerank_results($results, $query, $query_embedding);
        }

        // 缓存结果
        $this->cache_manager->set($cache_key, $results, 3600); // 缓存1小时

        return array(
            'success' => true,
            'query' => $query,
            'results' => $results,
            'total' => $this->count_results($results)
        );
    }

    /**
     * 搜索文章
     */
    private function search_posts($query, $limit, $query_embedding = null)
    {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $limit,
            'orderby' => 'relevance',
            'order' => 'DESC'
        );

        $posts_query = new WP_Query($args);
        $posts = array();

        if ($posts_query->have_posts()) {
            while ($posts_query->have_posts()) {
                $posts_query->the_post();
                $post_id = get_the_ID();

                $post_data = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'url' => get_permalink(),
                    'date' => get_the_date('c'),
                    'author' => get_the_author(),
                    'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
                    'categories' => wp_get_post_categories($post_id, array('fields' => 'names')),
                    'tags' => wp_get_post_tags($post_id, array('fields' => 'names'))
                );

                // 如果有嵌入向量，计算相似度
                if ($query_embedding) {
                    $post_embedding = get_post_meta($post_id, '_xiaowu_embedding', true);
                    if ($post_embedding) {
                        $post_data['similarity'] = $this->calculate_similarity($query_embedding, json_decode($post_embedding, true));
                    }
                }

                $posts[] = $post_data;
            }
            wp_reset_postdata();
        }

        return $posts;
    }

    /**
     * 搜索页面
     */
    private function search_pages($query, $limit, $query_embedding = null)
    {
        $args = array(
            'post_type' => 'page',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $limit,
            'orderby' => 'relevance',
            'order' => 'DESC'
        );

        $pages_query = new WP_Query($args);
        $pages = array();

        if ($pages_query->have_posts()) {
            while ($pages_query->have_posts()) {
                $pages_query->the_post();
                $page_id = get_the_ID();

                $page_data = array(
                    'id' => $page_id,
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'url' => get_permalink(),
                    'date' => get_the_date('c')
                );

                // 如果有嵌入向量，计算相似度
                if ($query_embedding) {
                    $page_embedding = get_post_meta($page_id, '_xiaowu_embedding', true);
                    if ($page_embedding) {
                        $page_data['similarity'] = $this->calculate_similarity($query_embedding, json_decode($page_embedding, true));
                    }
                }

                $pages[] = $page_data;
            }
            wp_reset_postdata();
        }

        return $pages;
    }

    /**
     * 搜索3D模型
     */
    private function search_3d_models($query, $limit, $query_embedding = null)
    {
        $args = array(
            'post_type' => 'xiaowu_3d_model',
            'post_status' => 'publish',
            's' => $query,
            'posts_per_page' => $limit,
            'orderby' => 'relevance',
            'order' => 'DESC'
        );

        $models_query = new WP_Query($args);
        $models = array();

        if ($models_query->have_posts()) {
            while ($models_query->have_posts()) {
                $models_query->the_post();
                $model_id = get_the_ID();

                $model_data = array(
                    'id' => $model_id,
                    'title' => get_the_title(),
                    'description' => get_the_excerpt(),
                    'thumbnail' => get_the_post_thumbnail_url($model_id, 'medium'),
                    'model_url' => get_post_meta($model_id, '_model_url', true),
                    'category' => wp_get_post_terms($model_id, 'model_category', array('fields' => 'names')),
                    'tags' => wp_get_post_terms($model_id, 'model_tag', array('fields' => 'names'))
                );

                // 如果有嵌入向量，计算相似度
                if ($query_embedding) {
                    $model_embedding = get_post_meta($model_id, '_xiaowu_embedding', true);
                    if ($model_embedding) {
                        $model_data['similarity'] = $this->calculate_similarity($query_embedding, json_decode($model_embedding, true));
                    }
                }

                $models[] = $model_data;
            }
            wp_reset_postdata();
        }

        return $models;
    }

    /**
     * 计算余弦相似度
     */
    private function calculate_similarity($vector1, $vector2)
    {
        if (empty($vector1) || empty($vector2) || count($vector1) !== count($vector2)) {
            return 0;
        }

        $dot_product = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dot_product += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dot_product / ($magnitude1 * $magnitude2);
    }

    /**
     * 重新排序搜索结果
     */
    private function rerank_results($results, $query, $query_embedding)
    {
        // 合并所有结果
        $all_items = array();
        foreach ($results as $type => $items) {
            foreach ($items as $item) {
                $item['type'] = $type;
                $all_items[] = $item;
            }
        }

        // 按相似度排序
        usort($all_items, function($a, $b) {
            $sim_a = isset($a['similarity']) ? $a['similarity'] : 0;
            $sim_b = isset($b['similarity']) ? $b['similarity'] : 0;
            return $sim_b <=> $sim_a;
        });

        // 重新分组
        $reranked_results = array(
            'posts' => array(),
            'pages' => array(),
            'models' => array()
        );

        foreach ($all_items as $item) {
            $type = $item['type'];
            unset($item['type']);
            $reranked_results[$type][] = $item;
        }

        return $reranked_results;
    }

    /**
     * 计算结果总数
     */
    private function count_results($results)
    {
        $total = 0;
        foreach ($results as $items) {
            $total += count($items);
        }
        return $total;
    }

    /**
     * 生成搜索建议
     */
    public function get_suggestions($query, $limit = 5)
    {
        $cache_key = 'xiaowu_search_suggestions_' . md5($query);
        $cached_suggestions = $this->cache_manager->get($cache_key);

        if ($cached_suggestions !== false) {
            return $cached_suggestions;
        }

        // 基于文章标题的建议
        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';

        $suggestions = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT post_title as suggestion
             FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND (post_type = 'post' OR post_type = 'page')
             AND post_title LIKE %s
             ORDER BY post_date DESC
             LIMIT %d",
            $like,
            $limit
        ), ARRAY_A);

        $result = array(
            'success' => true,
            'suggestions' => array_column($suggestions, 'suggestion')
        );

        $this->cache_manager->set($cache_key, $result, 1800); // 缓存30分钟

        return $result;
    }

    /**
     * 生成内容嵌入向量
     */
    public function generate_content_embedding($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'error' => '文章不存在');
        }

        $text = $post->post_title . ' ' . wp_strip_all_tags($post->post_content);
        $text = mb_substr($text, 0, 8000); // 限制长度

        $result = $this->ai_service->generate_embedding($text);

        if ($result['success']) {
            update_post_meta($post_id, '_xiaowu_embedding', json_encode($result['embedding']));
            update_post_meta($post_id, '_xiaowu_embedding_date', current_time('mysql'));
        }

        return $result;
    }

    /**
     * 批量生成嵌入向量
     */
    public function batch_generate_embeddings($post_ids)
    {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );

        foreach ($post_ids as $post_id) {
            $result = $this->generate_content_embedding($post_id);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][$post_id] = $result['error'];
            }
        }

        return $results;
    }
}
