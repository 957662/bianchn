<?php
/**
 * AI增强搜索类
 *
 * @package Xiaowu_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Xiaowu_AI_Search
{
    private $ai_enabled;

    public function __construct()
    {
        $this->ai_enabled = get_option('xiaowu_search_enable_ai', true);
    }

    /**
     * 增强搜索结果
     */
    public function enhance_results($query, $results)
    {
        if (!$this->ai_enabled) {
            return $results;
        }

        // AI重排序
        $results = $this->ai_rerank($query, $results);

        // 添加智能摘要
        $results = $this->add_intelligent_excerpts($query, $results);

        return $results;
    }

    /**
     * AI重排序结果
     */
    private function ai_rerank($query, $results)
    {
        // 使用简单的TF-IDF算法进行重排序
        // 实际项目中可以调用外部AI服务

        $query_terms = $this->tokenize($query);

        foreach ($results as &$result) {
            $score = 0;

            // 标题匹配权重更高
            $title_terms = $this->tokenize($result['title']);
            $title_match = count(array_intersect($query_terms, $title_terms));
            $score += $title_match * 3;

            // 内容匹配
            $content_terms = $this->tokenize(strip_tags($result['content']));
            $content_match = count(array_intersect($query_terms, $content_terms));
            $score += $content_match;

            // 新鲜度加分
            $days_old = (time() - strtotime($result['date'])) / (60 * 60 * 24);
            if ($days_old < 30) {
                $score += (30 - $days_old) / 30 * 2;
            }

            // 用户互动加分
            if (isset($result['views']) && $result['views'] > 0) {
                $score += log($result['views'] + 1) * 0.5;
            }

            $result['ai_score'] = $score;
        }

        // 按AI评分排序
        usort($results, function ($a, $b) {
            return $b['ai_score'] <=> $a['ai_score'];
        });

        return $results;
    }

    /**
     * 添加智能摘要
     */
    private function add_intelligent_excerpts($query, $results)
    {
        $query_terms = $this->tokenize($query);

        foreach ($results as &$result) {
            $content = strip_tags($result['content']);

            // 查找包含查询词的句子
            $sentences = preg_split('/[.!?。!?]/u', $content);
            $relevant_sentences = array();

            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (empty($sentence)) {
                    continue;
                }

                $sentence_terms = $this->tokenize($sentence);
                $matches = count(array_intersect($query_terms, $sentence_terms));

                if ($matches > 0) {
                    $relevant_sentences[] = array(
                        'text' => $sentence,
                        'matches' => $matches
                    );
                }
            }

            // 排序并选择最相关的句子
            usort($relevant_sentences, function ($a, $b) {
                return $b['matches'] <=> $a['matches'];
            });

            if (!empty($relevant_sentences)) {
                $excerpt = $relevant_sentences[0]['text'];
                if (isset($relevant_sentences[1])) {
                    $excerpt .= ' ' . $relevant_sentences[1]['text'];
                }
                $result['intelligent_excerpt'] = wp_trim_words($excerpt, 50);
            }
        }

        return $results;
    }

    /**
     * 语义搜索
     */
    public function semantic_search($query, $limit = 20)
    {
        // 这里是一个简化的实现
        // 实际项目中应该使用向量数据库和嵌入模型

        // 扩展查询词
        $expanded_query = $this->expand_query_semantically($query);

        // 使用扩展后的查询搜索
        $search_engine = new Xiaowu_Search_Engine();
        $results = $search_engine->search($expanded_query, array(
            'per_page' => $limit
        ));

        return $results;
    }

    /**
     * 语义扩展查询
     */
    private function expand_query_semantically($query)
    {
        // 简单的同义词扩展
        $synonyms = array(
            'tutorial' => array('guide', 'howto', '教程', '指南'),
            'error' => array('bug', 'issue', 'problem', '错误', '问题'),
            'install' => array('setup', 'deploy', '安装', '部署'),
            'code' => array('programming', 'development', '代码', '编程'),
        );

        $query_lower = mb_strtolower($query);
        $expanded = $query;

        foreach ($synonyms as $word => $syns) {
            if (stripos($query_lower, $word) !== false) {
                $expanded .= ' ' . implode(' ', $syns);
            }
        }

        return $expanded;
    }

    /**
     * 智能问答
     */
    public function question_answering($question)
    {
        // 检测是否是问题
        if (!$this->is_question($question)) {
            return array(
                'success' => false,
                'message' => '不是一个问题'
            );
        }

        // 提取关键词
        $keywords = $this->extract_keywords($question);

        // 搜索相关内容
        $search_engine = new Xiaowu_Search_Engine();
        $results = $search_engine->search($keywords, array('per_page' => 5));

        if (!$results['success'] || empty($results['data']['results'])) {
            return array(
                'success' => false,
                'message' => '未找到相关答案'
            );
        }

        // 提取最佳答案
        $answer = $this->extract_answer($question, $results['data']['results']);

        return array(
            'success' => true,
            'data' => array(
                'question' => $question,
                'answer' => $answer,
                'sources' => array_slice($results['data']['results'], 0, 3)
            )
        );
    }

    /**
     * 判断是否是问题
     */
    private function is_question($text)
    {
        $question_words = array('什么', '如何', '怎么', '为什么', '哪里', '谁', '什么时候', 'what', 'how', 'why', 'where', 'who', 'when');

        foreach ($question_words as $word) {
            if (mb_stripos($text, $word) !== false) {
                return true;
            }
        }

        // 检查问号
        if (mb_strpos($text, '?') !== false || mb_strpos($text, '?') !== false) {
            return true;
        }

        return false;
    }

    /**
     * 提取关键词
     */
    private function extract_keywords($text)
    {
        // 移除停用词
        $stopwords = array('的', '了', '在', '是', '我', '有', '和', '就', '不', '人', 'the', 'is', 'at', 'which', 'on');

        $terms = $this->tokenize($text);
        $keywords = array_diff($terms, $stopwords);

        return implode(' ', $keywords);
    }

    /**
     * 提取答案
     */
    private function extract_answer($question, $results)
    {
        // 从最相关的结果中提取答案
        $best_result = $results[0];

        // 简单实现：返回最相关内容的摘要
        $content = strip_tags($best_result['content']);
        $answer = wp_trim_words($content, 100);

        return array(
            'text' => $answer,
            'title' => $best_result['title'],
            'url' => $best_result['url'],
            'confidence' => 0.8
        );
    }

    /**
     * 分词
     */
    private function tokenize($text)
    {
        // 转小写
        $text = mb_strtolower($text);

        // 移除标点
        $text = preg_replace('/[^\w\s\u4e00-\u9fa5]/u', ' ', $text);

        // 分割
        $terms = preg_split('/\s+/', $text);

        // 过滤空值
        $terms = array_filter($terms, function ($term) {
            return mb_strlen($term) >= 2;
        });

        return $terms;
    }

    /**
     * 获取搜索意图
     */
    public function detect_search_intent($query)
    {
        $intents = array(
            'navigational' => array('登录', '注册', '首页', '关于', 'login', 'register', 'home', 'about'),
            'informational' => array('什么', '如何', '教程', '指南', 'what', 'how', 'tutorial', 'guide'),
            'transactional' => array('下载', '购买', '安装', 'download', 'buy', 'install'),
            'commercial' => array('比较', '最好', '推荐', 'compare', 'best', 'review')
        );

        $query_lower = mb_strtolower($query);

        foreach ($intents as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($query_lower, $keyword) !== false) {
                    return $intent;
                }
            }
        }

        return 'general';
    }

    /**
     * 个性化搜索结果
     */
    public function personalize_results($results, $user_id = null)
    {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return $results;
        }

        // 获取用户兴趣
        $user_interests = $this->get_user_interests($user_id);

        // 根据用户兴趣调整结果排序
        foreach ($results as &$result) {
            $boost = 0;

            // 检查分类匹配
            if (isset($result['categories'])) {
                foreach ($result['categories'] as $category) {
                    if (in_array($category, $user_interests['categories'])) {
                        $boost += 2;
                    }
                }
            }

            // 检查标签匹配
            if (isset($result['tags'])) {
                foreach ($result['tags'] as $tag) {
                    if (in_array($tag, $user_interests['tags'])) {
                        $boost += 1;
                    }
                }
            }

            // 检查作者匹配
            if (isset($result['author_id']) && in_array($result['author_id'], $user_interests['authors'])) {
                $boost += 3;
            }

            $result['personalization_boost'] = $boost;
            $result['relevance'] += $boost;
        }

        // 重新排序
        usort($results, function ($a, $b) {
            return $b['relevance'] <=> $a['relevance'];
        });

        return $results;
    }

    /**
     * 获取用户兴趣
     */
    private function get_user_interests($user_id)
    {
        $interests = array(
            'categories' => array(),
            'tags' => array(),
            'authors' => array()
        );

        // 从用户阅读历史推断兴趣
        $user_posts = get_posts(array(
            'author' => $user_id,
            'posts_per_page' => 10,
            'fields' => 'ids'
        ));

        foreach ($user_posts as $post_id) {
            $categories = wp_get_post_categories($post_id, array('fields' => 'names'));
            $tags = wp_get_post_tags($post_id, array('fields' => 'names'));

            $interests['categories'] = array_merge($interests['categories'], $categories);
            $interests['tags'] = array_merge($interests['tags'], $tags);
        }

        // 去重
        $interests['categories'] = array_unique($interests['categories']);
        $interests['tags'] = array_unique($interests['tags']);

        return $interests;
    }
}
