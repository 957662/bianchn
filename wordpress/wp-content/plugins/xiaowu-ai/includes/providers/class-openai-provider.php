<?php
/**
 * OpenAI提供商实现
 *
 * @package Xiaowu_AI
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI提供商类
 */
class Xiaowu_AI_Provider_Openai implements Xiaowu_AI_Provider_Interface
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
            'model' => $options['model'] ?? $this->config['default_model'],
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
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

        if (isset($body['error'])) {
            return array(
                'success' => false,
                'error' => $body['error']['message'] ?? '未知错误'
            );
        }

        return array(
            'success' => true,
            'content' => $body['choices'][0]['message']['content'],
            'usage' => $body['usage'] ?? array()
        );
    }

    /**
     * 生成图片
     */
    public function generate_image($prompt, $options = array())
    {
        $endpoint = $this->config['image_endpoint'];
        $api_key = $this->config['api_key'];

        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'API密钥未配置'
            );
        }

        $body = array(
            'prompt' => $prompt,
            'n' => $options['n'] ?? 1,
            'size' => $options['size'] ?? '1024x1024',
            'model' => $options['model'] ?? 'dall-e-3'
        );

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 120
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return array(
                'success' => false,
                'error' => $body['error']['message'] ?? '未知错误'
            );
        }

        return array(
            'success' => true,
            'images' => $body['data'] ?? array()
        );
    }

    /**
     * 生成嵌入向量
     */
    public function generate_embedding($text)
    {
        $endpoint = $this->config['embedding_endpoint'];
        $api_key = $this->config['api_key'];

        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'API密钥未配置'
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
                'error' => $result['error']['message'] ?? '未知错误'
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

        if (empty($config['api_key'])) {
            $errors[] = 'API密钥不能为空';
        }

        if (empty($config['endpoint'])) {
            $errors[] = 'API端点不能为空';
        }

        if (!filter_var($config['endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = 'API端点格式不正确';
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * 获取支持的模型列表
     */
    public function get_models()
    {
        return $this->config['models'] ?? array('gpt-4', 'gpt-3.5-turbo');
    }

    /**
     * 获取提供商支持的功能
     */
    public function get_capabilities()
    {
        return $this->config['supports'] ?? array('text', 'image', 'embedding');
    }

    /**
     * 测试连接
     */
    public function test_connection()
    {
        $result = $this->request_text('测试', '', array(
            'model' => 'gpt-3.5-turbo',
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
