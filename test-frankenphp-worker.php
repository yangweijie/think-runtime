<?php

declare(strict_types=1);

/**
 * 测试FrankenPHP Worker脚本生成
 */

echo "FrankenPHP Worker 脚本生成测试\n";
echo "==============================\n\n";

require_once 'vendor/autoload.php';

// 创建模拟应用
$mockApp = new class {
    public function initialize() {
        echo "应用初始化完成\n";
    }
};

try {
    // 创建FrankenPHP适配器
    $adapter = new \yangweijie\thinkRuntime\adapter\FrankenphpAdapter($mockApp);
    
    echo "✅ FrankenPHP适配器创建成功\n\n";
    
    // 测试Worker脚本生成
    echo "测试Worker脚本生成...\n";
    echo "====================\n";
    
    // 使用反射调用私有方法
    $reflection = new ReflectionClass($adapter);
    $method = $reflection->getMethod('createWorkerScript');
    $method->setAccessible(true);
    
    $workerScriptPath = $method->invoke($adapter);
    
    echo "Worker脚本路径: {$workerScriptPath}\n";
    
    if (file_exists($workerScriptPath)) {
        echo "✅ Worker脚本文件创建成功\n";
        
        // 检查文件内容
        $content = file_get_contents($workerScriptPath);
        
        // 验证关键内容
        $checks = [
            'error_reporting(E_ERROR | E_WARNING | E_PARSE)' => '错误报告设置',
            'ini_set("session.sid_length", "")' => 'Session配置修复',
            'frankenphp_handle_request' => 'FrankenPHP Worker函数',
            'use think\\App' => 'ThinkPHP应用类',
            'gc_collect_cycles()' => '垃圾回收',
        ];
        
        foreach ($checks as $needle => $desc) {
            if (strpos($content, $needle) !== false) {
                echo "   ✅ {$desc}: 已包含\n";
            } else {
                echo "   ❌ {$desc}: 缺失\n";
            }
        }
        
        // 显示文件大小
        $size = filesize($workerScriptPath);
        echo "   文件大小: {$size} 字节\n";
        
        // 清理测试文件
        unlink($workerScriptPath);
        echo "   ✅ 测试文件已清理\n";
        
    } else {
        echo "❌ Worker脚本文件创建失败\n";
    }
    
    echo "\n";
    
    // 测试Caddyfile生成（包含Worker配置）
    echo "测试Caddyfile生成（Worker模式）...\n";
    echo "================================\n";
    
    $config = [
        'listen' => 'localhost:8080',
        'root' => 'public',
        'worker_num' => 4,
        'auto_https' => false,
        'debug' => true,
    ];
    
    $method = $reflection->getMethod('createCaddyfile');
    $method->setAccessible(true);
    
    $caddyfile = $method->invoke($adapter, $config);
    
    echo "生成的Caddyfile:\n";
    echo "```\n";
    echo $caddyfile;
    echo "```\n\n";
    
    // 验证Caddyfile内容
    if (strpos($caddyfile, 'worker /') !== false) {
        echo "✅ Caddyfile包含正确的worker配置\n";
    } else {
        echo "❌ Caddyfile缺少worker配置\n";
    }
    
    // 清理可能生成的Worker脚本
    $workerScript = getcwd() . '/frankenphp-worker.php';
    if (file_exists($workerScript)) {
        unlink($workerScript);
        echo "✅ 临时Worker脚本已清理\n";
    }
    
} catch (\Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n==============================\n";
echo "修复说明\n";
echo "==============================\n\n";

echo "主要修复:\n";
echo "1. ✅ 修正了Worker配置语法\n";
echo "   - 从 'worker 4' 改为 'worker /path/to/script.php'\n";
echo "   - FrankenPHP需要指定Worker脚本文件，而不是数量\n\n";

echo "2. ✅ 创建了专用的Worker脚本\n";
echo "   - 自动生成 frankenphp-worker.php\n";
echo "   - 包含完整的ThinkPHP集成代码\n";
echo "   - 处理PSR-7请求和响应\n\n";

echo "3. ✅ 修复了PHP弃用警告\n";
echo "   - 设置 error_reporting(E_ERROR | E_WARNING | E_PARSE)\n";
echo "   - 禁用session相关的弃用警告\n\n";

echo "4. ✅ 添加了文件清理机制\n";
echo "   - 自动清理临时生成的文件\n";
echo "   - 避免文件积累\n\n";

echo "正确的FrankenPHP Worker配置:\n";
echo "```\n";
echo "localhost:8080 {\n";
echo "    root * public\n";
echo "    php_server {\n";
echo "        worker /path/to/frankenphp-worker.php\n";
echo "    }\n";
echo "    tls off\n";
echo "}\n";
echo "```\n\n";

echo "Worker脚本功能:\n";
echo "- 初始化ThinkPHP应用\n";
echo "- 处理FrankenPHP Worker循环\n";
echo "- PSR-7请求/响应转换\n";
echo "- 错误处理和日志\n";
echo "- 内存管理和垃圾回收\n\n";

echo "现在可以重新尝试启动:\n";
echo "php think runtime:start frankenphp --host=localhost --port=8080\n\n";

echo "预期效果:\n";
echo "- 不再出现 'Failed opening required /path/4' 错误\n";
echo "- 减少PHP弃用警告\n";
echo "- Worker模式正常运行\n";
echo "- 支持高并发请求处理\n";
