<?php
/**
 * 用户等级系统类
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_User_Level
{
    /**
     * 等级配置
     */
    private $level_config = array(
        1 => array('name' => '新手', 'exp_required' => 0, 'color' => '#95a5a6'),
        2 => array('name' => '初学者', 'exp_required' => 100, 'color' => '#3498db'),
        3 => array('name' => '学徒', 'exp_required' => 300, 'color' => '#9b59b6'),
        4 => array('name' => '专家', 'exp_required' => 600, 'color' => '#e67e22'),
        5 => array('name' => '大师', 'exp_required' => 1000, 'color' => '#e74c3c'),
        6 => array('name' => '宗师', 'exp_required' => 1500, 'color' => '#f39c12'),
        7 => array('name' => '传说', 'exp_required' => 2500, 'color' => '#1abc9c'),
        8 => array('name' => '神话', 'exp_required' => 4000, 'color' => '#2ecc71'),
        9 => array('name' => '史诗', 'exp_required' => 6000, 'color' => '#c0392b'),
        10 => array('name' => '不朽', 'exp_required' => 10000, 'color' => '#d4af37')
    );

    /**
     * 经验值奖励配置
     */
    private $exp_rewards = array(
        'register' => 10,
        'email_verify' => 20,
        'first_comment' => 15,
        'comment' => 5,
        'post' => 50,
        'login_daily' => 5,
        'profile_complete' => 30,
        'social_bind' => 15,
        'avatar_upload' => 10
    );

    /**
     * 积分奖励配置
     */
    private $points_rewards = array(
        'register' => 100,
        'email_verify' => 50,
        'comment' => 10,
        'post' => 100,
        'login_daily' => 10,
        'comment_liked' => 5,
        'post_liked' => 20
    );

    /**
     * 获取用户等级信息
     */
    public function get($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        $level_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if (!$level_data) {
            // 初始化用户等级
            $this->init_user_level($user_id);
            $level_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d",
                $user_id
            ), ARRAY_A);
        }

        $current_level = $level_data['level'];
        $current_exp = $level_data['experience'];
        $next_level = $current_level + 1;

        // 计算到下一等级所需经验
        $exp_required = isset($this->level_config[$next_level])
            ? $this->level_config[$next_level]['exp_required']
            : 0;

        $exp_to_next = $exp_required - $current_exp;
        $exp_progress = $exp_required > 0 ? ($current_exp / $exp_required) * 100 : 100;

        // 解析徽章
        $badges = !empty($level_data['badges']) ? json_decode($level_data['badges'], true) : array();

        return array(
            'success' => true,
            'data' => array(
                'user_id' => $user_id,
                'level' => $current_level,
                'level_name' => $this->level_config[$current_level]['name'],
                'level_color' => $this->level_config[$current_level]['color'],
                'experience' => $current_exp,
                'points' => $level_data['points'],
                'badges' => $badges,
                'next_level' => $next_level,
                'exp_required' => $exp_required,
                'exp_to_next' => max(0, $exp_to_next),
                'exp_progress' => min(100, $exp_progress)
            )
        );
    }

    /**
     * 初始化用户等级
     */
    private function init_user_level($user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'level' => 1,
            'experience' => 0,
            'points' => 0,
            'badges' => json_encode(array())
        ));
    }

    /**
     * 添加经验值
     */
    public function add_experience($user_id, $exp, $reason = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        // 获取当前等级信息
        $level_info = $this->get($user_id);
        $current_level = $level_info['data']['level'];
        $current_exp = $level_info['data']['experience'];

        // 添加经验值
        $new_exp = $current_exp + $exp;

        // 检查是否升级
        $new_level = $this->calculate_level($new_exp);
        $level_up = $new_level > $current_level;

        // 更新数据库
        $wpdb->update(
            $table_name,
            array(
                'level' => $new_level,
                'experience' => $new_exp
            ),
            array('user_id' => $user_id)
        );

        // 记录活动
        $this->log_activity($user_id, 'exp_gained', array(
            'exp' => $exp,
            'reason' => $reason,
            'level_up' => $level_up,
            'new_level' => $new_level
        ));

        // 如果升级,发送通知
        if ($level_up) {
            $this->send_level_up_notification($user_id, $new_level);

            // 升级奖励
            $this->add_points($user_id, 50, 'level_up_reward');
        }

        return array(
            'success' => true,
            'data' => array(
                'exp_added' => $exp,
                'new_exp' => $new_exp,
                'new_level' => $new_level,
                'level_up' => $level_up
            )
        );
    }

    /**
     * 根据经验值计算等级
     */
    private function calculate_level($exp)
    {
        $level = 1;
        foreach ($this->level_config as $lv => $config) {
            if ($exp >= $config['exp_required']) {
                $level = $lv;
            } else {
                break;
            }
        }
        return $level;
    }

    /**
     * 添加积分
     */
    public function add_points($user_id, $points, $reason = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET points = points + %d WHERE user_id = %d",
            $points,
            $user_id
        ));

        // 记录活动
        $this->log_activity($user_id, 'points_gained', array(
            'points' => $points,
            'reason' => $reason
        ));

        return array(
            'success' => true,
            'data' => array('points_added' => $points)
        );
    }

    /**
     * 扣除积分
     */
    public function deduct_points($user_id, $points, $reason = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        // 检查积分是否足够
        $level_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        if ($level_data['points'] < $points) {
            return array(
                'success' => false,
                'message' => '积分不足'
            );
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET points = points - %d WHERE user_id = %d",
            $points,
            $user_id
        ));

        // 记录活动
        $this->log_activity($user_id, 'points_deducted', array(
            'points' => $points,
            'reason' => $reason
        ));

        return array(
            'success' => true,
            'data' => array('points_deducted' => $points)
        );
    }

    /**
     * 授予徽章
     */
    public function award_badge($user_id, $badge_id, $badge_name, $badge_description = '')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        $level_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ), ARRAY_A);

        $badges = !empty($level_data['badges']) ? json_decode($level_data['badges'], true) : array();

        // 检查是否已有该徽章
        if (isset($badges[$badge_id])) {
            return array(
                'success' => false,
                'message' => '已拥有该徽章'
            );
        }

        // 添加徽章
        $badges[$badge_id] = array(
            'name' => $badge_name,
            'description' => $badge_description,
            'earned_at' => current_time('mysql')
        );

        $wpdb->update(
            $table_name,
            array('badges' => json_encode($badges)),
            array('user_id' => $user_id)
        );

        // 记录活动
        $this->log_activity($user_id, 'badge_earned', array(
            'badge_id' => $badge_id,
            'badge_name' => $badge_name
        ));

        // 发送通知
        $this->send_badge_notification($user_id, $badge_name);

        return array(
            'success' => true,
            'message' => '徽章已授予'
        );
    }

    /**
     * 获取等级排行榜
     */
    public function get_leaderboard($limit = 50)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_levels';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ul.user_id, ul.level, ul.experience, ul.points, u.display_name, u.user_login
             FROM $table_name ul
             JOIN {$wpdb->users} u ON ul.user_id = u.ID
             ORDER BY ul.level DESC, ul.experience DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        $leaderboard = array();
        foreach ($results as $index => $row) {
            $leaderboard[] = array(
                'rank' => $index + 1,
                'user_id' => $row['user_id'],
                'username' => $row['user_login'],
                'display_name' => $row['display_name'],
                'level' => $row['level'],
                'level_name' => $this->level_config[$row['level']]['name'],
                'experience' => $row['experience'],
                'points' => $row['points']
            );
        }

        return array(
            'success' => true,
            'data' => $leaderboard
        );
    }

    /**
     * 记录活动
     */
    private function log_activity($user_id, $action, $data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_user_activities';

        $wpdb->insert($table_name, array(
            'user_id' => $user_id,
            'action' => $action,
            'object_type' => 'level',
            'object_id' => 0,
            'metadata' => json_encode($data),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * 发送升级通知
     */
    private function send_level_up_notification($user_id, $new_level)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $level_name = $this->level_config[$new_level]['name'];

        $subject = '[' . get_bloginfo('name') . '] 恭喜您升级了!';
        $message = "您好 {$user->display_name},\n\n";
        $message .= "恭喜您升级到 {$new_level} 级 - {$level_name}!\n\n";
        $message .= "继续努力,创造更多精彩内容!\n\n";
        $message .= "祝好!\n";
        $message .= get_bloginfo('name') . " 团队";

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * 发送徽章通知
     */
    private function send_badge_notification($user_id, $badge_name)
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $subject = '[' . get_bloginfo('name') . '] 您获得了新徽章!';
        $message = "您好 {$user->display_name},\n\n";
        $message .= "恭喜您获得了新徽章: {$badge_name}!\n\n";
        $message .= "祝好!\n";
        $message .= get_bloginfo('name') . " 团队";

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * 根据动作奖励经验值
     */
    public function reward_action($user_id, $action)
    {
        if (!isset($this->exp_rewards[$action])) {
            return array(
                'success' => false,
                'message' => '未知的动作类型'
            );
        }

        $exp = $this->exp_rewards[$action];
        $this->add_experience($user_id, $exp, $action);

        // 同时奖励积分
        if (isset($this->points_rewards[$action])) {
            $points = $this->points_rewards[$action];
            $this->add_points($user_id, $points, $action);
        }

        return array(
            'success' => true,
            'message' => '奖励已发放'
        );
    }

    /**
     * 检查每日登录奖励
     */
    public function check_daily_login($user_id)
    {
        $last_login = get_user_meta($user_id, 'last_daily_login', true);
        $today = date('Y-m-d');

        if ($last_login === $today) {
            return array(
                'success' => false,
                'message' => '今日已签到'
            );
        }

        // 更新最后登录时间
        update_user_meta($user_id, 'last_daily_login', $today);

        // 奖励经验和积分
        $this->reward_action($user_id, 'login_daily');

        // 检查连续登录
        $login_streak = get_user_meta($user_id, 'login_streak', true);
        if (empty($login_streak)) {
            $login_streak = 1;
        } else {
            $last_date = strtotime($last_login);
            $current_date = strtotime($today);
            $diff = ($current_date - $last_date) / (60 * 60 * 24);

            if ($diff === 1) {
                $login_streak++;
            } else {
                $login_streak = 1;
            }
        }

        update_user_meta($user_id, 'login_streak', $login_streak);

        // 连续登录奖励
        if ($login_streak % 7 === 0) {
            $this->add_points($user_id, 50, 'weekly_streak_bonus');
            $this->award_badge($user_id, 'login_streak_7', '坚持不懈', '连续登录7天');
        }

        if ($login_streak === 30) {
            $this->award_badge($user_id, 'login_streak_30', '忠实粉丝', '连续登录30天');
        }

        return array(
            'success' => true,
            'message' => '签到成功',
            'data' => array(
                'login_streak' => $login_streak
            )
        );
    }
}
