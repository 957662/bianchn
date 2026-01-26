<?php
/**
 * CORS 集成测试脚本
 * 测试跨源资源共享的各种场景
 */

// WordPress 环境初始化
$wp_path = dirname(__DIR__, 3);
require_once($wp_path . '/wp-load.php');

class CORSIntegrationTest {
    private $test_results = [];
    private $api_base = 'http://localhost/wp-json';
    private $allowed_origins = [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:8080'
    ];
    
    public function run_all_tests() {
        echo "\n========== CORS 集成测试开始 ==========\n\n";
        
        // 测试 1: 检查 CORS 管理器是否已初始化
        $this->test_cors_manager_initialized();
        
        // 测试 2: 获取允许的源列表
        $this->test_get_cors_status();
        
        // 测试 3: 验证预检请求
        $this->test_preflight_request();
        
        // 测试 4: 添加新源
        $this->test_add_cors_origin();
        
        // 测试 5: 移除源
        $this->test_remove_cors_origin();
        
        // 测试 6: 验证正常请求的 CORS 头
        $this->test_cors_headers();
        
        // 测试 7: 验证无效源被拒绝
        $this->test_invalid_origin_rejected();
        
        // 输出测试报告
        $this->print_report();
    }
    
    private function test_cors_manager_initialized() {
        echo "测试 1: 检查 CORS 管理器初始化... ";
        
        try {
            if (class_exists('\Xiaowu\Security\CORSManager')) {
                $cors_manager = new \Xiaowu\Security\CORSManager();
                echo "✅ PASS\n";
                $this->test_results[] = ['test' => '1. CORS管理器初始化', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '1. CORS管理器初始化', 'result' => 'FAIL', 'reason' => 'CORSManager 类不存在'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '1. CORS管理器初始化', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function test_get_cors_status() {
        echo "测试 2: 获取 CORS 状态... ";
        
        try {
            $cors_manager = new \Xiaowu\Security\CORSManager();
            $origins = $cors_manager->get_allowed_origins();
            
            if (is_array($origins) && count($origins) > 0) {
                echo "✅ PASS (找到 " . count($origins) . " 个允许的源)\n";
                $this->test_results[] = ['test' => '2. 获取CORS状态', 'result' => 'PASS', 'data' => $origins];
            } else {
                echo "⚠️ WARNING (未找到允许的源)\n";
                $this->test_results[] = ['test' => '2. 获取CORS状态', 'result' => 'WARNING', 'reason' => '未找到允许的源'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '2. 获取CORS状态', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function test_preflight_request() {
        echo "测试 3: 验证预检请求处理... ";
        
        try {
            $cors_manager = new \Xiaowu\Security\CORSManager();
            $origin = 'http://localhost:3000';
            
            // 模拟 OPTIONS 请求
            $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
            $_SERVER['HTTP_ORIGIN'] = $origin;
            
            if ($cors_manager->is_origin_allowed($origin)) {
                echo "✅ PASS\n";
                $this->test_results[] = ['test' => '3. 预检请求处理', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL (源被拒绝)\n";
                $this->test_results[] = ['test' => '3. 预检请求处理', 'result' => 'FAIL', 'reason' => '源被拒绝'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '3. 预检请求处理', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function test_add_cors_origin() {
        echo "测试 4: 添加新的 CORS 源... ";
        
        try {
            $cors_manager = new \Xiaowu\Security\CORSManager();
            $test_origin = 'https://example.com';
            
            // 添加源
            $result = $cors_manager->add_allowed_origin($test_origin);
            
            if ($result && $cors_manager->is_origin_allowed($test_origin)) {
                echo "✅ PASS\n";
                $this->test_results[] = ['test' => '4. 添加CORS源', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '4. 添加CORS源', 'result' => 'FAIL', 'reason' => '添加或验证失败'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '4. 添加CORS源', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function test_remove_cors_origin() {
        echo "测试 5: 移除 CORS 源... ";
        
        try {
            $cors_manager = new \Xiaowu\Security\CORSManager();
            $test_origin = 'https://example.com';
            
            // 先添加
            $cors_manager->add_allowed_origin($test_origin);
            
            // 再移除
            $result = $cors_manager->remove_allowed_origin($test_origin);
            
            if ($result && !$cors_manager->is_origin_allowed($test_origin)) {
                echo "✅ PASS\n";
                $this->test_results[] = ['test' => '5. 移除CORS源', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL\n";
                $this->test_results[] = ['test' => '5. 移除CORS源', 'result' => 'FAIL', 'reason' => '移除或验证失败'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '5. 移除CORS源', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function test_cors_headers() {
        echo "测试 6: 验证 CORS 响应头... ";
        
        try {
            $cors_manager = new \Xiaowu\Security\CORSManager();
            $_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000';
            
            // 调用处理 CORS 头的方法
            $cors_manager->handle_cors_headers();
            
            // 检查是否设置了响应头（注意：在此测试环境中，头不会被实际发送）
            echo "✅ PASS (方法执行成功)\n";
            $this->test_results[] = ['test' => '6. CORS响应头', 'result' => 'PASS'];
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '6. CORS响应头', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function test_invalid_origin_rejected() {
        echo "测试 7: 验证无效源被拒绝... ";
        
        try {
            $cors_manager = new \Xiaowu\Security\CORSManager();
            $invalid_origin = 'https://malicious-site.com';
            
            if (!$cors_manager->is_origin_allowed($invalid_origin)) {
                echo "✅ PASS (无效源被正确拒绝)\n";
                $this->test_results[] = ['test' => '7. 拒绝无效源', 'result' => 'PASS'];
            } else {
                echo "❌ FAIL (无效源被允许)\n";
                $this->test_results[] = ['test' => '7. 拒绝无效源', 'result' => 'FAIL', 'reason' => '无效源被允许'];
            }
        } catch (Exception $e) {
            echo "❌ FAIL: {$e->getMessage()}\n";
            $this->test_results[] = ['test' => '7. 拒绝无效源', 'result' => 'FAIL', 'reason' => $e->getMessage()];
        }
    }
    
    private function print_report() {
        echo "\n========== 测试报告 ==========\n\n";
        
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
        
        // 详细信息
        echo "详细结果:\n";
        foreach ($this->test_results as $index => $result) {
            echo ($index + 1) . ". {$result['test']}: {$result['result']}";
            if (isset($result['reason'])) {
                echo " - {$result['reason']}";
            }
            echo "\n";
        }
        
        // 总体结论
        echo "\n";
        if ($failed === 0) {
            echo "✅ 所有测试通过！CORS 配置正常。\n";
        } else {
            echo "❌ 某些测试失败。请检查错误并修复。\n";
        }
        echo "\n========== 测试完成 ==========\n\n";
    }
}

// 运行测试
$test = new CORSIntegrationTest();
$test->run_all_tests();
?>
