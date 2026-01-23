/**
 * 小伍评论系统 - 管理后台JavaScript
 */

(function($) {
    'use strict';

    /**
     * 管理后台主类
     */
    const XiaowuCommentsAdmin = {
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            // 标签页切换
            $(document).on('click', '.xiaowu-tab', this.switchTab.bind(this));

            // 清除缓存
            $(document).on('click', '.clear-cache-btn', this.clearCache.bind(this));
        },

        /**
         * 初始化标签页
         */
        initTabs: function() {
            // 检查URL中是否有tab参数
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'basic';

            // 激活对应的标签页
            $(`.xiaowu-tab[data-tab="${activeTab}"]`).click();
        },

        /**
         * 切换标签页
         */
        switchTab: function(e) {
            e.preventDefault();

            const $tab = $(e.currentTarget);
            const tabName = $tab.data('tab');

            // 更新标签页状态
            $('.xiaowu-tab').removeClass('active');
            $tab.addClass('active');

            // 更新内容区域
            $('.xiaowu-tab-content').removeClass('active');
            $(`.xiaowu-tab-content[data-tab="${tabName}"]`).addClass('active');

            // 更新URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        },

        /**
         * 清除缓存
         */
        clearCache: function(e) {
            e.preventDefault();

            if (!confirm('确定要清除评论缓存吗?')) {
                return;
            }

            const $btn = $(e.currentTarget);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('清除中...');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'xiaowu_comments_clear_cache',
                    nonce: xiaowuCommentsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuCommentsAdmin.showNotice('缓存已清除', 'success');
                    } else {
                        XiaowuCommentsAdmin.showNotice(response.data || '清除失败', 'error');
                    }
                },
                error: function() {
                    XiaowuCommentsAdmin.showNotice('清除失败', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * 显示通知
         */
        showNotice: function(message, type) {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">忽略此通知</span>
                    </button>
                </div>
            `);

            $('.xiaowu-comments-admin h1').after($notice);

            // 绑定关闭事件
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });

            // 自动关闭
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * 加载统计数据
         */
        loadStats: function() {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'xiaowu_comments_get_stats',
                    nonce: xiaowuCommentsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuCommentsAdmin.updateStats(response.data);
                    }
                }
            });
        },

        /**
         * 更新统计数据
         */
        updateStats: function(stats) {
            $('.xiaowu-comments-stats').each(function() {
                const $card = $(this);
                const statType = $card.data('stat');

                if (stats[statType] !== undefined) {
                    $card.find('.stat-number').text(stats[statType].toLocaleString());
                }
            });
        },

        /**
         * 添加到黑名单
         */
        addToBlacklist: function(email, ip) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'xiaowu_comments_add_blacklist',
                    nonce: xiaowuCommentsAdmin.nonce,
                    email: email,
                    ip: ip
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuCommentsAdmin.showNotice('已添加到黑名单', 'success');
                    } else {
                        XiaowuCommentsAdmin.showNotice(response.data || '添加失败', 'error');
                    }
                }
            });
        },

        /**
         * 从黑名单移除
         */
        removeFromBlacklist: function(email, ip) {
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'xiaowu_comments_remove_blacklist',
                    nonce: xiaowuCommentsAdmin.nonce,
                    email: email,
                    ip: ip
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuCommentsAdmin.showNotice('已从黑名单移除', 'success');
                    } else {
                        XiaowuCommentsAdmin.showNotice(response.data || '移除失败', 'error');
                    }
                }
            });
        },

        /**
         * 测试邮件发送
         */
        testEmail: function() {
            const email = prompt('请输入测试邮箱地址:');
            if (!email) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'xiaowu_comments_test_email',
                    nonce: xiaowuCommentsAdmin.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuCommentsAdmin.showNotice('测试邮件已发送', 'success');
                    } else {
                        XiaowuCommentsAdmin.showNotice(response.data || '发送失败', 'error');
                    }
                }
            });
        },

        /**
         * 导出设置
         */
        exportSettings: function() {
            const settings = {
                antispam_enabled: $('input[name="antispam_enabled"]').is(':checked'),
                notification_enabled: $('input[name="notification_enabled"]').is(':checked'),
                emoji_enabled: $('input[name="emoji_enabled"]').is(':checked'),
                mention_enabled: $('input[name="mention_enabled"]').is(':checked'),
                ai_moderation: $('input[name="ai_moderation"]').is(':checked'),
                spam_threshold: $('input[name="spam_threshold"]').val(),
                max_links: $('input[name="max_links"]').val(),
                max_frequency: $('input[name="max_frequency"]').val(),
                spam_keywords: $('textarea[name="spam_keywords"]').val(),
                blacklist_emails: $('textarea[name="blacklist_emails"]').val(),
                blacklist_ips: $('textarea[name="blacklist_ips"]').val()
            };

            const dataStr = JSON.stringify(settings, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);

            const link = document.createElement('a');
            link.download = 'xiaowu-comments-settings.json';
            link.href = url;
            link.click();

            URL.revokeObjectURL(url);
        },

        /**
         * 导入设置
         */
        importSettings: function(file) {
            const reader = new FileReader();

            reader.onload = function(e) {
                try {
                    const settings = JSON.parse(e.target.result);

                    // 填充表单
                    $('input[name="antispam_enabled"]').prop('checked', settings.antispam_enabled);
                    $('input[name="notification_enabled"]').prop('checked', settings.notification_enabled);
                    $('input[name="emoji_enabled"]').prop('checked', settings.emoji_enabled);
                    $('input[name="mention_enabled"]').prop('checked', settings.mention_enabled);
                    $('input[name="ai_moderation"]').prop('checked', settings.ai_moderation);
                    $('input[name="spam_threshold"]').val(settings.spam_threshold);
                    $('input[name="max_links"]').val(settings.max_links);
                    $('input[name="max_frequency"]').val(settings.max_frequency);
                    $('textarea[name="spam_keywords"]').val(settings.spam_keywords);
                    $('textarea[name="blacklist_emails"]').val(settings.blacklist_emails);
                    $('textarea[name="blacklist_ips"]').val(settings.blacklist_ips);

                    XiaowuCommentsAdmin.showNotice('设置已导入', 'success');
                } catch (error) {
                    XiaowuCommentsAdmin.showNotice('导入失败: ' + error.message, 'error');
                }
            };

            reader.readAsText(file);
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        XiaowuCommentsAdmin.init();
    });

    // 暴露全局对象
    window.xiaowuCommentsAdmin = XiaowuCommentsAdmin;

})(jQuery);
