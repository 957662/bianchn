<?php
/**
 * 3D模型查看器
 *
 * @package Xiaowu_3D_Gallery
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_3D_Viewer 类
 */
class Xiaowu_3D_Viewer
{
    /**
     * 渲染查看器
     */
    public function render($model_id, $atts = array())
    {
        $model = get_post($model_id);

        if (!$model || $model->post_type !== 'xiaowu_3d_model') {
            return '<p class="xiaowu-3d-error">模型不存在</p>';
        }

        // 获取模型信息
        $file_url = get_post_meta($model_id, '_model_file_url', true);
        $file_format = get_post_meta($model_id, '_model_file_format', true);
        $viewer_config = get_post_meta($model_id, '_viewer_config', true);

        if (empty($file_url)) {
            return '<p class="xiaowu-3d-error">模型文件不存在</p>';
        }

        // 解析配置
        if (!empty($viewer_config)) {
            $config = json_decode($viewer_config, true);
        } else {
            $config = array();
        }

        // 合并属性
        $width = esc_attr($atts['width'] ?? '100%');
        $height = esc_attr($atts['height'] ?? '500px');
        $auto_rotate = filter_var($atts['auto_rotate'] ?? $config['auto_rotate'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // 生成唯一ID
        $viewer_id = 'xiaowu-3d-viewer-' . $model_id . '-' . uniqid();

        // 构建查看器配置JSON
        $viewer_config_json = json_encode(array(
            'modelUrl' => $file_url,
            'modelFormat' => $file_format,
            'autoRotate' => $auto_rotate,
            'rotateSpeed' => floatval($config['rotate_speed'] ?? 1.0),
            'enableZoom' => $config['enable_zoom'] ?? true,
            'enablePan' => $config['enable_pan'] ?? true,
            'lighting' => $config['lighting'] ?? 'studio',
            'backgroundColor' => $config['background_color'] ?? '#ffffff',
            'cameraPosition' => $config['camera_position'] ?? array('x' => 0, 'y' => 0, 'z' => 5)
        ));

        // 渲染HTML
        ob_start();
        ?>
        <div class="xiaowu-3d-viewer-container" style="width: <?php echo $width; ?>; height: <?php echo $height; ?>;">
            <div id="<?php echo $viewer_id; ?>" class="xiaowu-3d-viewer" data-config='<?php echo esc_attr($viewer_config_json); ?>'></div>
            <div class="xiaowu-3d-controls">
                <button class="xiaowu-3d-btn" data-action="reset-camera" title="重置相机">
                    <span class="dashicons dashicons-image-rotate"></span>
                </button>
                <button class="xiaowu-3d-btn" data-action="toggle-rotate" title="自动旋转">
                    <span class="dashicons dashicons-update"></span>
                </button>
                <button class="xiaowu-3d-btn" data-action="toggle-fullscreen" title="全屏">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
                <button class="xiaowu-3d-btn" data-action="screenshot" title="截图">
                    <span class="dashicons dashicons-camera"></span>
                </button>
            </div>
            <div class="xiaowu-3d-info">
                <h3><?php echo esc_html(get_the_title($model_id)); ?></h3>
                <p><?php echo esc_html(get_the_excerpt($model_id)); ?></p>
            </div>
            <div class="xiaowu-3d-loading">
                <div class="xiaowu-3d-spinner"></div>
                <p>加载模型中...</p>
            </div>
            <div class="xiaowu-3d-error-message" style="display: none;"></div>
        </div>

        <script>
            (function() {
                if (typeof window.XiaowuViewer !== 'undefined') {
                    window.XiaowuViewer.init('<?php echo $viewer_id; ?>');
                }
            })();
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * 渲染图库网格
     */
    public function render_gallery($args = array())
    {
        $defaults = array(
            'category' => '',
            'limit' => 12,
            'columns' => 3,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'post_type' => 'xiaowu_3d_model',
            'post_status' => 'publish',
            'posts_per_page' => intval($args['limit']),
            'orderby' => $args['orderby'],
            'order' => $args['order']
        );

        if (!empty($args['category'])) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'model_category',
                    'field' => 'slug',
                    'terms' => $args['category']
                )
            );
        }

        $query = new WP_Query($query_args);

        if (!$query->have_posts()) {
            return '<p>暂无3D模型</p>';
        }

        $columns = intval($args['columns']);
        $column_class = 'xiaowu-3d-col-' . $columns;

        ob_start();
        ?>
        <div class="xiaowu-3d-gallery <?php echo esc_attr($column_class); ?>">
            <?php while ($query->have_posts()): $query->the_post();
                $model_id = get_the_ID();
                $thumbnail = get_the_post_thumbnail_url($model_id, 'medium');
                $view_count = get_post_meta($model_id, '_view_count', true) ?: 0;
            ?>
            <div class="xiaowu-3d-gallery-item">
                <a href="<?php echo get_permalink(); ?>" class="xiaowu-3d-gallery-link">
                    <?php if ($thumbnail): ?>
                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" />
                    <?php else: ?>
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
                            <span class="xiaowu-3d-date">
                                <?php echo get_the_date(); ?>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php

        return ob_get_clean();
    }
}
