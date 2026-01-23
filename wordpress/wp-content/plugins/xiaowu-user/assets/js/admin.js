/**
 * 小伍用户系统 - 管理后台脚本
 */

(function($) {
    'use strict';

    const XiaowuUserAdmin = {
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.loadStats();
        },

        bindEvents: function() {
            // 标签页切换
            $('.xiaowu-tabs .nav-tab').on('click', this.handleTabClick.bind(this));

            // 表单提交
            $('.xiaowu-user-settings form').on('submit', this.handleSettingsSave.bind(this));

            // 刷新统计
            $('#xiaowu-refresh-stats').on('click', this.refreshStats.bind(this));

            // 用户搜索
            $('#xiaowu-user-search').on('input', this.debounce(this.searchUsers.bind(this), 300));

            // 用户筛选
            $('#xiaowu-level-filter, #xiaowu-status-filter').on('change', this.filterUsers.bind(this));

            // 批量操作
            $('#xiaowu-bulk-action').on('click', this.handleBulkAction.bind(this));

            // 导出数据
            $('#xiaowu-export-users').on('click', this.exportUsers.bind(this));
        },

        initTabs: function() {
            // 从 URL 获取当前标签
            const hash = window.location.hash;
            if (hash) {
                $('.xiaowu-tabs .nav-tab[href="' + hash + '"]').click();
            }
        },

        handleTabClick: function(e) {
            e.preventDefault();

            const $tab = $(e.currentTarget);
            const target = $tab.attr('href');

            // 更新标签状态
            $('.xiaowu-tabs .nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');

            // 显示对应内容
            $('.xiaowu-tabs .tab-content').removeClass('active');
            $(target).addClass('active');

            // 更新 URL
            window.history.pushState(null, null, target);
        },

        loadStats: function() {
            const $statsContainer = $('#xiaowu-stats-overview');
            if ($statsContainer.length === 0) {
                return;
            }

            $.ajax({
                url: xiaowuUserAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_get_system_stats',
                    nonce: xiaowuUserAdmin.nonce
                },
                beforeSend: function() {
                    $statsContainer.append('<div class="xiaowu-loading"></div>');
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuUserAdmin.updateStatsDisplay(response.data);
                    }
                },
                complete: function() {
                    $statsContainer.find('.xiaowu-loading').remove();
                }
            });
        },

        updateStatsDisplay: function(stats) {
            // 更新统计卡片
            $('#stat-total-users').text(stats.total_users);
            $('#stat-total-posts').text(stats.total_posts);
            $('#stat-total-comments').text(stats.total_comments);
            $('#stat-new-users-today').text(stats.new_users_today);

            // 更新新用户表格
            $('#new-users-today').text(stats.new_users_today);
            $('#new-users-week').text(stats.new_users_week);
            $('#new-users-month').text(stats.new_users_month);

            // 更新活跃用户表格
            $('#active-users-today').text(stats.active_users_today);
            $('#active-users-week').text(stats.active_users_week);
            $('#active-users-month').text(stats.active_users_month);
        },

        refreshStats: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true);

            this.loadStats();

            setTimeout(function() {
                $btn.prop('disabled', false);
            }, 2000);
        },

        handleSettingsSave: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const formData = $form.serialize();

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: formData,
                beforeSend: function() {
                    $form.find('.xiaowu-notice').remove();
                    $form.find('input[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    $form.prepend(
                        '<div class="xiaowu-notice success">' +
                        '<p>设置已保存</p>' +
                        '</div>'
                    );

                    setTimeout(function() {
                        $form.find('.xiaowu-notice').fadeOut();
                    }, 3000);
                },
                error: function() {
                    $form.prepend(
                        '<div class="xiaowu-notice error">' +
                        '<p>保存失败,请重试</p>' +
                        '</div>'
                    );
                },
                complete: function() {
                    $form.find('input[type="submit"]').prop('disabled', false);
                }
            });
        },

        searchUsers: function(e) {
            const query = $(e.currentTarget).val();
            const $results = $('#xiaowu-user-search-results');

            if (query.length < 2) {
                $results.empty().hide();
                return;
            }

            $.ajax({
                url: xiaowuUserAdmin.restUrl + 'search',
                method: 'GET',
                data: { q: query },
                headers: {
                    'X-WP-Nonce': xiaowuUserAdmin.restNonce
                },
                beforeSend: function() {
                    $results.html('<div class="xiaowu-loading"></div>').show();
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        let html = '<ul class="xiaowu-user-search-list">';
                        response.data.forEach(function(user) {
                            html += '<li class="xiaowu-user-search-item" data-user-id="' + user.user_id + '">';
                            html += '<img src="' + user.avatar + '" class="user-avatar">';
                            html += '<div class="user-info">';
                            html += '<strong>' + user.display_name + '</strong>';
                            html += '<span>@' + user.username + '</span>';
                            html += '</div>';
                            html += '</li>';
                        });
                        html += '</ul>';
                        $results.html(html);
                    } else {
                        $results.html('<p>未找到用户</p>');
                    }
                },
                error: function() {
                    $results.html('<p>搜索失败</p>');
                }
            });
        },

        filterUsers: function() {
            const level = $('#xiaowu-level-filter').val();
            const status = $('#xiaowu-status-filter').val();
            const $table = $('#xiaowu-users-table tbody');

            $.ajax({
                url: xiaowuUserAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_filter_users',
                    nonce: xiaowuUserAdmin.nonce,
                    level: level,
                    status: status
                },
                beforeSend: function() {
                    $table.html('<tr><td colspan="6" class="text-center"><div class="xiaowu-loading"></div></td></tr>');
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuUserAdmin.renderUsersTable(response.data);
                    } else {
                        $table.html('<tr><td colspan="6">加载失败</td></tr>');
                    }
                }
            });
        },

        renderUsersTable: function(users) {
            const $table = $('#xiaowu-users-table tbody');

            if (users.length === 0) {
                $table.html('<tr><td colspan="6">没有找到用户</td></tr>');
                return;
            }

            let html = '';
            users.forEach(function(user) {
                html += '<tr>';
                html += '<td><input type="checkbox" class="user-checkbox" value="' + user.user_id + '"></td>';
                html += '<td>';
                html += '<img src="' + user.avatar + '" class="user-avatar">';
                html += '<strong>' + user.display_name + '</strong><br>';
                html += '<span style="color: #666;">@' + user.username + '</span>';
                html += '</td>';
                html += '<td>' + user.email + '</td>';
                html += '<td><span class="level-badge level-' + user.level + '">' + user.level_name + '</span></td>';
                html += '<td>' + user.registration_date + '</td>';
                html += '<td>';
                html += '<div class="action-buttons">';
                html += '<a href="#" class="button button-small" data-action="edit" data-user-id="' + user.user_id + '">编辑</a>';
                html += '<a href="#" class="button button-small" data-action="view" data-user-id="' + user.user_id + '">查看</a>';
                html += '</div>';
                html += '</td>';
                html += '</tr>';
            });

            $table.html(html);
        },

        handleBulkAction: function(e) {
            e.preventDefault();

            const action = $('#xiaowu-bulk-action-select').val();
            const $checkedBoxes = $('.user-checkbox:checked');

            if (!action) {
                alert('请选择操作');
                return;
            }

            if ($checkedBoxes.length === 0) {
                alert('请选择用户');
                return;
            }

            if (!confirm('确定要对 ' + $checkedBoxes.length + ' 个用户执行此操作吗?')) {
                return;
            }

            const userIds = [];
            $checkedBoxes.each(function() {
                userIds.push($(this).val());
            });

            $.ajax({
                url: xiaowuUserAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_bulk_action',
                    nonce: xiaowuUserAdmin.nonce,
                    bulk_action: action,
                    user_ids: userIds
                },
                beforeSend: function() {
                    $('#xiaowu-bulk-action').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert('操作成功');
                        location.reload();
                    } else {
                        alert('操作失败: ' + response.message);
                    }
                },
                complete: function() {
                    $('#xiaowu-bulk-action').prop('disabled', false);
                }
            });
        },

        exportUsers: function(e) {
            e.preventDefault();

            const format = $('#xiaowu-export-format').val() || 'csv';
            const level = $('#xiaowu-level-filter').val();
            const status = $('#xiaowu-status-filter').val();

            const params = new URLSearchParams({
                action: 'xiaowu_export_users',
                nonce: xiaowuUserAdmin.nonce,
                format: format,
                level: level,
                status: status
            });

            window.location.href = xiaowuUserAdmin.ajaxUrl + '?' + params.toString();
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = function() {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // 图表渲染 (如果使用 Chart.js)
        renderChart: function(chartId, data) {
            const $canvas = $('#' + chartId);
            if ($canvas.length === 0 || typeof Chart === 'undefined') {
                return;
            }

            new Chart($canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: data.label,
                        data: data.values,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        // 实时通知
        initRealtimeNotifications: function() {
            setInterval(function() {
                $.ajax({
                    url: xiaowuUserAdmin.ajaxUrl,
                    method: 'POST',
                    data: {
                        action: 'xiaowu_check_notifications',
                        nonce: xiaowuUserAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.count > 0) {
                            XiaowuUserAdmin.showNotificationBadge(response.data.count);
                        }
                    }
                });
            }, 30000); // 每30秒检查一次
        },

        showNotificationBadge: function(count) {
            let $badge = $('#xiaowu-notification-badge');
            if ($badge.length === 0) {
                $badge = $('<span id="xiaowu-notification-badge" class="update-plugins"><span class="plugin-count"></span></span>');
                $('#toplevel_page_xiaowu-user .wp-menu-name').append($badge);
            }
            $badge.find('.plugin-count').text(count);
        }
    };

    // 初始化
    $(document).ready(function() {
        XiaowuUserAdmin.init();
    });

})(jQuery);
