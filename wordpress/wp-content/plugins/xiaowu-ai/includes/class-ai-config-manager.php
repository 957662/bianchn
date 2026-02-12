<?php
/**
 * AI配置管理器
 *
 * 管理所有AI提供商的配置，支持从常量或WordPress选项读取配置
 *
 * @package Xiaowu_AI
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_AI_Config_Manager
{
    /**
     * 所有支持的提供商
     */
    private $providers;

    /**
     * API密钥存储
     */
    private $api_keys;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->init_providers();
        $this->init_api_keys();
    }

    /**
     * 初始化提供商配置
     */
    private function init_providers()
    {
        // 从常量或使用默认配置
        $this->providers = array(
            'openai' => array(
                'name' => 'OpenAI',
                'endpoint' => defined('XIAOWU_AI_OPENAI_ENDPOINT')
                    ? XIAOWU_AI_OPENAI_ENDPOINT
                    : 'https://api.openai.com/v1/chat/completions',
                'image_endpoint' => defined('XIAOWU_AI_OPENAI_IMAGE_ENDPOINT')
                    ? XIAOWU_AI_OPENAI_IMAGE_ENDPOINT
                    : 'https://api.openai.com/v1/images/generations',
                'embedding_endpoint' => defined('XIAOWU_AI_OPENAI_EMBEDDING_ENDPOINT')
                    ? XIAOWU_AI_OPENAI_EMBEDDING_ENDPOINT
                    : 'https://api.openai.com/v1/embeddings',
                'models' => array('gpt-4', 'gpt-3.5-turbo', 'gpt-4-turbo'),
                'default_model' => 'gpt-4',
                'supports' => array('text', 'image', 'embedding')
            ),
            'anthropic' => array(
                'name' => 'Claude (Anthropic)',
                'endpoint' => defined('XIAOWU_AI_ANTHROPIC_ENDPOINT')
                    ? XIAOWU_AI_ANTHROPIC_ENDPOINT
                    : 'https://api.anthropic.com/v1/messages',
                'models' => array('claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'),
                'default_model' => 'claude-3-sonnet',
                'supports' => array('text')
            ),
            'qianwen' => array(
                'name' => '通义千问',
                'endpoint' => defined('XIAOWU_AI_QIANWEN_ENDPOINT')
                    ? XIAOWU_AI_QIANWEN_ENDPOINT
                    : 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
                'models' => array('qwen-turbo', 'qwen-plus', 'qwen-max'),
                'default_model' => 'qwen-max',
                'supports' => array('text')
            ),
            'wenxin' => array(
                'name' => '文心一言',
                'endpoint' => defined('XIAOWU_AI_WENXIN_ENDPOINT')
                    ? XIAOWU_AI_WENXIN_ENDPOINT
                    : 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/completions',
                'models' => array('ernie-bot', 'ernie-bot-turbo'),
                'default_model' => 'ernie-bot',
                'supports' => array('text')
            ),
            'zhipu' => array(
                'name' => '智谱AI',
                'endpoint' => defined('XIAOWU_AI_ZHIPU_ENDPOINT')
                    ? XIAOWU_AI_ZHIPU_ENDPOINT
                    : 'https://open.bigmodel.cn/api/paas/v4/chat/completions',
                'models' => array('glm-4', 'glm-3-turbo'),
                'default_model' => 'glm-4',
                'supports' => array('text')
            ),
            'custom' => array(
                'name' => '自定义API',
                'endpoint' => get_option('xiaowu_ai_custom_endpoint', ''),
                'models' => array(),
                'default_model' => '',
                'supports' => array('text')
            )
        );
    }

    /**
     * 初始化API密钥
     */
    private function init_api_keys()
    {
        $this->api_keys = array(
            'openai' => $this->get_api_key('openai'),
            'anthropic' => $this->get_api_key('anthropic'),
            'qianwen' => $this->get_api_key('qianwen'),
            'wenxin' => $this->get_api_key('wenxin'),
            'zhipu' => $this->get_api_key('zhipu'),
            'custom' => $this->get_api_key('custom')
        );
    }

    /**
     * 获取指定提供商的API密钥
     */
    private function get_api_key($provider)
    {
        // 优先从常量获取
        $constant_name = 'XIAOWU_AI_' . strtoupper($provider) . '_API_KEY';
        if (defined($constant_name)) {
            return constant($constant_name);
        }

        // 从WordPress选项获取（解密）
        $encrypted_key = get_option("xiaowu_ai_{$provider}_key", '');
        if (!empty($encrypted_key)) {
            return $this->decrypt_api_key($encrypted_key);
        }

        return '';
    }

    /**
     * 加密API密钥
     */
    public function encrypt_api_key($key)
    {
        if (empty($key)) {
            return '';
        }

        $method = 'AES-256-CBC';
        $key = substr(hash('sha256', AUTH_KEY), 0, 32);
        $iv = substr(hash('sha256', SECURE_AUTH_KEY), 0, 16);

        $encrypted = openssl_encrypt($key, $method, $key, 0, $iv);
        return base64_encode($encrypted);
    }

    /**
     * 解密API密钥
     */
    public function decrypt_api_key($encrypted)
    {
        if (empty($encrypted)) {
            return '';
        }

        $method = 'AES-256-CBC';
        $key = substr(hash('sha256', AUTH_KEY), 0, 32);
        $iv = substr(hash('sha256', SECURE_AUTH_KEY), 0, 16);

        $decrypted = openssl_decrypt(base64_decode($encrypted), $method, $key, 0, $iv);
        return $decrypted;
    }

    /**
     * 获取提供商配置
     */
    public function get_provider_config($provider)
    {
        if (!isset($this->providers[$provider])) {
            return array();
        }

        $config = $this->providers[$provider];
        $config['api_key'] = $this->api_keys[$provider] ?? '';

        // 从选项覆盖端点（如果用户自定义了）
        $custom_endpoint = get_option("xiaowu_ai_{$provider}_endpoint", '');
        if (!empty($custom_endpoint)) {
            $config['endpoint'] = $custom_endpoint;
        }

        return $config;
    }

    /**
     * 获取所有提供商列表
     */
    public function get_all_providers()
    {
        $providers = array();
        foreach ($this->providers as $key => $provider) {
            $providers[$key] = $provider['name'];
        }
        return $providers;
    }

    /**
     * 获取可用的提供商（有API密钥的）
     */
    public function get_available_providers()
    {
        $available = array();
        foreach ($this->providers as $key => $provider) {
            if (!empty($this->api_keys[$key])) {
                $available[$key] = $provider['name'];
            }
        }
        return $available;
    }

    /**
     * 检查提供商是否可用
     */
    public function is_provider_available($provider)
    {
        return isset($this->providers[$provider]) && !empty($this->api_keys[$provider]);
    }

    /**
     * 获取默认提供商
     */
    public function get_default_provider()
    {
        // 从选项获取
        $default = get_option('xiaowu_ai_default_provider', 'openai');

        // 如果默认不可用，返回第一个可用的
        if (!$this->is_provider_available($default)) {
            $available = $this->get_available_providers();
            if (!empty($available)) {
                $default = array_key_first($available);
            } else {
                $default = 'openai'; // 即使不可用也返回
            }
        }

        return $default;
    }

    /**
     * 保存提供商配置
     */
    public function save_provider_config($provider, $config)
    {
        $success = true;

        // 保存API密钥（加密）
        if (isset($config['api_key'])) {
            $encrypted = $this->encrypt_api_key($config['api_key']);
            update_option("xiaowu_ai_{$provider}_key", $encrypted);
            $this->api_keys[$provider] = $config['api_key'];
        }

        // 保存自定义端点
        if (isset($config['endpoint'])) {
            update_option("xiaowu_ai_{$provider}_endpoint", $config['endpoint']);
            $this->providers[$provider]['endpoint'] = $config['endpoint'];
        }

        // 保存模型列表
        if (isset($config['models'])) {
            update_option("xiaowu_ai_{$provider}_models", $config['models']);
            $this->providers[$provider]['models'] = $config['models'];
        }

        return $success;
    }

    /**
     * 删除提供商配置
     */
    public function delete_provider_config($provider)
    {
        delete_option("xiaowu_ai_{$provider}_key");
        delete_option("xiaowu_ai_{$provider}_endpoint");
        delete_option("xiaowu_ai_{$provider}_models");

        $this->api_keys[$provider] = '';
    }
}
