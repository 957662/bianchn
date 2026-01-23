<?php
/**
 * 反垃圾评论类
 *
 * @package Xiaowu_Comments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_Antispam
{
    /**
     * 检查评论是否为垃圾
     */
    public function check($content, $email = '', $ip = '')
    {
        $checks = array(
            'keyword' => $this->check_keywords($content),
            'links' => $this->check_links($content),
            'frequency' => $this->check_frequency($email, $ip),
            'blacklist' => $this->check_blacklist($email, $ip),
            'length' => $this->check_length($content)
        );

        // 如果启用AI检测
        if (get_option('xiaowu_comments_ai_spam_detection')) {
            $checks['ai'] = $this->check_ai($content);
        }

        // 计算垃圾评分
        $spam_score = 0;
        $failed_checks = array();

        foreach ($checks as $check_name => $result) {
            if (!$result['pass']) {
                $spam_score += $result['score'];
                $failed_checks[] = $check_name;
            }
        }

        // 判断阈值
        $threshold = intval(get_option('xiaowu_comments_spam_threshold', 50));
        $is_spam = $spam_score >= $threshold;

        return array(
            'pass' => !$is_spam,
            'score' => $spam_score,
            'checks' => $checks,
            'failed' => $failed_checks,
            'message' => $is_spam ? $this->get_spam_message($failed_checks) : ''
        );
    }

    /**
     * 检查关键词
     */
    private function check_keywords($content)
    {
        $spam_keywords = get_option('xiaowu_comments_spam_keywords', array(
            'viagra', 'cialis', 'casino', 'poker', 'loan', 'mortgage',
            '色情', '赌博', '博彩', '贷款', '发票', '办证'
        ));

        $found_keywords = array();
        foreach ($spam_keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $found_keywords[] = $keyword;
            }
        }

        return array(
            'pass' => empty($found_keywords),
            'score' => count($found_keywords) * 20,
            'details' => $found_keywords
        );
    }

    /**
     * 检查链接数量
     */
    private function check_links($content)
    {
        preg_match_all('/(https?:\/\/[^\s]+)/i', $content, $matches);
        $link_count = count($matches[0]);

        $max_links = intval(get_option('xiaowu_comments_max_links', 2));

        return array(
            'pass' => $link_count <= $max_links,
            'score' => max(0, ($link_count - $max_links) * 15),
            'details' => array('count' => $link_count, 'max' => $max_links)
        );
    }

    /**
     * 检查评论频率
     */
    private function check_frequency($email, $ip)
    {
        $ip = $ip ?: $_SERVER['REMOTE_ADDR'];

        // 检查同一IP在短时间内的评论次数
        $recent_comments = get_comments(array(
            'author_email' => $email,
            'date_query' => array(
                array(
                    'after' => '5 minutes ago',
                    'inclusive' => true
                )
            ),
            'count' => true
        ));

        $max_frequency = intval(get_option('xiaowu_comments_max_frequency', 3));

        return array(
            'pass' => $recent_comments < $max_frequency,
            'score' => max(0, ($recent_comments - $max_frequency + 1) * 25),
            'details' => array('count' => $recent_comments, 'max' => $max_frequency)
        );
    }

    /**
     * 检查黑名单
     */
    private function check_blacklist($email, $ip)
    {
        $ip = $ip ?: $_SERVER['REMOTE_ADDR'];

        $blacklist_emails = get_option('xiaowu_comments_blacklist_emails', array());
        $blacklist_ips = get_option('xiaowu_comments_blacklist_ips', array());

        $is_blacklisted = in_array($email, $blacklist_emails) || in_array($ip, $blacklist_ips);

        return array(
            'pass' => !$is_blacklisted,
            'score' => $is_blacklisted ? 100 : 0,
            'details' => array('email' => in_array($email, $blacklist_emails), 'ip' => in_array($ip, $blacklist_ips))
        );
    }

    /**
     * 检查评论长度
     */
    private function check_length($content)
    {
        $length = mb_strlen($content);
        $min_length = intval(get_option('xiaowu_comments_min_length', 5));
        $max_length = intval(get_option('xiaowu_comments_max_length', 5000));

        $is_valid = $length >= $min_length && $length <= $max_length;

        return array(
            'pass' => $is_valid,
            'score' => $is_valid ? 0 : 30,
            'details' => array('length' => $length, 'min' => $min_length, 'max' => $max_length)
        );
    }

    /**
     * AI垃圾检测
     */
    private function check_ai($content)
    {
        // 调用AI服务检测垃圾评论
        if (class_exists('Xiaowu_AI_Service')) {
            try {
                $ai_service = Xiaowu_AI_Service::get_instance();
                $result = $ai_service->moderate_content($content);

                return array(
                    'pass' => !$result['is_spam'],
                    'score' => $result['is_spam'] ? $result['confidence'] : 0,
                    'details' => $result
                );
            } catch (Exception $e) {
                error_log('AI spam detection failed: ' . $e->getMessage());
            }
        }

        return array(
            'pass' => true,
            'score' => 0,
            'details' => array('error' => 'AI service not available')
        );
    }

    /**
     * 获取垃圾评论提示消息
     */
    private function get_spam_message($failed_checks)
    {
        $messages = array(
            'keyword' => '评论包含敏感词汇',
            'links' => '评论包含过多链接',
            'frequency' => '评论过于频繁，请稍后再试',
            'blacklist' => '您的账号或IP已被列入黑名单',
            'length' => '评论长度不符合要求',
            'ai' => '评论内容被识别为垃圾信息'
        );

        $reasons = array();
        foreach ($failed_checks as $check) {
            if (isset($messages[$check])) {
                $reasons[] = $messages[$check];
            }
        }

        return '评论被拦截：' . implode('；', $reasons);
    }

    /**
     * 添加到黑名单
     */
    public function add_to_blacklist($email = '', $ip = '')
    {
        if ($email) {
            $blacklist_emails = get_option('xiaowu_comments_blacklist_emails', array());
            if (!in_array($email, $blacklist_emails)) {
                $blacklist_emails[] = $email;
                update_option('xiaowu_comments_blacklist_emails', $blacklist_emails);
            }
        }

        if ($ip) {
            $blacklist_ips = get_option('xiaowu_comments_blacklist_ips', array());
            if (!in_array($ip, $blacklist_ips)) {
                $blacklist_ips[] = $ip;
                update_option('xiaowu_comments_blacklist_ips', $blacklist_ips);
            }
        }
    }

    /**
     * 从黑名单移除
     */
    public function remove_from_blacklist($email = '', $ip = '')
    {
        if ($email) {
            $blacklist_emails = get_option('xiaowu_comments_blacklist_emails', array());
            $blacklist_emails = array_diff($blacklist_emails, array($email));
            update_option('xiaowu_comments_blacklist_emails', array_values($blacklist_emails));
        }

        if ($ip) {
            $blacklist_ips = get_option('xiaowu_comments_blacklist_ips', array());
            $blacklist_ips = array_diff($blacklist_ips, array($ip));
            update_option('xiaowu_comments_blacklist_ips', array_values($blacklist_ips));
        }
    }
}
