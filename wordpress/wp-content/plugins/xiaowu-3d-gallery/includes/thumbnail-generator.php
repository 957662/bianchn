<?php
/**
 * 缩略图生成器
 *
 * @package Xiaowu_3D_Gallery
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Thumbnail_Generator 类
 */
class Xiaowu_Thumbnail_Generator
{
    /**
     * 生成模型缩略图
     */
    public function generate($model_id, $file_url)
    {
        // 方案1: 使用AI图像生成服务生成缩略图
        // 方案2: 使用无头浏览器渲染Three.js场景并截图
        // 方案3: 使用预设的占位图

        // 这里先使用AI生成方案
        if (class_exists('Xiaowu_AI_Service')) {
            $result = $this->generate_with_ai($model_id, $file_url);
            if ($result['success']) {
                return $result;
            }
        }

        // 降级到占位图
        return $this->use_placeholder($model_id);
    }

    /**
     * 使用AI生成缩略图
     */
    private function generate_with_ai($model_id, $file_url)
    {
        try {
            $ai_service = new Xiaowu_AI_Service();

            // 获取模型信息
            $title = get_the_title($model_id);
            $description = get_post_field('post_content', $model_id);
            $metadata = get_post_meta($model_id, '_model_metadata', true);

            // 构建提示词
            $prompt = $this->build_thumbnail_prompt($title, $description, $metadata);

            // 生成图像
            $result = $ai_service->generate_image($prompt, 'realistic', '512x512', 'png', 1);

            if ($result['success'] && !empty($result['images'])) {
                $image_url = $result['images'][0];

                // 下载并保存为WordPress附件
                $thumbnail_id = $this->save_thumbnail_as_attachment($image_url, $model_id);

                if ($thumbnail_id) {
                    // 设置为特色图片
                    set_post_thumbnail($model_id, $thumbnail_id);

                    // 保存缩略图URL
                    update_post_meta($model_id, '_model_thumbnail_url', wp_get_attachment_url($thumbnail_id));

                    return array(
                        'success' => true,
                        'thumbnail_id' => $thumbnail_id,
                        'thumbnail_url' => wp_get_attachment_url($thumbnail_id)
                    );
                }
            }

            return array('success' => false, 'error' => 'AI生成失败');

        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 构建缩略图生成提示词
     */
    private function build_thumbnail_prompt($title, $description, $metadata)
    {
        $prompt = "Create a professional 3D model thumbnail for: {$title}. ";

        if (!empty($description)) {
            $excerpt = wp_trim_words($description, 20);
            $prompt .= "Description: {$excerpt}. ";
        }

        $prompt .= "Style: Clean, professional, with good lighting and composition. Show the 3D model from an attractive angle. ";
        $prompt .= "Background: Neutral gradient or solid color. No text or watermarks.";

        return $prompt;
    }

    /**
     * 保存缩略图为WordPress附件
     */
    private function save_thumbnail_as_attachment($image_url, $model_id)
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // 下载图片
        $tmp = download_url($image_url);

        if (is_wp_error($tmp)) {
            return false;
        }

        // 准备附件数据
        $file_array = array(
            'name' => 'model-' . $model_id . '-thumbnail.png',
            'tmp_name' => $tmp
        );

        // 上传为附件
        $thumbnail_id = media_handle_sideload($file_array, $model_id, get_the_title($model_id) . ' 缩略图');

        // 删除临时文件
        @unlink($tmp);

        if (is_wp_error($thumbnail_id)) {
            return false;
        }

        return $thumbnail_id;
    }

    /**
     * 使用占位图
     */
    private function use_placeholder($model_id)
    {
        // 使用默认的3D图标作为占位图
        $placeholder_url = XIAOWU_3D_PLUGIN_URL . 'assets/images/placeholder-3d.png';

        update_post_meta($model_id, '_model_thumbnail_url', $placeholder_url);

        return array(
            'success' => true,
            'thumbnail_url' => $placeholder_url,
            'is_placeholder' => true
        );
    }

    /**
     * 从3D模型渲染缩略图(使用无头浏览器)
     */
    private function render_with_headless_browser($model_id, $file_url)
    {
        // 需要安装puppeteer或类似工具
        // 这里暂时不实现,留作未来扩展

        return array('success' => false, 'error' => '无头浏览器渲染功能待实现');
    }
}
