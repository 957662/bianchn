<?php
/**
 * 搜索分析页面
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取分析数据
$analytics = new Xiaowu_Search_Analytics();
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$stats_result = $analytics->get_stats($days);
$stats = $stats_result['success'] ? $stats_result['data'] : array();

$quality_result = $analytics->get_quality_metrics($days);
$quality = $quality_result['success'] ? $quality_result['data'] : array();
?>

<div class="wrap xiaowu-search-analytics">
    <h1>搜索分析</h1>

    <div class="analytics-filters">
        <form method="get">
            <input type="hidden" name="page" value="xiaowu-search-analytics">
            <label>
                时间范围:
                <select name="days" onchange="this.form.submit()">
                    <option value="7" <?php selected($days, 7); ?>>最近7天</option>
                    <option value="30" <?php selected($days, 30); ?>>最近30天</option>
                    <option value="90" <?php selected($days, 90); ?>>最近90天</option>
                    <option value="365" <?php selected($days, 365); ?>>最近一年</option>
                </select>
            </label>
        </form>

        <div class="export-buttons">
            <button type="button" id="export-csv" class="button">导出CSV</button>
            <button type="button" id="export-json" class="button">导出JSON</button>
        </div>
    </div>

    <!-- 统计概览 -->
    <div class="analytics-overview">
        <div class="stat-card">
            <h3>总搜索次数</h3>
            <p class="stat-number"><?php echo number_format($stats['total_searches'] ?? 0); ?></p>
        </div>

        <div class="stat-card">
            <h3>独立查询词</h3>
            <p class="stat-number"><?php echo number_format($stats['unique_queries'] ?? 0); ?></p>
        </div>

        <div class="stat-card">
            <h3>独立用户数</h3>
            <p class="stat-number"><?php echo number_format($stats['unique_users'] ?? 0); ?></p>
        </div>

        <div class="stat-card">
            <h3>平均结果数</h3>
            <p class="stat-number"><?php echo number_format($stats['avg_results_per_search'] ?? 0, 1); ?></p>
        </div>
    </div>

    <!-- 质量指标 -->
    <div class="analytics-section">
        <h2>搜索质量指标</h2>
        <div class="quality-metrics">
            <div class="metric-card">
                <h4>点击率</h4>
                <p class="metric-value"><?php echo number_format($quality['click_through_rate'] ?? 0, 2); ?>%</p>
                <p class="metric-description">用户点击搜索结果的比率</p>
            </div>

            <div class="metric-card">
                <h4>零结果率</h4>
                <p class="metric-value"><?php echo number_format($quality['zero_result_rate'] ?? 0, 2); ?>%</p>
                <p class="metric-description">没有找到结果的搜索比率</p>
            </div>

            <div class="metric-card">
                <h4>平均点击位置</h4>
                <p class="metric-value"><?php echo number_format($quality['avg_click_position'] ?? 0, 1); ?></p>
                <p class="metric-description">用户点击结果的平均排名</p>
            </div>

            <div class="metric-card">
                <h4>查询优化率</h4>
                <p class="metric-value"><?php echo number_format($quality['refinement_rate'] ?? 0, 2); ?>%</p>
                <p class="metric-description">用户修改查询词的比率</p>
            </div>
        </div>
    </div>

    <!-- 热门查询 -->
    <div class="analytics-section">
        <h2>热门查询</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%">排名</th>
                    <th width="50%">查询词</th>
                    <th width="15%">搜索次数</th>
                    <th width="15%">点击次数</th>
                    <th width="15%">点击率</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stats['top_queries'])): ?>
                    <?php foreach ($stats['top_queries'] as $index => $query): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo esc_html($query['query']); ?></strong></td>
                            <td><?php echo number_format($query['count']); ?></td>
                            <td><?php echo number_format($query['clicks'] ?? 0); ?></td>
                            <td>
                                <?php
                                $ctr = $query['count'] > 0 ? ($query['clicks'] ?? 0) / $query['count'] * 100 : 0;
                                echo number_format($ctr, 2) . '%';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 零结果查询 -->
    <div class="analytics-section">
        <h2>零结果查询</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="10%">排名</th>
                    <th width="60%">查询词</th>
                    <th width="30%">搜索次数</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stats['no_result_queries'])): ?>
                    <?php foreach ($stats['no_result_queries'] as $index => $query): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><strong><?php echo esc_html($query['query']); ?></strong></td>
                            <td><?php echo number_format($query['count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 搜索趋势图 -->
    <div class="analytics-section">
        <h2>搜索趋势</h2>
        <canvas id="search-trends-chart" width="800" height="300"></canvas>
    </div>

<script>
// 将趋势数据传递给外部JS
window.xiaowuSearchTrends = <?php echo json_encode($stats['search_trends'] ?? array()); ?>;
</script>

    <!-- 热门内容 -->
    <div class="analytics-section">
        <h2>热门内容(按点击)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="5%">排名</th>
                    <th width="55%">内容标题</th>
                    <th width="15%">类型</th>
                    <th width="15%">点击次数</th>
                    <th width="10%">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($stats['popular_content'])): ?>
                    <?php foreach ($stats['popular_content'] as $index => $content): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo esc_html($content['title']); ?></strong>
                            </td>
                            <td>
                                <?php
                                $type_labels = array(
                                    'post' => '文章',
                                    'comment' => '评论',
                                    'user' => '用户'
                                );
                                echo $type_labels[$content['type']] ?? $content['type'];
                                ?>
                            </td>
                            <td><?php echo number_format($content['clicks']); ?></td>
                            <td>
                                <a href="<?php echo esc_url($content['url'] ?? '#'); ?>" target="_blank" class="button button-small">查看</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.xiaowu-search-analytics {
    max-width: 1400px;
}

.analytics-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.analytics-filters label {
    font-weight: 600;
}

.analytics-filters select {
    margin-left: 10px;
    padding: 5px 10px;
}

.export-buttons {
    display: flex;
    gap: 10px;
}

.analytics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    font-weight: 600;
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    color: #2271b1;
    margin: 0;
}

.analytics-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.analytics-section h2 {
    margin-top: 0;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 10px;
}

.quality-metrics {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.metric-card {
    background: #f6f7f7;
    border-left: 4px solid #2271b1;
    padding: 15px;
    border-radius: 4px;
}

.metric-card h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
}

.metric-value {
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
    margin: 5px 0;
}

.metric-description {
    font-size: 12px;
    color: #666;
    margin: 5px 0 0 0;
}

#search-trends-chart {
    max-width: 100%;
    height: auto !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
