(function($) {
    'use strict';

    /**
     * 小伍AI管理后台JS
     */
    const XiaowuAI = {
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
            // AJAX处理清空缓存
            $(document).on('click', '[data-action="clear-cache"]', function(e) {
                e.preventDefault();
                XiaowuAI.clearCache();
            });

            // AJAX获取任务详情
            $(document).on('click', '[data-action="view-task"]', function(e) {
                e.preventDefault();
                const taskId = $(this).data('task-id');
                XiaowuAI.viewTask(taskId);
            });
        },

        /**
         * 初始化标签页
         */
        initTabs: function() {
            $('.xiaowu-ai-tab').on('click', function() {
                const tabId = $(this).data('tab');

                // 切换标签激活状态
                $('.xiaowu-ai-tab').removeClass('active');
                $(this).addClass('active');

                // 切换内容显示
                $('.xiaowu-ai-tab-content').removeClass('active');
                $('[data-tab-content="' + tabId + '"]').addClass('active');
            });
        },

        /**
         * 显示通知
         */
        showNotice: function(message, type = 'info') {
            const notice = $('<div>')
                .addClass('xiaowu-ai-notice')
                .addClass(type)
                .text(message);

            $('.xiaowu-ai-admin').prepend(notice);

            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * 清空缓存
         */
        clearCache: function() {
            if (!confirm('确定要清空所有缓存吗？')) {
                return;
            }

            $.ajax({
                url: xiaowuAI.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'xiaowu_clear_cache',
                    nonce: xiaowuAI.nonce
                },
                beforeSend: function() {
                    XiaowuAI.showNotice('正在清空缓存...', 'info');
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuAI.showNotice('缓存已清空', 'success');
                    } else {
                        XiaowuAI.showNotice('清空缓存失败：' + response.data, 'error');
                    }
                },
                error: function(xhr) {
                    XiaowuAI.showNotice('清空缓存失败', 'error');
                }
            });
        },

        /**
         * 查看任务详情
         */
        viewTask: function(taskId) {
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
                        XiaowuAI.showTaskModal(response.data);
                    } else {
                        XiaowuAI.showNotice('获取任务详情失败：' + response.data, 'error');
                    }
                },
                error: function() {
                    XiaowuAI.showNotice('获取任务详情失败', 'error');
                }
            });
        },

        /**
         * 显示任务模态框
         */
        showTaskModal: function(task) {
            const modal = $('<div>')
                .addClass('xiaowu-ai-modal')
                .html(`
                    <div class="xiaowu-ai-modal-content">
                        <span class="xiaowu-ai-modal-close">&times;</span>
                        <h2>任务详情 #${task.id}</h2>
                        <div class="xiaowu-ai-card">
                            <table class="widefat">
                                <tr><th>类型</th><td>${task.type}</td></tr>
                                <tr><th>状态</th><td>${task.status}</td></tr>
                                <tr><th>令牌使用</th><td>${task.tokens_used}</td></tr>
                                <tr><th>成本</th><td>¥${parseFloat(task.cost).toFixed(4)}</td></tr>
                                <tr><th>创建时间</th><td>${task.created_at}</td></tr>
                                ${task.completed_at ? `<tr><th>完成时间</th><td>${task.completed_at}</td></tr>` : ''}
                                <tr><th>输入</th><td><pre class="xiaowu-ai-code">${task.input}</pre></td></tr>
                                ${task.result ? `<tr><th>结果</th><td><pre class="xiaowu-ai-code">${task.result}</pre></td></tr>` : ''}
                                ${task.error ? `<tr><th>错误</th><td class="xiaowu-ai-notice error">${task.error}</td></tr>` : ''}
                            </table>
                        </div>
                    </div>
                `);

            $('body').append(modal);

            // 关闭模态框
            modal.find('.xiaowu-ai-modal-close').on('click', function() {
                modal.fadeOut(function() {
                    modal.remove();
                });
            });

            modal.on('click', function(e) {
                if ($(e.target).is('.xiaowu-ai-modal')) {
                    modal.fadeOut(function() {
                        modal.remove();
                    });
                }
            });
        },

        /**
         * API请求封装
         */
        apiRequest: function(endpoint, data = {}, method = 'POST') {
            return $.ajax({
                url: xiaowuAI.apiUrl + endpoint,
                method: method,
                headers: {
                    'X-WP-Nonce': xiaowuAI.nonce
                },
                data: JSON.stringify(data),
                contentType: 'application/json'
            });
        },

        /**
         * 优化文章
         */
        optimizeArticle: function(title, content, type = 'seo') {
            return this.apiRequest('/optimize-article', {
                title: title,
                content: content,
                type: type,
                language: 'zh-CN'
            });
        },

        /**
         * 智能搜索
         */
        smartSearch: function(query, limit = 10) {
            return this.apiRequest('/search', {
                q: query,
                limit: limit,
                semantic: true
            }, 'GET');
        },

        /**
         * 生成代码
         */
        generateCode: function(description, language = 'php', framework = 'wordpress') {
            return this.apiRequest('/generate-code', {
                description: description,
                language: language,
                framework: framework
            });
        },

        /**
         * 联网搜索
         */
        webSearch: function(query, numResults = 5) {
            return this.apiRequest('/web-search', {
                query: query,
                num_results: numResults,
                language: 'zh-CN'
            });
        },

        /**
         * 生成图像
         */
        generateImage: function(prompt, style = 'icon', size = '512x512') {
            return this.apiRequest('/generate-image', {
                prompt: prompt,
                style: style,
                size: size,
                format: 'png',
                num_images: 1
            });
        }
    };

    // 文档加载完成后初始化
    $(document).ready(function() {
        XiaowuAI.init();
    });

    // 暴露到全局
    window.XiaowuAI = XiaowuAI;

})(jQuery);
