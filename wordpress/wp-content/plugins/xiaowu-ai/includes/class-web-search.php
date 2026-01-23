<?php
/**
 * 联网搜索类
 *
 * @package Xiaowu_AI
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xiaowu_Web_Search 类
 */
class Xiaowu_Web_Search
{
    /**
     * AI服务实例
     */
    private $ai_service;

    /**
     * 缓存管理器
     */
    private $cache_manager;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->ai_service = new Xiaowu_AI_Service();
        $this->cache_manager = new Xiaowu_Cache_Manager();
    }

    /**
     * 联网搜索
     */
    public function search($query, $num_results = 5, $language = 'zh-CN')
    {
        // 检查缓存
        $cache_key = 'xiaowu_web_search_' . md5($query . $num_results . $language);
        $cached_result = $this->cache_manager->get($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        // 执行搜索（这里使用Google自定义搜索API作为示例）
        $search_results = $this->perform_search($query, $num_results, $language);

        if (!$search_results['success']) {
            return $search_results;
        }

        // 使用AI总结搜索结果
        $summary = $this->summarize_results($query, $search_results['results']);

        $result = array(
            'success' => true,
            'query' => $query,
            'results' => $search_results['results'],
            'summary' => $summary,
            'total' => count($search_results['results'])
        );

        // 缓存结果
        $this->cache_manager->set($cache_key, $result, 7200); // 缓存2小时

        return $result;
    }

    /**
     * 执行搜索
     */
    private function perform_search($query, $num_results, $language)
    {
        // 这里使用SerpAPI作为搜索引擎接口
        // 实际使用时需要配置API密钥
        $api_key = get_option('xiaowu_serpapi_key');

        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'SerpAPI密钥未配置'
            );
        }

        $url = add_query_arg(array(
            'engine' => 'google',
            'q' => urlencode($query),
            'num' => $num_results,
            'hl' => $language,
            'api_key' => $api_key
        ), 'https://serpapi.com/search.json');

        $response = wp_remote_get($url, array('timeout' => 30));

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
                'error' => $body['error']
            );
        }

        $results = array();
        if (isset($body['organic_results'])) {
            foreach ($body['organic_results'] as $item) {
                $results[] = array(
                    'title' => $item['title'],
                    'url' => $item['link'],
                    'snippet' => isset($item['snippet']) ? $item['snippet'] : '',
                    'source' => isset($item['source']) ? $item['source'] : ''
                );
            }
        }

        return array(
            'success' => true,
            'results' => $results
        );
    }

    /**
     * 使用AI总结搜索结果
     */
    private function summarize_results($query, $results)
    {
        if (empty($results)) {
            return '';
        }

        $results_text = '';
        foreach ($results as $index => $result) {
            $results_text .= ($index + 1) . ". {$result['title']}\n";
            $results_text .= "   {$result['snippet']}\n";
            $results_text .= "   来源：{$result['url']}\n\n";
        }

        $prompt = "根据以下搜索结果，为查询「{$query}」提供一个综合性的总结：\n\n{$results_text}\n\n请提供：\n1. 简洁的总结（200字以内）\n2. 关键信息点\n3. 相关建议";

        $system_prompt = "你是一位信息整理专家，擅长从多个来源提取和综合信息。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        return $result['success'] ? $result['content'] : '';
    }

    /**
     * 获取网页内容
     */
    public function fetch_url_content($url)
    {
        // 检查缓存
        $cache_key = 'xiaowu_url_content_' . md5($url);
        $cached_content = $this->cache_manager->get($cache_key);

        if ($cached_content !== false) {
            return $cached_content;
        }

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; XiaowuBot/1.0)'
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }

        $html = wp_remote_retrieve_body($response);

        // 提取主要内容（简单的HTML解析）
        $content = $this->extract_main_content($html);

        $result = array(
            'success' => true,
            'url' => $url,
            'content' => $content,
            'length' => mb_strlen($content)
        );

        // 缓存结果
        $this->cache_manager->set($cache_key, $result, 3600); // 缓存1小时

        return $result;
    }

    /**
     * 提取主要内容
     */
    private function extract_main_content($html)
    {
        // 移除脚本和样式标签
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // 移除HTML标签
        $text = wp_strip_all_tags($html);

        // 移除多余空白
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // 限制长度
        return mb_substr($text, 0, 5000);
    }

    /**
     * 使用AI分析网页内容
     */
    public function analyze_url($url, $question = '')
    {
        $content_result = $this->fetch_url_content($url);

        if (!$content_result['success']) {
            return $content_result;
        }

        $content = $content_result['content'];

        if (empty($question)) {
            $prompt = "请总结以下网页的主要内容：\n\n{$content}";
        } else {
            $prompt = "根据以下网页内容回答问题：{$question}\n\n网页内容：\n{$content}";
        }

        $system_prompt = "你是一位专业的内容分析师，擅长从文本中提取关键信息。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        return array(
            'success' => true,
            'url' => $url,
            'analysis' => $result['content']
        );
    }

    /**
     * 搜索并回答问题
     */
    public function search_and_answer($question, $num_results = 5)
    {
        // 先执行搜索
        $search_result = $this->search($question, $num_results);

        if (!$search_result['success']) {
            return $search_result;
        }

        // 获取前几个结果的详细内容
        $detailed_content = '';
        $sources = array();

        foreach (array_slice($search_result['results'], 0, 3) as $result) {
            $content_result = $this->fetch_url_content($result['url']);
            if ($content_result['success']) {
                $detailed_content .= "来源：{$result['title']}\n";
                $detailed_content .= "URL：{$result['url']}\n";
                $detailed_content .= "内容：" . mb_substr($content_result['content'], 0, 1000) . "...\n\n";
                $sources[] = $result['url'];
            }
        }

        // 使用AI基于搜索结果回答问题
        $prompt = "根据以下搜索结果回答问题：{$question}\n\n{$detailed_content}\n\n请提供详细的回答，并注明信息来源。";

        $system_prompt = "你是一位知识渊博的助手，擅长基于多个来源综合回答问题。";

        $result = $this->ai_service->request($prompt, $system_prompt);

        if (!$result['success']) {
            return $result;
        }

        return array(
            'success' => true,
            'question' => $question,
            'answer' => $result['content'],
            'sources' => $sources,
            'search_results' => $search_result['results']
        );
    }

    /**
     * 获取最新资讯
     */
    public function get_latest_news($topic, $num_results = 10, $language = 'zh-CN')
    {
        $query = $topic . ' 最新';
        return $this->search($query, $num_results, $language);
    }
}
