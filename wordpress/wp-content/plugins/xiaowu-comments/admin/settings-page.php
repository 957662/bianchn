<?php
/**
 * 评论系统设置页面
 *
 * @package Xiaowu_Comments
 */

if (!defined('ABSPATH')) {
    exit;
}

// 处理表单提交
if (isset($_POST['xiaowu_comments_settings']) && check_admin_referer('xiaowu_comments_settings')) {
    // 基础设置
    update_option('xiaowu_comments_antispam_enabled', isset($_POST['antispam_enabled']));
    update_option('xiaowu_comments_notification_enabled', isset($_POST['notification_enabled']));
    update_option('xiaowu_comments_emoji_enabled', isset($_POST['emoji_enabled']));
    update_option('xiaowu_comments_mention_enabled', isset($_POST['mention_enabled']));
    update_option('xiaowu_comments_ai_moderation', isset($_POST['ai_moderation']));

    // 通知设置
    update_option('xiaowu_comments_notify_admin', isset($_POST['notify_admin']));
    update_option('xiaowu_comments_notify_author', isset($_POST['notify_author']));

    // 反垃圾设置
    update_option('xiaowu_comments_spam_threshold', intval($_POST['spam_threshold']));
    update_option('xiaowu_comments_max_links', intval($_POST['max_links']));
    update_option('xiaowu_comments_max_frequency', intval($_POST['max_frequency']));
    update_option('xiaowu_comments_min_length', intval($_POST['min_length']));
    update_option('xiaowu_comments_max_length', intval($_POST['max_length']));
    update_option('xiaowu_comments_ai_spam_detection', isset($_POST['ai_spam_detection']));

    // 敏感词
    if (!empty($_POST['spam_keywords'])) {
        $keywords = explode("\n", $_POST['spam_keywords']);
        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords);
        update_option('xiaowu_comments_spam_keywords', $keywords);
    }

    // 黑名单
    if (!empty($_POST['blacklist_emails'])) {
        $emails = explode("\n", $_POST['blacklist_emails']);
        $emails = array_map('trim', $emails);
        $emails = array_filter($emails);
        update_option('xiaowu_comments_blacklist_emails', $emails);
    }

    if (!empty($_POST['blacklist_ips'])) {
        $ips = explode("\n", $_POST['blacklist_ips']);
        $ips = array_map('trim', $ips);
        $ips = array_filter($ips);
        update_option('xiaowu_comments_blacklist_ips', $ips);
    }

    echo '<div class="notice notice-success"><p>设置已保存</p></div>';
}

// 获取当前设置
$settings = array(
    'antispam_enabled' => get_option('xiaowu_comments_antispam_enabled', true),
    'notification_enabled' => get_option('xiaowu_comments_notification_enabled', true),
    'emoji_enabled' => get_option('xiaowu_comments_emoji_enabled', true),
    'mention_enabled' => get_option('xiaowu_comments_mention_enabled', true),
    'ai_moderation' => get_option('xiaowu_comments_ai_moderation', false),
    'notify_admin' => get_option('xiaowu_comments_notify_admin', true),
    'notify_author' => get_option('xiaowu_comments_notify_author', true),
    'spam_threshold' => get_option('xiaowu_comments_spam_threshold', 50),
    'max_links' => get_option('xiaowu_comments_max_links', 2),
    'max_frequency' => get_option('xiaowu_comments_max_frequency', 3),
    'min_length' => get_option('xiaowu_comments_min_length', 5),
    'max_length' => get_option('xiaowu_comments_max_length', 5000),
    'ai_spam_detection' => get_option('xiaowu_comments_ai_spam_detection', false),
    'spam_keywords' => get_option('xiaowu_comments_spam_keywords', array()),
    'blacklist_emails' => get_option('xiaowu_comments_blacklist_emails', array()),
    'blacklist_ips' => get_option('xiaowu_comments_blacklist_ips', array())
);

// 获取评论统计
$handler = new Xiaowu_Comment_Handler();
$stats = $handler->get_stats();
?>

<div class="wrap xiaowu-comments-admin">
    <h1>评论系统设置</h1>

    <!-- 统计卡片 -->
    <div class="xiaowu-comments-stats">
        <div class="xiaowu-stat-card">
            <h3>总评论数</h3>
            <p class="stat-number"><?php echo number_format($stats['total']); ?></p>
        </div>
        <div class="xiaowu-stat-card">
            <h3>今日评论</h3>
            <p class="stat-number"><?php echo number_format($stats['today']); ?></p>
        </div>
        <div class="xiaowu-stat-card">
            <h3>本周评论</h3>
            <p class="stat-number"><?php echo number_format($stats['this_week']); ?></p>
        </div>
        <div class="xiaowu-stat-card">
            <h3>本月评论</h3>
            <p class="stat-number"><?php echo number_format($stats['this_month']); ?></p>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('xiaowu_comments_settings'); ?>
        <input type="hidden" name="xiaowu_comments_settings" value="1">

        <!-- 标签页导航 -->
        <div class="xiaowu-tabs">
            <button type="button" class="xiaowu-tab active" data-tab="basic">基础设置</button>
            <button type="button" class="xiaowu-tab" data-tab="antispam">反垃圾</button>
            <button type="button" class="xiaowu-tab" data-tab="notification">通知</button>
            <button type="button" class="xiaowu-tab" data-tab="advanced">高级设置</button>
        </div>

        <!-- 基础设置 -->
        <div class="xiaowu-tab-content active" data-tab="basic">
            <table class="form-table">
                <tr>
                    <th scope="row">功能开关</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="antispam_enabled" value="1" <?php checked($settings['antispam_enabled']); ?>>
                                启用反垃圾评论
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="notification_enabled" value="1" <?php checked($settings['notification_enabled']); ?>>
                                启用邮件通知
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="emoji_enabled" value="1" <?php checked($settings['emoji_enabled']); ?>>
                                启用表情包
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="mention_enabled" value="1" <?php checked($settings['mention_enabled']); ?>>
                                启用@提及功能
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row">AI内容审核</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ai_moderation" value="1" <?php checked($settings['ai_moderation']); ?>>
                            新评论需要AI审核后才能发布
                        </label>
                        <p class="description">启用后，评论将先进入待审核状态，通过AI审核后自动发布</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">评论长度限制</th>
                    <td>
                        <label>
                            最小长度:
                            <input type="number" name="min_length" value="<?php echo esc_attr($settings['min_length']); ?>" min="1" max="1000" class="small-text">
                            字符
                        </label>
                        <br>
                        <label>
                            最大长度:
                            <input type="number" name="max_length" value="<?php echo esc_attr($settings['max_length']); ?>" min="100" max="10000" class="small-text">
                            字符
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 反垃圾设置 -->
        <div class="xiaowu-tab-content" data-tab="antispam">
            <table class="form-table">
                <tr>
                    <th scope="row">垃圾评分阈值</th>
                    <td>
                        <input type="number" name="spam_threshold" value="<?php echo esc_attr($settings['spam_threshold']); ?>" min="0" max="100" class="small-text">
                        <p class="description">评分超过此值将被标记为垃圾评论(0-100)</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">AI垃圾检测</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ai_spam_detection" value="1" <?php checked($settings['ai_spam_detection']); ?>>
                            使用AI检测垃圾评论
                        </label>
                        <p class="description">需要配置小伍AI插件</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">最大链接数</th>
                    <td>
                        <input type="number" name="max_links" value="<?php echo esc_attr($settings['max_links']); ?>" min="0" max="10" class="small-text">
                        <p class="description">评论中允许的最大链接数量</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">评论频率限制</th>
                    <td>
                        <input type="number" name="max_frequency" value="<?php echo esc_attr($settings['max_frequency']); ?>" min="1" max="20" class="small-text">
                        次 / 5分钟
                        <p class="description">同一用户5分钟内最多评论次数</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">垃圾关键词</th>
                    <td>
                        <textarea name="spam_keywords" rows="10" cols="50" class="large-text"><?php echo esc_textarea(implode("\n", $settings['spam_keywords'])); ?></textarea>
                        <p class="description">每行一个关键词</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">邮箱黑名单</th>
                    <td>
                        <textarea name="blacklist_emails" rows="10" cols="50" class="large-text"><?php echo esc_textarea(implode("\n", $settings['blacklist_emails'])); ?></textarea>
                        <p class="description">每行一个邮箱地址</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">IP黑名单</th>
                    <td>
                        <textarea name="blacklist_ips" rows="10" cols="50" class="large-text"><?php echo esc_textarea(implode("\n", $settings['blacklist_ips'])); ?></textarea>
                        <p class="description">每行一个IP地址</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 通知设置 -->
        <div class="xiaowu-tab-content" data-tab="notification">
            <table class="form-table">
                <tr>
                    <th scope="row">通知对象</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="notify_admin" value="1" <?php checked($settings['notify_admin']); ?>>
                                通知管理员
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="notify_author" value="1" <?php checked($settings['notify_author']); ?>>
                                通知文章作者
                            </label>
                        </fieldset>
                        <p class="description">被回复者和@提及的用户会自动收到通知</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">邮件服务器</th>
                    <td>
                        <p>当前使用: <strong><?php echo ini_get('SMTP') ?: 'PHP mail()'; ?></strong></p>
                        <p class="description">
                            可通过 <a href="<?php echo admin_url('options-general.php'); ?>">WordPress 邮件设置</a> 配置SMTP服务器
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- 高级设置 -->
        <div class="xiaowu-tab-content" data-tab="advanced">
            <table class="form-table">
                <tr>
                    <th scope="row">REST API端点</th>
                    <td>
                        <code><?php echo rest_url('xiaowu-comments/v1'); ?></code>
                        <p class="description">可用于前端JavaScript调用</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">数据库表</th>
                    <td>
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'xiaowu_comment_meta';
                        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                        ?>
                        <p>
                            表名: <code><?php echo $table_name; ?></code>
                            <?php if ($table_exists): ?>
                                <span style="color: green;">✓ 已创建</span>
                            <?php else: ?>
                                <span style="color: red;">✗ 未创建</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">缓存</th>
                    <td>
                        <button type="button" class="button" onclick="xiaowuCommentsAdmin.clearCache()">
                            清除评论缓存
                        </button>
                        <p class="description">清除所有评论相关的缓存数据</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="submit" class="button button-primary" value="保存设置">
        </p>
    </form>
</div>

<style>
.xiaowu-comments-admin {
    margin: 20px 20px 0 0;
}

.xiaowu-comments-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.xiaowu-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.xiaowu-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.stat-number {
    margin: 0;
    font-size: 32px;
    font-weight: 700;
    color: #667eea;
}

.xiaowu-tabs {
    display: flex;
    gap: 10px;
    margin: 20px 0;
    border-bottom: 2px solid #e5e5e5;
}

.xiaowu-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #666;
}

.xiaowu-tab:hover {
    color: #333;
}

.xiaowu-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

.xiaowu-tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.xiaowu-tab-content.active {
    display: block;
}
</style>
