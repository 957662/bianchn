<?php
/**
 * 评论处理器类
 *
 * @package Xiaowu_Comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Comment_Handler
{
    /**
     * 处理评论数据
     */
    public function process($comment_id, $comment_data)
    {
        // 处理评论内容
        $content = $comment_data['comment_content'];

        // 提取@提及
        if (get_option('xiaowu_comments_mention_enabled')) {
            $this->extract_mentions($comment_id, $content);
        }

        // 检测敏感词
        $this->check_sensitive_words($comment_id, $content);

        // 记录用户信息
        $this->record_user_info($comment_id, $comment_data);

        return true;
    }

    /**
     * 提取@提及
     */
    private function extract_mentions($comment_id, $content)
    {
        preg_match_all('/@([a-zA-Z0-9_\x{4e00}-\x{9fa5}]+)/u', $content, $matches);

        if (!empty($matches[1])) {
            $mentions = array();
            foreach ($matches[1] as $username) {
                $user = get_user_by('login', $username);
                if ($user) {
                    $mentions[] = array(
                        'id' => $user->ID,
                        'name' => $user->display_name,
                        'username' => $username
                    );
                }
            }

            if (!empty($mentions)) {
                update_comment_meta($comment_id, 'mentions', $mentions);
            }
        }
    }

    /**
     * 检测敏感词
     */
    private function check_sensitive_words($comment_id, $content)
    {
        $sensitive_words = get_option('xiaowu_comments_sensitive_words', array());

        if (empty($sensitive_words)) {
            return;
        }

        $found_words = array();
        foreach ($sensitive_words as $word) {
            if (stripos($content, $word) !== false) {
                $found_words[] = $word;
            }
        }

        if (!empty($found_words)) {
            update_comment_meta($comment_id, 'sensitive_words', $found_words);

            // 如果包含敏感词，标记为待审核
            wp_set_comment_status($comment_id, 'hold');
        }
    }

    /**
     * 记录用户信息
     */
    private function record_user_info($comment_id, $comment_data)
    {
        $user_info = array(
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'browser' => $this->get_browser(),
            'os' => $this->get_os(),
            'device' => wp_is_mobile() ? 'mobile' : 'desktop'
        );

        update_comment_meta($comment_id, 'user_info', $user_info);
    }

    /**
     * 获取浏览器信息
     */
    private function get_browser()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($user_agent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            return 'IE';
        }

        return 'Unknown';
    }

    /**
     * 获取操作系统信息
     */
    private function get_os()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        if (strpos($user_agent, 'Windows') !== false) {
            return 'Windows';
        } elseif (strpos($user_agent, 'Mac') !== false) {
            return 'MacOS';
        } elseif (strpos($user_agent, 'Linux') !== false) {
            return 'Linux';
        } elseif (strpos($user_agent, 'Android') !== false) {
            return 'Android';
        } elseif (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false || strpos($user_agent, 'iPad') !== false) {
            return 'iOS';
        }

        return 'Unknown';
    }

    /**
     * 获取评论统计
     */
    public function get_stats($user_id = 0)
    {
        $args = array(
            'status' => 'approve',
            'count' => true
        );

        if ($user_id > 0) {
            $args['user_id'] = $user_id;
        }

        $total_comments = get_comments($args);

        $stats = array(
            'total' => $total_comments,
            'today' => $this->get_today_comments($user_id),
            'this_week' => $this->get_week_comments($user_id),
            'this_month' => $this->get_month_comments($user_id)
        );

        return $stats;
    }

    /**
     * 获取今日评论数
     */
    private function get_today_comments($user_id = 0)
    {
        $args = array(
            'status' => 'approve',
            'count' => true,
            'date_query' => array(
                array(
                    'after' => '1 day ago',
                    'inclusive' => true
                )
            )
        );

        if ($user_id > 0) {
            $args['user_id'] = $user_id;
        }

        return get_comments($args);
    }

    /**
     * 获取本周评论数
     */
    private function get_week_comments($user_id = 0)
    {
        $args = array(
            'status' => 'approve',
            'count' => true,
            'date_query' => array(
                array(
                    'after' => '1 week ago',
                    'inclusive' => true
                )
            )
        );

        if ($user_id > 0) {
            $args['user_id'] = $user_id;
        }

        return get_comments($args);
    }

    /**
     * 获取本月评论数
     */
    private function get_month_comments($user_id = 0)
    {
        $args = array(
            'status' => 'approve',
            'count' => true,
            'date_query' => array(
                array(
                    'after' => '1 month ago',
                    'inclusive' => true
                )
            )
        );

        if ($user_id > 0) {
            $args['user_id'] = $user_id;
        }

        return get_comments($args);
    }
}
