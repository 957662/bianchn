<?php
/**
 * 搜索表单模板
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取当前查询词
$query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$placeholder = isset($args['placeholder']) ? $args['placeholder'] : '搜索文章、评论、用户...';
$show_suggestions = isset($args['show_suggestions']) ? $args['show_suggestions'] : true;
$enable_voice = isset($args['enable_voice']) ? $args['enable_voice'] : false;
?>

<div class="xiaowu-search-form-wrapper">
    <form role="search" method="get" class="xiaowu-search-form" action="<?php echo esc_url(home_url('/')); ?>">
        <div class="search-input-wrapper">
            <span class="search-icon">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM19 19l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>

            <input type="search"
                   class="xiaowu-search-input"
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   value="<?php echo esc_attr($query); ?>"
                   name="s"
                   autocomplete="off"
                   data-enable-suggestions="<?php echo $show_suggestions ? '1' : '0'; ?>">

            <?php if ($enable_voice): ?>
            <button type="button" class="voice-search-btn" title="语音搜索">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 1a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V4a3 3 0 0 1 3-3zM4 10v1a6 6 0 0 0 12 0v-1M10 17v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <?php endif; ?>

            <button type="submit" class="search-submit-btn">
                搜索
            </button>

            <button type="button" class="search-clear-btn" style="display: none;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        </div>

        <!-- 搜索建议下拉框 -->
        <?php if ($show_suggestions): ?>
        <div class="search-suggestions" style="display: none;">
            <div class="suggestions-list"></div>
        </div>
        <?php endif; ?>

        <!-- 搜索过滤器 -->
        <div class="search-filters">
            <label class="filter-item">
                <input type="radio" name="type" value="all" <?php checked(!isset($_GET['type']) || $_GET['type'] === 'all'); ?>>
                <span>全部</span>
            </label>
            <label class="filter-item">
                <input type="radio" name="type" value="post" <?php checked(isset($_GET['type']) && $_GET['type'] === 'post'); ?>>
                <span>文章</span>
            </label>
            <label class="filter-item">
                <input type="radio" name="type" value="comment" <?php checked(isset($_GET['type']) && $_GET['type'] === 'comment'); ?>>
                <span>评论</span>
            </label>
            <label class="filter-item">
                <input type="radio" name="type" value="user" <?php checked(isset($_GET['type']) && $_GET['type'] === 'user'); ?>>
                <span>用户</span>
            </label>
        </div>
    </form>

    <!-- 热门搜索 -->
    <?php if (!$query && $show_suggestions): ?>
    <div class="popular-searches">
        <h4>🔥 热门搜索</h4>
        <div class="popular-searches-list">
            <span class="loading">加载中...</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- 搜索历史 -->
    <?php if (is_user_logged_in() && !$query): ?>
    <div class="search-history">
        <div class="history-header">
            <h4>📝 搜索历史</h4>
            <button type="button" class="clear-history-btn">清空</button>
        </div>
        <div class="search-history-list">
            <span class="loading">加载中...</span>
        </div>
    </div>
    <?php endif; ?>
</div>
