<?php
/**
 * 索引管理页面
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取索引统计
$indexer = new Xiaowu_Search_Indexer();
$stats_result = $indexer->get_stats();
$stats = $stats_result['success'] ? $stats_result['data'] : array();

// 获取内容总数
global $wpdb;
$total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
$total_comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '1'");
$total_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

// 计算索引覆盖率
$posts_coverage = $total_posts > 0 ? round(($stats['posts'] / $total_posts) * 100, 1) : 0;
$comments_coverage = $total_comments > 0 ? round(($stats['comments'] / $total_comments) * 100, 1) : 0;
$users_coverage = $total_users > 0 ? round(($stats['users'] / $total_users) * 100, 1) : 0;
?>

<div class="wrap xiaowu-search-indexing">
    <h1>索引管理</h1>

    <!-- 索引状态概览 -->
    <div class="indexing-overview">
        <div class="index-stat-card">
            <div class="stat-icon">📝</div>
            <div class="stat-info">
                <h3>文章索引</h3>
                <p class="stat-numbers">
                    <span class="indexed"><?php echo number_format($stats['posts']); ?></span> /
                    <span class="total"><?php echo number_format($total_posts); ?></span>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $posts_coverage; ?>%"></div>
                </div>
                <p class="coverage"><?php echo $posts_coverage; ?>% 覆盖率</p>
            </div>
            <div class="stat-actions">
                <button type="button" class="button index-type-btn" data-type="posts">重建文章索引</button>
            </div>
        </div>

        <div class="index-stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-info">
                <h3>评论索引</h3>
                <p class="stat-numbers">
                    <span class="indexed"><?php echo number_format($stats['comments']); ?></span> /
                    <span class="total"><?php echo number_format($total_comments); ?></span>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $comments_coverage; ?>%"></div>
                </div>
                <p class="coverage"><?php echo $comments_coverage; ?>% 覆盖率</p>
            </div>
            <div class="stat-actions">
                <button type="button" class="button index-type-btn" data-type="comments">重建评论索引</button>
            </div>
        </div>

        <div class="index-stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-info">
                <h3>用户索引</h3>
                <p class="stat-numbers">
                    <span class="indexed"><?php echo number_format($stats['users']); ?></span> /
                    <span class="total"><?php echo number_format($total_users); ?></span>
                </p>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $users_coverage; ?>%"></div>
                </div>
                <p class="coverage"><?php echo $users_coverage; ?>% 覆盖率</p>
            </div>
            <div class="stat-actions">
                <button type="button" class="button index-type-btn" data-type="users">重建用户索引</button>
            </div>
        </div>

        <div class="index-stat-card total">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3>总索引数</h3>
                <p class="stat-numbers large">
                    <span class="indexed"><?php echo number_format($stats['total']); ?></span>
                </p>
                <p class="last-indexed">
                    最后索引: <?php echo $stats['last_indexed'] ? date('Y-m-d H:i:s', strtotime($stats['last_indexed'])) : '从未'; ?>
                </p>
            </div>
            <div class="stat-actions">
                <button type="button" class="button button-primary" id="rebuild-all-btn">重建全部索引</button>
            </div>
        </div>
    </div>

    <!-- 索引操作区 -->
    <div class="indexing-section">
        <h2>索引操作</h2>
        <div class="operation-cards">
            <div class="operation-card">
                <h3>🔄 重建索引</h3>
                <p>清空现有索引并重新构建所有内容的搜索索引。适用于首次安装或数据损坏时。</p>
                <button type="button" class="button button-primary" id="full-rebuild-btn">开始重建</button>
            </div>

            <div class="operation-card">
                <h3>⚡ 优化索引</h3>
                <p>清理无效记录并优化数据库表，提升搜索性能。建议定期执行。</p>
                <button type="button" class="button" id="optimize-btn">立即优化</button>
            </div>

            <div class="operation-card">
                <h3>🔍 检查索引</h3>
                <p>检查索引完整性，找出未索引的内容和孤立的索引记录。</p>
                <button type="button" class="button" id="check-index-btn">开始检查</button>
            </div>

            <div class="operation-card">
                <h3>📥 批量导入</h3>
                <p>从外部数据源批量导入内容到搜索索引。支持CSV和JSON格式。</p>
                <button type="button" class="button" id="import-btn">导入数据</button>
            </div>
        </div>
    </div>

    <!-- 进度显示区 -->
    <div id="indexing-progress" style="display: none;">
        <div class="progress-container">
            <h3 id="progress-title">正在处理...</h3>
            <div class="progress-bar-large">
                <div id="progress-bar-fill" class="progress-fill-large"></div>
            </div>
            <p id="progress-status">准备中...</p>
            <div id="progress-details"></div>
        </div>
    </div>

    <!-- 索引日志 -->
    <div class="indexing-section">
        <h2>索引日志</h2>
        <div id="index-log" class="index-log">
            <p class="log-empty">暂无日志记录</p>
        </div>
    </div>

    <!-- 高级设置 -->
    <div class="indexing-section">
        <h2>高级设置</h2>
        <form method="post" action="">
            <?php wp_nonce_field('xiaowu_indexing_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">自动索引</th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_index_posts" <?php checked(get_option('xiaowu_search_auto_index_posts', true)); ?>>
                            自动索引新发布的文章
                        </label><br>
                        <label>
                            <input type="checkbox" name="auto_index_comments" <?php checked(get_option('xiaowu_search_auto_index_comments', true)); ?>>
                            自动索引新评论
                        </label><br>
                        <label>
                            <input type="checkbox" name="auto_index_users" <?php checked(get_option('xiaowu_search_auto_index_users', true)); ?>>
                            自动索引新用户
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">批量索引设置</th>
                    <td>
                        <label>
                            每批处理数量:
                            <input type="number" name="batch_size" value="<?php echo esc_attr(get_option('xiaowu_search_batch_size', 50)); ?>" min="10" max="500" class="small-text">
                        </label>
                        <p class="description">批量索引时每次处理的记录数，数值越大速度越快但消耗资源越多</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">索引内容深度</th>
                    <td>
                        <label>
                            <input type="checkbox" name="index_post_meta" <?php checked(get_option('xiaowu_search_index_post_meta', false)); ?>>
                            索引文章自定义字段
                        </label><br>
                        <label>
                            <input type="checkbox" name="index_taxonomies" <?php checked(get_option('xiaowu_search_index_taxonomies', true)); ?>>
                            索引分类和标签
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_indexing_settings" class="button button-primary" value="保存设置">
            </p>
        </form>
    </div>
</div>

<?php
// 保存设置
if (isset($_POST['save_indexing_settings'])) {
    check_admin_referer('xiaowu_indexing_settings');

    update_option('xiaowu_search_auto_index_posts', isset($_POST['auto_index_posts']));
    update_option('xiaowu_search_auto_index_comments', isset($_POST['auto_index_comments']));
    update_option('xiaowu_search_auto_index_users', isset($_POST['auto_index_users']));
    update_option('xiaowu_search_batch_size', intval($_POST['batch_size']));
    update_option('xiaowu_search_index_post_meta', isset($_POST['index_post_meta']));
    update_option('xiaowu_search_index_taxonomies', isset($_POST['index_taxonomies']));

    echo '<div class="notice notice-success"><p>设置已保存</p></div>';
}
?>

<style>
.xiaowu-search-indexing {
    max-width: 1400px;
}

.indexing-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.index-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.index-stat-card.total {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    color: #fff;
    border: none;
}

.index-stat-card.total .stat-numbers,
.index-stat-card.total h3,
.index-stat-card.total .last-indexed {
    color: #fff;
}

.stat-icon {
    font-size: 48px;
    text-align: center;
}

.stat-info h3 {
    margin: 0;
    font-size: 16px;
    color: #666;
}

.stat-numbers {
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
    margin: 10px 0;
}

.stat-numbers.large {
    font-size: 48px;
}

.stat-numbers .indexed {
    color: #2271b1;
}

.index-stat-card.total .stat-numbers .indexed {
    color: #fff;
}

.stat-numbers .total {
    color: #999;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e5e5;
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    transition: width 0.3s ease;
}

.coverage {
    font-size: 14px;
    color: #666;
    margin: 5px 0;
}

.last-indexed {
    font-size: 13px;
    opacity: 0.9;
    margin: 5px 0;
}

.stat-actions {
    margin-top: auto;
}

.indexing-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.indexing-section h2 {
    margin-top: 0;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 10px;
}

.operation-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.operation-card {
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.operation-card:hover {
    border-color: #2271b1;
    box-shadow: 0 4px 12px rgba(34, 113, 177, 0.1);
    transform: translateY(-2px);
}

.operation-card h3 {
    font-size: 18px;
    margin: 0 0 10px 0;
}

.operation-card p {
    font-size: 14px;
    color: #666;
    margin: 10px 0;
    min-height: 60px;
}

.operation-card .button {
    margin-top: 10px;
}

#indexing-progress {
    margin: 20px 0;
}

.progress-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
}

.progress-container h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
}

.progress-bar-large {
    width: 100%;
    height: 30px;
    background: #e5e5e5;
    border-radius: 15px;
    overflow: hidden;
    margin: 20px 0;
}

.progress-fill-large {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #135e96);
    transition: width 0.5s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: bold;
}

#progress-status {
    font-size: 16px;
    color: #666;
    margin: 10px 0;
}

#progress-details {
    margin-top: 20px;
    padding: 15px;
    background: #f6f7f7;
    border-radius: 4px;
    text-align: left;
    font-family: monospace;
    font-size: 12px;
    max-height: 200px;
    overflow-y: auto;
}

.index-log {
    background: #f6f7f7;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 13px;
}

.log-empty {
    color: #999;
    text-align: center;
}

.log-entry {
    padding: 8px;
    margin: 5px 0;
    background: #fff;
    border-left: 3px solid #2271b1;
    border-radius: 3px;
}

.log-entry.error {
    border-left-color: #d63638;
}

.log-entry.success {
    border-left-color: #00a32a;
}

.log-entry.warning {
    border-left-color: #dba617;
}

.log-time {
    color: #666;
    font-size: 11px;
}
</style>
