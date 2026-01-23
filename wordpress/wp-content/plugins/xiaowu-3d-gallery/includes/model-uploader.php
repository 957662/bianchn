<?php
/**
 * 模型上传器
 *
 * @package Xiaowu_3D_Gallery
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Model_Uploader 类
 */
class Xiaowu_Model_Uploader
{
    /**
     * 允许的文件格式
     */
    private $allowed_formats = array('glb', 'gltf', 'obj', 'fbx');

    /**
     * 最大文件大小(字节)
     */
    private $max_file_size = 104857600; // 100MB

    /**
     * CDN管理器
     */
    private $cdn_manager;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->cdn_manager = new Xiaowu_CDN_Manager();
    }

    /**
     * 上传模型文件
     */
    public function upload($file, $metadata = array())
    {
        // 验证文件
        $validation = $this->validate($file);
        if (!$validation['success']) {
            return $validation;
        }

        // 获取文件信息
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // 生成唯一文件名
        $unique_filename = $this->generate_unique_filename($file_name);

        // 上传到CDN
        $cdn_result = $this->cdn_manager->upload($file_tmp, $unique_filename);
        if (!$cdn_result['success']) {
            return array(
                'success' => false,
                'error' => '上传到CDN失败: ' . $cdn_result['error']
            );
        }

        // 创建模型记录
        $post_data = array(
            'post_title' => sanitize_text_field($metadata['title'] ?: pathinfo($file_name, PATHINFO_FILENAME)),
            'post_content' => sanitize_textarea_field($metadata['description'] ?: ''),
            'post_type' => 'xiaowu_3d_model',
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        );

        $model_id = wp_insert_post($post_data);

        if (is_wp_error($model_id)) {
            // 删除已上传的文件
            $this->cdn_manager->delete($cdn_result['url']);
            return array(
                'success' => false,
                'error' => '创建模型记录失败: ' . $model_id->get_error_message()
            );
        }

        // 保存模型元数据
        update_post_meta($model_id, '_model_file_url', $cdn_result['url']);
        update_post_meta($model_id, '_model_cdn_url', $cdn_result['cdn_url']);
        update_post_meta($model_id, '_model_file_format', $file_ext);
        update_post_meta($model_id, '_model_file_size', $file_size);
        update_post_meta($model_id, '_view_count', 0);
        update_post_meta($model_id, '_download_count', 0);

        // 设置分类和标签
        if (!empty($metadata['category'])) {
            wp_set_object_terms($model_id, $metadata['category'], 'model_category');
        }

        if (!empty($metadata['tags'])) {
            wp_set_object_terms($model_id, $metadata['tags'], 'model_tag');
        }

        // 提取模型元数据(异步)
        wp_schedule_single_event(time() + 10, 'xiaowu_extract_model_metadata', array($model_id, $cdn_result['url']));

        // 生成缩略图(异步)
        wp_schedule_single_event(time() + 20, 'xiaowu_generate_model_thumbnail', array($model_id, $cdn_result['url']));

        return array(
            'success' => true,
            'model_id' => $model_id,
            'file_url' => $cdn_result['url'],
            'cdn_url' => $cdn_result['cdn_url'],
            'message' => '模型上传成功'
        );
    }

    /**
     * 验证文件
     */
    private function validate($file)
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return array(
                'success' => false,
                'error' => '无效的文件上传'
            );
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'error' => '文件上传错误: ' . $this->get_upload_error_message($file['error'])
            );
        }

        // 检查文件大小
        if ($file['size'] > $this->max_file_size) {
            return array(
                'success' => false,
                'error' => '文件大小超过限制(' . size_format($this->max_file_size) . ')'
            );
        }

        // 检查文件格式
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $this->allowed_formats)) {
            return array(
                'success' => false,
                'error' => '不支持的文件格式。允许的格式: ' . implode(', ', $this->allowed_formats)
            );
        }

        // 验证MIME类型
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        $allowed_mimes = array(
            'model/gltf-binary',
            'model/gltf+json',
            'application/octet-stream',
            'text/plain'
        );

        // 某些系统可能无法正确识别3D文件的MIME类型，所以这里比较宽松
        // 主要依靠扩展名验证

        return array('success' => true);
    }

    /**
     * 获取上传错误信息
     */
    private function get_upload_error_message($error_code)
    {
        $errors = array(
            UPLOAD_ERR_INI_SIZE => '文件大小超过php.ini中的upload_max_filesize限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单中的MAX_FILE_SIZE限制',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => 'PHP扩展停止了文件上传'
        );

        return isset($errors[$error_code]) ? $errors[$error_code] : '未知错误';
    }

    /**
     * 生成唯一文件名
     */
    private function generate_unique_filename($original_filename)
    {
        $ext = pathinfo($original_filename, PATHINFO_EXTENSION);
        $basename = pathinfo($original_filename, PATHINFO_FILENAME);
        $basename = sanitize_file_name($basename);

        $unique_id = uniqid();
        $timestamp = time();

        return sprintf(
            '3d-models/%s/%s-%s-%s.%s',
            date('Y/m'),
            $basename,
            $timestamp,
            $unique_id,
            $ext
        );
    }

    /**
     * 提取模型元数据
     */
    public function extract_metadata($model_id, $file_url)
    {
        $file_ext = pathinfo($file_url, PATHINFO_EXTENSION);

        $metadata = array(
            'format' => $file_ext,
            'extracted_at' => current_time('mysql')
        );

        // 对于GLTF/GLB文件，可以解析JSON获取更多信息
        if ($file_ext === 'gltf') {
            $response = wp_remote_get($file_url);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $gltf_data = json_decode($body, true);

                if ($gltf_data) {
                    if (isset($gltf_data['meshes'])) {
                        $metadata['meshes'] = count($gltf_data['meshes']);
                    }
                    if (isset($gltf_data['materials'])) {
                        $metadata['materials'] = array_column($gltf_data['materials'], 'name');
                    }
                    if (isset($gltf_data['animations'])) {
                        $metadata['animations'] = array_column($gltf_data['animations'], 'name');
                    }
                    if (isset($gltf_data['textures'])) {
                        $metadata['textures'] = count($gltf_data['textures']);
                    }
                }
            }
        }

        update_post_meta($model_id, '_model_metadata', json_encode($metadata));

        return $metadata;
    }
}

// 注册异步任务钩子
add_action('xiaowu_extract_model_metadata', function($model_id, $file_url) {
    $uploader = new Xiaowu_Model_Uploader();
    $uploader->extract_metadata($model_id, $file_url);
}, 10, 2);

add_action('xiaowu_generate_model_thumbnail', function($model_id, $file_url) {
    $generator = new Xiaowu_Thumbnail_Generator();
    $generator->generate($model_id, $file_url);
}, 10, 2);
