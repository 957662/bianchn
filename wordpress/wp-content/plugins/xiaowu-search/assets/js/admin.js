/**
 * 小伍搜索 - 管理后台JavaScript
 *
 * @package Xiaowu_Search
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // 等待DOM加载完成
    $(document).ready(function() {
        // 初始化各个页面的功能
        initSettingsPage();
        initAnalyticsPage();
        initIndexingPage();
    });

    /**
     * 设置页面初始化
     */
    function initSettingsPage() {
        if (!$('.xiaowu-search-settings').length) {
            return;
        }

        // AI服务商切换
        $('input[name="ai_provider"]').on('change', function() {
            const provider = $(this).val();
            $('.ai-provider-settings').hide();
            $(`.ai-provider-settings[data-provider="${provider}"]`).show();
        });

        // 测试AI连接
        $('#test-ai-connection').on('click', function() {
            const $btn = $(this);
            const provider = $('input[name="ai_provider"]:checked').val();
            const apiKey = $(`input[name="${provider}_api_key"]`).val();

            if (!apiKey) {
                alert('请先输入API密钥');
                return;
            }

            $btn.prop('disabled', true).text('测试中...');

            $.ajax({
                url: xiaowuSearchAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_test_ai_connection',
                    provider: provider,
                    api_key: apiKey,
                    nonce: xiaowuSearchAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('连接成功!\n' + response.message);
                    } else {
                        alert('连接失败: ' + response.message);
                    }
                    $btn.prop('disabled', false).text('测试连接');
                },
                error: function() {
                    alert('测试失败,请检查网络连接');
                    $btn.prop('disabled', false).text('测试连接');
                }
            });
        });

        // 清除缓存
        $('#clear-cache-btn').on('click', function() {
            const $btn = $(this);

            if (!confirm('确定要清除所有搜索缓存吗？')) {
                return;
            }

            $btn.prop('disabled', true).text('清除中...');

            $.ajax({
                url: xiaowuSearchAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_clear_cache',
                    nonce: xiaowuSearchAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('缓存已清除');
                    } else {
                        alert('清除失败: ' + response.message);
                    }
                    $btn.prop('disabled', false).text('清除缓存');
                },
                error: function() {
                    alert('清除失败');
                    $btn.prop('disabled', false).text('清除缓存');
                }
            });
        });

        // 重置统计
        $('#reset-stats-btn').on('click', function() {
            const $btn = $(this);

            if (!confirm('确定要重置所有搜索统计数据吗？此操作不可恢复！')) {
                return;
            }

            $btn.prop('disabled', true).text('重置中...');

            $.ajax({
                url: xiaowuSearchAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_reset_stats',
                    nonce: xiaowuSearchAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('统计数据已重置');
                        location.reload();
                    } else {
                        alert('重置失败: ' + response.message);
                    }
                    $btn.prop('disabled', false).text('重置统计');
                },
                error: function() {
                    alert('重置失败');
                    $btn.prop('disabled', false).text('重置统计');
                }
            });
        });
    }

    /**
     * 分析页面初始化
     */
    function initAnalyticsPage() {
        if (!$('.xiaowu-search-analytics').length) {
            return;
        }

        // 初始化搜索趋势图
        initSearchTrendsChart();

        // 导出CSV
        $('#export-csv').on('click', function() {
            exportAnalytics('csv', $(this));
        });

        // 导出JSON
        $('#export-json').on('click', function() {
            exportAnalytics('json', $(this));
        });
    }

    /**
     * 初始化搜索趋势图
     */
    function initSearchTrendsChart() {
        const trendsData = window.xiaowuSearchTrends || [];

        if (trendsData.length === 0 || !document.getElementById('search-trends-chart')) {
            return;
        }

        const ctx = document.getElementById('search-trends-chart');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => item.date),
                datasets: [{
                    label: '搜索次数',
                    data: trendsData.map(item => item.count),
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
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
    }

    /**
     * 导出分析数据
     */
    function exportAnalytics(format, $btn) {
        const days = new URLSearchParams(window.location.search).get('days') || 30;

        $btn.prop('disabled', true).text('导出中...');

        $.ajax({
            url: xiaowuSearchAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'xiaowu_export_analytics',
                format: format,
                days: days,
                nonce: xiaowuSearchAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const mimeType = format === 'csv' ? 'text/csv' : 'application/json';
                    const blob = new Blob([response.data], { type: mimeType });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'search-analytics-' + Date.now() + '.' + format;
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('导出失败: ' + response.message);
                }
                $btn.prop('disabled', false).text('导出' + format.toUpperCase());
            },
            error: function() {
                alert('导出失败');
                $btn.prop('disabled', false).text('导出' + format.toUpperCase());
            }
        });
    }

    /**
     * 索引管理页面初始化
     */
    function initIndexingPage() {
        if (!$('.xiaowu-search-indexing').length) {
            return;
        }

        let indexing = false;

        // 重建特定类型索引
        $('.index-type-btn').on('click', function() {
            if (indexing) {
                alert('正在执行索引操作,请稍候...');
                return;
            }

            const type = $(this).data('type');
            const typeNames = {
                'posts': '文章',
                'comments': '评论',
                'users': '用户'
            };

            if (!confirm(`确定要重建${typeNames[type]}索引吗？`)) {
                return;
            }

            startIndexing(type, typeNames[type]);
        });

        // 重建全部索引
        $('#rebuild-all-btn, #full-rebuild-btn').on('click', function() {
            if (indexing) {
                alert('正在执行索引操作,请稍候...');
                return;
            }

            if (!confirm('确定要重建全部索引吗？这可能需要较长时间。')) {
                return;
            }

            startIndexing('all', '全部内容');
        });

        // 优化索引
        $('#optimize-btn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).text('优化中...');

            $.ajax({
                url: xiaowuSearchAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_optimize_index',
                    nonce: xiaowuSearchAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        addLog('success', '索引优化完成');
                        alert('索引优化完成');
                        location.reload();
                    } else {
                        addLog('error', '优化失败: ' + response.message);
                        alert('优化失败');
                    }
                    $btn.prop('disabled', false).text('立即优化');
                },
                error: function() {
                    addLog('error', '优化失败: 网络错误');
                    alert('优化失败');
                    $btn.prop('disabled', false).text('立即优化');
                }
            });
        });

        // 检查索引
        $('#check-index-btn').on('click', function() {
            const $btn = $(this);
            $btn.prop('disabled', true).text('检查中...');

            $.ajax({
                url: xiaowuSearchAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_check_index',
                    nonce: xiaowuSearchAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        let message = `索引检查完成:\n`;
                        message += `- 未索引文章: ${data.missing_posts || 0}\n`;
                        message += `- 未索引评论: ${data.missing_comments || 0}\n`;
                        message += `- 未索引用户: ${data.missing_users || 0}\n`;
                        message += `- 孤立索引: ${data.orphaned || 0}`;
                        alert(message);
                        addLog('success', message.replace(/\n/g, '<br>'));
                    } else {
                        alert('检查失败');
                    }
                    $btn.prop('disabled', false).text('开始检查');
                },
                error: function() {
                    alert('检查失败');
                    $btn.prop('disabled', false).text('开始检查');
                }
            });
        });

        /**
         * 开始索引
         */
        function startIndexing(type, typeName) {
            indexing = true;
            $('#indexing-progress').show();
            $('#progress-title').text(`正在重建${typeName}索引`);
            $('#progress-bar-fill').css('width', '0%').text('0%');
            $('#progress-status').text('初始化...');
            $('#progress-details').html('');
            addLog('info', `开始重建${typeName}索引`);

            $.ajax({
                url: xiaowuSearchAdmin.restUrl + 'index/rebuild',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuSearchAdmin.restNonce
                },
                data: {
                    type: type
                },
                success: function(response) {
                    $('#progress-bar-fill').css('width', '100%').text('100%');
                    $('#progress-status').text('索引重建完成!');
                    addLog('success', `${typeName}索引重建完成`);

                    setTimeout(function() {
                        $('#indexing-progress').hide();
                        indexing = false;
                        location.reload();
                    }, 2000);
                },
                error: function() {
                    $('#progress-status').text('索引重建失败');
                    addLog('error', `${typeName}索引重建失败`);
                    indexing = false;

                    setTimeout(function() {
                        $('#indexing-progress').hide();
                    }, 3000);
                }
            });
        }

        /**
         * 添加日志
         */
        function addLog(type, message) {
            const $log = $('#index-log');
            if ($log.find('.log-empty').length) {
                $log.empty();
            }

            const time = new Date().toLocaleTimeString('zh-CN');
            const entry = $('<div>')
                .addClass('log-entry')
                .addClass(type)
                .html(`<span class="log-time">[${time}]</span> ${message}`);

            $log.prepend(entry);

            // 限制日志数量
            if ($log.find('.log-entry').length > 50) {
                $log.find('.log-entry:last').remove();
            }
        }
    }

})(jQuery);
