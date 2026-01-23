/**
 * 小伍评论系统 - 前端JavaScript
 */

(function($) {
    'use strict';

    /**
     * 评论系统主类
     */
    const XiaowuComments = {
        /**
         * 初始化
         */
        init: function() {
            this.bindEvents();
            this.initEmoji();
            this.initMention();
            this.loadComments();
        },

        /**
         * 绑定事件
         */
        bindEvents: function() {
            // 提交评论
            $(document).on('submit', '#commentform', this.submitComment.bind(this));

            // 点赞评论
            $(document).on('click', '.comment-like-btn', this.likeComment.bind(this));

            // 举报评论
            $(document).on('click', '.comment-report-btn', this.reportComment.bind(this));

            // 回复评论
            $(document).on('click', '.comment-reply-link', this.replyComment.bind(this));

            // 加载更多评论
            $(document).on('click', '.load-more-comments', this.loadMoreComments.bind(this));
        },

        /**
         * 初始化表情选择器
         */
        initEmoji: function() {
            if (!xiaowuComments.emojiEnabled) {
                return;
            }

            const $commentField = $('#comment');
            const $emojiPicker = $('.xiaowu-emoji-picker');

            if ($emojiPicker.length === 0) {
                return;
            }

            // 创建表情按钮
            $commentField.after('<button type="button" class="xiaowu-emoji-picker-trigger">😊</button>');

            // 切换表情选择器
            $(document).on('click', '.xiaowu-emoji-picker-trigger', function(e) {
                e.preventDefault();
                $emojiPicker.toggle();
            });

            // 切换表情分类
            $(document).on('click', '.xiaowu-emoji-tab', function() {
                const category = $(this).data('category');
                $('.xiaowu-emoji-tab').removeClass('active');
                $(this).addClass('active');
                $('.xiaowu-emoji-category').removeClass('active');
                $(`.xiaowu-emoji-category[data-category="${category}"]`).addClass('active');
            });

            // 选择表情
            $(document).on('click', '.xiaowu-emoji-item', function() {
                const emoji = $(this).data('emoji');
                const cursorPos = $commentField[0].selectionStart;
                const value = $commentField.val();
                const newValue = value.substring(0, cursorPos) + emoji + value.substring(cursorPos);
                $commentField.val(newValue);
                $commentField.focus();

                // 移动光标到表情后面
                $commentField[0].selectionStart = $commentField[0].selectionEnd = cursorPos + emoji.length;
            });

            // 点击外部关闭
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.xiaowu-emoji-picker, .xiaowu-emoji-picker-trigger').length) {
                    $emojiPicker.hide();
                }
            });

            // 激活第一个分类
            $('.xiaowu-emoji-tab:first').click();
        },

        /**
         * 初始化@提及功能
         */
        initMention: function() {
            if (!xiaowuComments.mentionEnabled) {
                return;
            }

            const $commentField = $('#comment');
            let currentMention = '';
            let mentionStart = -1;

            $commentField.on('keyup', function(e) {
                const value = $(this).val();
                const cursorPos = this.selectionStart;

                // 查找@符号
                let atPos = -1;
                for (let i = cursorPos - 1; i >= 0; i--) {
                    if (value[i] === '@') {
                        atPos = i;
                        break;
                    }
                    if (value[i] === ' ' || value[i] === '\n') {
                        break;
                    }
                }

                if (atPos >= 0) {
                    currentMention = value.substring(atPos + 1, cursorPos);
                    mentionStart = atPos;

                    if (currentMention.length >= 1) {
                        XiaowuComments.searchMentions(currentMention);
                    } else {
                        XiaowuComments.hideMentionAutocomplete();
                    }
                } else {
                    XiaowuComments.hideMentionAutocomplete();
                }
            });

            // 键盘导航
            $commentField.on('keydown', function(e) {
                const $autocomplete = $('.xiaowu-mention-autocomplete');
                if (!$autocomplete.is(':visible')) {
                    return;
                }

                const $items = $autocomplete.find('.xiaowu-mention-item');
                const $active = $items.filter('.active');

                if (e.keyCode === 38) { // 上箭头
                    e.preventDefault();
                    if ($active.length) {
                        $active.removeClass('active').prev().addClass('active');
                    } else {
                        $items.last().addClass('active');
                    }
                } else if (e.keyCode === 40) { // 下箭头
                    e.preventDefault();
                    if ($active.length) {
                        $active.removeClass('active').next().addClass('active');
                    } else {
                        $items.first().addClass('active');
                    }
                } else if (e.keyCode === 13) { // 回车
                    if ($active.length) {
                        e.preventDefault();
                        $active.click();
                    }
                } else if (e.keyCode === 27) { // ESC
                    XiaowuComments.hideMentionAutocomplete();
                }
            });

            // 选择提及
            $(document).on('click', '.xiaowu-mention-item', function() {
                const username = $(this).data('username');
                const value = $commentField.val();
                const newValue = value.substring(0, mentionStart) + '@' + username + ' ' + value.substring($commentField[0].selectionStart);
                $commentField.val(newValue);
                XiaowuComments.hideMentionAutocomplete();
                $commentField.focus();
            });
        },

        /**
         * 搜索可提及的用户
         */
        searchMentions: function(search) {
            $.ajax({
                url: xiaowuComments.apiUrl + '/mentions/search',
                method: 'GET',
                data: { search: search },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        XiaowuComments.showMentionAutocomplete(response.data);
                    } else {
                        XiaowuComments.hideMentionAutocomplete();
                    }
                }
            });
        },

        /**
         * 显示提及自动完成
         */
        showMentionAutocomplete: function(users) {
            let $autocomplete = $('.xiaowu-mention-autocomplete');
            if ($autocomplete.length === 0) {
                $autocomplete = $('<div class="xiaowu-mention-autocomplete"><ul class="xiaowu-mention-list"></ul></div>');
                $('body').append($autocomplete);
            }

            const $list = $autocomplete.find('.xiaowu-mention-list');
            $list.empty();

            users.forEach(function(user) {
                const $item = $(`
                    <li class="xiaowu-mention-item" data-username="${user.username}">
                        <img src="${user.avatar}" alt="" class="xiaowu-mention-avatar">
                        <div class="xiaowu-mention-info">
                            <div class="xiaowu-mention-name">${user.display_name}</div>
                            <div class="xiaowu-mention-username">@${user.username}</div>
                        </div>
                    </li>
                `);
                $list.append($item);
            });

            // 定位自动完成框
            const $commentField = $('#comment');
            const offset = $commentField.offset();
            $autocomplete.css({
                top: offset.top + $commentField.outerHeight(),
                left: offset.left,
                display: 'block'
            });
        },

        /**
         * 隐藏提及自动完成
         */
        hideMentionAutocomplete: function() {
            $('.xiaowu-mention-autocomplete').hide();
        },

        /**
         * 提交评论
         */
        submitComment: function(e) {
            e.preventDefault();

            const $form = $(e.target);
            const $submitBtn = $form.find('input[type="submit"]');
            const formData = {
                post_id: $form.find('input[name="comment_post_ID"]').val(),
                content: $form.find('#comment').val(),
                parent: $form.find('input[name="comment_parent"]').val() || 0,
                author_name: $form.find('#author').val(),
                author_email: $form.find('#email').val(),
                author_url: $form.find('#url').val()
            };

            $submitBtn.prop('disabled', true).val('提交中...');

            $.ajax({
                url: xiaowuComments.apiUrl + '/comments',
                method: 'POST',
                data: formData,
                headers: {
                    'X-WP-Nonce': xiaowuComments.nonce
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuComments.showMessage('评论已发布', 'success');
                        $form.find('#comment').val('');
                        XiaowuComments.loadComments();
                    } else {
                        XiaowuComments.showMessage(response.message || '评论提交失败', 'error');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || '评论提交失败';
                    XiaowuComments.showMessage(message, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).val('提交评论');
                }
            });
        },

        /**
         * 点赞评论
         */
        likeComment: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const commentId = $btn.data('comment-id');

            $.ajax({
                url: xiaowuComments.apiUrl + '/comments/' + commentId + '/like',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': xiaowuComments.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.addClass('liked');
                        $btn.find('.like-count').text(response.likes);
                    } else {
                        XiaowuComments.showMessage(response.message || '点赞失败', 'error');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || '点赞失败';
                    XiaowuComments.showMessage(message, 'error');
                }
            });
        },

        /**
         * 举报评论
         */
        reportComment: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const commentId = $btn.data('comment-id');
            const reason = prompt('请输入举报原因:');

            if (!reason) {
                return;
            }

            $.ajax({
                url: xiaowuComments.apiUrl + '/comments/' + commentId + '/report',
                method: 'POST',
                data: { reason: reason },
                headers: {
                    'X-WP-Nonce': xiaowuComments.nonce
                },
                success: function(response) {
                    if (response.success) {
                        XiaowuComments.showMessage('感谢您的反馈', 'success');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || '举报失败';
                    XiaowuComments.showMessage(message, 'error');
                }
            });
        },

        /**
         * 加载评论
         */
        loadComments: function() {
            const postId = $('input[name="comment_post_ID"]').val();
            if (!postId) {
                return;
            }

            const $commentList = $('.comment-list');
            $commentList.html('<div class="xiaowu-comments-loading"><div class="xiaowu-comments-spinner"></div></div>');

            $.ajax({
                url: xiaowuComments.apiUrl + '/comments',
                method: 'GET',
                data: {
                    post_id: postId,
                    per_page: 20
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        XiaowuComments.renderComments(response.data);
                    } else {
                        $commentList.html('<p class="no-comments">暂无评论</p>');
                    }
                },
                error: function() {
                    $commentList.html('<p class="error">加载评论失败</p>');
                }
            });
        },

        /**
         * 渲染评论
         */
        renderComments: function(comments) {
            const $commentList = $('.comment-list');
            $commentList.empty();

            comments.forEach(function(comment) {
                const $comment = XiaowuComments.renderComment(comment);
                $commentList.append($comment);
            });
        },

        /**
         * 渲染单个评论
         */
        renderComment: function(comment) {
            const likedClass = comment.is_liked ? 'liked' : '';

            return $(`
                <li class="comment" id="comment-${comment.id}">
                    <div class="comment-author">
                        <img src="${comment.author.avatar}" alt="" width="48" height="48">
                        <div>
                            <div class="comment-author-name">${comment.author.name}</div>
                            <div class="comment-metadata">${comment.date}</div>
                        </div>
                    </div>
                    <div class="comment-content">${comment.content}</div>
                    <div class="comment-actions">
                        <button class="comment-action-btn comment-like-btn ${likedClass}" data-comment-id="${comment.id}">
                            <span class="dashicons dashicons-heart"></span>
                            <span class="like-count">${comment.likes}</span>
                        </button>
                        <button class="comment-action-btn comment-reply-link" data-comment-id="${comment.id}">
                            <span class="dashicons dashicons-admin-comments"></span>
                            回复
                        </button>
                        <button class="comment-action-btn comment-report-btn" data-comment-id="${comment.id}">
                            <span class="dashicons dashicons-flag"></span>
                            举报
                        </button>
                    </div>
                </li>
            `);
        },

        /**
         * 显示消息
         */
        showMessage: function(message, type) {
            const $message = $(`<div class="xiaowu-comments-message ${type}">${message}</div>`);
            $('#commentform').before($message);

            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // 页面加载完成后初始化
    $(document).ready(function() {
        XiaowuComments.init();
    });

    // 暴露全局对象
    window.XiaowuComments = XiaowuComments;

})(jQuery);
