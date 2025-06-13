<?php

declare(strict_types=1);

/**
 * FrankenPHP 环境检查和测试脚本
 */

echo "FrankenPHP 环境检查\n";
echo "==================\n\n";

// 检查基本环境
echo "1. 检查基本环境...\n";
echo "- PHP 版本: " . PHP_VERSION . "\n";
echo "- 操作系统: " . PHP_OS . "\n";
echo "- SAPI: " . PHP_SAPI . "\n\n";

// 检查 FrankenPHP 环境变量
echo "2. 检查 FrankenPHP 环境...\n";

$frankenphpIndicators = [
    'FRANKENPHP_VERSION' => '检查 FrankenPHP 版本',
    'FRANKENPHP_CONFIG' => '检查 FrankenPHP 配置',
    'FRANKENPHP_WORKER_NUM' => '检查 Worker 数量',
];

$inFrankenphp = false;
foreach ($frankenphpIndicators as $var => $desc) {
    $value = $_SERVER[$var] ?? getenv($var);
    if ($value !== false && $value !== '') {
        echo "✅ {$desc}: {$value}\n";
        $inFrankenphp = true;
    } else {
        echo "❌ {$desc}: 未设置\n";
    }
}

if ($inFrankenphp) {
    echo "\n✅ 当前运行在 FrankenPHP 环境中\n";
} else {
    echo "\n⚠️  当前未运行在 FrankenPHP 环境中\n";
}

// 检查 FrankenPHP 函数
echo "\n3. 检查 FrankenPHP 函数...\n";

$frankenphpFunctions = [
    'frankenphp_handle_request' => 'Worker 模式处理函数',
    'frankenphp_stop' => '停止函数',
    'frankenphp_finish_request' => '完成请求函数',
];

$functionsAvailable = 0;
foreach ($frankenphpFunctions as $func => $desc) {
    if (function_exists($func)) {
        echo "✅ {$desc}: {$func}()\n";
        $functionsAvailable++;
    } else {
        echo "❌ {$desc}: {$func}() 不可用\n";
    }
}

// 检查 think-runtime 适配器
echo "\n4. 检查 think-runtime 适配器...\n";

require_once 'vendor/autoload.php';

if (class_exists('yangweijie\\thinkRuntime\\adapter\\FrankenphpAdapter')) {
    echo "✅ FrankenPHP 适配器已加载\n";
    
    try {
        // 创建模拟应用
        $mockApp = new class {
            public function initialize() {
                echo "应用初始化完成\n";
            }
        };
        
        $adapter = new \yangweijie\thinkRuntime\adapter\FrankenphpAdapter($mockApp);
        
        echo "✅ FrankenPHP 适配器创建成功\n";
        echo "- 适配器名称: " . $adapter->getName() . "\n";
        echo "- 适配器优先级: " . $adapter->getPriority() . "\n";
        
        if ($adapter->isSupported()) {
            echo "✅ FrankenPHP 适配器支持当前环境\n";
        } else {
            echo "❌ FrankenPHP 适配器不支持当前环境\n";
        }
        
        // 测试配置
        $config = $adapter->getConfig();
        echo "- 默认监听地址: " . $config['listen'] . "\n";
        echo "- 默认 Worker 数: " . $config['worker_num'] . "\n";
        echo "- 文档根目录: " . $config['root'] . "\n";
        
    } catch (\Exception $e) {
        echo "❌ FrankenPHP 适配器测试失败: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ FrankenPHP 适配器未找到\n";
    echo "请确保已安装 think-runtime 包\n";
}

// 检查依赖
echo "\n5. 检查依赖包...\n";

$dependencies = [
    'Nyholm\\Psr7\\Factory\\Psr17Factory' => 'PSR-7 工厂',
    'Nyholm\\Psr7Server\\ServerRequestCreator' => 'PSR-7 服务器请求创建器',
];

foreach ($dependencies as $class => $desc) {
    if (class_exists($class)) {
        echo "✅ {$desc}: {$class}\n";
    } else {
        echo "❌ {$desc}: {$class} 未找到\n";
    }
}

// 生成使用建议
echo "\n==================\n";
echo "使用建议\n";
echo "==================\n\n";

if ($inFrankenphp && $functionsAvailable > 0) {
    echo "🎉 您已经在 FrankenPHP 环境中！\n\n";
    
    echo "可以直接使用:\n";
    echo "1. Worker 模式处理请求\n";
    echo "2. 高性能 HTTP/2 支持\n";
    echo "3. 自动 HTTPS 功能\n\n";
    
    echo "启动命令:\n";
    echo "php think runtime:start frankenphp\n";
    
} else {
    echo "📦 需要安装 FrankenPHP\n\n";
    
    echo "安装方法:\n";
    echo "1. 官方安装脚本:\n";
    echo "   curl -fsSL https://frankenphp.dev/install.sh | bash\n\n";
    
    echo "2. 手动下载:\n";
    echo "   wget https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64\n";
    echo "   chmod +x frankenphp-linux-x86_64\n";
    echo "   sudo mv frankenphp-linux-x86_64 /usr/local/bin/frankenphp\n\n";
    
    echo "3. Docker 方式:\n";
    echo "   docker run -p 80:80 -p 443:443 -v \$PWD:/app dunglas/frankenphp\n\n";
    
    echo "4. Composer 方式 (开发环境):\n";
    echo "   composer require dunglas/frankenphp\n\n";
}

echo "配置示例:\n";
echo "```php\n";
echo "\$options = [\n";
echo "    'listen' => ':8080',\n";
echo "    'worker_num' => 4,\n";
echo "    'max_requests' => 1000,\n";
echo "    'auto_https' => false,  // 开发环境\n";
echo "    'http2' => true,\n";
echo "    'debug' => true,\n";
echo "    'root' => 'public',\n";
echo "];\n";
echo "```\n\n";

echo "启动方式:\n";
echo "1. 命令行: php think runtime:start frankenphp\n";
echo "2. 示例脚本: php examples/frankenphp_server.php\n";
echo "3. 手动配置: 使用 RuntimeManager->start('frankenphp', \$options)\n\n";

echo "特性:\n";
echo "- ⚡ 高性能 (比 PHP-FPM 快 3-4 倍)\n";
echo "- 🔒 自动 HTTPS\n";
echo "- 🚀 HTTP/2 & HTTP/3 支持\n";
echo "- 🔄 Worker 模式 (常驻内存)\n";
echo "- 🐳 Docker 友好\n";
echo "- 🛠️ 零配置启动\n\n";

echo "更多信息:\n";
echo "- 官方文档: https://frankenphp.dev/\n";
echo "- 使用指南: vendor/yangweijie/think-runtime/FRANKENPHP-GUIDE.md\n";
echo "- 示例代码: examples/frankenphp_server.php\n";
