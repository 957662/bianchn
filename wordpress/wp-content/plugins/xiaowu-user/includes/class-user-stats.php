<?php
/**
 * 用户统计类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_User_Stats
{
    /**
     * 获取用户统计信息
     */
    public function get($user_id)
    {
        global $wpdb;

        $stats = array(
            'user_id' => $user_id,
            'posts_count' => $this->get_posts_count($user_id),
            'comments_count' => $this->get_comments_count($user_id),
            'likes_received' => $this->get_likes_received($user_id),
            'views_count' => $this->get_views_count($user_id),
            'following_count' => $this->get_following_count($user_id),
            'followers_count' => $this->get_followers_count($user_id),
            'messages_count' => $this->get_messages_count($user_id),
            'level_info' => $this->get_level_info($user_id),
            'registration_date' => $this->get_registration_date($user_id),
            'last_active' => $this->get_last_active($user_id),
            'achievements' => $this->get_achievements($user_id)
        );

        return array(
            'success' => true,
            'data' => $stats
        );
    }

    /**
     * 获取文章数量
     */
    private function get_posts_count($user_id)
    {
        $count = count_user_posts($user_id, 'post', true);
        return (int)$count;
    }

    /**
     * 获取评论数量
     */
    private function get_comments_count($user_id)
    {
        $comments = get_comments(array(
            'user_id' => $user_id,
            'count' => true
        ));
        return (int)$comments;
    }

    /**
     * 获取收到的点赞数
     */
    private function get_likes_received($user_id)
    {
        global $wpdb;

        // 统计文章点赞
        $posts_likes = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_value)
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_author = %d
               AND pm.meta_key = 'likes_count'",
            $user_id
        ));

        // 统计评论点赞
        $comments_table = $wpdb->prefix . 'xiaowu_comments';
        $comments_likes = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$comments_table'") === $comments_table) {
            $comments_likes = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(likes)
                 FROM $comments_table
                 WHERE user_id = %d",
                $user_id
            ));
        }

        return (int)($posts_likes + $comments_likes);
    }

    /**
     * 获取浏览量
     */
    private function get_views_count($user_id)
    {
        global $wpdb;

        $views = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_value)
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_author = %d
               AND pm.meta_key = 'views_count'",
            $user_id
        ));

        return (int)$views;
    }

    /**
     * 获取关注数
     */
    private function get_following_count($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE follower_id = %d",
            $user_id
        ));

        return (int)$count;
    }

    /**
     * 获取粉丝数
     */
    private function get_followers_count($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_follows';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE following_id = %d",
            $user_id
        ));

        return (int)$count;
    }

    /**
     * 获取私信数量
     */
    private function get_messages_count($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_private_messages';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM $table_name
             WHERE from_user_id = %d OR to_user_id = %d",
            $user_id,
            $user_id
        ));

        return (int)$count;
    }

    /**
     * 获取等级信息
     */
    private function get_level_info($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return null;
        }

        $level_data = $wpdb->get_row($wpdb->prepare(
            "SELECT level, experience, points FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        return $level_data;
    }

    /**
     * 获取注册日期
     */
    private function get_registration_date($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return null;
        }

        return $user->user_registered;
    }

    /**
     * 获取最后活跃时间
     */
    private function get_last_active($user_id)
    {
        $last_active = get_user_meta($user_id, 'last_active', true);
        return $last_active ? $last_active : null;
    }

    /**
     * 更新最后活跃时间
     */
    public function update_last_active($user_id)
    {
        update_user_meta($user_id, 'last_active', current_time('mysql'));

        return array(
            'success' => true,
            'message' => '已更新活跃时间'
        );
    }

    /**
     * 获取成就
     */
    private function get_achievements($user_id)
    {
        $achievements = array();

        // 文章相关成就
        $posts_count = $this->get_posts_count($user_id);
        if ($posts_count >= 1) {
            $achievements[] = array('id' => 'first_post', 'name' => '初露锋芒', 'description' => '发布第一篇文章');
        }
        if ($posts_count >= 10) {
            $achievements[] = array('id' => 'author_10', 'name' => '笔耕不辍', 'description' => '发布10篇文章');
        }
        if ($posts_count >= 50) {
            $achievements[] = array('id' => 'author_50', 'name' => '著作等身', 'description' => '发布50篇文章');
        }
        if ($posts_count >= 100) {
            $achievements[] = array('id' => 'author_100', 'name' => '大作家', 'description' => '发布100篇文章');
        }

        // 评论相关成就
        $comments_count = $this->get_comments_count($user_id);
        if ($comments_count >= 1) {
            $achievements[] = array('id' => 'first_comment', 'name' => '初次发言', 'description' => '发布第一条评论');
        }
        if ($comments_count >= 50) {
            $achievements[] = array('id' => 'commenter_50', 'name' => '话唠', 'description' => '发布50条评论');
        }
        if ($comments_count >= 100) {
            $achievements[] = array('id' => 'commenter_100', 'name' => '评论达人', 'description' => '发布100条评论');
        }

        // 社交相关成就
        $followers_count = $this->get_followers_count($user_id);
        if ($followers_count >= 10) {
            $achievements[] = array('id' => 'popular_10', 'name' => '小有名气', 'description' => '获得10个粉丝');
        }
        if ($followers_count >= 100) {
            $achievements[] = array('id' => 'popular_100', 'name' => '网红', 'description' => '获得100个粉丝');
        }
        if ($followers_count >= 1000) {
            $achievements[] = array('id' => 'popular_1000', 'name' => '大V', 'description' => '获得1000个粉丝');
        }

        // 点赞相关成就
        $likes_received = $this->get_likes_received($user_id);
        if ($likes_received >= 100) {
            $achievements[] = array('id' => 'liked_100', 'name' => '受欢迎', 'description' => '获得100个赞');
        }
        if ($likes_received >= 1000) {
            $achievements[] = array('id' => 'liked_1000', 'name' => '超人气', 'description' => '获得1000个赞');
        }

        // 时间相关成就
        $registration_date = $this->get_registration_date($user_id);
        if ($registration_date) {
            $days = floor((time() - strtotime($registration_date)) / (60 * 60 * 24));
            if ($days >= 30) {
                $achievements[] = array('id' => 'member_30', 'name' => '老朋友', 'description' => '注册30天');
            }
            if ($days >= 365) {
                $achievements[] = array('id' => 'member_365', 'name' => '元老', 'description' => '注册1年');
            }
        }

        return $achievements;
    }

    /**
     * 获取活动统计(按时间范围)
     */
    public function get_activity_stats($user_id, $days = 30)
    {
        global $wpdb;

        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // 文章发布统计
        $posts_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(post_date) as date, COUNT(*) as count
             FROM {$wpdb->posts}
             WHERE post_author = %d
               AND post_status = 'publish'
               AND post_date >= %s
             GROUP BY DATE(post_date)
             ORDER BY date ASC",
            $user_id,
            $start_date
        ), ARRAY_A);

        // 评论统计
        $comments_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(comment_date) as date, COUNT(*) as count
             FROM {$wpdb->comments}
             WHERE user_id = %d
               AND comment_approved = '1'
               AND comment_date >= %s
             GROUP BY DATE(comment_date)
             ORDER BY date ASC",
            $user_id,
            $start_date
        ), ARRAY_A);

        return array(
            'success' => true,
            'data' => array(
                'posts' => $posts_stats,
                'comments' => $comments_stats,
                'period' => $days
            )
        );
    }

    /**
     * 获取热门内容
     */
    public function get_popular_content($user_id, $limit = 10)
    {
        global $wpdb;

        // 最受欢迎的文章
        $popular_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm.meta_value as views
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'views_count'
             WHERE p.post_author = %d
               AND p.post_status = 'publish'
               AND p.post_type = 'post'
             ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A);

        return array(
            'success' => true,
            'data' => array(
                'popular_posts' => $popular_posts
            )
        );
    }

    /**
     * 获取系统整体统计
     */
    public function get_system_stats()
    {
        global $wpdb;

        $stats = array(
            'total_users' => $this->get_total_users(),
            'total_posts' => wp_count_posts('post')->publish,
            'total_comments' => wp_count_comments()->approved,
            'new_users_today' => $this->get_new_users_count(1),
            'new_users_week' => $this->get_new_users_count(7),
            'new_users_month' => $this->get_new_users_count(30),
            'active_users_today' => $this->get_active_users_count(1),
            'active_users_week' => $this->get_active_users_count(7),
            'active_users_month' => $this->get_active_users_count(30)
        );

        return array(
            'success' => true,
            'data' => $stats
        );
    }

    /**
     * 获取用户总数
     */
    private function get_total_users()
    {
        $users = count_users();
        return (int)$users['total_users'];
    }

    /**
     * 获取新用户数量
     */
    private function get_new_users_count($days)
    {
        global $wpdb;

        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users}
             WHERE user_registered >= %s",
            $start_date
        ));

        return (int)$count;
    }

    /**
     * 获取活跃用户数量
     */
    private function get_active_users_count($days)
    {
        global $wpdb;

        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id)
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'last_active'
               AND meta_value >= %s",
            $start_date
        ));

        return (int)$count;
    }

    /**
     * 记录页面访问
     */
    public function track_page_view($user_id, $page_type, $page_id = 0)
    {
        // 增加文章浏览量
        if ($page_type === 'post' && $page_id > 0) {
            $views = get_post_meta($page_id, 'views_count', true);
            $views = $views ? (int)$views + 1 : 1;
            update_post_meta($page_id, 'views_count', $views);
        }

        // 更新用户最后活跃时间
        if ($user_id > 0) {
            $this->update_last_active($user_id);
        }

        return array(
            'success' => true,
            'message' => '已记录访问'
        );
    }

    /**
     * 获取用户活跃度评分
     */
    public function get_activity_score($user_id)
    {
        $score = 0;

        // 文章贡献 (0-40分)
        $posts_count = $this->get_posts_count($user_id);
        $score += min(40, $posts_count * 2);

        // 评论贡献 (0-20分)
        $comments_count = $this->get_comments_count($user_id);
        $score += min(20, $comments_count * 0.5);

        // 社交影响力 (0-30分)
        $followers_count = $this->get_followers_count($user_id);
        $score += min(30, $followers_count * 0.3);

        // 获赞数 (0-10分)
        $likes_received = $this->get_likes_received($user_id);
        $score += min(10, $likes_received * 0.1);

        $score = round($score);

        return array(
            'success' => true,
            'data' => array(
                'score' => $score,
                'level' => $this->get_score_level($score)
            )
        );
    }

    /**
     * 根据评分获取等级
     */
    private function get_score_level($score)
    {
        if ($score >= 90) {
            return '传奇';
        } elseif ($score >= 70) {
            return '大师';
        } elseif ($score >= 50) {
            return '专家';
        } elseif ($score >= 30) {
            return '进阶';
        } elseif ($score >= 10) {
            return '新手';
        } else {
            return '萌新';
        }
    }
}
