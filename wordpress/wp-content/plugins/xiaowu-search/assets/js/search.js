/**
 * 小伍搜索 - 前端JavaScript
 *
 * @package Xiaowu_Search
 * @version 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // 初始化搜索表单
        initSearchForm();

        // 初始化搜索结果
        initSearchResults();
    });

    /**
     * 初始化搜索表单
     */
    function initSearchForm() {
        if (!$('.xiaowu-search-form').length) {
            return;
        }

        const $form = $('.xiaowu-search-form');
        const $input = $('.xiaowu-search-input');
        const $suggestions = $('.search-suggestions');
        const $suggestionsList = $('.suggestions-list');
        const $clearBtn = $('.search-clear-btn');
        const $popularList = $('.popular-searches-list');
        const $historyList = $('.search-history-list');
        let suggestionTimer;

        // 输入框事件
        $input.on('input', function() {
            const query = $(this).val().trim();

            // 显示/隐藏清除按钮
            if (query) {
                $clearBtn.show();
            } else {
                $clearBtn.hide();
                $suggestions.hide();
            }

            // 获取搜索建议
            if ($input.data('enable-suggestions') === 1 && query.length >= 2) {
                clearTimeout(suggestionTimer);
                suggestionTimer = setTimeout(function() {
                    getSuggestions(query);
                }, 300);
            }
        });

        // 清除按钮
        $clearBtn.on('click', function() {
            $input.val('').focus();
            $clearBtn.hide();
            $suggestions.hide();
        });

        // 获取搜索建议
        function getSuggestions(query) {
            $.ajax({
                url: xiaowuSearchData.restUrl + 'suggestions',
                method: 'GET',
                data: {
                    q: query,
                    limit: 8
                },
                success: function(response) {
                    if (response && response.suggestions && response.suggestions.length > 0) {
                        renderSuggestions(response.suggestions);
                        $suggestions.show();
                    } else {
                        $suggestions.hide();
                    }
                }
            });
        }

        // 渲染建议列表
        function renderSuggestions(suggestions) {
            $suggestionsList.empty();

            suggestions.forEach(function(item) {
                const icon = getTypeIcon(item.type);
                const $item = $('<div>')
                    .addClass('suggestion-item')
                    .html(`<span class="suggestion-icon">${icon}</span><span class="suggestion-text">${item.text}</span>`)
                    .on('click', function() {
                        $input.val(item.text);
                        $form.submit();
                    });

                $suggestionsList.append($item);
            });
        }

        // 获取类型图标
        function getTypeIcon(type) {
            const icons = {
                'history': '🕐',
                'popular': '🔥',
                'content': '📄',
                'category': '📁',
                'tag': '🏷️',
                'post': '📝',
                'search': '🔍'
            };
            return icons[type] || '🔍';
        }

        // 点击外部关闭建议
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.xiaowu-search-form-wrapper').length) {
                $suggestions.hide();
            }
        });

        // 加载热门搜索
        if ($popularList.length) {
            $.ajax({
                url: xiaowuSearchData.restUrl + 'popular',
                method: 'GET',
                data: { limit: 10 },
                success: function(response) {
                    if (response && response.length > 0) {
                        $popularList.empty();
                        response.forEach(function(item) {
                            const $tag = $('<span>')
                                .addClass('popular-tag')
                                .text(item.query)
                                .on('click', function() {
                                    $input.val(item.query);
                                    $form.submit();
                                });
                            $popularList.append($tag);
                        });
                    } else {
                        $popularList.html('<span class="empty">暂无热门搜索</span>');
                    }
                },
                error: function() {
                    $popularList.html('<span class="empty">加载失败</span>');
                }
            });
        }

        // 加载搜索历史
        if ($historyList.length) {
            $.ajax({
                url: xiaowuSearchData.restUrl + 'history',
                method: 'GET',
                headers: {
                    'X-WP-Nonce': xiaowuSearchData.restNonce
                },
                data: { limit: 10 },
                success: function(response) {
                    if (response && response.length > 0) {
                        $historyList.empty();
                        response.forEach(function(item) {
                            const $item = $('<div>')
                                .addClass('history-item')
                                .html(`<span class="history-text">${item.query}</span><span class="history-time">${item.search_time}</span>`)
                                .on('click', function() {
                                    $input.val(item.query);
                                    $form.submit();
                                });
                            $historyList.append($item);
                        });
                    } else {
                        $historyList.html('<span class="empty">暂无搜索历史</span>');
                    }
                },
                error: function() {
                    $historyList.html('<span class="empty">加载失败</span>');
                }
            });
        }

        // 清空搜索历史
        $('.clear-history-btn').on('click', function() {
            if (!confirm('确定要清空搜索历史吗？')) {
                return;
            }

            $.ajax({
                url: xiaowuSearchData.restUrl + 'history',
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': xiaowuSearchData.restNonce
                },
                success: function() {
                    $historyList.html('<span class="empty">暂无搜索历史</span>');
                },
                error: function() {
                    alert('清空失败');
                }
            });
        });

        // 语音搜索
        if ($('.voice-search-btn').length) {
            $('.voice-search-btn').on('click', function() {
                if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                    alert('您的浏览器不支持语音搜索');
                    return;
                }

                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                const recognition = new SpeechRecognition();
                recognition.lang = 'zh-CN';
                recognition.continuous = false;

                recognition.onresult = function(event) {
                    const transcript = event.results[0][0].transcript;
                    $input.val(transcript);
                };

                recognition.start();
            });
        }

        // 键盘导航
        $input.on('keydown', function(e) {
            const $items = $suggestionsList.find('.suggestion-item');
            const $active = $items.filter('.active');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if ($active.length === 0) {
                    $items.first().addClass('active');
                } else {
                    $active.removeClass('active').next().addClass('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if ($active.length > 0) {
                    $active.removeClass('active').prev().addClass('active');
                }
            } else if (e.key === 'Enter') {
                if ($active.length > 0) {
                    e.preventDefault();
                    $active.click();
                }
            } else if (e.key === 'Escape') {
                $suggestions.hide();
            }
        });
    }

    /**
     * 初始化搜索结果
     */
    function initSearchResults() {
        if (!$('.xiaowu-search-results-wrapper').length) {
            return;
        }

        // 获取搜索查询词
        const query = new URLSearchParams(window.location.search).get('s') || '';

        // 点击追踪
        $('.search-result-item').on('click', 'a', function() {
            const $item = $(this).closest('.search-result-item');
            const resultId = $item.data('id');
            const resultType = $item.data('type');

            // 发送点击统计
            $.ajax({
                url: xiaowuSearchData.restUrl + 'track-click',
                method: 'POST',
                data: {
                    query: query,
                    result_id: resultId,
                    result_type: resultType
                }
            });
        });

        // 加载相关搜索
        if ($('.related-searches-list').length && query) {
            $.ajax({
                url: xiaowuSearchData.restUrl + 'suggestions',
                method: 'GET',
                data: {
                    q: query,
                    limit: 5
                },
                success: function(response) {
                    const $list = $('.related-searches-list');
                    if (response && response.suggestions && response.suggestions.length > 0) {
                        $list.empty();
                        response.suggestions.forEach(function(item) {
                            const $tag = $('<a>')
                                .addClass('related-tag')
                                .attr('href', window.location.pathname + '?s=' + encodeURIComponent(item.text))
                                .text(item.text);
                            $list.append($tag);
                        });
                    } else {
                        $list.html('<span class="empty">暂无相关建议</span>');
                    }
                },
                error: function() {
                    $('.related-searches-list').html('<span class="empty">加载失败</span>');
                }
            });
        }

        // 结果项悬停效果
        $('.search-result-item').on('mouseenter', function() {
            $(this).addClass('hover');
        }).on('mouseleave', function() {
            $(this).removeClass('hover');
        });
    }

})(jQuery);
