<?php
/**
 * AI统计页面
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'xiaowu_ai_tasks';

// 获取统计数据
$total_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$completed_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
$failed_tasks = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
$total_tokens = $wpdb->get_var("SELECT SUM(tokens_used) FROM $table_name");
$total_cost = $wpdb->get_var("SELECT SUM(cost) FROM $table_name");

// 按类型统计
$tasks_by_type = $wpdb->get_results("
    SELECT type, COUNT(*) as count, SUM(tokens_used) as tokens
    FROM $table_name
    GROUP BY type
    ORDER BY count DESC
");

// 最近的任务
$recent_tasks = $wpdb->get_results("
    SELECT *
    FROM $table_name
    ORDER BY created_at DESC
    LIMIT 20
");

// 按日期统计
$tasks_by_date = $wpdb->get_results("
    SELECT DATE(created_at) as date, COUNT(*) as count, SUM(tokens_used) as tokens
    FROM $table_name
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
");

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="xiaowu-ai-stats">
        <!-- 总览卡片 -->
        <div class="stats-cards">
            <div class="stats-card">
                <div class="stats-card-icon">📊</div>
                <div class="stats-card-content">
                    <div class="stats-card-value"><?php echo number_format($total_tasks); ?></div>
                    <div class="stats-card-label">总任务数</div>
                </div>
            </div>

            <div class="stats-card success">
                <div class="stats-card-icon">✅</div>
                <div class="stats-card-content">
                    <div class="stats-card-value"><?php echo number_format($completed_tasks); ?></div>
                    <div class="stats-card-label">成功任务</div>
                </div>
            </div>

            <div class="stats-card error">
                <div class="stats-card-icon">❌</div>
                <div class="stats-card-content">
                    <div class="stats-card-value"><?php echo number_format($failed_tasks); ?></div>
                    <div class="stats-card-label">失败任务</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-card-icon">🎯</div>
                <div class="stats-card-content">
                    <div class="stats-card-value"><?php echo number_format($total_tokens); ?></div>
                    <div class="stats-card-label">总令牌数</div>
                </div>
            </div>

            <div class="stats-card">
                <div class="stats-card-icon">💰</div>
                <div class="stats-card-content">
                    <div class="stats-card-value">¥<?php echo number_format($total_cost, 2); ?></div>
                    <div class="stats-card-label">总成本</div>
                </div>
            </div>
        </div>

        <!-- 按类型统计 -->
        <div class="stats-section">
            <h2>按任务类型统计</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>任务类型</th>
                        <th>数量</th>
                        <th>令牌使用</th>
                        <th>占比</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($tasks_by_type)): ?>
                        <?php foreach ($tasks_by_type as $task): ?>
                        <tr>
                            <td><?php echo esc_html($task->type); ?></td>
                            <td><?php echo number_format($task->count); ?></td>
                            <td><?php echo number_format($task->tokens); ?></td>
                            <td><?php echo round(($task->count / $total_tasks) * 100, 1); ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">暂无数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 最近30天趋势 -->
        <div class="stats-section">
            <h2>最近30天使用趋势</h2>
            <div class="chart-container">
                <canvas id="usageChart"></canvas>
            </div>
        </div>

        <!-- 最近任务 -->
        <div class="stats-section">
            <h2>最近任务</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="15%">类型</th>
                        <th width="10%">状态</th>
                        <th width="10%">令牌</th>
                        <th width="15%">用户</th>
                        <th width="20%">创建时间</th>
                        <th width="25%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_tasks)): ?>
                        <?php foreach ($recent_tasks as $task): ?>
                        <tr>
                            <td><?php echo $task->id; ?></td>
                            <td><?php echo esc_html($task->type); ?></td>
                            <td>
                                <?php if ($task->status === 'completed'): ?>
                                    <span class="status-badge success">完成</span>
                                <?php elseif ($task->status === 'failed'): ?>
                                    <span class="status-badge error">失败</span>
                                <?php else: ?>
                                    <span class="status-badge">处理中</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($task->tokens_used); ?></td>
                            <td><?php echo get_userdata($task->user_id)->display_name ?? 'Unknown'; ?></td>
                            <td><?php echo $task->created_at; ?></td>
                            <td>
                                <button class="button button-small view-task-detail" data-task-id="<?php echo $task->id; ?>">查看详情</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">暂无任务记录</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 任务详情模态框 -->
<div id="task-detail-modal" class="xiaowu-modal" style="display: none;">
    <div class="xiaowu-modal-content">
        <span class="xiaowu-modal-close">&times;</span>
        <h2>任务详情</h2>
        <div id="task-detail-content"></div>
    </div>
</div>

<style>
.xiaowu-ai-stats {
    max-width: 1200px;
}

.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.stats-card.success {
    border-left: 4px solid #46b450;
}

.stats-card.error {
    border-left: 4px solid #dc3232;
}

.stats-card-icon {
    font-size: 32px;
}

.stats-card-value {
    font-size: 28px;
    font-weight: bold;
    color: #2271b1;
}

.stats-card-label {
    font-size: 14px;
    color: #666;
}

.stats-section {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.stats-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.chart-container {
    position: relative;
    height: 300px;
    margin-top: 20px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    background: #f0f0f0;
    color: #666;
}

.status-badge.success {
    background: #ecf7ed;
    color: #46b450;
}

.status-badge.error {
    background: #f9e9e9;
    color: #dc3232;
}

.xiaowu-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.xiaowu-modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
}

.xiaowu-modal-close {
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.xiaowu-modal-close:hover {
    color: #dc3232;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
jQuery(document).ready(function($) {
    // 绘制趋势图
    var tasksByDate = <?php echo json_encode(array_reverse($tasks_by_date)); ?>;
    var dates = tasksByDate.map(function(item) { return item.date; });
    var counts = tasksByDate.map(function(item) { return parseInt(item.count); });

    var ctx = document.getElementById('usageChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: '任务数量',
                data: counts,
                borderColor: '#2271b1',
                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });

    // 查看任务详情
    $('.view-task-detail').on('click', function() {
        var taskId = $(this).data('task-id');
        var modal = $('#task-detail-modal');
        var content = $('#task-detail-content');

        content.html('<p>加载中...</p>');
        modal.show();

        $.ajax({
            url: xiaowuAI.ajaxUrl,
            method: 'POST',
            data: {
                action: 'xiaowu_get_task_detail',
                task_id: taskId,
                nonce: xiaowuAI.nonce
            },
            success: function(response) {
                if (response.success) {
                    var task = response.data;
                    var html = '<table class="widefat">';
                    html += '<tr><th>任务ID</th><td>' + task.id + '</td></tr>';
                    html += '<tr><th>类型</th><td>' + task.type + '</td></tr>';
                    html += '<tr><th>状态</th><td>' + task.status + '</td></tr>';
                    html += '<tr><th>令牌使用</th><td>' + task.tokens_used + '</td></tr>';
                    html += '<tr><th>成本</th><td>¥' + parseFloat(task.cost).toFixed(4) + '</td></tr>';
                    html += '<tr><th>创建时间</th><td>' + task.created_at + '</td></tr>';
                    if (task.completed_at) {
                        html += '<tr><th>完成时间</th><td>' + task.completed_at + '</td></tr>';
                    }
                    html += '<tr><th>输入</th><td><pre>' + task.input + '</pre></td></tr>';
                    if (task.result) {
                        html += '<tr><th>结果</th><td><pre>' + task.result + '</pre></td></tr>';
                    }
                    if (task.error) {
                        html += '<tr><th>错误</th><td class="error">' + task.error + '</td></tr>';
                    }
                    html += '</table>';
                    content.html(html);
                } else {
                    content.html('<p class="error">加载失败：' + response.data + '</p>');
                }
            },
            error: function() {
                content.html('<p class="error">加载失败</p>');
            }
        });
    });

    // 关闭模态框
    $('.xiaowu-modal-close').on('click', function() {
        $('#task-detail-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).is('#task-detail-modal')) {
            $('#task-detail-modal').hide();
        }
    });
});
</script>
