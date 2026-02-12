<?php
/**
 * AI提供商接口
 *
 * 定义所有AI提供商必须实现的标准接口
 *
 * @package Xiaowu_AI
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI提供商接口
 */
interface Xiaowu_AI_Provider_Interface
{
    /**
     * 发送文本请求
     *
     * @param string $prompt 用户提示词
     * @param string $system_prompt 系统提示词
     * @param array $options 额外选项（model, temperature, max_tokens等）
     * @return array 响应结果
     */
    public function request_text($prompt, $system_prompt = '', $options = array());

    /**
     * 生成图片
     *
     * @param string $prompt 图片生成提示词
     * @param array $options 额外选项（size, n, model等）
     * @return array 响应结果
     */
    public function generate_image($prompt, $options = array());

    /**
     * 生成嵌入向量
     *
     * @param string $text 要生成向量的文本
     * @return array 响应结果
     */
    public function generate_embedding($text);

    /**
     * 验证配置
     *
     * @param array $config 配置数组
     * @return bool|array 成功返回true，失败返回错误信息数组
     */
    public function validate_config($config);

    /**
     * 获取支持的模型列表
     *
     * @return array 模型列表
     */
    public function get_models();

    /**
     * 获取提供商支持的功能
     *
     * @return array 支持的功能列表（text, image, embedding等）
     */
    public function get_capabilities();

    /**
     * 测试连接
     *
     * @return array 测试结果
     */
    public function test_connection();
}
