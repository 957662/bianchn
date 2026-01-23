<?php
/**
 * 代码生成类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Code_Generator 类
 */
class Xiaowu_Code_Generator
{
    /**
     * AI服务实例
     */
    private $ai_service;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->ai_service = new Xiaowu_AI_Service();
    }

    /**
     * 生成代码
     */
    public function generate($description, $language = 'php', $framework = 'wordpress', $context = array())
    {
        $prompt = $this->build_prompt($description, $language, $framework, $context);
        $system_prompt = $this->get_system_prompt($language, $framework);

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        // 提取代码块
        $code = $this->extract_code($result['content'], $language);

        // 记录任务
        $tokens_used = isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : 0;
        $this->ai_service->log_task('code_generation', array(
            'description' => $description,
            'language' => $language,
            'framework' => $framework
        ), $result, $tokens_used);

        return array(
            'success' => true,
            'code' => $code,
            'explanation' => $result['content'],
            'language' => $language,
            'framework' => $framework
        );
    }

    /**
     * 构建提示词
     */
    private function build_prompt($description, $language, $framework, $context)
    {
        $prompt = "请根据以下描述生成{$language}代码";

        if (!empty($framework)) {
            $prompt .= "（使用{$framework}框架）";
        }

        $prompt .= "：\n\n{$description}\n\n";

        if (!empty($context)) {
            $prompt .= "上下文信息：\n";
            if (isset($context['existing_code'])) {
                $prompt .= "现有代码：\n```{$language}\n{$context['existing_code']}\n```\n\n";
            }
            if (isset($context['requirements'])) {
                $prompt .= "需求：\n{$context['requirements']}\n\n";
            }
        }

        $prompt .= "请提供：\n";
        $prompt .= "1. 完整的代码实现\n";
        $prompt .= "2. 代码说明和注释\n";
        $prompt .= "3. 使用示例\n";
        $prompt .= "4. 注意事项";

        return $prompt;
    }

    /**
     * 获取系统提示词
     */
    private function get_system_prompt($language, $framework)
    {
        $base_prompt = "你是一位经验丰富的软件工程师，擅长编写清晰、高效、可维护的代码。";

        $language_specific = array(
            'php' => '你精通PHP编程，熟悉PSR标准和最佳实践。',
            'javascript' => '你精通JavaScript/TypeScript，熟悉ES6+语法和现代前端开发。',
            'python' => '你精通Python编程，熟悉PEP 8规范和Pythonic编码风格。',
            'java' => '你精通Java编程，熟悉面向对象设计和Java最佳实践。'
        );

        $framework_specific = array(
            'wordpress' => '你熟悉WordPress插件开发和WordPress编码标准。',
            'laravel' => '你熟悉Laravel框架和其最佳实践。',
            'vue' => '你熟悉Vue.js框架和组件化开发。',
            'react' => '你熟悉React框架和函数式编程。'
        );

        $prompt = $base_prompt;

        if (isset($language_specific[$language])) {
            $prompt .= ' ' . $language_specific[$language];
        }

        if (isset($framework_specific[$framework])) {
            $prompt .= ' ' . $framework_specific[$framework];
        }

        return $prompt;
    }

    /**
     * 提取代码块
     */
    private function extract_code($content, $language)
    {
        // 尝试提取Markdown代码块
        $pattern = '/```' . preg_quote($language, '/') . '\s*(.*?)```/s';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }

        // 尝试提取通用代码块
        $pattern = '/```\s*(.*?)```/s';
        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }

        // 如果没有代码块标记，返回原内容
        return $content;
    }

    /**
     * 生成WordPress短代码
     */
    public function generate_shortcode($description, $parameters = array())
    {
        $context = array(
            'requirements' => "这是一个WordPress短代码，需要：\n"
                . "1. 使用WordPress短代码API\n"
                . "2. 支持参数：" . implode(', ', $parameters) . "\n"
                . "3. 返回HTML输出\n"
                . "4. 包含必要的安全检查"
        );

        return $this->generate($description, 'php', 'wordpress', $context);
    }

    /**
     * 生成WordPress钩子
     */
    public function generate_hook($description, $hook_type = 'action')
    {
        $context = array(
            'requirements' => "这是一个WordPress {$hook_type}，需要：\n"
                . "1. 使用add_{$hook_type}注册\n"
                . "2. 遵循WordPress钩子命名规范\n"
                . "3. 包含必要的参数和优先级\n"
                . "4. 添加适当的注释"
        );

        return $this->generate($description, 'php', 'wordpress', $context);
    }

    /**
     * 生成REST API端点
     */
    public function generate_rest_endpoint($description, $method = 'GET', $parameters = array())
    {
        $context = array(
            'requirements' => "这是一个WordPress REST API端点，需要：\n"
                . "1. 使用register_rest_route注册\n"
                . "2. HTTP方法：{$method}\n"
                . "3. 参数：" . implode(', ', $parameters) . "\n"
                . "4. 包含权限检查回调\n"
                . "5. 返回WP_REST_Response对象"
        );

        return $this->generate($description, 'php', 'wordpress', $context);
    }

    /**
     * 生成Vue组件
     */
    public function generate_vue_component($description, $props = array(), $emits = array())
    {
        $context = array(
            'requirements' => "这是一个Vue 3组件，需要：\n"
                . "1. 使用Composition API\n"
                . "2. Props：" . implode(', ', $props) . "\n"
                . "3. Emits：" . implode(', ', $emits) . "\n"
                . "4. 包含TypeScript类型定义\n"
                . "5. 遵循Vue风格指南"
        );

        return $this->generate($description, 'javascript', 'vue', $context);
    }

    /**
     * 代码审查
     */
    public function review_code($code, $language = 'php')
    {
        $prompt = "请审查以下{$language}代码并提供改进建议：\n\n```{$language}\n{$code}\n```\n\n请从以下方面进行审查：\n1. 代码质量和可读性\n2. 潜在的bug和安全问题\n3. 性能优化建议\n4. 最佳实践\n5. 具体的改进建议";

        $system_prompt = "你是一位资深的代码审查专家，擅长发现代码中的问题并提供建设性的改进建议。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        return array(
            'success' => true,
            'review' => $result['content']
        );
    }

    /**
     * 代码优化
     */
    public function optimize_code($code, $language = 'php', $focus = 'performance')
    {
        $focus_descriptions = array(
            'performance' => '性能优化',
            'readability' => '可读性优化',
            'security' => '安全性优化',
            'maintainability' => '可维护性优化'
        );

        $focus_text = isset($focus_descriptions[$focus]) ? $focus_descriptions[$focus] : '全面优化';

        $prompt = "请优化以下{$language}代码（重点：{$focus_text}）：\n\n```{$language}\n{$code}\n```\n\n请提供优化后的代码，并说明优化的理由。";

        $system_prompt = "你是一位专业的代码优化专家，擅长提高代码质量和性能。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        $optimized_code = $this->extract_code($result['content'], $language);

        return array(
            'success' => true,
            'original_code' => $code,
            'optimized_code' => $optimized_code,
            'explanation' => $result['content']
        );
    }

    /**
     * 代码文档生成
     */
    public function generate_documentation($code, $language = 'php', $format = 'phpdoc')
    {
        $prompt = "请为以下{$language}代码生成{$format}格式的文档注释：\n\n```{$language}\n{$code}\n```\n\n文档应包括：\n1. 函数/类的描述\n2. 参数说明\n3. 返回值说明\n4. 使用示例\n5. 注意事项";

        $system_prompt = "你是一位技术文档专家，擅长编写清晰、准确的代码文档。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        return array(
            'success' => true,
            'documentation' => $result['content']
        );
    }

    /**
     * 单元测试生成
     */
    public function generate_unit_tests($code, $language = 'php', $framework = 'phpunit')
    {
        $prompt = "请为以下{$language}代码生成{$framework}单元测试：\n\n```{$language}\n{$code}\n```\n\n测试应包括：\n1. 正常情况测试\n2. 边界条件测试\n3. 异常情况测试\n4. 模拟对象的使用（如果需要）";

        $system_prompt = "你是一位测试工程师，擅长编写全面的单元测试。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        $test_code = $this->extract_code($result['content'], $language);

        return array(
            'success' => true,
            'test_code' => $test_code,
            'explanation' => $result['content']
        );
    }
}
