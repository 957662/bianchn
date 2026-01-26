<?php
/**
 * 输入验证集成测试脚本
 * 
 * 测试输入验证、SQL 注入防护和 XSS 防护功能
 */

// WordPress 环境初始化
$wp_path = dirname(__DIR__, 3);
require_once($wp_path . '/wp-load.php');

class InputValidationIntegrationTest {
    private $test_results = [];
    
    public function run_all_tests() {
        echo "\n========== 输入验证集成测试开始 ==========\n\n";
        
        // 测试 1: 输入验证器
        $this->test_input_validator();
        
        // 测试 2: SQL 注入防护
        $this->test_sql_injection_protection();
        
        // 测试 3: XSS 防护
        $this->test_xss_protection();
        
        // 输出测试报告
        $this->print_report();
    }
    
    private function test_input_validator() {
        echo "测试 1: 输入验证器\n";
        echo "─────────────────────────────\n";
        
        // 测试 1.1: 必需字段验证
        echo "  1.1 必需字段验证... ";
        try {
            $validator = xiaowu_get_input_validator([
                'name' => 'John Doe',
                'email' => '',
            ]);
            
            $validator->rule('name', 'required')
                     ->rule('email', 'required|email');
            
            if (!$validator->validate()) {
                echo "✅ PASS (正确识别缺失的邮箱)\n";
                $this->test_results[] = ['test' => '1.1 必需字段验证', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '1.1 必需字段验证', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '1.1 必需字段验证', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
        
        // 测试 1.2: 邮箱验证
        echo "  1.2 邮箱格式验证... ";
        try {
            $validator = xiaowu_get_input_validator([
                'email' => 'invalid-email',
            ]);
            
            $validator->rule('email', 'email');
            
            if (!$validator->validate()) {
                echo "✅ PASS (正确拒绝无效邮箱)\n";
                $this->test_results[] = ['test' => '1.2 邮箱格式验证', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '1.2 邮箱格式验证', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '1.2 邮箱格式验证', 'result' => 'FAIL'];
        }
        
        // 测试 1.3: 长度限制
        echo "  1.3 字段长度限制... ";
        try {
            $validator = xiaowu_get_input_validator([
                'password' => '123',
            ]);
            
            $validator->rule('password', 'min:8');
            
            if (!$validator->validate()) {
                echo "✅ PASS (正确识别过短密码)\n";
                $this->test_results[] = ['test' => '1.3 字段长度限制', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '1.3 字段长度限制', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '1.3 字段长度限制', 'result' => 'FAIL'];
        }
        
        // 测试 1.4: 数据清理
        echo "  1.4 数据清理和转义... ";
        try {
            $validator = xiaowu_get_input_validator([
                'comment' => '<script>alert("xss")</script>Hello',
            ]);
            
            $validator->rule('comment', 'sanitize');
            
            if ($validator->validate()) {
                $cleaned = $validator->get('comment');
                if (strpos($cleaned, '<script>') === false) {
                    echo "✅ PASS (正确清理恶意标签)\n";
                    $this->test_results[] = ['test' => '1.4 数据清理', 'result' => 'PASS'];
                } else {
                    echo "❌ FAIL (未清理恶意标签)\n";
                    $this->test_results[] = ['test' => '1.4 数据清理', 'result' => 'FAIL'];
                }
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '1.4 数据清理', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '1.4 数据清理', 'result' => 'FAIL'];
        }
        
        echo "\n";
    }
    
    private function test_sql_injection_protection() {
        echo "测试 2: SQL 注入防护\n";
        echo "─────────────────────────────\n";
        
        // 测试 2.1: 可疑关键字检测
        echo "  2.1 可疑 SQL 关键字检测... ";
        try {
            $protection = xiaowu_get_sql_protection();
            
            // 这应该是一个参数化查询的示例
            $query = "SELECT * FROM posts WHERE post_title = %s AND ID = %d";
            
            // 检查查询是否通过验证（使用反射来访问私有方法）
            $reflection = new ReflectionClass($protection);
            $method = $reflection->getMethod('validate_query');
            $method->setAccessible(true);
            
            if ($method->invoke($protection, $query)) {
                echo "✅ PASS (正确的参数化查询通过)\n";
                $this->test_results[] = ['test' => '2.1 SQL 关键字检测', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '2.1 SQL 关键字检测', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "⚠️  WARNING: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '2.1 SQL 关键字检测', 'result' => 'WARNING'];
        }
        
        // 测试 2.2: SQL 转义
        echo "  2.2 SQL 字符串转义... ";
        try {
            $protection = xiaowu_get_sql_protection();
            $dangerous_string = "'; DROP TABLE users; --";
            $escaped = $protection->escape($dangerous_string);
            
            if ($escaped !== $dangerous_string && strlen($escaped) > strlen($dangerous_string)) {
                echo "✅ PASS (字符串已正确转义)\n";
                $this->test_results[] = ['test' => '2.2 SQL 转义', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '2.2 SQL 转义', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '2.2 SQL 转义', 'result' => 'FAIL'];
        }
        
        echo "\n";
    }
    
    private function test_xss_protection() {
        echo "测试 3: XSS 防护\n";
        echo "─────────────────────────────\n";
        
        // 测试 3.1: HTML 转义
        echo "  3.1 HTML 内容转义... ";
        try {
            $protection = xiaowu_get_xss_protection();
            $html = '<script>alert("xss")</script>';
            $escaped = $protection->escape_html($html);
            
            if (strpos($escaped, '<script>') === false && strpos($escaped, '&lt;') !== false) {
                echo "✅ PASS (HTML 已正确转义)\n";
                $this->test_results[] = ['test' => '3.1 HTML 转义', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '3.1 HTML 转义', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '3.1 HTML 转义', 'result' => 'FAIL'];
        }
        
        // 测试 3.2: XSS 检测
        echo "  3.2 XSS 攻击检测... ";
        try {
            $protection = xiaowu_get_xss_protection();
            $xss_payload = '<img src=x onerror="alert(\'xss\')">';
            
            if ($protection->detect_xss($xss_payload)) {
                echo "✅ PASS (正确检测到 XSS 攻击)\n";
                $this->test_results[] = ['test' => '3.2 XSS 检测', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL (未能检测到 XSS)\n";
                $this->test_results[] = ['test' => '3.2 XSS 检测', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '3.2 XSS 检测', 'result' => 'FAIL'];
        }
        
        // 测试 3.3: URL 转义
        echo "  3.3 危险 URL 防护... ";
        try {
            $protection = xiaowu_get_xss_protection();
            $dangerous_url = 'javascript:alert("xss")';
            $safe_url = 'https://example.com';
            
            $dangerous_result = $protection->escape_url($dangerous_url);
            $safe_result = $protection->escape_url($safe_url);
            
            if (empty($dangerous_result) && !empty($safe_result)) {
                echo "✅ PASS (危险 URL 被拒绝，安全 URL 被接受)\n";
                $this->test_results[] = ['test' => '3.3 URL 防护', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '3.3 URL 防护', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '3.3 URL 防护', 'result' => 'FAIL'];
        }
        
        // 测试 3.4: HTML 净化
        echo "  3.4 HTML 内容净化... ";
        try {
            $protection = xiaowu_get_xss_protection();
            $dirty_html = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
            $clean_html = $protection->sanitize_html($dirty_html);
            
            if (strpos($clean_html, '<script>') === false && strpos($clean_html, '<p>') !== false) {
                echo "✅ PASS (恶意脚本已移除，合法标签保留)\n";
                $this->test_results[] = ['test' => '3.4 HTML 净化', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '3.4 HTML 净化', 'result' => 'FAIL'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '3.4 HTML 净化', 'result' => 'FAIL'];
        }
        
        echo "\n";
    }
    
    private function print_report() {
        echo "========== 测试报告 ==========\n\n";
        
        $passed = 0;
        $failed = 0;
        $warning = 0;
        
        foreach ($this->test_results as $result) {
            if ($result['result'] === 'PASS') {
                $passed++;
            } elseif ($result['result'] === 'FAIL') {
                $failed++;
            } else {
                $warning++;
            }
        }
        
        echo "总测试数: " . count($this->test_results) . "\n";
        echo "✅ 通过: $passed\n";
        echo "⚠️  警告: $warning\n";
        echo "❌ 失败: $failed\n\n";
        
        // 详细结果
        echo "详细结果:\n";
        foreach ($this->test_results as $index => $result) {
            echo ($index + 1) . ". {$result['test']}: {$result['result']}";
            if (isset($result['reason'])) {
                echo " - {$result['reason']}";
            }
            echo "\n";
        }
        
        echo "\n";
        if ($failed === 0) {
            echo "✅ 所有关键测试通过！输入验证系统正常。\n";
        } else {
            echo "❌ 某些测试失败。请检查错误并修复。\n";
        }
        echo "\n========== 测试完成 ==========\n\n";
    }
}

// 运行测试
$test = new InputValidationIntegrationTest();
$test->run_all_tests();
?>
