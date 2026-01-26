<?php
/**
 * SQL 注入防护类
 * 
 * 提供多层次的 SQL 注入防护机制
 * 包括参数化查询、查询验证、日志记录等
 * 
 * @package Xiaowu\Security
 * @author  Xiaowu Team
 * @version 1.0
 */

namespace Xiaowu\Security;

/**
 * SQLInjectionProtection 类
 * 
 * SQL 注入防护系统
 * 
 * 防护策略：
 * 1. 参数化查询 - 始终使用 prepare() 和占位符
 * 2. 输入验证 - 验证输入数据类型和格式
 * 3. 查询分析 - 检测可疑的 SQL 语法
 * 4. 错误处理 - 隐藏敏感的数据库错误信息
 * 5. 日志记录 - 记录可疑的查询操作
 */
class SQLInjectionProtection {
    
    /**
     * 数据库连接
     * 
     * @var \wpdb
     */
    private $wpdb;
    
    /**
     * 可疑 SQL 关键字
     * 
     * @var array
     */
    private $suspicious_keywords = [
        'DROP', 'DELETE', 'TRUNCATE', 'INSERT', 'UPDATE', 'ALTER',
        'CREATE', 'REPLACE', 'EXEC', 'EXECUTE', 'UNION', 'SELECT',
        'INTO', 'VALUES', 'SLEEP', 'BENCHMARK', 'WAITFOR', 'CAST',
        'CONVERT', 'LOAD_FILE', 'INTO OUTFILE', 'SCRIPT', 'JAVASCRIPT'
    ];
    
    /**
     * 可疑的 SQL 模式
     * 
     * @var array
     */
    private $suspicious_patterns = [
        '/(\d\s+OR\s+\d|\d\s*=\s*\d)/i',           // 数字比较 1 OR 1=1
        '/(UNION\s+SELECT|UNION\s+ALL)/i',         // UNION SELECT
        '/(;|--|#|\/\*)/i',                        // SQL 注释
        '/(\bOR\b|\bAND\b)\s+[\'"]?\d+[\'"]?\s*=/i', // OR/AND 后跟数字等式
        '/xp_|sp_|exec|execute/i',                 // 系统存储过程
        '/into\s+(outfile|dumpfile)/i',            // 文件操作
        '/load_file|load\s+data/i',                // 文件加载
        '/0x[0-9a-f]+/i',                          // 十六进制编码
    ];
    
    /**
     * 安全日志
     * 
     * @var array
     */
    private $security_log = [];
    
    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * 执行安全的数据库查询
     * 
     * @param string $query   SQL 查询字符串，使用 %s, %d, %f 作为占位符
     * @param mixed  $args    查询参数
     * 
     * @return array|null 查询结果
     * 
     * @example
     * $result = $protection->safe_query(
     *     "SELECT * FROM posts WHERE post_title = %s AND post_status = %s",
     *     ['My Post', 'publish']
     * );
     */
    public function safe_query($query, $args = []) {
        // 验证查询
        if (!$this->validate_query($query)) {
            $this->log_suspicious_activity('invalid_query', $query, $args);
            return null;
        }
        
        // 准备查询
        $prepared_query = $this->wpdb->prepare($query, $args);
        
        if (!$prepared_query) {
            $this->log_suspicious_activity('prepare_failed', $query, $args);
            return null;
        }
        
        // 执行查询
        $result = $this->wpdb->get_results($prepared_query);
        
        // 检查执行错误
        if ($this->wpdb->last_error) {
            $this->log_suspicious_activity('query_error', $query, $this->wpdb->last_error);
            return null;
        }
        
        return $result;
    }
    
    /**
     * 执行安全的单行查询
     * 
     * @param string $query SQL 查询
     * @param mixed  $args  查询参数
     * 
     * @return object|null 查询结果的单行记录
     */
    public function safe_query_row($query, $args = []) {
        $result = $this->safe_query($query, $args);
        return !empty($result) ? $result[0] : null;
    }
    
    /**
     * 执行安全的单值查询
     * 
     * @param string $query SQL 查询
     * @param mixed  $args  查询参数
     * 
     * @return mixed|null 查询结果的单个值
     */
    public function safe_query_var($query, $args = []) {
        $result = $this->safe_query($query, $args);
        if (!empty($result) && isset($result[0])) {
            $row = (array) $result[0];
            return reset($row);
        }
        return null;
    }
    
    /**
     * 验证 SQL 查询
     * 
     * 检查查询是否包含可疑的 SQL 注入特征
     * 
     * @param string $query SQL 查询
     * 
     * @return bool 查询是否通过验证
     */
    private function validate_query($query) {
        // 检查空查询
        if (empty($query)) {
            return false;
        }
        
        // 检查查询长度（防止超大查询）
        if (strlen($query) > 10000) {
            $this->log_suspicious_activity('query_too_long', substr($query, 0, 100));
            return false;
        }
        
        // 检查可疑关键字
        if ($this->has_suspicious_keywords($query)) {
            $this->log_suspicious_activity('suspicious_keywords', $query);
            return false;
        }
        
        // 检查可疑模式
        if ($this->has_suspicious_patterns($query)) {
            $this->log_suspicious_activity('suspicious_patterns', $query);
            return false;
        }
        
        // 检查多语句查询（防止 SQL 注入中的多语句）
        if ($this->has_multiple_statements($query)) {
            $this->log_suspicious_activity('multiple_statements', $query);
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查是否包含可疑关键字
     * 
     * @param string $query SQL 查询
     * 
     * @return bool
     */
    private function has_suspicious_keywords($query) {
        foreach ($this->suspicious_keywords as $keyword) {
            // 使用单词边界防止误报（如 SELECT 在占位符中）
            if (preg_match('/\b' . preg_quote($keyword) . '\b/i', $query)) {
                // 对于 SELECT，特别检查是否在占位符之外
                if ($keyword === 'SELECT' && preg_match('/%[sdf]/', $query)) {
                    continue; // 允许参数化的 SELECT
                }
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检查是否包含可疑模式
     * 
     * @param string $query SQL 查询
     * 
     * @return bool
     */
    private function has_suspicious_patterns($query) {
        foreach ($this->suspicious_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检查是否是多语句查询
     * 
     * 多语句查询（如 SELECT * FROM users; DROP TABLE users;）是常见的注入手法
     * 
     * @param string $query SQL 查询
     * 
     * @return bool
     */
    private function has_multiple_statements($query) {
        // 移除字符串中的分号以避免误报
        $query_without_strings = preg_replace("/'[^']*'/", "''", $query);
        $query_without_strings = preg_replace('/"[^"]*"/', '""', $query_without_strings);
        
        // 检查是否有多个非注释分号
        $semicolon_count = substr_count($query_without_strings, ';');
        if ($semicolon_count > 1) {
            return true;
        }
        
        // 检查是否有语句分隔符后跟关键字
        if (preg_match('/;\s*(DROP|DELETE|INSERT|UPDATE|TRUNCATE|EXEC)/i', $query)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 安全地插入数据
     * 
     * @param string $table  表名
     * @param array  $data   数据数组 ['column' => 'value', ...]
     * @param array  $format 数据格式 ['column' => '%s', ...]（可选）
     * 
     * @return int|false 插入的行 ID 或失败
     */
    public function safe_insert($table, $data, $format = null) {
        // 验证表名
        if (!$this->validate_table_name($table)) {
            $this->log_suspicious_activity('invalid_table', $table);
            return false;
        }
        
        // 验证数据
        if (!is_array($data) || empty($data)) {
            return false;
        }
        
        // 使用 WordPress 的 insert 方法
        $result = $this->wpdb->insert($table, $data, $format);
        
        if ($result === false) {
            $this->log_suspicious_activity('insert_failed', $table, $this->wpdb->last_error);
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * 安全地更新数据
     * 
     * @param string $table  表名
     * @param array  $data   更新数据
     * @param array  $where  条件数组
     * @param array  $format 数据格式（可选）
     * @param array  $where_format 条件格式（可选）
     * 
     * @return int|false 受影响的行数或失败
     */
    public function safe_update($table, $data, $where, $format = null, $where_format = null) {
        // 验证表名
        if (!$this->validate_table_name($table)) {
            $this->log_suspicious_activity('invalid_table', $table);
            return false;
        }
        
        // 验证数据
        if (!is_array($data) || empty($data) || !is_array($where) || empty($where)) {
            return false;
        }
        
        // 使用 WordPress 的 update 方法
        $result = $this->wpdb->update($table, $data, $where, $format, $where_format);
        
        if ($result === false) {
            $this->log_suspicious_activity('update_failed', $table, $this->wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    /**
     * 安全地删除数据
     * 
     * @param string $table        表名
     * @param array  $where        条件数组
     * @param array  $where_format 条件格式（可选）
     * 
     * @return int|false 受影响的行数或失败
     */
    public function safe_delete($table, $where, $where_format = null) {
        // 验证表名
        if (!$this->validate_table_name($table)) {
            $this->log_suspicious_activity('invalid_table', $table);
            return false;
        }
        
        // 验证条件
        if (!is_array($where) || empty($where)) {
            return false;
        }
        
        // 使用 WordPress 的 delete 方法
        $result = $this->wpdb->delete($table, $where, $where_format);
        
        if ($result === false) {
            $this->log_suspicious_activity('delete_failed', $table, $this->wpdb->last_error);
            return false;
        }
        
        return $result;
    }
    
    /**
     * 验证表名
     * 
     * @param string $table 表名
     * 
     * @return bool
     */
    private function validate_table_name($table) {
        // 表名只能包含字母、数字、下划线
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        
        // 表名长度检查
        if (strlen($table) > 64) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 转义字符串
     * 
     * @param string|array $data 数据
     * 
     * @return string|array 转义后的数据
     */
    public function escape($data) {
        if (is_array($data)) {
            return array_map([$this, 'escape'], $data);
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        return esc_sql($data);
    }
    
    /**
     * 记录可疑活动
     * 
     * @param string $type    活动类型
     * @param string $query   SQL 查询或其他数据
     * @param mixed  $details 详细信息
     */
    private function log_suspicious_activity($type, $query, $details = null) {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'query' => substr($query, 0, 500), // 截断以防止日志过大
            'details' => $details,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
        ];
        
        $this->security_log[] = $log_entry;
        
        // 如果是严重的威胁，记录到 WordPress 日志
        if (in_array($type, ['multiple_statements', 'query_too_long'])) {
            error_log('SQL Injection Attempt Detected: ' . wp_json_encode($log_entry));
        }
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
     * 获取安全日志
     * 
     * @return array 日志数组
     */
    public function get_log() {
        return $this->security_log;
    }
    
    /**
     * 清空安全日志
     */
    public function clear_log() {
        $this->security_log = [];
    }
}
?>
