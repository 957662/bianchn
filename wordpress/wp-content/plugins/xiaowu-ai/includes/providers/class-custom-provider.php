<?php
/**
 * 自定义API提供商实现
 *
 * 支持用户自定义任何兼容OpenAI格式的API端点
 *
 * @package Xiaowu_AI
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 自定义提供商类
 */
class Xiaowu_AI_Provider_Custom implements Xiaowu_AI_Provider_Interface
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 发送文本请求
     */
    public function request_text($prompt, $system_prompt = '', $options = array())
    {
        $endpoint = $this->config['endpoint'];
        $api_key = $this->config['api_key'];

        if (empty($endpoint)) {
            return array(
                'success' => false,
                'error' => 'API端点未配置'
            );
        }

        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'API密钥未配置'
            );
        }

        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array(
                'role' => 'system',
                'content' => $system_prompt
            );
        }
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );

        $body = array(
            'model' => $options['model'] ?? $this->config['default_model'] ?? 'default',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000
        );

        $headers = array(
            'Content-Type' => 'application/json'
        );

        // 根据用户配置添加认证头
        if (!empty($api_key)) {
            $auth_type = $options['auth_type'] ?? 'bearer';
            if ($auth_type === 'bearer') {
                $headers['Authorization'] = 'Bearer ' . $api_key;
            } elseif ($auth_type === 'api-key') {
                $headers['api-key'] = $api_key;
            } else {
                $headers['x-api-key'] = $api_key;
            }
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // 兼容不同的响应格式
        if (isset($body['error'])) {
            return array(
                'success' => false,
                'error' => is_array($body['error']) ? ($body['error']['message'] ?? '未知错误') : $body['error']
            );
        }

        // 尝试从OpenAI格式获取响应
        if (isset($body['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'content' => $body['choices'][0]['message']['content'],
                'usage' => $body['usage'] ?? array()
            );
        }

        // 尝试从简单格式获取响应
        if (isset($body['content'])) {
            return array(
                'success' => true,
                'content' => $body['content']
            );
        }

        // 尝试从response字段获取
        if (isset($body['response'])) {
            return array(
                'success' => true,
                'content' => $body['response']
            );
        }

        // 尝试从text字段获取
        if (isset($body['text'])) {
            return array(
                'success' => true,
                'content' => $body['text']
            );
        }

        return array(
            'success' => false,
            'error' => '无法解析API响应格式'
        );
    }

    /**
     * 生成图片
     */
    public function generate_image($prompt, $options = array())
    {
        $endpoint = $this->config['image_endpoint'] ?? str_replace('/chat/completions', '/images/generations', $this->config['endpoint']);
        $api_key = $this->config['api_key'];

        if (empty($endpoint)) {
            return array(
                'success' => false,
                'error' => '图像生成端点未配置'
            );
        }

        $body = array(
            'prompt' => $prompt,
            'n' => $options['n'] ?? 1,
            'size' => $options['size'] ?? '1024x1024'
        );

        $headers = array(
            'Content-Type' => 'application/json'
        );

        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($result['error'])) {
            return array(
                'success' => false,
                'error' => $result['error']
            );
        }

        return array(
            'success' => true,
            'images' => $result['data'] ?? array()
        );
    }

    /**
     * 生成嵌入向量
     */
    public function generate_embedding($text)
    {
        $endpoint = $this->config['embedding_endpoint'] ?? str_replace('/chat/completions', '/embeddings', $this->config['endpoint']);
        $api_key = $this->config['api_key'];

        if (empty($endpoint)) {
            return array(
                'success' => false,
                'error' => '嵌入向量端点未配置'
            );
        }

        $body = array(
            'input' => $text,
            'model' => 'text-embedding-ada-002'
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($result['error'])) {
            return array(
                'success' => false,
                'error' => $result['error']
            );
        }

        return array(
            'success' => true,
            'embedding' => $result['data'][0]['embedding'] ?? array()
        );
    }

    /**
     * 验证配置
     */
    public function validate_config($config)
    {
        $errors = array();

        if (empty($config['endpoint'])) {
            $errors[] = 'API端点不能为空';
        }

        if (!empty($config['endpoint']) && !filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = 'API端点格式不正确';
        }

        if (empty($config['api_key'])) {
            $errors[] = 'API密钥不能为空';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * 获取支持的模型列表
     */
    public function get_models()
    {
        // 自定义提供商的模型由用户在配置中指定
        return $this->config['models'] ?? array();
    }

    /**
     * 获取提供商支持的功能
     */
    public function get_capabilities()
    {
        return $this->config['supports'] ?? array('text');
    }

    /**
     * 测试连接
     */
    public function test_connection()
    {
        $result = $this->request_text('Hello', '', array(
            'model' => 'default',
            'max_tokens' => 10
        ));

        if ($result['success']) {
            return array(
                'success' => true,
                'message' => '连接成功'
            );
        } else {
            return array(
                'success' => false,
                'message' => $result['error']
            );
        }
    }
}
