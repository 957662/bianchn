<?php
/**
 * 3D模型列表管理页面
 *
 * @package Xiaowu_3D_Gallery
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取所有模型
$paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$per_page = 20;

$args = array(
    'post_type' => 'xiaowu_3d_model',
    'post_status' => array('publish', 'draft', 'private'),
    'posts_per_page' => $per_page,
    'paged' => $paged,
    'orderby' => 'date',
    'order' => 'DESC'
);

$models_query = new WP_Query($args);
$total_models = $models_query->found_posts;

?>
<div class="wrap xiaowu-3d-admin">
    <h1 class="wp-heading-inline">3D模型管理</h1>
    <a href="<?php echo admin_url('post-new.php?post_type=xiaowu_3d_model'); ?>" class="page-title-action">添加新模型</a>
    <hr class="wp-header-end">

    <div class="xiaowu-3d-stats">
        <div class="xiaowu-3d-stat-box">
            <h3>总模型数</h3>
            <p class="xiaowu-3d-stat-number"><?php echo number_format($total_models); ?></p>
        </div>
        <div class="xiaowu-3d-stat-box">
            <h3>已发布</h3>
            <p class="xiaowu-3d-stat-number">
                <?php echo number_format(wp_count_posts('xiaowu_3d_model')->publish); ?>
            </p>
        </div>
        <div class="xiaowu-3d-stat-box">
            <h3>草稿</h3>
            <p class="xiaowu-3d-stat-number">
                <?php echo number_format(wp_count_posts('xiaowu_3d_model')->draft); ?>
            </p>
        </div>
        <div class="xiaowu-3d-stat-box">
            <h3>总浏览量</h3>
            <p class="xiaowu-3d-stat-number">
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'xiaowu_model_views';
                $total_views = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                echo number_format($total_views);
                ?>
            </p>
        </div>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="xiaowu-3d-gallery" />

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="category" id="filter-category">
                    <option value="">所有分类</option>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'model_category',
                        'hide_empty' => false
                    ));
                    foreach ($categories as $category) {
                        $selected = isset($_GET['category']) && $_GET['category'] == $category->slug ? 'selected' : '';
                        echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                    }
                    ?>
                </select>
                <input type="submit" class="button" value="筛选" />
            </div>

            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_models / $per_page);
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;'
                    ));
                }
                ?>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <th class="manage-column column-cb check-column">
                        <input type="checkbox" />
                    </th>
                    <th class="manage-column">缩略图</th>
                    <th class="manage-column column-title column-primary">标题</th>
                    <th class="manage-column">格式</th>
                    <th class="manage-column">分类</th>
                    <th class="manage-column">浏览量</th>
                    <th class="manage-column">状态</th>
                    <th class="manage-column">日期</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($models_query->have_posts()) {
                    while ($models_query->have_posts()) {
                        $models_query->the_post();
                        $model_id = get_the_ID();
                        $thumbnail = get_the_post_thumbnail_url($model_id, 'thumbnail');
                        $file_format = get_post_meta($model_id, '_model_file_format', true);
                        $view_count = get_post_meta($model_id, '_view_count', true) ?: 0;
                        $categories = get_the_terms($model_id, 'model_category');
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="post[]" value="<?php echo $model_id; ?>" />
                            </th>
                            <td>
                                <?php if ($thumbnail): ?>
                                    <img src="<?php echo esc_url($thumbnail); ?>" alt="" style="width: 60px; height: 60px; object-fit: cover;" />
                                <?php else: ?>
                                    <div class="xiaowu-3d-placeholder-small" style="width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <span class="dashicons dashicons-images-alt2"></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="title column-title column-primary">
                                <strong>
                                    <a href="<?php echo get_edit_post_link($model_id); ?>" class="row-title">
                                        <?php the_title(); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo get_edit_post_link($model_id); ?>">编辑</a> |
                                    </span>
                                    <span class="view">
                                        <a href="<?php echo get_permalink($model_id); ?>" target="_blank">查看</a> |
                                    </span>
                                    <span class="trash">
                                        <a href="<?php echo get_delete_post_link($model_id); ?>" class="submitdelete">删除</a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="xiaowu-3d-format-badge">
                                    <?php echo strtoupper(esc_html($file_format)); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ($categories && !is_wp_error($categories)) {
                                    $cat_names = array_map(function($cat) {
                                        return $cat->name;
                                    }, $categories);
                                    echo esc_html(implode(', ', $cat_names));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="xiaowu-3d-views">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php echo number_format($view_count); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $status = get_post_status($model_id);
                                $status_label = array(
                                    'publish' => '<span class="xiaowu-3d-status-badge status-publish">已发布</span>',
                                    'draft' => '<span class="xiaowu-3d-status-badge status-draft">草稿</span>',
                                    'private' => '<span class="xiaowu-3d-status-badge status-private">私密</span>'
                                );
                                echo $status_label[$status] ?? $status;
                                ?>
                            </td>
                            <td>
                                <?php echo get_the_date('Y-m-d H:i'); ?>
                            </td>
                        </tr>
                        <?php
                    }
                    wp_reset_postdata();
                } else {
                    ?>
                    <tr>
                        <td colspan="8" class="colspanchange">
                            <p style="text-align: center; padding: 20px;">暂无3D模型</p>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'current' => $paged,
                        'total' => $total_pages,
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;'
                    ));
                }
                ?>
            </div>
        </div>
    </form>
</div>
