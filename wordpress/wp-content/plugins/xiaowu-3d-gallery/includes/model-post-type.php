<?php
/**
 * 3D模型自定义文章类型
 *
 * @package Xiaowu_3D_Gallery
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Model_Post_Type 类
 */
class Xiaowu_Model_Post_Type
{
    /**
     * 注册自定义文章类型
     */
    public function register()
    {
        $labels = array(
            'name' => '3D模型',
            'singular_name' => '3D模型',
            'menu_name' => '3D模型',
            'name_admin_bar' => '3D模型',
            'add_new' => '添加新模型',
            'add_new_item' => '添加新3D模型',
            'new_item' => '新3D模型',
            'edit_item' => '编辑3D模型',
            'view_item' => '查看3D模型',
            'all_items' => '所有模型',
            'search_items' => '搜索模型',
            'parent_item_colon' => '父级模型:',
            'not_found' => '未找到模型',
            'not_found_in_trash' => '回收站中未找到模型',
            'featured_image' => '特色图片',
            'set_featured_image' => '设置特色图片',
            'remove_featured_image' => '移除特色图片',
            'use_featured_image' => '使用特色图片',
            'archives' => '3D模型归档',
            'insert_into_item' => '插入到模型',
            'uploaded_to_this_item' => '上传到此模型',
            'filter_items_list' => '筛选模型列表',
            'items_list_navigation' => '模型列表导航',
            'items_list' => '模型列表',
        );

        $args = array(
            'labels' => $labels,
            'description' => '3D模型展示系统',
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false, // 使用自定义菜单
            'show_in_rest' => true,
            'rest_base' => 'models',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'query_var' => true,
            'rewrite' => array('slug' => '3d-model'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-images-alt2',
            'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
        );

        register_post_type('xiaowu_3d_model', $args);

        // 注册元数据框
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_xiaowu_3d_model', array($this, 'save_meta_boxes'), 10, 2);
    }

    /**
     * 添加元数据框
     */
    public function add_meta_boxes()
    {
        add_meta_box(
            'xiaowu_model_file',
            '模型文件信息',
            array($this, 'render_file_meta_box'),
            'xiaowu_3d_model',
            'normal',
            'high'
        );

        add_meta_box(
            'xiaowu_model_viewer',
            '查看器配置',
            array($this, 'render_viewer_meta_box'),
            'xiaowu_3d_model',
            'normal',
            'default'
        );

        add_meta_box(
            'xiaowu_model_stats',
            '模型统计',
            array($this, 'render_stats_meta_box'),
            'xiaowu_3d_model',
            'side',
            'default'
        );
    }

    /**
     * 渲染文件信息元数据框
     */
    public function render_file_meta_box($post)
    {
        wp_nonce_field('xiaowu_model_meta', 'xiaowu_model_meta_nonce');

        $file_url = get_post_meta($post->ID, '_model_file_url', true);
        $file_format = get_post_meta($post->ID, '_model_file_format', true);
        $file_size = get_post_meta($post->ID, '_model_file_size', true);
        $cdn_url = get_post_meta($post->ID, '_model_cdn_url', true);
        $metadata = get_post_meta($post->ID, '_model_metadata', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="model_file_url">模型文件URL</label></th>
                <td>
                    <input type="url" id="model_file_url" name="model_file_url"
                           value="<?php echo esc_attr($file_url); ?>"
                           class="regular-text" readonly />
                    <p class="description">上传后自动生成</p>
                </td>
            </tr>
            <tr>
                <th><label for="model_file_format">文件格式</label></th>
                <td>
                    <input type="text" id="model_file_format" name="model_file_format"
                           value="<?php echo esc_attr($file_format); ?>"
                           class="regular-text" readonly />
                </td>
            </tr>
            <tr>
                <th><label for="model_file_size">文件大小</label></th>
                <td>
                    <input type="text" id="model_file_size" name="model_file_size"
                           value="<?php echo $file_size ? size_format($file_size) : ''; ?>"
                           class="regular-text" readonly />
                </td>
            </tr>
            <tr>
                <th><label for="model_cdn_url">CDN URL</label></th>
                <td>
                    <input type="url" id="model_cdn_url" name="model_cdn_url"
                           value="<?php echo esc_attr($cdn_url); ?>"
                           class="regular-text" readonly />
                </td>
            </tr>
            <?php if ($metadata):
                $metadata = json_decode($metadata, true);
            ?>
            <tr>
                <th>模型元数据</th>
                <td>
                    <ul>
                        <?php if (isset($metadata['vertices'])): ?>
                        <li>顶点数: <?php echo number_format($metadata['vertices']); ?></li>
                        <?php endif; ?>
                        <?php if (isset($metadata['faces'])): ?>
                        <li>面数: <?php echo number_format($metadata['faces']); ?></li>
                        <?php endif; ?>
                        <?php if (isset($metadata['materials'])): ?>
                        <li>材质: <?php echo implode(', ', $metadata['materials']); ?></li>
                        <?php endif; ?>
                        <?php if (isset($metadata['animations'])): ?>
                        <li>动画: <?php echo implode(', ', $metadata['animations']); ?></li>
                        <?php endif; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * 渲染查看器配置元数据框
     */
    public function render_viewer_meta_box($post)
    {
        $config = get_post_meta($post->ID, '_viewer_config', true);
        if (empty($config)) {
            $config = array(
                'auto_rotate' => true,
                'rotate_speed' => 1.0,
                'enable_zoom' => true,
                'enable_pan' => true,
                'lighting' => 'studio',
                'background_color' => '#ffffff',
                'camera_position' => array('x' => 0, 'y' => 0, 'z' => 5)
            );
        } else {
            $config = json_decode($config, true);
        }

        ?>
        <table class="form-table">
            <tr>
                <th><label for="auto_rotate">自动旋转</label></th>
                <td>
                    <input type="checkbox" id="auto_rotate" name="viewer_config[auto_rotate]"
                           value="1" <?php checked(!empty($config['auto_rotate'])); ?> />
                    <label for="auto_rotate">启用自动旋转</label>
                </td>
            </tr>
            <tr>
                <th><label for="rotate_speed">旋转速度</label></th>
                <td>
                    <input type="number" id="rotate_speed" name="viewer_config[rotate_speed]"
                           value="<?php echo esc_attr($config['rotate_speed'] ?? 1.0); ?>"
                           step="0.1" min="0" max="5" class="small-text" />
                </td>
            </tr>
            <tr>
                <th><label for="enable_zoom">缩放功能</label></th>
                <td>
                    <input type="checkbox" id="enable_zoom" name="viewer_config[enable_zoom]"
                           value="1" <?php checked(!empty($config['enable_zoom'])); ?> />
                    <label for="enable_zoom">启用缩放</label>
                </td>
            </tr>
            <tr>
                <th><label for="enable_pan">平移功能</label></th>
                <td>
                    <input type="checkbox" id="enable_pan" name="viewer_config[enable_pan]"
                           value="1" <?php checked(!empty($config['enable_pan'])); ?> />
                    <label for="enable_pan">启用平移</label>
                </td>
            </tr>
            <tr>
                <th><label for="lighting">灯光模式</label></th>
                <td>
                    <select id="lighting" name="viewer_config[lighting]">
                        <option value="studio" <?php selected($config['lighting'] ?? 'studio', 'studio'); ?>>工作室</option>
                        <option value="natural" <?php selected($config['lighting'] ?? '', 'natural'); ?>>自然光</option>
                        <option value="dark" <?php selected($config['lighting'] ?? '', 'dark'); ?>>暗光</option>
                        <option value="custom" <?php selected($config['lighting'] ?? '', 'custom'); ?>>自定义</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="background_color">背景颜色</label></th>
                <td>
                    <input type="color" id="background_color" name="viewer_config[background_color]"
                           value="<?php echo esc_attr($config['background_color'] ?? '#ffffff'); ?>" />
                </td>
            </tr>
            <tr>
                <th>相机位置</th>
                <td>
                    X: <input type="number" name="viewer_config[camera_position][x]"
                             value="<?php echo esc_attr($config['camera_position']['x'] ?? 0); ?>"
                             step="0.1" class="small-text" />
                    Y: <input type="number" name="viewer_config[camera_position][y]"
                             value="<?php echo esc_attr($config['camera_position']['y'] ?? 0); ?>"
                             step="0.1" class="small-text" />
                    Z: <input type="number" name="viewer_config[camera_position][z]"
                             value="<?php echo esc_attr($config['camera_position']['z'] ?? 5); ?>"
                             step="0.1" class="small-text" />
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * 渲染统计元数据框
     */
    public function render_stats_meta_box($post)
    {
        $view_count = get_post_meta($post->ID, '_view_count', true) ?: 0;
        $download_count = get_post_meta($post->ID, '_download_count', true) ?: 0;

        ?>
        <div class="xiaowu-stats">
            <p><strong>查看次数:</strong> <?php echo number_format($view_count); ?></p>
            <p><strong>下载次数:</strong> <?php echo number_format($download_count); ?></p>
            <p><strong>发布日期:</strong> <?php echo get_the_date('Y-m-d H:i', $post); ?></p>
            <p><strong>最后更新:</strong> <?php echo get_the_modified_date('Y-m-d H:i', $post); ?></p>
        </div>
        <?php
    }

    /**
     * 保存元数据框
     */
    public function save_meta_boxes($post_id, $post)
    {
        // 验证nonce
        if (!isset($_POST['xiaowu_model_meta_nonce']) ||
            !wp_verify_nonce($_POST['xiaowu_model_meta_nonce'], 'xiaowu_model_meta')) {
            return;
        }

        // 检查自动保存
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // 检查权限
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // 保存查看器配置
        if (isset($_POST['viewer_config'])) {
            $viewer_config = $_POST['viewer_config'];

            // 转换checkbox值
            $viewer_config['auto_rotate'] = isset($viewer_config['auto_rotate']);
            $viewer_config['enable_zoom'] = isset($viewer_config['enable_zoom']);
            $viewer_config['enable_pan'] = isset($viewer_config['enable_pan']);

            update_post_meta($post_id, '_viewer_config', json_encode($viewer_config));
        }
    }
}
