<?php
/**
 * 内容推荐类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Recommendation 类
 */
class Xiaowu_Recommendation
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
     * 获取推荐内容
     */
    public function recommend($user_id = 0, $context = array(), $limit = 5)
    {
        // 检查缓存
        $cache_key = 'xiaowu_recommend_' . $user_id . '_' . md5(json_encode($context)) . '_' . $limit;
        $cached_result = $this->cache_manager->get($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // 获取用户历史
        $user_history = $this->get_user_history($user_id);

        // 获取当前上下文
        $current_context = $this->parse_context($context);

        // 基于协同过滤的推荐
        $collaborative_recommendations = $this->get_collaborative_recommendations($user_id, $limit);

        // 基于内容的推荐
        $content_based_recommendations = $this->get_content_based_recommendations($current_context, $limit);

        // 基于嵌入向量的推荐
        $embedding_based_recommendations = $this->get_embedding_based_recommendations($current_context, $limit);

        // 合并推荐结果
        $all_recommendations = array_merge(
            $collaborative_recommendations,
            $content_based_recommendations,
            $embedding_based_recommendations
        );

        // 去重
        $unique_recommendations = $this->deduplicate_recommendations($all_recommendations);

        // 排序和限制数量
        $final_recommendations = array_slice($unique_recommendations, 0, $limit);

        $result = array(
            'success' => true,
            'recommendations' => $final_recommendations,
            'total' => count($final_recommendations)
        );

        // 缓存结果
        $this->cache_manager->set($cache_key, $result, 1800); // 缓存30分钟

        return $result;
    }

    /**
     * 获取用户历史
     */
    private function get_user_history($user_id)
    {
        if ($user_id === 0) {
            return array(
                'viewed_posts' => array(),
                'liked_posts' => array(),
                'commented_posts' => array()
            );
        }

        return array(
            'viewed_posts' => $this->get_user_viewed_posts($user_id),
            'liked_posts' => $this->get_user_liked_posts($user_id),
            'commented_posts' => $this->get_user_commented_posts($user_id)
        );
    }

    /**
     * 获取用户浏览历史
     */
    private function get_user_viewed_posts($user_id)
    {
        $viewed = get_user_meta($user_id, '_xiaowu_viewed_posts', true);
        return is_array($viewed) ? $viewed : array();
    }

    /**
     * 获取用户点赞文章
     */
    private function get_user_liked_posts($user_id)
    {
        $liked = get_user_meta($user_id, '_xiaowu_liked_posts', true);
        return is_array($liked) ? $liked : array();
    }

    /**
     * 获取用户评论过的文章
     */
    private function get_user_commented_posts($user_id)
    {
        $comments = get_comments(array(
            'user_id' => $user_id,
            'status' => 'approve',
            'type' => 'comment'
        ));

        $post_ids = array();
        foreach ($comments as $comment) {
            $post_ids[] = $comment->comment_post_ID;
        }

        return array_unique($post_ids);
    }

    /**
     * 解析上下文
     */
    private function parse_context($context)
    {
        $parsed = array(
            'current_post_id' => 0,
            'current_category' => array(),
            'current_tags' => array(),
            'search_query' => ''
        );

        if (isset($context['post_id'])) {
            $parsed['current_post_id'] = intval($context['post_id']);
            $parsed['current_category'] = wp_get_post_categories($parsed['current_post_id']);
            $parsed['current_tags'] = wp_get_post_tags($parsed['current_post_id'], array('fields' => 'ids'));
        }

        if (isset($context['category'])) {
            $parsed['current_category'] = array(intval($context['category']));
        }

        if (isset($context['query'])) {
            $parsed['search_query'] = sanitize_text_field($context['query']);
        }

        return $parsed;
    }

    /**
     * 协同过滤推荐
     */
    private function get_collaborative_recommendations($user_id, $limit)
    {
        if ($user_id === 0) {
            return array();
        }

        $user_history = $this->get_user_history($user_id);
        $all_user_posts = array_merge(
            $user_history['viewed_posts'],
            $user_history['liked_posts'],
            $user_history['commented_posts']
        );

        if (empty($all_user_posts)) {
            return array();
        }

        // 查找浏览过相似文章的其他用户
        global $wpdb;
        $post_ids_str = implode(',', array_map('intval', $all_user_posts));

        $similar_users = $wpdb->get_results("
            SELECT DISTINCT user_id
            FROM {$wpdb->comments}
            WHERE comment_post_ID IN ($post_ids_str)
            AND user_id != $user_id
            AND user_id != 0
            LIMIT 20
        ");

        if (empty($similar_users)) {
            return array();
        }

        $similar_user_ids = array_column($similar_users, 'user_id');

        // 获取这些用户喜欢的其他文章
        $recommendations = array();
        foreach ($similar_user_ids as $similar_user_id) {
            $similar_user_posts = $this->get_user_liked_posts($similar_user_id);
            foreach ($similar_user_posts as $post_id) {
                if (!in_array($post_id, $all_user_posts)) {
                    $recommendations[] = $this->format_recommendation($post_id, 'collaborative');
                }
            }
        }

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * 基于内容的推荐
     */
    private function get_content_based_recommendations($context, $limit)
    {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'rand'
        );

        // 如果有当前文章，排除它
        if ($context['current_post_id'] > 0) {
            $args['post__not_in'] = array($context['current_post_id']);
        }

        // 如果有分类，优先推荐同分类文章
        if (!empty($context['current_category'])) {
            $args['category__in'] = $context['current_category'];
        }

        // 如果有标签，优先推荐同标签文章
        if (!empty($context['current_tags'])) {
            $args['tag__in'] = $context['current_tags'];
        }

        $query = new WP_Query($args);
        $recommendations = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $recommendations[] = $this->format_recommendation(get_the_ID(), 'content_based');
            }
            wp_reset_postdata();
        }

        return $recommendations;
    }

    /**
     * 基于嵌入向量的推荐
     */
    private function get_embedding_based_recommendations($context, $limit)
    {
        if ($context['current_post_id'] === 0) {
            return array();
        }

        // 获取当前文章的嵌入向量
        $current_embedding = get_post_meta($context['current_post_id'], '_xiaowu_embedding', true);
        if (empty($current_embedding)) {
            return array();
        }

        $current_embedding = json_decode($current_embedding, true);

        // 获取所有已有嵌入向量的文章
        global $wpdb;
        $posts_with_embeddings = $wpdb->get_results("
            SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_xiaowu_embedding'
            AND post_id != {$context['current_post_id']}
        ");

        $similarities = array();
        foreach ($posts_with_embeddings as $post_meta) {
            $embedding = json_decode($post_meta->meta_value, true);
            $similarity = $this->calculate_similarity($current_embedding, $embedding);
            $similarities[$post_meta->post_id] = $similarity;
        }

        // 按相似度排序
        arsort($similarities);

        $recommendations = array();
        $count = 0;
        foreach ($similarities as $post_id => $similarity) {
            if ($count >= $limit) {
                break;
            }
            $recommendation = $this->format_recommendation($post_id, 'embedding_based');
            $recommendation['similarity'] = $similarity;
            $recommendations[] = $recommendation;
            $count++;
        }

        return $recommendations;
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
     * 格式化推荐结果
     */
    private function format_recommendation($post_id, $source)
    {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        return array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'excerpt' => get_the_excerpt($post),
            'url' => get_permalink($post_id),
            'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
            'date' => get_the_date('c', $post_id),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'categories' => wp_get_post_categories($post_id, array('fields' => 'names')),
            'tags' => wp_get_post_tags($post_id, array('fields' => 'names')),
            'source' => $source
        );
    }

    /**
     * 去重推荐结果
     */
    private function deduplicate_recommendations($recommendations)
    {
        $seen_ids = array();
        $unique = array();

        foreach ($recommendations as $recommendation) {
            if ($recommendation === null) {
                continue;
            }

            $post_id = $recommendation['id'];
            if (!in_array($post_id, $seen_ids)) {
                $seen_ids[] = $post_id;
                $unique[] = $recommendation;
            }
        }

        return $unique;
    }

    /**
     * 记录用户浏览
     */
    public function track_view($user_id, $post_id)
    {
        if ($user_id === 0) {
            return;
        }

        $viewed = $this->get_user_viewed_posts($user_id);

        // 添加到历史记录（最多保存100个）
        array_unshift($viewed, $post_id);
        $viewed = array_unique($viewed);
        $viewed = array_slice($viewed, 0, 100);

        update_user_meta($user_id, '_xiaowu_viewed_posts', $viewed);
    }

    /**
     * 记录用户点赞
     */
    public function track_like($user_id, $post_id)
    {
        if ($user_id === 0) {
            return array('success' => false, 'error' => '用户未登录');
        }

        $liked = $this->get_user_liked_posts($user_id);

        if (in_array($post_id, $liked)) {
            // 取消点赞
            $liked = array_diff($liked, array($post_id));
            $action = 'unliked';
        } else {
            // 添加点赞
            $liked[] = $post_id;
            $action = 'liked';
        }

        update_user_meta($user_id, '_xiaowu_liked_posts', $liked);

        // 更新文章点赞数
        $like_count = intval(get_post_meta($post_id, '_xiaowu_like_count', true));
        if ($action === 'liked') {
            $like_count++;
        } else {
            $like_count = max(0, $like_count - 1);
        }
        update_post_meta($post_id, '_xiaowu_like_count', $like_count);

        return array(
            'success' => true,
            'action' => $action,
            'like_count' => $like_count
        );
    }
}
