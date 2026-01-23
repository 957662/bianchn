<?php
/**
 * 管理后台统计页面
 *
 * @package Xiaowu_User
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats_class = new Xiaowu_User_Stats();
$system_stats = $stats_class->get_system_stats();
$stats = $system_stats['data'];

// 获取用户等级分布
global $wpdb;
$level_table = $wpdb->prefix . 'xiaowu_user_levels';
$level_distribution = $wpdb->get_results(
    "SELECT level, COUNT(*) as count
     FROM $level_table
     GROUP BY level
     ORDER BY level ASC",
    ARRAY_A
);

// 获取最活跃用户
$active_users = $wpdb->get_results(
    "SELECT u.ID, u.user_login, u.display_name, ul.level, ul.points, ul.experience
     FROM {$wpdb->users} u
     JOIN $level_table ul ON u.ID = ul.user_id
     ORDER BY ul.experience DESC
     LIMIT 10",
    ARRAY_A
);
?>

<div class="wrap xiaowu-user-stats">
    <h1>用户系统统计</h1>

    <!-- 概览统计 -->
    <div class="xiaowu-stats-overview">
        <div class="xiaowu-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <h3>总用户数</h3>
                <p class="stat-number"><?php echo number_format($stats['total_users']); ?></p>
            </div>
        </div>

        <div class="xiaowu-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-post"></span>
            </div>
            <div class="stat-content">
                <h3>总文章数</h3>
                <p class="stat-number"><?php echo number_format($stats['total_posts']); ?></p>
            </div>
        </div>

        <div class="xiaowu-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-admin-comments"></span>
            </div>
            <div class="stat-content">
                <h3>总评论数</h3>
                <p class="stat-number"><?php echo number_format($stats['total_comments']); ?></p>
            </div>
        </div>

        <div class="xiaowu-stat-card">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="stat-content">
                <h3>今日新增用户</h3>
                <p class="stat-number"><?php echo number_format($stats['new_users_today']); ?></p>
            </div>
        </div>
    </div>

    <!-- 详细统计 -->
    <div class="xiaowu-stats-details">
        <div class="stats-row">
            <!-- 新用户统计 -->
            <div class="stats-column">
                <div class="stats-box">
                    <h2>新用户统计</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>时间范围</th>
                                <th>新增用户</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>今天</td>
                                <td><?php echo number_format($stats['new_users_today']); ?></td>
                            </tr>
                            <tr>
                                <td>最近7天</td>
                                <td><?php echo number_format($stats['new_users_week']); ?></td>
                            </tr>
                            <tr>
                                <td>最近30天</td>
                                <td><?php echo number_format($stats['new_users_month']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 活跃用户统计 -->
            <div class="stats-column">
                <div class="stats-box">
                    <h2>活跃用户统计</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>时间范围</th>
                                <th>活跃用户</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>今天</td>
                                <td><?php echo number_format($stats['active_users_today']); ?></td>
                            </tr>
                            <tr>
                                <td>最近7天</td>
                                <td><?php echo number_format($stats['active_users_week']); ?></td>
                            </tr>
                            <tr>
                                <td>最近30天</td>
                                <td><?php echo number_format($stats['active_users_month']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 等级分布 -->
        <div class="stats-box">
            <h2>用户等级分布</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>等级</th>
                        <th>用户数</th>
                        <th>百分比</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_users = $stats['total_users'];
                    foreach ($level_distribution as $row):
                        $percentage = $total_users > 0 ? ($row['count'] / $total_users) * 100 : 0;
                    ?>
                        <tr>
                            <td>等级 <?php echo esc_html($row['level']); ?></td>
                            <td><?php echo number_format($row['count']); ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    <span class="progress-text"><?php echo number_format($percentage, 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- 最活跃用户 -->
        <div class="stats-box">
            <h2>最活跃用户 (Top 10)</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>排名</th>
                        <th>用户</th>
                        <th>等级</th>
                        <th>经验值</th>
                        <th>积分</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    foreach ($active_users as $user):
                    ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td>
                                <strong><?php echo esc_html($user['display_name']); ?></strong><br>
                                <small>@<?php echo esc_html($user['user_login']); ?></small>
                            </td>
                            <td>LV.<?php echo esc_html($user['level']); ?></td>
                            <td><?php echo number_format($user['experience']); ?></td>
                            <td><?php echo number_format($user['points']); ?></td>
                            <td>
                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $user['ID']); ?>" class="button button-small">查看</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.xiaowu-user-stats {
    padding: 20px;
}

.xiaowu-stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.xiaowu-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.xiaowu-stat-card .stat-icon {
    width: 60px;
    height: 60px;
    background: #2271b1;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.xiaowu-stat-card .stat-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #fff;
}

.xiaowu-stat-card .stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #666;
}

.xiaowu-stat-card .stat-number {
    margin: 0;
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
}

.xiaowu-stats-details {
    margin-top: 30px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stats-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.stats-box h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #2271b1;
}

.stats-box table {
    margin-top: 15px;
}

.progress-bar {
    position: relative;
    height: 24px;
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #000;
    font-size: 12px;
    font-weight: bold;
}
</style>
