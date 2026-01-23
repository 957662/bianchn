<?php
/**
 * 搜索 REST API
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Search_API
{
    private $namespace = 'xiaowu-search/v1';

    /**
     * 注册路由
     */
    public function register_routes()
    {
        // 搜索
        register_rest_route($this->namespace, '/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'type' => array(
                    'default' => 'all',
                    'type' => 'string',
                    'enum' => array('all', 'post', 'comment', 'user')
                ),
                'page' => array(
                    'default' => 1,
                    'type' => 'integer'
                ),
                'per_page' => array(
                    'default' => 20,
                    'type' => 'integer'
                ),
                'order_by' => array(
                    'default' => 'relevance',
                    'type' => 'string',
                    'enum' => array('relevance', 'date', 'views')
                )
            )
        ));

        // 搜索建议
        register_rest_route($this->namespace, '/suggestions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_suggestions'),
            'permission_callback' => '__return_true',
            'args' => array(
                'q' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'limit' => array(
                    'default' => 5,
                    'type' => 'integer'
                )
            )
        ));

        // 热门搜索
        register_rest_route($this->namespace, '/popular', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_popular'),
            'permission_callback' => '__return_true',
            'args' => array(
                'limit' => array(
                    'default' => 10,
                    'type' => 'integer'
                )
            )
        ));

        // 搜索历史
        register_rest_route($this->namespace, '/history', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_history'),
            'permission_callback' => array($this, 'is_user_logged_in'),
            'args' => array(
                'limit' => array(
                    'default' => 10,
                    'type' => 'integer'
                )
            )
        ));

        // 清除搜索历史
        register_rest_route($this->namespace, '/history', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'clear_history'),
            'permission_callback' => array($this, 'is_user_logged_in')
        ));

        // 记录点击
        register_rest_route($this->namespace, '/track-click', array(
            'methods' => 'POST',
            'callback' => array($this, 'track_click'),
            'permission_callback' => '__return_true',
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string'
                ),
                'result_id' => array(
                    'required' => true,
                    'type' => 'integer'
                ),
                'result_type' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));

        // 索引统计
        register_rest_route($this->namespace, '/index/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_index_stats'),
            'permission_callback' => array($this, 'user_can_manage')
        ));

        // 重建索引
        register_rest_route($this->namespace, '/index/rebuild', array(
            'methods' => 'POST',
            'callback' => array($this, 'rebuild_index'),
            'permission_callback' => array($this, 'user_can_manage')
        ));

        // 搜索分析
        register_rest_route($this->namespace, '/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_analytics'),
            'permission_callback' => array($this, 'user_can_manage'),
            'args' => array(
                'days' => array(
                    'default' => 30,
                    'type' => 'integer'
                )
            )
        ));
    }

    /**
     * 执行搜索
     */
    public function search($request)
    {
        $query = $request->get_param('q');
        $type = $request->get_param('type');
        $page = $request->get_param('page');
        $per_page = $request->get_param('per_page');
        $order_by = $request->get_param('order_by');

        $search_engine = new Xiaowu_Search_Engine();
        $result = $search_engine->search($query, array(
            'type' => $type,
            'page' => $page,
            'per_page' => $per_page,
            'order_by' => $order_by
        ));

        if ($result['success']) {
            return rest_ensure_response($result['data']);
        } else {
            return new WP_Error('search_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * 获取搜索建议
     */
    public function get_suggestions($request)
    {
        $query = $request->get_param('q');
        $limit = $request->get_param('limit');

        $suggestions = new Xiaowu_Search_Suggestions();
        $result = $suggestions->get_suggestions_for_query($query, $limit);

        return rest_ensure_response($result);
    }

    /**
     * 获取热门搜索
     */
    public function get_popular($request)
    {
        $limit = $request->get_param('limit');

        $search_engine = new Xiaowu_Search_Engine();
        $result = $search_engine->get_popular_searches($limit);

        if ($result['success']) {
            return rest_ensure_response($result['data']);
        } else {
            return new WP_Error('fetch_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * 获取搜索历史
     */
    public function get_history($request)
    {
        $limit = $request->get_param('limit');

        $search_engine = new Xiaowu_Search_Engine();
        $result = $search_engine->get_user_history(null, $limit);

        if ($result['success']) {
            return rest_ensure_response($result['data']);
        } else {
            return new WP_Error('fetch_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * 清除搜索历史
     */
    public function clear_history($request)
    {
        $search_engine = new Xiaowu_Search_Engine();
        $result = $search_engine->clear_user_history();

        if ($result['success']) {
            return rest_ensure_response(array('message' => $result['message']));
        } else {
            return new WP_Error('clear_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * 记录点击
     */
    public function track_click($request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_search_history';

        $query = $request->get_param('query');
        $result_id = $request->get_param('result_id');
        $result_type = $request->get_param('result_type');

        // 更新最近的搜索记录
        $recent_search = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name
             WHERE query = %s
             AND user_id = %d
             ORDER BY search_time DESC
             LIMIT 1",
            $query,
            get_current_user_id()
        ));

        if ($recent_search) {
            $wpdb->update(
                $table_name,
                array(
                    'clicked_result_id' => $result_id,
                    'clicked_result_type' => $result_type
                ),
                array('id' => $recent_search->id)
            );
        }

        return rest_ensure_response(array('success' => true));
    }

    /**
     * 获取索引统计
     */
    public function get_index_stats($request)
    {
        $indexer = new Xiaowu_Search_Indexer();
        $result = $indexer->get_stats();

        if ($result['success']) {
            return rest_ensure_response($result['data']);
        } else {
            return new WP_Error('fetch_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * 重建索引
     */
    public function rebuild_index($request)
    {
        $indexer = new Xiaowu_Search_Indexer();
        $indexer->reindex_all();

        return rest_ensure_response(array('success' => true));
    }

    /**
     * 获取搜索分析
     */
    public function get_analytics($request)
    {
        $days = $request->get_param('days');

        $analytics = new Xiaowu_Search_Analytics();
        $result = $analytics->get_stats($days);

        if ($result['success']) {
            return rest_ensure_response($result['data']);
        } else {
            return new WP_Error('fetch_failed', $result['message'], array('status' => 400));
        }
    }

    /**
     * 检查用户是否登录
     */
    public function is_user_logged_in()
    {
        return is_user_logged_in();
    }

    /**
     * 检查用户是否有管理权限
     */
    public function user_can_manage()
    {
        return current_user_can('manage_options');
    }
}
