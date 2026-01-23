<?php
/**
 * 单个3D模型查看器模板
 *
 * @package Xiaowu_3D_Gallery
 */

get_header();

while (have_posts()) : the_post();
    $model_id = get_the_ID();
    $file_url = get_post_meta($model_id, '_model_file_url', true);
    $file_format = get_post_meta($model_id, '_model_file_format', true);
    $viewer_config = get_post_meta($model_id, '_viewer_config', true);
    $view_count = get_post_meta($model_id, '_view_count', true) ?: 0;
    $download_count = get_post_meta($model_id, '_download_count', true) ?: 0;

    $config = $viewer_config ? json_decode($viewer_config, true) : array();
?>

<article id="model-<?php echo $model_id; ?>" <?php post_class('xiaowu-3d-single-model'); ?>>
    <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>

        <div class="entry-meta">
            <span class="model-author">
                <span class="dashicons dashicons-admin-users"></span>
                <?php the_author(); ?>
            </span>
            <span class="model-date">
                <span class="dashicons dashicons-calendar"></span>
                <?php echo get_the_date(); ?>
            </span>
            <span class="model-views">
                <span class="dashicons dashicons-visibility"></span>
                <?php echo number_format($view_count); ?> 次浏览
            </span>
            <span class="model-format">
                <span class="dashicons dashicons-media-code"></span>
                <?php echo strtoupper($file_format); ?>
            </span>
        </div>

        <?php
        $categories = get_the_terms($model_id, 'model_category');
        $tags = get_the_terms($model_id, 'model_tag');

        if ($categories || $tags) :
        ?>
        <div class="model-taxonomy">
            <?php if ($categories && !is_wp_error($categories)) : ?>
            <div class="model-categories">
                <span class="dashicons dashicons-category"></span>
                <?php
                foreach ($categories as $category) {
                    echo '<a href="' . get_term_link($category) . '">' . esc_html($category->name) . '</a> ';
                }
                ?>
            </div>
            <?php endif; ?>

            <?php if ($tags && !is_wp_error($tags)) : ?>
            <div class="model-tags">
                <span class="dashicons dashicons-tag"></span>
                <?php
                foreach ($tags as $tag) {
                    echo '<a href="' . get_term_link($tag) . '">' . esc_html($tag->name) . '</a> ';
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>

    <div class="entry-content">
        <?php
        // 渲染3D查看器
        if (class_exists('Xiaowu_3D_Viewer')) {
            $viewer = new Xiaowu_3D_Viewer();
            echo $viewer->render($model_id, array(
                'width' => '100%',
                'height' => '600px'
            ));
        }
        ?>

        <div class="model-description">
            <?php the_content(); ?>
        </div>

        <?php
        $metadata = get_post_meta($model_id, '_model_metadata', true);
        if ($metadata) :
            $metadata = json_decode($metadata, true);
        ?>
        <div class="model-technical-info">
            <h3>技术信息</h3>
            <div class="xiaowu-3d-card">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th>文件格式</th>
                            <td><?php echo strtoupper($file_format); ?></td>
                        </tr>
                        <?php if (isset($metadata['vertices'])) : ?>
                        <tr>
                            <th>顶点数</th>
                            <td><?php echo number_format($metadata['vertices']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($metadata['faces'])) : ?>
                        <tr>
                            <th>面数</th>
                            <td><?php echo number_format($metadata['faces']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($metadata['materials']) && !empty($metadata['materials'])) : ?>
                        <tr>
                            <th>材质</th>
                            <td><?php echo implode(', ', $metadata['materials']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (isset($metadata['animations']) && !empty($metadata['animations'])) : ?>
                        <tr>
                            <th>动画</th>
                            <td><?php echo implode(', ', $metadata['animations']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th>文件大小</th>
                            <td>
                                <?php
                                $file_size = get_post_meta($model_id, '_model_file_size', true);
                                echo $file_size ? size_format($file_size) : '未知';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="entry-footer">
        <div class="model-actions">
            <?php if (current_user_can('edit_post', $model_id)) : ?>
            <a href="<?php echo get_edit_post_link($model_id); ?>" class="button">
                <span class="dashicons dashicons-edit"></span> 编辑
            </a>
            <?php endif; ?>

            <button class="button" onclick="window.print()">
                <span class="dashicons dashicons-printer"></span> 打印
            </button>

            <button class="button" onclick="navigator.share ? navigator.share({title: '<?php echo esc_js(get_the_title()); ?>', url: window.location.href}) : alert('分享功能不可用')">
                <span class="dashicons dashicons-share"></span> 分享
            </button>
        </div>
    </footer>

    <?php
    // 显示相关模型
    if ($categories && !is_wp_error($categories)) :
        $related_args = array(
            'post_type' => 'xiaowu_3d_model',
            'post__not_in' => array($model_id),
            'posts_per_page' => 4,
            'tax_query' => array(
                array(
                    'taxonomy' => 'model_category',
                    'field' => 'term_id',
                    'terms' => wp_list_pluck($categories, 'term_id')
                )
            )
        );

        $related_query = new WP_Query($related_args);

        if ($related_query->have_posts()) :
    ?>
    <div class="related-models">
        <h3>相关模型</h3>
        <div class="xiaowu-3d-gallery xiaowu-3d-col-4">
            <?php
            while ($related_query->have_posts()) : $related_query->the_post();
                $related_id = get_the_ID();
                $thumbnail = get_the_post_thumbnail_url($related_id, 'medium');
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
                    </div>
                </a>
            </div>
            <?php
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </div>
    <?php
        endif;
    endif;
    ?>

    <?php
    // 评论区
    if (comments_open() || get_comments_number()) :
        comments_template();
    endif;
    ?>
</article>

<?php
endwhile;

get_footer();
