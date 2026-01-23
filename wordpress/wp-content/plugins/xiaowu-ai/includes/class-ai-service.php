<?php
/**
 * AI服务核心类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_AI_Service 类
 */
class Xiaowu_AI_Service
{
    /**
     * AI提供商
     */
    private $provider;

    /**
     * API密钥
     */
    private $api_key;

    /**
     * 模型名称
     */
    private $model;

    /**
     * 最大令牌数
     */
    private $max_tokens;

    /**
     * 温度参数
     */
    private $temperature;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->provider = defined('XIAOWU_AI_PROVIDER') ? XIAOWU_AI_PROVIDER : get_option('xiaowu_ai_provider', 'openai');
        $this->api_key = defined('XIAOWU_AI_API_KEY') ? XIAOWU_AI_API_KEY : get_option('xiaowu_ai_api_key');
        $this->model = defined('XIAOWU_AI_MODEL') ? XIAOWU_AI_MODEL : get_option('xiaowu_ai_model', 'gpt-4');
        $this->max_tokens = defined('XIAOWU_AI_MAX_TOKENS') ? XIAOWU_AI_MAX_TOKENS : get_option('xiaowu_ai_max_tokens', 4000);
        $this->temperature = defined('XIAOWU_AI_TEMPERATURE') ? XIAOWU_AI_TEMPERATURE : get_option('xiaowu_ai_temperature', 0.7);
    }

    /**
     * 发送请求到AI服务
     */
    public function request($prompt, $system_prompt = '', $options = array())
    {
        $default_options = array(
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'model' => $this->model
        );

        $options = wp_parse_args($options, $default_options);

        switch ($this->provider) {
            case 'openai':
                return $this->request_openai($prompt, $system_prompt, $options);
            case 'qianwen':
                return $this->request_qianwen($prompt, $system_prompt, $options);
            case 'claude':
                return $this->request_claude($prompt, $system_prompt, $options);
            default:
                return array(
                    'success' => false,
                    'error' => '不支持的AI提供商'
                );
        }
    }

    /**
     * OpenAI API请求
     */
    private function request_openai($prompt, $system_prompt, $options)
    {
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
            'model' => $options['model'],
            'messages' => $messages,
            'temperature' => floatval($options['temperature']),
            'max_tokens' => intval($options['max_tokens'])
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
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
                'error' => $body['error']['message']
            );
        }

        return array(
            'success' => true,
            'content' => $body['choices'][0]['message']['content'],
            'usage' => $body['usage']
        );
    }

    /**
     * 通义千问API请求
     */
    private function request_qianwen($prompt, $system_prompt, $options)
    {
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
            'model' => $options['model'],
            'input' => array(
                'messages' => $messages
            ),
            'parameters' => array(
                'temperature' => floatval($options['temperature']),
                'max_tokens' => intval($options['max_tokens'])
            )
        );

        $response = wp_remote_post('https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
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

        if (isset($body['code']) && $body['code'] !== '200') {
            return array(
                'success' => false,
                'error' => $body['message']
            );
        }

        return array(
            'success' => true,
            'content' => $body['output']['text'],
            'usage' => $body['usage']
        );
    }

    /**
     * Claude API请求
     */
    private function request_claude($prompt, $system_prompt, $options)
    {
        $body = array(
            'model' => $options['model'],
            'max_tokens' => intval($options['max_tokens']),
            'temperature' => floatval($options['temperature']),
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );

        if (!empty($system_prompt)) {
            $body['system'] = $system_prompt;
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
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
                'error' => $body['error']['message']
            );
        }

        return array(
            'success' => true,
            'content' => $body['content'][0]['text'],
            'usage' => $body['usage']
        );
    }

    /**
     * 生成图像
     */
    public function generate_image($prompt, $style = 'icon', $size = '512x512', $format = 'png', $num_images = 1)
    {
        $provider = defined('XIAOWU_IMG_GEN_PROVIDER') ? XIAOWU_IMG_GEN_PROVIDER : 'dall-e';
        $api_key = defined('XIAOWU_IMG_GEN_API_KEY') ? XIAOWU_IMG_GEN_API_KEY : get_option('xiaowu_img_gen_api_key');

        if ($provider === 'dall-e') {
            return $this->generate_image_dalle($prompt, $size, $num_images, $api_key);
        }

        return array(
            'success' => false,
            'error' => '不支持的图像生成提供商'
        );
    }

    /**
     * DALL-E图像生成
     */
    private function generate_image_dalle($prompt, $size, $num_images, $api_key)
    {
        $body = array(
            'prompt' => $prompt,
            'n' => intval($num_images),
            'size' => $size,
            'response_format' => 'url'
        );

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
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
                'error' => $body['error']['message']
            );
        }

        $images = array();
        foreach ($body['data'] as $image) {
            $images[] = $image['url'];
        }

        return array(
            'success' => true,
            'images' => $images
        );
    }

    /**
     * 生成嵌入向量
     */
    public function generate_embedding($text)
    {
        if ($this->provider === 'openai') {
            return $this->generate_embedding_openai($text);
        }

        return array(
            'success' => false,
            'error' => '不支持的嵌入向量提供商'
        );
    }

    /**
     * OpenAI嵌入向量生成
     */
    private function generate_embedding_openai($text)
    {
        $body = array(
            'input' => $text,
            'model' => 'text-embedding-ada-002'
        );

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
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

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return array(
                'success' => false,
                'error' => $body['error']['message']
            );
        }

        return array(
            'success' => true,
            'embedding' => $body['data'][0]['embedding'],
            'usage' => $body['usage']
        );
    }

    /**
     * 记录AI任务
     */
    public function log_task($type, $input, $result, $tokens_used = 0, $cost = 0.0)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'xiaowu_ai_tasks';

        $wpdb->insert(
            $table_name,
            array(
                'type' => $type,
                'input' => json_encode($input),
                'status' => $result['success'] ? 'completed' : 'failed',
                'result' => isset($result['content']) ? $result['content'] : null,
                'error' => isset($result['error']) ? $result['error'] : null,
                'tokens_used' => $tokens_used,
                'cost' => $cost,
                'user_id' => get_current_user_id(),
                'completed_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%s')
        );

        return $wpdb->insert_id;
    }
}
