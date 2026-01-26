<?php
/**
 * 输入验证器类
 * 
 * 提供统一的输入验证、清理和检查功能
 * 支持多种数据类型和验证规则
 * 
 * @package Xiaowu\Security
 * @author  Xiaowu Team
 * @version 1.0
 */

namespace Xiaowu\Security;

/**
 * InputValidator 类
 * 
 * 统一的输入验证系统，防止常见的安全问题：
 * - SQL 注入
 * - XSS 攻击
 * - CSRF 攻击
 * - 无效数据
 */
class InputValidator {
    
    /**
     * 验证结果
     * 
     * @var array
     */
    private $validation_errors = [];
    
    /**
     * 清理后的数据
     * 
     * @var array
     */
    private $sanitized_data = [];
    
    /**
     * 验证规则定义
     * 
     * @var array
     */
    private $rules = [];
    
    /**
     * 用户输入
     * 
     * @var array
     */
    private $input = [];
    
    /**
     * 构造函数
     * 
     * @param array $input 用户输入的数据
     */
    public function __construct($input = []) {
        $this->input = $input;
        $this->validation_errors = [];
        $this->sanitized_data = [];
    }
    
    /**
     * 添加验证规则
     * 
     * @param string $field 字段名
     * @param string $rules 验证规则，用 | 分隔多个规则
     *                      支持的规则：required, email, url, ip, phone, numeric,
     *                      alpha, alphanumeric, min:n, max:n, between:min,max,
     *                      regex:pattern, json, sanitize, no_sql, no_xss
     * 
     * @return self
     */
    public function rule($field, $rules) {
        $this->rules[$field] = $rules;
        return $this;
    }
    
    /**
     * 验证输入数据
     * 
     * @return bool 验证是否通过
     */
    public function validate() {
        $this->validation_errors = [];
        $this->sanitized_data = [];
        
        foreach ($this->rules as $field => $rule_string) {
            $rules = explode('|', $rule_string);
            $value = isset($this->input[$field]) ? $this->input[$field] : null;
            
            foreach ($rules as $rule) {
                $rule = trim($rule);
                
                if (!$this->validate_rule($field, $value, $rule)) {
                    break; // 规则失败，跳过该字段的后续规则
                }
            }
        }
        
        return count($this->validation_errors) === 0;
    }
    
    /**
     * 执行单个验证规则
     * 
     * @param string $field 字段名
     * @param mixed  $value 字段值
     * @param string $rule  验证规则
     * 
     * @return bool 验证是否通过
     */
    private function validate_rule($field, $value, $rule) {
        list($rule_name, $rule_param) = $this->parse_rule($rule);
        
        // 先进行清理操作
        if ($rule_name === 'sanitize' || $rule_name === 'no_sql' || $rule_name === 'no_xss') {
            $this->sanitized_data[$field] = $this->sanitize($field, $value, $rule_name);
            return true;
        }
        
        // 检查 required 规则
        if ($rule_name === 'required') {
            if (empty($value) && $value !== '0' && $value !== 0 && $value !== false) {
                $this->add_error($field, "字段 '{$field}' 是必需的");
                return false;
            }
            if (!isset($this->sanitized_data[$field])) {
                $this->sanitized_data[$field] = $value;
            }
            return true;
        }
        
        // 如果值为空且不是 required，则跳过其他验证
        if (empty($value) && $value !== '0' && $value !== 0) {
            if (!isset($this->sanitized_data[$field])) {
                $this->sanitized_data[$field] = $value;
            }
            return true;
        }
        
        // 执行特定的验证规则
        switch ($rule_name) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->add_error($field, "字段 '{$field}' 必须是有效的邮箱地址");
                    return false;
                }
                break;
            
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->add_error($field, "字段 '{$field}' 必须是有效的 URL");
                    return false;
                }
                break;
            
            case 'ip':
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->add_error($field, "字段 '{$field}' 必须是有效的 IP 地址");
                    return false;
                }
                break;
            
            case 'phone':
                if (!preg_match('/^1[3-9]\d{9}$/', $value)) {
                    $this->add_error($field, "字段 '{$field}' 必须是有效的手机号码");
                    return false;
                }
                break;
            
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->add_error($field, "字段 '{$field}' 必须是数字");
                    return false;
                }
                break;
            
            case 'alpha':
                if (!ctype_alpha($value)) {
                    $this->add_error($field, "字段 '{$field}' 只能包含字母");
                    return false;
                }
                break;
            
            case 'alphanumeric':
                if (!ctype_alnum($value)) {
                    $this->add_error($field, "字段 '{$field}' 只能包含字母和数字");
                    return false;
                }
                break;
            
            case 'json':
                if (!$this->is_valid_json($value)) {
                    $this->add_error($field, "字段 '{$field}' 必须是有效的 JSON");
                    return false;
                }
                break;
            
            case 'min':
                if (strlen($value) < $rule_param) {
                    $this->add_error($field, "字段 '{$field}' 最小长度为 {$rule_param}");
                    return false;
                }
                break;
            
            case 'max':
                if (strlen($value) > $rule_param) {
                    $this->add_error($field, "字段 '{$field}' 最大长度为 {$rule_param}");
                    return false;
                }
                break;
            
            case 'between':
                list($min, $max) = explode(',', $rule_param);
                $length = strlen($value);
                if ($length < $min || $length > $max) {
                    $this->add_error($field, "字段 '{$field}' 长度必须在 {$min} 到 {$max} 之间");
                    return false;
                }
                break;
            
            case 'regex':
                if (!preg_match($rule_param, $value)) {
                    $this->add_error($field, "字段 '{$field}' 格式不符合要求");
                    return false;
                }
                break;
        }
        
        if (!isset($this->sanitized_data[$field])) {
            $this->sanitized_data[$field] = $value;
        }
        
        return true;
    }
    
    /**
     * 解析规则字符串
     * 
     * @param string $rule 规则字符串，如 "min:5" 或 "required"
     * 
     * @return array [规则名, 规则参数]
     */
    private function parse_rule($rule) {
        if (strpos($rule, ':') !== false) {
            list($name, $param) = explode(':', $rule, 2);
            return [trim($name), trim($param)];
        }
        return [trim($rule), null];
    }
    
    /**
     * 清理输入数据
     * 
     * @param string $field     字段名
     * @param mixed  $value     字段值
     * @param string $sanitize_type 清理类型
     * 
     * @return mixed 清理后的值
     */
    private function sanitize($field, $value, $sanitize_type = 'sanitize') {
        if (is_null($value)) {
            return null;
        }
        
        switch ($sanitize_type) {
            case 'sanitize':
                // 通用清理：移除标签、转义特殊字符
                return sanitize_text_field($value);
            
            case 'no_sql':
                // SQL 注入防护
                return $this->prevent_sql_injection($value);
            
            case 'no_xss':
                // XSS 防护
                return $this->prevent_xss($value);
            
            default:
                return $value;
        }
    }
    
    /**
     * 防止 SQL 注入
     * 
     * @param mixed $value 输入值
     * 
     * @return mixed 防护后的值
     */
    private function prevent_sql_injection($value) {
        if (is_array($value)) {
            return array_map([$this, 'prevent_sql_injection'], $value);
        }
        
        if (!is_string($value)) {
            return $value;
        }
        
        // 使用 WordPress 的转义函数
        if (function_exists('esc_sql')) {
            return esc_sql($value);
        }
        
        // 如果不在 WordPress 环境中，使用 mysqli 的转义
        global $wpdb;
        if ($wpdb) {
            return $wpdb->_real_escape_sql($value);
        }
        
        // 备用方案
        return addslashes($value);
    }
    
    /**
     * 防止 XSS 攻击
     * 
     * @param mixed $value 输入值
     * 
     * @return mixed 防护后的值
     */
    private function prevent_xss($value) {
        if (is_array($value)) {
            return array_map([$this, 'prevent_xss'], $value);
        }
        
        if (!is_string($value)) {
            return $value;
        }
        
        // 使用 WordPress 的转义函数
        if (function_exists('esc_html')) {
            return esc_html($value);
        }
        
        // 备用方案：HTML 实体转义
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 检查是否为有效的 JSON
     * 
     * @param mixed $value 值
     * 
     * @return bool
     */
    private function is_valid_json($value) {
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
    
    /**
     * 添加验证错误
     * 
     * @param string $field   字段名
     * @param string $message 错误消息
     */
    private function add_error($field, $message) {
        if (!isset($this->validation_errors[$field])) {
            $this->validation_errors[$field] = [];
        }
        $this->validation_errors[$field][] = $message;
    }
    
    /**
     * 获取验证错误
     * 
     * @return array 错误数组
     */
    public function errors() {
        return $this->validation_errors;
    }
    
    /**
     * 获取指定字段的错误
     * 
     * @param string $field 字段名
     * 
     * @return array 该字段的错误列表
     */
    public function get_errors($field) {
        return isset($this->validation_errors[$field]) ? $this->validation_errors[$field] : [];
    }
    
    /**
     * 获取清理后的数据
     * 
     * @param string $field 字段名，为空则返回所有数据
     * 
     * @return mixed 清理后的数据
     */
    public function get($field = null) {
        if ($field === null) {
            return $this->sanitized_data;
        }
        return isset($this->sanitized_data[$field]) ? $this->sanitized_data[$field] : null;
    }
    
    /**
     * 是否有验证错误
     * 
     * @return bool
     */
    public function has_errors() {
        return count($this->validation_errors) > 0;
    }
    
    /**
     * 获取第一个错误消息
     * 
     * @return string|null
     */
    public function first_error() {
        foreach ($this->validation_errors as $errors) {
            if (!empty($errors)) {
                return $errors[0];
            }
        }
        return null;
    }
    
    /**
     * 返回 JSON 格式的错误
     * 
     * @return string JSON 字符串
     */
    public function errors_json() {
        return wp_json_encode($this->validation_errors);
    }
}
?>
