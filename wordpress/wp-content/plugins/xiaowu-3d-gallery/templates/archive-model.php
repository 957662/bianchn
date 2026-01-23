<?php
/**
 * 3D模型归档页面模板
 *
 * @package Xiaowu_3D_Gallery
 */

get_header();
?>

<div class="xiaowu-3d-archive">
    <header class="page-header">
        <?php
        if (is_tax('model_category')) {
            $term = get_queried_object();
            echo '<h1 class="page-title">分类: ' . esc_html($term->name) . '</h1>';
            if ($term->description) {
                echo '<div class="taxonomy-description">' . wpautop($term->description) . '</div>';
            }
        } elseif (is_tax('model_tag')) {
            $term = get_queried_object();
            echo '<h1 class="page-title">标签: ' . esc_html($term->name) . '</h1>';
        } elseif (is_post_type_archive('xiaowu_3d_model')) {
            echo '<h1 class="page-title">3D模型图库</h1>';
            echo '<p class="archive-description">浏览我们的3D模型收藏</p>';
        }
        ?>
    </header>

    <?php
    // 分类筛选
    $categories = get_terms(array(
        'taxonomy' => 'model_category',
        'hide_empty' => true
    ));

    if ($categories && !is_wp_error($categories)) :
    ?>
    <div class="model-filters">
        <div class="filter-group">
            <label>分类筛选:</label>
            <select id="category-filter" onchange="window.location.href=this.value">
                <option value="<?php echo get_post_type_archive_link('xiaowu_3d_model'); ?>">所有分类</option>
                <?php
                $current_term = is_tax() ? get_queried_object()->term_id : 0;
                foreach ($categories as $category) {
                    $selected = ($category->term_id === $current_term) ? 'selected' : '';
                    echo '<option value="' . get_term_link($category) . '" ' . $selected . '>' .
                         esc_html($category->name) . ' (' . $category->count . ')</option>';
                }
                ?>
            </select>
        </div>

        <div class="filter-group">
            <label>排序:</label>
            <select id="sort-filter" onchange="window.location.href=this.value">
                <?php
                $current_orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'date';
                $base_url = add_query_arg(array(), remove_query_arg('orderby'));
                ?>
                <option value="<?php echo add_query_arg('orderby', 'date', $base_url); ?>"
                        <?php selected($current_orderby, 'date'); ?>>最新发布</option>
                <option value="<?php echo add_query_arg('orderby', 'views', $base_url); ?>"
                        <?php selected($current_orderby, 'views'); ?>>最多浏览</option>
                <option value="<?php echo add_query_arg('orderby', 'title', $base_url); ?>"
                        <?php selected($current_orderby, 'title'); ?>>按标题</option>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <div class="archive-content">
        <?php
        if (have_posts()) :
            echo '<div class="xiaowu-3d-gallery xiaowu-3d-col-3">';

            while (have_posts()) : the_post();
                $model_id = get_the_ID();
                $thumbnail = get_the_post_thumbnail_url($model_id, 'medium');
                $view_count = get_post_meta($model_id, '_view_count', true) ?: 0;
                $file_format = get_post_meta($model_id, '_model_file_format', true);
        ?>
            <div class="xiaowu-3d-gallery-item">
                <a href="<?php echo get_permalink(); ?>" class="xiaowu-3d-gallery-link">
                    <?php if ($thumbnail) : ?>
                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" />
                    <?php else : ?>
                    <div class="xiaowu-3d-placeholder">
                        <span class="dashicons dashicons-images-alt2"></span>
                    </div>
                    <?php endif; ?>
                    <div class="xiaowu-3d-gallery-overlay">
                        <h4><?php the_title(); ?></h4>
                        <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 15)); ?></p>
                        <div class="xiaowu-3d-gallery-meta">
                            <span class="xiaowu-3d-views">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php echo number_format($view_count); ?>
                            </span>
                            <span class="xiaowu-3d-format">
                                <?php echo strtoupper($file_format); ?>
                            </span>
                            <span class="xiaowu-3d-date">
                                <?php echo get_the_date('Y-m-d'); ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        <?php
            endwhile;

            echo '</div>'; // .xiaowu-3d-gallery

            // 分页导航
            the_posts_pagination(array(
                'mid_size' => 2,
                'prev_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span> 上一页',
                'next_text' => '下一页 <span class="dashicons dashicons-arrow-right-alt2"></span>',
            ));

        else :
        ?>
            <div class="no-models-found">
                <span class="dashicons dashicons-info"></span>
                <p>暂无3D模型</p>
            </div>
        <?php
        endif;
        ?>
    </div>
</div>

<?php
get_footer();
