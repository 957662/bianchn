/**
 * 小伍用户系统 - 前端脚本
 */

(function($) {
    'use strict';

    const XiaowuUser = {
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.loadUserData();
        },

        bindEvents: function() {
            // 标签页切换
            $('.xiaowu-profile-tab').on('click', this.handleTabClick.bind(this));

            // 关注/取消关注
            $(document).on('click', '.xiaowu-follow-btn', this.handleFollow.bind(this));

            // 发送私信
            $(document).on('click', '.xiaowu-send-btn', this.sendMessage.bind(this));
            $(document).on('keypress', '.xiaowu-message-input', this.handleMessageKeypress.bind(this));

            // 选择对话
            $(document).on('click', '.xiaowu-conversation-item', this.selectConversation.bind(this));

            // 资料编辑
            $('#xiaowu-profile-edit-form').on('submit', this.handleProfileUpdate.bind(this));

            // 头像上传
            $('#xiaowu-avatar-upload').on('change', this.handleAvatarUpload.bind(this));

            // 密码修改
            $('#xiaowu-password-change-form').on('submit', this.handlePasswordChange.bind(this));

            // 用户搜索
            $('#xiaowu-user-search-input').on('input', this.debounce(this.searchUsers.bind(this), 300));

            // 无限滚动
            $('.xiaowu-infinite-scroll').on('scroll', this.handleInfiniteScroll.bind(this));

            // 登录表单
            $('#xiaowu-login-form').on('submit', this.handleLogin.bind(this));

            // 注册表单
            $('#xiaowu-register-form').on('submit', this.handleRegister.bind(this));

            // 每日签到
            $('#xiaowu-daily-checkin').on('click', this.handleDailyCheckin.bind(this));
        },

        initTabs: function() {
            const hash = window.location.hash;
            if (hash) {
                $('.xiaowu-profile-tab[data-tab="' + hash.substring(1) + '"]').click();
            }
        },

        handleTabClick: function(e) {
            e.preventDefault();

            const $tab = $(e.currentTarget);
            const tabName = $tab.data('tab');

            // 更新标签状态
            $('.xiaowu-profile-tab').removeClass('active');
            $tab.addClass('active');

            // 显示对应内容
            $('.xiaowu-profile-tab-content').removeClass('active');
            $('#tab-' + tabName).addClass('active');

            // 更新 URL
            window.history.pushState(null, null, '#' + tabName);

            // 加载标签数据
            this.loadTabData(tabName);
        },

        loadUserData: function() {
            const userId = $('#xiaowu-user-id').val();
            if (!userId) {
                return;
            }

            $.ajax({
                url: xiaowuUser.apiUrl + '/stats/' + userId,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuUser.updateStatsDisplay(response.data);
                    }
                }
            });
        },

        updateStatsDisplay: function(stats) {
            $('#user-posts-count').text(stats.posts_count);
            $('#user-followers-count').text(stats.followers_count);
            $('#user-following-count').text(stats.following_count);
            $('#user-likes-count').text(stats.likes_received);
        },

        loadTabData: function(tabName) {
            const userId = $('#xiaowu-user-id').val();
            const $content = $('#tab-' + tabName);

            if ($content.data('loaded')) {
                return;
            }

            let endpoint = '';
            switch(tabName) {
                case 'posts':
                    endpoint = '/users/' + userId + '/posts';
                    break;
                case 'followers':
                    endpoint = '/users/' + userId + '/followers';
                    break;
                case 'following':
                    endpoint = '/users/' + userId + '/following';
                    break;
                case 'achievements':
                    endpoint = '/users/' + userId + '/achievements';
                    break;
            }

            if (!endpoint) {
                return;
            }

            $.ajax({
                url: xiaowuUser.apiUrl + endpoint,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                beforeSend: function() {
                    $content.html('<div class="xiaowu-loading"></div>');
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuUser.renderTabContent(tabName, response.data);
                        $content.data('loaded', true);
                    }
                }
            });
        },

        renderTabContent: function(tabName, data) {
            const $content = $('#tab-' + tabName);
            let html = '';

            switch(tabName) {
                case 'followers':
                case 'following':
                    html = this.renderUserList(data);
                    break;
                case 'achievements':
                    html = this.renderAchievements(data);
                    break;
            }

            $content.html(html);
        },

        renderUserList: function(users) {
            if (users.length === 0) {
                return '<div class="xiaowu-empty-state"><p>暂无数据</p></div>';
            }

            let html = '<div class="xiaowu-user-list">';
            users.forEach(function(user) {
                html += '<div class="xiaowu-user-card">';
                html += '<img src="' + user.avatar + '" class="xiaowu-user-card-avatar">';
                html += '<h3 class="xiaowu-user-card-name">' + user.display_name + '</h3>';
                html += '<p class="xiaowu-user-card-username">@' + user.username + '</p>';
                if (user.bio) {
                    html += '<p class="xiaowu-user-card-bio">' + user.bio + '</p>';
                }
                html += '<div class="xiaowu-user-card-stats">';
                html += '<div class="xiaowu-user-card-stat">';
                html += '<div class="xiaowu-user-card-stat-value">' + user.followers_count + '</div>';
                html += '<div class="xiaowu-user-card-stat-label">粉丝</div>';
                html += '</div>';
                html += '<div class="xiaowu-user-card-stat">';
                html += '<div class="xiaowu-user-card-stat-value">' + user.posts_count + '</div>';
                html += '<div class="xiaowu-user-card-stat-label">文章</div>';
                html += '</div>';
                html += '</div>';
                html += '<button class="xiaowu-follow-btn" data-user-id="' + user.user_id + '">关注</button>';
                html += '</div>';
            });
            html += '</div>';

            return html;
        },

        renderAchievements: function(achievements) {
            if (achievements.length === 0) {
                return '<div class="xiaowu-empty-state"><p>还没有获得成就</p></div>';
            }

            let html = '<div class="xiaowu-badges-list">';
            achievements.forEach(function(achievement) {
                html += '<div class="xiaowu-badge-item">';
                html += '<div class="xiaowu-badge-icon">🏆</div>';
                html += '<div class="xiaowu-badge-name">' + achievement.name + '</div>';
                html += '<div class="xiaowu-badge-description">' + achievement.description + '</div>';
                html += '</div>';
            });
            html += '</div>';

            return html;
        },

        handleFollow: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const isFollowing = $btn.hasClass('following');
            const endpoint = isFollowing ? '/unfollow' : '/follow';

            $.ajax({
                url: xiaowuUser.apiUrl + endpoint,
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: {
                    user_id: userId
                },
                beforeSend: function() {
                    $btn.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        $btn.toggleClass('following');
                        $btn.text(isFollowing ? '关注' : '已关注');

                        // 更新统计数字
                        const $count = $('#user-followers-count');
                        const currentCount = parseInt($count.text());
                        $count.text(isFollowing ? currentCount - 1 : currentCount + 1);
                    } else {
                        alert(response.message || '操作失败');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        selectConversation: function(e) {
            const $item = $(e.currentTarget);
            const userId = $item.data('user-id');

            $('.xiaowu-conversation-item').removeClass('active');
            $item.addClass('active');

            this.loadMessages(userId);
        },

        loadMessages: function(userId, page = 1) {
            $.ajax({
                url: xiaowuUser.apiUrl + '/messages/' + userId,
                method: 'GET',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: { page: page },
                beforeSend: function() {
                    if (page === 1) {
                        $('.xiaowu-messages-body').html('<div class="xiaowu-loading"></div>');
                    }
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuUser.renderMessages(response.data, page === 1);
                        $('.xiaowu-messages-body').data('other-user-id', userId);
                    }
                }
            });
        },

        renderMessages: function(messages, clearFirst = true) {
            const $container = $('.xiaowu-messages-body');

            if (clearFirst) {
                $container.empty();
            }

            messages.forEach(function(message) {
                const html = XiaowuUser.createMessageHTML(message);
                $container.append(html);
            });

            // 滚动到底部
            $container.scrollTop($container[0].scrollHeight);
        },

        createMessageHTML: function(message) {
            let html = '<div class="xiaowu-message-item' + (message.is_mine ? ' mine' : '') + '">';
            html += '<img src="' + message.from_avatar + '" class="xiaowu-message-avatar">';
            html += '<div class="xiaowu-message-content">';
            html += '<div class="xiaowu-message-bubble">' + message.content + '</div>';
            html += '<div class="xiaowu-message-time">' + this.formatTime(message.created_at) + '</div>';
            html += '</div>';
            html += '</div>';
            return html;
        },

        sendMessage: function(e) {
            e.preventDefault();

            const $input = $('.xiaowu-message-input');
            const content = $input.val().trim();
            const toUserId = $('.xiaowu-messages-body').data('other-user-id');

            if (!content || !toUserId) {
                return;
            }

            $.ajax({
                url: xiaowuUser.apiUrl + '/messages/send',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: {
                    to_user_id: toUserId,
                    content: content
                },
                beforeSend: function() {
                    $('.xiaowu-send-btn').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        // 添加消息到界面
                        const message = {
                            content: content,
                            is_mine: true,
                            from_avatar: $('#current-user-avatar').val(),
                            created_at: new Date().toISOString()
                        };
                        const html = XiaowuUser.createMessageHTML(message);
                        $('.xiaowu-messages-body').append(html);

                        // 清空输入框
                        $input.val('');

                        // 滚动到底部
                        const $body = $('.xiaowu-messages-body');
                        $body.scrollTop($body[0].scrollHeight);
                    } else {
                        alert(response.message || '发送失败');
                    }
                },
                complete: function() {
                    $('.xiaowu-send-btn').prop('disabled', false);
                }
            });
        },

        handleMessageKeypress: function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                $('.xiaowu-send-btn').click();
            }
        },

        handleProfileUpdate: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const formData = new FormData($form[0]);

            $.ajax({
                url: xiaowuUser.apiUrl + '/profile/update',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true);
                    $form.find('.xiaowu-notice').remove();
                },
                success: function(response) {
                    if (response.success) {
                        $form.prepend(
                            '<div class="xiaowu-notice success">' +
                            '<p>资料已更新</p>' +
                            '</div>'
                        );

                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $form.prepend(
                            '<div class="xiaowu-notice error">' +
                            '<p>' + (response.message || '更新失败') + '</p>' +
                            '</div>'
                        );
                    }
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        handleAvatarUpload: function(e) {
            const file = e.target.files[0];
            if (!file) {
                return;
            }

            // 预览
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#xiaowu-avatar-preview').attr('src', e.target.result);
            };
            reader.readAsDataURL(file);

            // 上传
            const formData = new FormData();
            formData.append('avatar', file);

            $.ajax({
                url: xiaowuUser.apiUrl + '/profile/avatar',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('头像已更新');
                    } else {
                        alert(response.message || '上传失败');
                    }
                }
            });
        },

        handlePasswordChange: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const oldPassword = $form.find('[name="old_password"]').val();
            const newPassword = $form.find('[name="new_password"]').val();
            const confirmPassword = $form.find('[name="confirm_password"]').val();

            if (newPassword !== confirmPassword) {
                alert('两次输入的新密码不一致');
                return;
            }

            $.ajax({
                url: xiaowuUser.apiUrl + '/profile/password',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: {
                    old_password: oldPassword,
                    new_password: newPassword
                },
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert('密码已修改,请重新登录');
                        location.href = '/login';
                    } else {
                        alert(response.message || '修改失败');
                    }
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
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
                url: xiaowuUser.apiUrl + '/users/search',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                data: { q: query },
                beforeSend: function() {
                    $results.html('<div class="xiaowu-loading"></div>').show();
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        const html = XiaowuUser.renderUserList(response.data);
                        $results.html(html).show();
                    } else {
                        $results.html('<p>未找到用户</p>').show();
                    }
                }
            });
        },

        handleInfiniteScroll: function(e) {
            const $container = $(e.currentTarget);
            const scrollTop = $container.scrollTop();
            const scrollHeight = $container[0].scrollHeight;
            const clientHeight = $container[0].clientHeight;

            if (scrollTop + clientHeight >= scrollHeight - 100) {
                const page = $container.data('current-page') || 1;
                const nextPage = page + 1;

                if ($container.data('loading') || $container.data('no-more')) {
                    return;
                }

                $container.data('loading', true);
                $container.data('current-page', nextPage);

                // 加载下一页数据
                const userId = $('.xiaowu-messages-body').data('other-user-id');
                if (userId) {
                    this.loadMessages(userId, nextPage);
                }

                $container.data('loading', false);
            }
        },

        handleLogin: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const username = $form.find('[name="username"]').val();
            const password = $form.find('[name="password"]').val();

            $.ajax({
                url: xiaowuUser.apiUrl + '/auth/login',
                method: 'POST',
                data: {
                    username: username,
                    password: password
                },
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true);
                    $form.find('.xiaowu-form-error').remove();
                },
                success: function(response) {
                    if (response.success) {
                        location.href = response.data.redirect_url || '/';
                    } else {
                        $form.prepend(
                            '<div class="xiaowu-form-error">' + response.message + '</div>'
                        );
                    }
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        handleRegister: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const formData = $form.serialize();

            $.ajax({
                url: xiaowuUser.apiUrl + '/auth/register',
                method: 'POST',
                data: formData,
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true);
                    $form.find('.xiaowu-form-error').remove();
                },
                success: function(response) {
                    if (response.success) {
                        alert('注册成功,请查收邮件验证');
                        location.href = '/login';
                    } else {
                        $form.prepend(
                            '<div class="xiaowu-form-error">' + response.message + '</div>'
                        );
                    }
                },
                complete: function() {
                    $form.find('button[type="submit"]').prop('disabled', false);
                }
            });
        },

        handleDailyCheckin: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);

            $.ajax({
                url: xiaowuUser.apiUrl + '/level/daily-checkin',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuUser.nonce
                },
                beforeSend: function() {
                    $btn.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        alert('签到成功! 连续签到 ' + response.data.login_streak + ' 天');
                        location.reload();
                    } else {
                        alert(response.message || '签到失败');
                    }
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
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

        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            const seconds = Math.floor(diff / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);

            if (days > 0) {
                return days + '天前';
            } else if (hours > 0) {
                return hours + '小时前';
            } else if (minutes > 0) {
                return minutes + '分钟前';
            } else {
                return '刚刚';
            }
        }
    };

    // 初始化
    $(document).ready(function() {
        XiaowuUser.init();
    });

})(jQuery);
