<?php
/**
 * XSS 防护类
 * 
 * 提供全面的跨站脚本攻击 (XSS) 防护
 * 包括输出转义、HTML 净化、内容安全策略等
 * 
 * @package Xiaowu\Security
 * @author  Xiaowu Team
 * @version 1.0
 */

namespace Xiaowu\Security;

/**
 * XSSProtection 类
 * 
 * XSS 防护系统
 * 
 * 防护策略：
 * 1. 输出转义 - 根据上下文进行适当的转义
 * 2. HTML 净化 - 移除恶意的 HTML 标签
 * 3. JavaScript 净化 - 防止嵌入式脚本
 * 4. URL 验证 - 检查 URL 是否安全
 * 5. CSP 头 - Content Security Policy 头设置
 * 6. 日志记录 - 记录可疑的 XSS 尝试
 */
class XSSProtection {
    
    /**
     * 允许的 HTML 标签
     * 
     * @var array
     */
    private $allowed_html_tags = [
        'p' => ['class' => true, 'id' => true, 'style' => true],
        'div' => ['class' => true, 'id' => true, 'style' => true],
        'span' => ['class' => true, 'id' => true, 'style' => true],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true],
        'img' => ['src' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'br' => [],
        'hr' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => ['cite' => true],
        'code' => ['class' => true],
        'pre' => ['class' => true],
        'table' => ['class' => true],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'td' => ['colspan' => true, 'rowspan' => true],
        'th' => ['colspan' => true, 'rowspan' => true],
    ];
    
    /**
     * 可疑的 JavaScript 关键字
     * 
     * @var array
     */
    private $javascript_keywords = [
        'javascript:',
        'onerror',
        'onload',
        'onclick',
        'onmouseover',
        'onmouseout',
        'onmousemove',
        'onkeydown',
        'onkeyup',
        'onfocus',
        'onblur',
        'onchange',
        'onsubmit',
        'script',
        'eval',
        'expression',
        'vbscript:',
        'data:text/html',
        '<iframe',
        '<object',
        '<embed',
    ];
    
    /**
     * XSS 检测日志
     * 
     * @var array
     */
    private $xss_log = [];
    
    /**
     * 构造函数
     */
    public function __construct() {
        // 设置安全的 HTTP 头
        $this->set_security_headers();
    }
    
    /**
     * 设置安全的 HTTP 头
     */
    private function set_security_headers() {
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'", false);
        
        // 防止点击劫持
        header("X-Frame-Options: DENY", false);
        
        // 防止 MIME 嗅探
        header("X-Content-Type-Options: nosniff", false);
        
        // 启用 XSS 防护
        header("X-XSS-Protection: 1; mode=block", false);
        
        // 引用者政策
        header("Referrer-Policy: strict-origin-when-cross-origin", false);
    }
    
    /**
     * 转义 HTML 输出
     * 
     * 用于在 HTML 内容中安全地输出数据
     * 
     * @param string $data 数据
     * 
     * @return string 转义后的数据
     */
    public function escape_html($data) {
        if (is_array($data)) {
            return array_map([$this, 'escape_html'], $data);
        }
        
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 转义 HTML 属性
     * 
     * 用于在 HTML 属性中安全地输出数据
     * 
     * @param string $data 数据
     * 
     * @return string 转义后的数据
     */
    public function escape_attr($data) {
        if (is_array($data)) {
            return array_map([$this, 'escape_attr'], $data);
        }
        
        // 移除可能的危险字符和协议
        $data = preg_replace('/[^a-zA-Z0-9\-_\.\s#\/@:]/u', '', $data);
        
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 转义 JavaScript 字符串
     * 
     * 用于在 JavaScript 中安全地输出数据
     * 
     * @param mixed $data 数据
     * 
     * @return string 转义后的 JavaScript 字符串
     */
    public function escape_js($data) {
        if (is_array($data) || is_object($data)) {
            $data = wp_json_encode($data);
        }
        
        $data = (string) $data;
        
        // 转义特殊字符
        $data = str_replace(
            ['\\', '"', "'", "\n", "\r", "\t", '<', '>'],
            ['\\\\', '\"', "\\'", '\\n', '\\r', '\\t', '\\x3c', '\\x3e'],
            $data
        );
        
        return $data;
    }
    
    /**
     * 转义 URL
     * 
     * 用于在 href 属性中安全地输出 URL
     * 
     * @param string $url URL
     * 
     * @return string 转义后的 URL
     */
    public function escape_url($url) {
        if (empty($url)) {
            return '';
        }
        
        // 检查 URL 是否安全
        if (!$this->is_safe_url($url)) {
            $this->log_xss_attempt('unsafe_url', $url);
            return '';
        }
        
        return esc_url($url);
    }
    
    /**
     * 转义 CSS
     * 
     * 用于在 style 属性中安全地输出 CSS
     * 
     * @param string $css CSS
     * 
     * @return string 转义后的 CSS
     */
    public function escape_css($css) {
        // 移除危险的 CSS 属性
        $dangerous_css = [
            'expression',
            'behavior',
            'binding',
            '-moz-binding',
            'behavior',
            'javascript:',
            'vbscript:',
        ];
        
        foreach ($dangerous_css as $pattern) {
            if (stripos($css, $pattern) !== false) {
                $this->log_xss_attempt('dangerous_css', $css);
                return '';
            }
        }
        
        // 基本的 CSS 净化
        $css = preg_replace('/[^a-zA-Z0-9\-_:\.\s;#()%,]/u', '', $css);
        
        return $css;
    }
    
    /**
     * 净化 HTML 内容
     * 
     * 移除恶意的 HTML 标签和属性
     * 
     * @param string $html HTML 内容
     * @param array  $allowed_tags 允许的标签（可选）
     * 
     * @return string 净化后的 HTML
     */
    public function sanitize_html($html, $allowed_tags = null) {
        if (empty($html)) {
            return '';
        }
        
        $allowed_tags = $allowed_tags ?? $this->allowed_html_tags;
        
        // 使用 WordPress 的 kses 函数
        if (function_exists('wp_kses')) {
            return wp_kses($html, $allowed_tags);
        }
        
        // 备用方案：简单的标签移除
        return strip_tags($html, array_keys($allowed_tags));
    }
    
    /**
     * 检查 URL 是否安全
     * 
     * @param string $url URL
     * 
     * @return bool
     */
    private function is_safe_url($url) {
        // 检查 URL 是否为空
        if (empty($url)) {
            return false;
        }
        
        // 检查是否包含危险的协议
        $dangerous_protocols = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
            'about:',
        ];
        
        $url_lower = strtolower($url);
        foreach ($dangerous_protocols as $protocol) {
            if (strpos($url_lower, $protocol) === 0) {
                return false;
            }
        }
        
        // 检查是否是有效的 URL
        if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('/^\/[^\s]*$/', $url)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 检测 XSS 攻击
     * 
     * 扫描输入数据中的 XSS 特征
     * 
     * @param string $data 输入数据
     * 
     * @return bool 是否检测到可疑内容
     */
    public function detect_xss($data) {
        if (is_array($data)) {
            foreach ($data as $value) {
                if ($this->detect_xss($value)) {
                    return true;
                }
            }
            return false;
        }
        
        $data_lower = strtolower($data);
        
        // 检查 JavaScript 关键字
        foreach ($this->javascript_keywords as $keyword) {
            if (stripos($data, $keyword) !== false) {
                $this->log_xss_attempt('javascript_keyword_detected', $data, $keyword);
                return true;
            }
        }
        
        // 检查 HTML 编码的危险字符
        $encoded_patterns = [
            '&#x3c',      // <
            '&#x3e',      // >
            '&#x22',      // "
            '&#x27',      // '
            '&#60',       // <
            '&#62',       // >
            '&#34',       // "
            '&#39',       // '
            '%3c',        // <
            '%3e',        // >
            '%22',        // "
            '%27',        // '
        ];
        
        foreach ($encoded_patterns as $pattern) {
            if (stripos($data, $pattern) !== false) {
                // 进一步检查是否跟随 script 或其他危险标签
                $following_text = substr($data, stripos($data, $pattern) + strlen($pattern), 20);
                if (preg_match('/(script|iframe|object|embed|img.*on)/i', $following_text)) {
                    $this->log_xss_attempt('encoded_dangerous_tag', $data);
                    return true;
                }
            }
        }
        
        // 检查 Unicode 转义
        if (preg_match('/\\\\u[0-9a-f]{4}/i', $data)) {
            $this->log_xss_attempt('unicode_escape_detected', $data);
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取允许的 HTML 标签和属性
     * 
     * @return array
     */
    public function get_allowed_html_tags() {
        return $this->allowed_html_tags;
    }
    
    /**
     * 添加允许的 HTML 标签
     * 
     * @param string $tag   标签名
     * @param array  $attrs 允许的属性
     */
    public function add_allowed_tag($tag, $attrs = []) {
        $this->allowed_html_tags[$tag] = $attrs;
    }
    
    /**
     * 移除允许的 HTML 标签
     * 
     * @param string $tag 标签名
     */
    public function remove_allowed_tag($tag) {
        unset($this->allowed_html_tags[$tag]);
    }
    
    /**
     * 记录 XSS 尝试
     * 
     * @param string $type    攻击类型
     * @param string $payload 恶意载荷
     * @param string $details 详细信息
     */
    private function log_xss_attempt($type, $payload, $details = null) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'payload' => substr($payload, 0, 500),
            'details' => $details,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'unknown',
        ];
        
        $this->xss_log[] = $log_entry;
        
        // 记录严重的威胁
        error_log('XSS Attempt Detected: ' . wp_json_encode($log_entry));
    }
    
    /**
     * 获取客户端 IP
     * 
     * @return string IP 地址
     */
    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR'])));
            return $ips[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        return 'unknown';
    }
    
    /**
     * 获取 XSS 检测日志
     * 
     * @return array 日志数组
     */
    public function get_log() {
        return $this->xss_log;
    }
    
    /**
     * 清空 XSS 检测日志
     */
    public function clear_log() {
        $this->xss_log = [];
    }
}
?>
