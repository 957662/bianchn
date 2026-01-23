<?php
/**
 * 文章优化器类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Article_Optimizer 类
 */
class Xiaowu_Article_Optimizer
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
     * 优化文章
     */
    public function optimize($title, $content, $type = 'seo', $language = 'zh-CN')
    {
        $prompt = $this->build_prompt($title, $content, $type, $language);
        $system_prompt = $this->get_system_prompt($type, $language);

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        // 解析AI返回的建议
        $suggestions = $this->parse_suggestions($result['content'], $type);

        // 记录任务
        $tokens_used = isset($result['usage']['total_tokens']) ? $result['usage']['total_tokens'] : 0;
        $this->ai_service->log_task('article_optimization', array(
            'title' => $title,
            'type' => $type
        ), $result, $tokens_used);

        return array(
            'success' => true,
            'suggestions' => $suggestions,
            'raw_response' => $result['content']
        );
    }

    /**
     * 构建提示词
     */
    private function build_prompt($title, $content, $type, $language)
    {
        $prompts = array(
            'seo' => "请分析以下文章并提供SEO优化建议：\n\n标题：{$title}\n\n内容：\n{$content}\n\n请提供具体的优化建议，包括：\n1. 标题优化（使其更具吸引力和SEO友好）\n2. 关键词建议（提取3-5个核心关键词）\n3. 元描述建议（120-150字符）\n4. 内容结构优化建议\n5. 内部链接建议",

            'readability' => "请分析以下文章的可读性并提供改进建议：\n\n标题：{$title}\n\n内容：\n{$content}\n\n请提供具体的可读性优化建议，包括：\n1. 段落结构优化\n2. 句子长度和复杂度改进\n3. 添加小标题建议\n4. 内容层次结构优化\n5. 语言表达改进",

            'style' => "请分析以下文章的写作风格并提供改进建议：\n\n标题：{$title}\n\n内容：\n{$content}\n\n请提供具体的风格优化建议，包括：\n1. 语气和口吻调整\n2. 专业术语使用优化\n3. 文章节奏改进\n4. 开头和结尾优化\n5. 整体风格统一性建议",

            'grammar' => "请检查以下文章的语法和拼写错误：\n\n标题：{$title}\n\n内容：\n{$content}\n\n请指出所有语法错误、拼写错误和标点符号问题，并提供修正建议。"
        );

        return isset($prompts[$type]) ? $prompts[$type] : $prompts['seo'];
    }

    /**
     * 获取系统提示词
     */
    private function get_system_prompt($type, $language)
    {
        $base_prompt = "你是一位专业的内容编辑和SEO专家，擅长优化中文技术博客文章。";

        $type_prompts = array(
            'seo' => $base_prompt . "请专注于SEO优化，提供具体可执行的建议。",
            'readability' => $base_prompt . "请专注于提高文章的可读性和用户体验。",
            'style' => $base_prompt . "请专注于改进文章的写作风格和表达方式。",
            'grammar' => $base_prompt . "请专注于识别和修正语法、拼写和标点符号错误。"
        );

        return isset($type_prompts[$type]) ? $type_prompts[$type] : $type_prompts['seo'];
    }

    /**
     * 解析AI建议
     */
    private function parse_suggestions($content, $type)
    {
        $suggestions = array();

        // 根据不同的优化类型解析建议
        switch ($type) {
            case 'seo':
                $suggestions = $this->parse_seo_suggestions($content);
                break;
            case 'readability':
                $suggestions = $this->parse_readability_suggestions($content);
                break;
            case 'style':
                $suggestions = $this->parse_style_suggestions($content);
                break;
            case 'grammar':
                $suggestions = $this->parse_grammar_suggestions($content);
                break;
        }

        return $suggestions;
    }

    /**
     * 解析SEO建议
     */
    private function parse_seo_suggestions($content)
    {
        $suggestions = array(
            'title' => '',
            'keywords' => array(),
            'meta_description' => '',
            'structure' => array(),
            'internal_links' => array()
        );

        // 简单的文本解析（实际应用中可能需要更复杂的解析逻辑）
        $lines = explode("\n", $content);
        $current_section = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, '标题优化') !== false || strpos($line, '1.') === 0) {
                $current_section = 'title';
            } elseif (strpos($line, '关键词') !== false || strpos($line, '2.') === 0) {
                $current_section = 'keywords';
            } elseif (strpos($line, '元描述') !== false || strpos($line, '3.') === 0) {
                $current_section = 'meta_description';
            } elseif (strpos($line, '结构优化') !== false || strpos($line, '4.') === 0) {
                $current_section = 'structure';
            } elseif (strpos($line, '内部链接') !== false || strpos($line, '5.') === 0) {
                $current_section = 'internal_links';
            } else {
                // 将内容添加到当前部分
                if ($current_section === 'title' && empty($suggestions['title'])) {
                    $suggestions['title'] = $line;
                } elseif ($current_section === 'keywords') {
                    $suggestions['keywords'][] = $line;
                } elseif ($current_section === 'meta_description' && empty($suggestions['meta_description'])) {
                    $suggestions['meta_description'] = $line;
                } elseif ($current_section === 'structure') {
                    $suggestions['structure'][] = $line;
                } elseif ($current_section === 'internal_links') {
                    $suggestions['internal_links'][] = $line;
                }
            }
        }

        return $suggestions;
    }

    /**
     * 解析可读性建议
     */
    private function parse_readability_suggestions($content)
    {
        return array(
            'paragraphs' => array(),
            'sentences' => array(),
            'headings' => array(),
            'structure' => array(),
            'language' => array(),
            'raw_content' => $content
        );
    }

    /**
     * 解析风格建议
     */
    private function parse_style_suggestions($content)
    {
        return array(
            'tone' => array(),
            'terminology' => array(),
            'pacing' => array(),
            'opening_closing' => array(),
            'consistency' => array(),
            'raw_content' => $content
        );
    }

    /**
     * 解析语法建议
     */
    private function parse_grammar_suggestions($content)
    {
        return array(
            'grammar_errors' => array(),
            'spelling_errors' => array(),
            'punctuation_errors' => array(),
            'raw_content' => $content
        );
    }

    /**
     * 生成文章摘要
     */
    public function generate_summary($title, $content, $max_length = 200, $language = 'zh-CN')
    {
        $prompt = "请为以下文章生成一个简洁的摘要（不超过{$max_length}字）：\n\n标题：{$title}\n\n内容：\n{$content}";
        $system_prompt = "你是一位专业的内容编辑，擅长提取文章核心要点并生成简洁的摘要。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        return array(
            'success' => true,
            'summary' => trim($result['content'])
        );
    }

    /**
     * 提取关键词
     */
    public function extract_keywords($title, $content, $count = 5, $language = 'zh-CN')
    {
        $prompt = "请从以下文章中提取{$count}个核心关键词：\n\n标题：{$title}\n\n内容：\n{$content}\n\n请只返回关键词列表，每行一个关键词。";
        $system_prompt = "你是一位专业的SEO专家，擅长提取文章的核心关键词。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        $keywords = array_filter(array_map('trim', explode("\n", $result['content'])));

        return array(
            'success' => true,
            'keywords' => array_slice($keywords, 0, $count)
        );
    }

    /**
     * 优化标题
     */
    public function optimize_title($title, $content, $style = 'seo', $language = 'zh-CN')
    {
        $style_prompts = array(
            'seo' => '请优化以下标题，使其更符合SEO要求，包含关键词且具有吸引力',
            'catchy' => '请优化以下标题，使其更具吸引力和点击欲望',
            'professional' => '请优化以下标题，使其更加专业和正式',
            'casual' => '请优化以下标题，使其更加轻松和口语化'
        );

        $style_text = isset($style_prompts[$style]) ? $style_prompts[$style] : $style_prompts['seo'];
        $prompt = "{$style_text}：\n\n原标题：{$title}\n\n文章内容摘要：\n" . mb_substr($content, 0, 500) . "...\n\n请提供3个优化后的标题选项。";
        $system_prompt = "你是一位专业的标题优化专家。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        $titles = array_filter(array_map('trim', explode("\n", $result['content'])));

        return array(
            'success' => true,
            'titles' => array_slice($titles, 0, 3)
        );
    }
}
