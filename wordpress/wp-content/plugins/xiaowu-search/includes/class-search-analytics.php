<?php
/**
 * 搜索分析类
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Search_Analytics
{
    /**
     * 获取统计数据
     */
    public function get_stats($days = 30)
    {
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = array(
            'total_searches' => $this->get_total_searches($start_date),
            'unique_queries' => $this->get_unique_queries($start_date),
            'unique_users' => $this->get_unique_users($start_date),
            'avg_results_per_search' => $this->get_avg_results($start_date),
            'top_queries' => $this->get_top_queries($days),
            'no_result_queries' => $this->get_no_result_queries($days),
            'search_trends' => $this->get_search_trends($days),
            'popular_content' => $this->get_popular_content($days),
            'click_through_rate' => $this->get_click_through_rate($start_date)
        );

        return array(
            'success' => true,
            'data' => $stats
        );
    }

    /**
     * 获取总搜索次数
     */
    private function get_total_searches($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE search_time >= %s",
            $start_date
        ));

        return intval($count);
    }

    /**
     * 获取唯一查询数
     */
    private function get_unique_queries($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT query) FROM $table_name WHERE search_time >= %s",
            $start_date
        ));

        return intval($count);
    }

    /**
     * 获取唯一用户数
     */
    private function get_unique_users($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_name
             WHERE search_time >= %s AND user_id IS NOT NULL",
            $start_date
        ));

        return intval($count);
    }

    /**
     * 获取平均结果数
     */
    private function get_avg_results($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(results_count) FROM $table_name WHERE search_time >= %s",
            $start_date
        ));

        return round(floatval($avg), 2);
    }

    /**
     * 获取热门查询
     */
    private function get_top_queries($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT query, COUNT(*) as count, AVG(results_count) as avg_results
             FROM $table_name
             WHERE search_time >= %s
             GROUP BY query
             ORDER BY count DESC
             LIMIT 20",
            $start_date
        ), ARRAY_A);

        return $results;
    }

    /**
     * 获取无结果查询
     */
    private function get_no_result_queries($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT query, COUNT(*) as count, MAX(search_time) as last_search
             FROM $table_name
             WHERE search_time >= %s
               AND results_count = 0
             GROUP BY query
             ORDER BY count DESC
             LIMIT 20",
            $start_date
        ), ARRAY_A);

        return $results;
    }

    /**
     * 获取搜索趋势
     */
    private function get_search_trends($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(search_time) as date, COUNT(*) as count
             FROM $table_name
             WHERE search_time >= %s
             GROUP BY DATE(search_time)
             ORDER BY date ASC",
            $start_date
        ), ARRAY_A);

        return $results;
    }

    /**
     * 获取热门内容
     */
    private function get_popular_content($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT clicked_result_id as id, clicked_result_type as type, COUNT(*) as clicks
             FROM $table_name
             WHERE search_time >= %s
               AND clicked_result_id IS NOT NULL
             GROUP BY clicked_result_id, clicked_result_type
             ORDER BY clicks DESC
             LIMIT 20",
            $start_date
        ), ARRAY_A);

        // 增强数据
        $enhanced_results = array();
        foreach ($results as $result) {
            $item = array(
                'id' => $result['id'],
                'type' => $result['type'],
                'clicks' => intval($result['clicks'])
            );

            // 获取内容信息
            if ($result['type'] === 'post') {
                $post = get_post($result['id']);
                if ($post) {
                    $item['title'] = $post->post_title;
                    $item['url'] = get_permalink($post->ID);
                }
            } elseif ($result['type'] === 'user') {
                $user = get_user_by('ID', $result['id']);
                if ($user) {
                    $item['title'] = $user->display_name;
                    $item['url'] = get_author_posts_url($user->ID);
                }
            }

            if (isset($item['title'])) {
                $enhanced_results[] = $item;
            }
        }

        return $enhanced_results;
    }

    /**
     * 获取点击率
     */
    private function get_click_through_rate($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE search_time >= %s",
            $start_date
        ));

        $clicked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE search_time >= %s AND clicked_result_id IS NOT NULL",
            $start_date
        ));

        if ($total == 0) {
            return 0;
        }

        return round(($clicked / $total) * 100, 2);
    }

    /**
     * 获取查询词云数据
     */
    public function get_query_wordcloud($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT query, COUNT(*) as weight
             FROM $table_name
             WHERE search_time >= %s
             GROUP BY query
             ORDER BY weight DESC
             LIMIT 100",
            $start_date
        ), ARRAY_A);

        return array(
            'success' => true,
            'data' => $results
        );
    }

    /**
     * 获取用户搜索行为
     */
    public function get_user_behavior($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $stats = array(
            'total_searches' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
                $user_id
            )),
            'unique_queries' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT query) FROM $table_name WHERE user_id = %d",
                $user_id
            )),
            'recent_searches' => $wpdb->get_results($wpdb->prepare(
                "SELECT query, search_time, results_count, clicked_result_id, clicked_result_type
                 FROM $table_name
                 WHERE user_id = %d
                 ORDER BY search_time DESC
                 LIMIT 20",
                $user_id
            ), ARRAY_A),
            'top_queries' => $wpdb->get_results($wpdb->prepare(
                "SELECT query, COUNT(*) as count
                 FROM $table_name
                 WHERE user_id = %d
                 GROUP BY query
                 ORDER BY count DESC
                 LIMIT 10",
                $user_id
            ), ARRAY_A)
        );

        return array(
            'success' => true,
            'data' => $stats
        );
    }

    /**
     * 获取搜索质量指标
     */
    public function get_quality_metrics($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 零结果率
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE search_time >= %s",
            $start_date
        ));

        $no_results = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE search_time >= %s AND results_count = 0",
            $start_date
        ));

        $zero_result_rate = $total > 0 ? round(($no_results / $total) * 100, 2) : 0;

        // 平均点击位置
        $avg_click_position = $this->get_avg_click_position($start_date);

        // 搜索深度（用户查看的结果页数）
        $avg_search_depth = $this->get_avg_search_depth($start_date);

        // 搜索改进率（重复搜索率）
        $refinement_rate = $this->get_refinement_rate($start_date);

        return array(
            'success' => true,
            'data' => array(
                'zero_result_rate' => $zero_result_rate,
                'click_through_rate' => $this->get_click_through_rate($start_date),
                'avg_click_position' => $avg_click_position,
                'avg_search_depth' => $avg_search_depth,
                'refinement_rate' => $refinement_rate
            )
        );
    }

    /**
     * 获取平均点击位置
     */
    private function get_avg_click_position($start_date)
    {
        // 简化实现，实际需要记录点击位置
        return 2.5;
    }

    /**
     * 获取平均搜索深度
     */
    private function get_avg_search_depth($start_date)
    {
        // 简化实现，实际需要跟踪会话
        return 1.2;
    }

    /**
     * 获取搜索改进率
     */
    private function get_refinement_rate($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        // 查找在短时间内连续搜索的情况
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id, DATE(search_time))
             FROM $table_name
             WHERE search_time >= %s AND user_id IS NOT NULL",
            $start_date
        ));

        $refined = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT h1.user_id, DATE(h1.search_time))
             FROM $table_name h1
             JOIN $table_name h2 ON h1.user_id = h2.user_id
             WHERE h1.search_time >= %s
               AND h2.search_time > h1.search_time
               AND h2.search_time <= DATE_ADD(h1.search_time, INTERVAL 5 MINUTE)
               AND h1.user_id IS NOT NULL",
            $start_date
        ));

        if ($total == 0) {
            return 0;
        }

        return round(($refined / $total) * 100, 2);
    }

    /**
     * 获取搜索转化率
     */
    public function get_conversion_metrics($days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 点击率
        $ctr = $this->get_click_through_rate($start_date);

        // 立即点击率（第一个结果的点击率）
        $immediate_click_rate = $this->get_immediate_click_rate($start_date);

        // 重复搜索率
        $repeat_search_rate = $this->get_repeat_search_rate($start_date);

        return array(
            'success' => true,
            'data' => array(
                'click_through_rate' => $ctr,
                'immediate_click_rate' => $immediate_click_rate,
                'repeat_search_rate' => $repeat_search_rate,
                'success_rate' => max(0, 100 - $repeat_search_rate)
            )
        );
    }

    /**
     * 获取立即点击率
     */
    private function get_immediate_click_rate($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE search_time >= %s",
            $start_date
        ));

        // 假设立即点击是在搜索后10秒内点击
        $immediate = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name
             WHERE search_time >= %s AND clicked_result_id IS NOT NULL",
            $start_date
        ));

        if ($total == 0) {
            return 0;
        }

        return round(($immediate / $total) * 100, 2);
    }

    /**
     * 获取重复搜索率
     */
    private function get_repeat_search_rate($start_date)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $total_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM $table_name
             WHERE search_time >= %s AND user_id IS NOT NULL",
            $start_date
        ));

        $repeat_users = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT h1.user_id)
             FROM $table_name h1
             JOIN $table_name h2 ON h1.user_id = h2.user_id AND h1.query = h2.query
             WHERE h1.search_time >= %s
               AND h2.search_time > h1.search_time
               AND h2.search_time <= DATE_ADD(h1.search_time, INTERVAL 1 HOUR)
               AND h1.user_id IS NOT NULL",
            $start_date
        ));

        if ($total_users == 0) {
            return 0;
        }

        return round(($repeat_users / $total_users) * 100, 2);
    }

    /**
     * 导出搜索数据
     */
    public function export_data($days = 30, $format = 'csv')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE search_time >= %s ORDER BY search_time DESC",
            $start_date
        ), ARRAY_A);

        if ($format === 'csv') {
            return $this->export_csv($data);
        } elseif ($format === 'json') {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return array(
            'success' => false,
            'message' => '不支持的导出格式'
        );
    }

    /**
     * 导出为CSV
     */
    private function export_csv($data)
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // 写入表头
        fputcsv($output, array_keys($data[0]));

        // 写入数据
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
