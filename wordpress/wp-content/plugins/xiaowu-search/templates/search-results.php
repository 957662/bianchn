<?php
/**
 * 搜索结果模板
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取搜索参数
$query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$order_by = isset($_GET['order_by']) ? sanitize_text_field($_GET['order_by']) : 'relevance';

// 执行搜索
$search_engine = new Xiaowu_Search_Engine();
$result = $search_engine->search($query, array(
    'type' => $type,
    'page' => $page,
    'per_page' => get_option('xiaowu_search_results_per_page', 20),
    'order_by' => $order_by
));

$data = $result['success'] ? $result['data'] : array();
$results = $data['results'] ?? array();
$total = $data['total'] ?? 0;
$total_pages = $data['total_pages'] ?? 1;
?>

<div class="xiaowu-search-results-wrapper">
    <?php if ($query): ?>
        <!-- 搜索头部 -->
        <div class="search-results-header">
            <h1 class="search-title">
                搜索结果: <span class="search-query"><?php echo esc_html($query); ?></span>
            </h1>
            <p class="search-meta">
                找到 <strong><?php echo number_format($total); ?></strong> 个结果
                <?php if ($total > 0): ?>
                    <span class="search-time">(耗时 <?php echo number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3); ?> 秒)</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- 搜索工具栏 -->
        <div class="search-toolbar">
            <div class="search-filters-bar">
                <label class="filter-label">类型:</label>
                <a href="<?php echo esc_url(add_query_arg(array('type' => 'all'))); ?>" class="filter-link <?php echo $type === 'all' ? 'active' : ''; ?>">
                    全部 (<?php echo $total; ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('type' => 'post'))); ?>" class="filter-link <?php echo $type === 'post' ? 'active' : ''; ?>">
                    文章
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('type' => 'comment'))); ?>" class="filter-link <?php echo $type === 'comment' ? 'active' : ''; ?>">
                    评论
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('type' => 'user'))); ?>" class="filter-link <?php echo $type === 'user' ? 'active' : ''; ?>">
                    用户
                </a>
            </div>

            <div class="search-sort-bar">
                <label class="sort-label">排序:</label>
                <select class="sort-select" onchange="window.location.href=this.value">
                    <option value="<?php echo esc_url(add_query_arg(array('order_by' => 'relevance'))); ?>" <?php selected($order_by, 'relevance'); ?>>
                        相关性
                    </option>
                    <option value="<?php echo esc_url(add_query_arg(array('order_by' => 'date'))); ?>" <?php selected($order_by, 'date'); ?>>
                        最新发布
                    </option>
                    <option value="<?php echo esc_url(add_query_arg(array('order_by' => 'views'))); ?>" <?php selected($order_by, 'views'); ?>>
                        浏览量
                    </option>
                </select>
            </div>
        </div>

        <!-- 搜索结果列表 -->
        <?php if (!empty($results)): ?>
            <div class="search-results-list">
                <?php foreach ($results as $item): ?>
                    <article class="search-result-item" data-type="<?php echo esc_attr($item['type']); ?>" data-id="<?php echo esc_attr($item['id']); ?>">
                        <?php if ($item['type'] === 'post'): ?>
                            <!-- 文章结果 -->
                            <div class="result-content">
                                <?php if (!empty($item['thumbnail'])): ?>
                                    <div class="result-thumbnail">
                                        <a href="<?php echo esc_url($item['url']); ?>">
                                            <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['title']); ?>">
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <div class="result-main">
                                    <div class="result-header">
                                        <span class="result-type-badge post">文章</span>
                                        <h2 class="result-title">
                                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
                                        </h2>
                                    </div>

                                    <div class="result-excerpt">
                                        <?php echo wp_kses_post($item['content']); ?>
                                    </div>

                                    <div class="result-meta">
                                        <span class="meta-item author">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M7 7a3 3 0 100-6 3 3 0 000 6zM2 13a5 5 0 0110 0H2z"/></svg>
                                            <?php echo esc_html($item['author']); ?>
                                        </span>
                                        <span class="meta-item date">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M7 13A6 6 0 107 1a6 6 0 000 12zM7 3v4l3 2"/></svg>
                                            <?php echo date('Y-m-d', strtotime($item['date'])); ?>
                                        </span>
                                        <?php if (!empty($item['categories'])): ?>
                                            <span class="meta-item categories">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M2 2h4v4H2V2zM8 2h4v4H8V2zM2 8h4v4H2V8zM8 8h4v4H8V8z"/></svg>
                                                <?php echo implode(', ', array_slice($item['categories'], 0, 3)); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($item['relevance'])): ?>
                                            <span class="meta-item relevance">
                                                相关度: <?php echo number_format($item['relevance'] * 100, 0); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($item['type'] === 'comment'): ?>
                            <!-- 评论结果 -->
                            <div class="result-content">
                                <div class="result-main">
                                    <div class="result-header">
                                        <span class="result-type-badge comment">评论</span>
                                        <h2 class="result-title">
                                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
                                        </h2>
                                    </div>

                                    <div class="result-excerpt">
                                        <?php echo wp_kses_post($item['content']); ?>
                                    </div>

                                    <div class="result-meta">
                                        <span class="meta-item author">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M7 7a3 3 0 100-6 3 3 0 000 6zM2 13a5 5 0 0110 0H2z"/></svg>
                                            <?php echo esc_html($item['author']); ?>
                                        </span>
                                        <span class="meta-item date">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor"><path d="M7 13A6 6 0 107 1a6 6 0 000 12zM7 3v4l3 2"/></svg>
                                            <?php echo date('Y-m-d', strtotime($item['date'])); ?>
                                        </span>
                                        <?php if (isset($item['post_title'])): ?>
                                            <span class="meta-item post-link">
                                                评论于: <a href="<?php echo esc_url($item['post_url']); ?>"><?php echo esc_html($item['post_title']); ?></a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        <?php elseif ($item['type'] === 'user'): ?>
                            <!-- 用户结果 -->
                            <div class="result-content">
                                <div class="result-avatar">
                                    <a href="<?php echo esc_url($item['url']); ?>">
                                        <img src="<?php echo esc_url($item['avatar']); ?>" alt="<?php echo esc_attr($item['title']); ?>">
                                    </a>
                                </div>

                                <div class="result-main">
                                    <div class="result-header">
                                        <span class="result-type-badge user">用户</span>
                                        <h2 class="result-title">
                                            <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
                                        </h2>
                                    </div>

                                    <div class="result-excerpt">
                                        <?php echo wp_kses_post($item['excerpt']); ?>
                                    </div>

                                    <div class="result-meta">
                                        <span class="meta-item username">
                                            @<?php echo esc_html($item['username']); ?>
                                        </span>
                                        <span class="meta-item posts-count">
                                            <?php echo number_format($item['posts_count']); ?> 篇文章
                                        </span>
                                        <span class="meta-item date">
                                            加入于 <?php echo date('Y-m-d', strtotime($item['date'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <nav class="search-pagination">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('page', '%#%'),
                        'format' => '',
                        'current' => $page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo; 上一页',
                        'next_text' => '下一页 &raquo;',
                        'type' => 'list'
                    ));
                    ?>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <!-- 无结果 -->
            <div class="search-no-results">
                <div class="no-results-icon">🔍</div>
                <h2>未找到相关结果</h2>
                <p>抱歉,没有找到与 "<strong><?php echo esc_html($query); ?></strong>" 相关的内容</p>

                <div class="no-results-suggestions">
                    <h3>建议:</h3>
                    <ul>
                        <li>检查拼写是否正确</li>
                        <li>尝试使用不同的关键词</li>
                        <li>使用更通用的关键词</li>
                        <li>减少关键词数量</li>
                    </ul>
                </div>

                <!-- 相关搜索建议 -->
                <div class="related-searches">
                    <h3>您可能想搜索:</h3>
                    <div class="related-searches-list">
                        <span class="loading">正在加载建议...</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- 空搜索 -->
        <div class="search-empty">
            <div class="empty-icon">🔍</div>
            <h2>请输入搜索关键词</h2>
            <p>在上方输入框中输入您要搜索的内容</p>
        </div>
    <?php endif; ?>
</div>
