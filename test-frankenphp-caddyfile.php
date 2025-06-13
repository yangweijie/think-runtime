<?php

declare(strict_types=1);

/**
 * 测试FrankenPHP Caddyfile生成
 */

echo "FrankenPHP Caddyfile 生成测试\n";
echo "============================\n\n";

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
    
    // 测试不同配置的Caddyfile生成
    $testConfigs = [
        'basic' => [
            'listen' => 'localhost:8080',
            'root' => 'public',
            'index' => 'index.php',
            'worker_num' => 0,
            'auto_https' => false,
            'debug' => false,
        ],
        'worker_mode' => [
            'listen' => 'localhost:8080',
            'root' => 'public',
            'index' => 'index.php',
            'worker_num' => 4,
            'auto_https' => false,
            'debug' => true,
        ],
        'production' => [
            'listen' => 'example.com',
            'root' => 'public',
            'index' => 'index.php',
            'worker_num' => 8,
            'auto_https' => true,
            'debug' => false,
        ],
    ];
    
    foreach ($testConfigs as $name => $config) {
        echo "测试配置: {$name}\n";
        echo str_repeat('-', 30) . "\n";
        
        // 使用反射调用私有方法
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('createCaddyfile');
        $method->setAccessible(true);
        
        $caddyfile = $method->invoke($adapter, $config);
        
        echo "生成的Caddyfile:\n";
        echo "```\n";
        echo $caddyfile;
        echo "```\n\n";
        
        // 验证语法
        $lines = explode("\n", $caddyfile);
        $hasValidSyntax = true;
        $errors = [];
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // 检查是否包含错误的指令
            if (strpos($line, 'worker_num') !== false) {
                $hasValidSyntax = false;
                $errors[] = "第" . ($lineNum + 1) . "行: 使用了错误的指令 'worker_num'，应该是 'worker'";
            }
            
            if (strpos($line, 'max_requests') !== false) {
                $hasValidSyntax = false;
                $errors[] = "第" . ($lineNum + 1) . "行: 'max_requests' 不是有效的FrankenPHP指令";
            }
        }
        
        if ($hasValidSyntax && empty($errors)) {
            echo "✅ Caddyfile语法检查通过\n";
        } else {
            echo "❌ Caddyfile语法检查失败:\n";
            foreach ($errors as $error) {
                echo "   - {$error}\n";
            }
        }
        
        echo "\n";
    }
    
    // 测试FrankenPHP二进制文件查找
    echo "测试FrankenPHP二进制文件查找\n";
    echo "============================\n";
    
    $reflection = new ReflectionClass($adapter);
    $method = $reflection->getMethod('findFrankenphpBinary');
    $method->setAccessible(true);
    
    $binary = $method->invoke($adapter);
    
    if ($binary) {
        echo "✅ 找到FrankenPHP二进制文件: {$binary}\n";
        
        // 检查版本
        $version = shell_exec("{$binary} version 2>/dev/null");
        if ($version) {
            echo "版本信息: " . trim($version) . "\n";
        }
    } else {
        echo "❌ 未找到FrankenPHP二进制文件\n";
        echo "请安装FrankenPHP:\n";
        echo "curl -fsSL https://frankenphp.dev/install.sh | bash\n";
    }
    
} catch (\Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n============================\n";
echo "修复说明\n";
echo "============================\n\n";

echo "主要修复:\n";
echo "1. ✅ 将 'worker_num' 改为 'worker'\n";
echo "2. ✅ 移除了不支持的 'max_requests' 指令\n";
echo "3. ✅ 简化了Worker模式和标准模式的配置\n";
echo "4. ✅ 优化了Caddyfile结构\n\n";

echo "正确的FrankenPHP Caddyfile语法:\n";
echo "```\n";
echo "localhost:8080 {\n";
echo "    root * public\n";
echo "    php_server {\n";
echo "        worker 4    # 正确: 使用 'worker' 而不是 'worker_num'\n";
echo "    }\n";
echo "    tls off\n";
echo "}\n";
echo "```\n\n";

echo "支持的php_server指令:\n";
echo "- root: 设置PHP脚本根目录\n";
echo "- split: 设置PATH_INFO分割\n";
echo "- env: 设置环境变量\n";
echo "- resolve_root_symlink: 解析根目录符号链接\n";
echo "- worker: 启用Worker模式并设置Worker数量\n\n";

echo "现在可以重新尝试启动:\n";
echo "php think runtime:start frankenphp --host=localhost --port=8080\n";
