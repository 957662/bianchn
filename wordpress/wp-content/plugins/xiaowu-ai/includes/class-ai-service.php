<?php
/**
 * AI服务核心类
 *
 * 重构版本 v2.0.0 - 使用提供商模式，移除硬编码端点
 *
 * @package Xiaowu_AI
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 引入配置管理器
require_once XIAOWU_AI_PLUGIN_DIR . 'includes/class-ai-config-manager.php';

/**
 * Xiaowu_AI_Service 类
 */
class Xiaowu_AI_Service
{
    /**
     * 配置管理器
     */
    private $config_manager;

    /**
     * 当前提供商
     */
    private $provider;

    /**
     * 提供商实例
     */
    private $provider_instance;

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
    public function __construct($provider = null)
    {
        $this->config_manager = new Xiaowu_AI_Config_Manager();
        $this->provider = $provider ?: $this->config_manager->get_default_provider();
        $this->init_provider();

        $this->model = defined('XIAOWU_AI_MODEL') ? XIAOWU_AI_MODEL : get_option('xiaowu_ai_model', 'gpt-4');
        $this->max_tokens = defined('XIAOWU_AI_MAX_TOKENS') ? XIAOWU_AI_MAX_TOKENS : get_option('xiaowu_ai_max_tokens', 4000);
        $this->temperature = defined('XIAOWU_AI_TEMPERATURE') ? XIAOWU_AI_TEMPERATURE : get_option('xiaowu_ai_temperature', 0.7);
    }

    /**
     * 初始化提供商实例
     */
    private function init_provider()
    {
        $config = $this->config_manager->get_provider_config($this->provider);

        $provider_class_map = array(
            'openai' => 'Xiaowu_AI_Provider_Openai',
            'anthropic' => 'Xiaowu_AI_Provider_Anthropic',
            'qianwen' => 'Xiaowu_AI_Provider_Qianwen',
            'wenxin' => 'Xiaowu_AI_Provider_Wenxin',
            'zhipu' => 'Xiaowu_AI_Provider_Zhipu',
            'custom' => 'Xiaowu_AI_Provider_Custom'
        );

        $class_name = $provider_class_map[$this->provider] ?? null;

        if ($class_name) {
            $provider_file = XIAOWU_AI_PLUGIN_DIR . 'includes/providers/class-' . $this->provider . '-provider.php';

            if (file_exists($provider_file)) {
                require_once $provider_file;
                $this->provider_instance = new $class_name($config);
            } else {
                // 如果特定提供商文件不存在，使用自定义提供商
                require_once XIAOWU_AI_PLUGIN_DIR . 'includes/providers/class-custom-provider.php';
                $this->provider_instance = new Xiaowu_AI_Provider_Custom($config);
            }
        } else {
            // 使用自定义提供商作为后备
            require_once XIAOWU_AI_PLUGIN_DIR . 'includes/providers/class-custom-provider.php';
            $this->provider_instance = new Xiaowu_AI_Provider_Custom($config);
        }
    }

    /**
     * 发送请求到AI服务
     */
    public function request($prompt, $system_prompt = '', $options = array())
    {
        if (!$this->provider_instance) {
            return array(
                'success' => false,
                'error' => 'AI提供商未初始化'
            );
        }

        $default_options = array(
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'model' => $this->model
        );

        $options = wp_parse_args($options, $default_options);

        return $this->provider_instance->request_text($prompt, $system_prompt, $options);
    }

    /**
     * 生成图像
     */
    public function generate_image($prompt, $options = array())
    {
        if (!$this->provider_instance) {
            return array(
                'success' => false,
                'error' => 'AI提供商未初始化'
            );
        }

        $default_options = array(
            'size' => '1024x1024',
            'n' => 1,
            'model' => 'dall-e-3'
        );

        $options = wp_parse_args($options, $default_options);

        return $this->provider_instance->generate_image($prompt, $options);
    }

    /**
     * 生成嵌入向量
     */
    public function generate_embedding($text)
    {
        if (!$this->provider_instance) {
            return array(
                'success' => false,
                'error' => 'AI提供商未初始化'
            );
        }

        return $this->provider_instance->generate_embedding($text);
    }

    /**
     * 切换提供商
     */
    public function switch_provider($new_provider)
    {
        if ($this->config_manager->is_provider_available($new_provider)) {
            $this->provider = $new_provider;
            $this->init_provider();
            return true;
        }

        return false;
    }

    /**
     * 获取当前提供商
     */
    public function get_provider()
    {
        return $this->provider;
    }

    /**
     * 获取配置管理器
     */
    public function get_config_manager()
    {
        return $this->config_manager;
    }

    /**
     * 测试连接
     */
    public function test_connection()
    {
        if (!$this->provider_instance) {
            return array(
                'success' => false,
                'message' => 'AI提供商未初始化'
            );
        }

        return $this->provider_instance->test_connection();
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
                'provider' => $this->provider,
                'completed_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%s', '%s')
        );

        return $wpdb->insert_id;
    }

    /**
     * 获取所有可用提供商
     */
    public function get_available_providers()
    {
        return $this->config_manager->get_available_providers();
    }
}
