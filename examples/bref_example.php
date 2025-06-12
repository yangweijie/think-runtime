<?php

/**
 * Bref Runtime 示例
 * 
 * 这个示例展示了如何在 AWS Lambda 环境中使用 ThinkPHP 和 Bref Runtime
 */

require_once __DIR__ . '/../vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\adapter\BrefAdapter;
use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

echo "=== Bref Runtime 示例 ===\n\n";

// 1. 创建 ThinkPHP 应用实例
echo "1. 创建 ThinkPHP 应用实例\n";
$app = new App();

try {
    $app->initialize();
    echo "   ✅ ThinkPHP 应用初始化成功\n";
} catch (\Exception $e) {
    echo "   ❌ ThinkPHP 应用初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. 创建 Bref 适配器
echo "\n2. 创建 Bref 适配器\n";

$brefConfig = [
    'lambda' => [
        'timeout' => 30,
        'memory' => 512,
        'environment' => 'development', // 开发环境
    ],
    'http' => [
        'enable_cors' => true,
        'cors_origin' => '*',
        'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
    ],
    'error' => [
        'display_errors' => true, // 开发环境显示错误
        'log_errors' => true,
    ],
    'monitor' => [
        'enable' => true,
        'slow_request_threshold' => 1000,
    ],
];

try {
    $adapter = new BrefAdapter($app, $brefConfig);
    echo "   ✅ Bref 适配器创建成功\n";
} catch (\Exception $e) {
    echo "   ❌ Bref 适配器创建失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. 测试适配器方法
echo "\n3. 测试适配器方法\n";

// 测试 getName
echo "   - 适配器名称: " . $adapter->getName() . "\n";

// 测试 isSupported
if ($adapter->isSupported()) {
    echo "   ✅ 适配器支持当前环境\n";
} else {
    echo "   ❌ 适配器不支持当前环境\n";
}

// 测试 isAvailable
if ($adapter->isAvailable()) {
    echo "   ✅ 适配器在当前环境可用\n";
} else {
    echo "   ❌ 适配器在当前环境不可用\n";
}

// 测试 getPriority
echo "   - 适配器优先级: " . $adapter->getPriority() . "\n";

// 4. 测试配置
echo "\n4. 测试配置\n";
$config = $adapter->getConfig();
echo "   - Lambda 超时: " . ($config['lambda']['timeout'] ?? 'N/A') . " 秒\n";
echo "   - Lambda 内存: " . ($config['lambda']['memory'] ?? 'N/A') . " MB\n";
echo "   - CORS 启用: " . ($config['http']['enable_cors'] ? '是' : '否') . "\n";
echo "   - 错误显示: " . ($config['error']['display_errors'] ? '是' : '否') . "\n";

// 5. 模拟 Lambda 环境变量
echo "\n5. 模拟 Lambda 环境\n";

// 设置模拟的 Lambda 环境变量
$_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
$_ENV['AWS_LAMBDA_FUNCTION_VERSION'] = '$LATEST';
$_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] = '512';
$_ENV['AWS_EXECUTION_ENV'] = 'AWS_Lambda_php81';

echo "   - 函数名称: " . ($_ENV['AWS_LAMBDA_FUNCTION_NAME'] ?? 'N/A') . "\n";
echo "   - 函数版本: " . ($_ENV['AWS_LAMBDA_FUNCTION_VERSION'] ?? 'N/A') . "\n";
echo "   - 内存大小: " . ($_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] ?? 'N/A') . " MB\n";
echo "   - 执行环境: " . ($_ENV['AWS_EXECUTION_ENV'] ?? 'N/A') . "\n";

// 6. 测试运行时管理器
echo "\n6. 测试运行时管理器\n";

try {
    // 创建运行时配置
    $runtimeConfigData = [
        'default' => 'bref',
        'auto_detect_order' => ['bref', 'swoole', 'frankenphp'],
        'runtimes' => [
            'bref' => $brefConfig
        ]
    ];
    
    $runtimeConfig = new RuntimeConfig($runtimeConfigData);
    $manager = new RuntimeManager($app, $runtimeConfig);
    
    echo "   ✅ 运行时管理器创建成功\n";
    
    // 测试运行时检测
    $detectedRuntime = $manager->detectRuntime();
    echo "   - 检测到的运行时: " . $detectedRuntime . "\n";
    
    // 测试可用运行时
    $availableRuntimes = $manager->getAvailableRuntimes();
    echo "   - 可用运行时: " . implode(', ', $availableRuntimes) . "\n";
    
    // 获取运行时信息
    $runtimeInfo = $manager->getRuntimeInfo();
    echo "   - 当前运行时: " . $runtimeInfo['name'] . "\n";
    echo "   - 运行时可用: " . ($runtimeInfo['available'] ? '是' : '否') . "\n";
    
} catch (\Exception $e) {
    echo "   ❌ 运行时管理器测试失败: " . $e->getMessage() . "\n";
}

// 7. 使用说明
echo "\n7. 在 AWS Lambda 中使用 Bref Runtime\n";
echo "   要在 AWS Lambda 中使用此适配器，请按以下步骤操作：\n\n";
echo "   1. 安装 bref 依赖:\n";
echo "      composer require runtime/bref bref/bref\n\n";
echo "   2. 创建 serverless.yml 配置文件:\n";
echo "      service: my-thinkphp-app\n";
echo "      plugins:\n";
echo "        - ./vendor/runtime/bref-layer\n";
echo "      provider:\n";
echo "        name: aws\n";
echo "        runtime: provided.al2\n";
echo "        environment:\n";
echo "          APP_RUNTIME: yangweijie\\\\thinkRuntime\\\\adapter\\\\BrefAdapter\n";
echo "      functions:\n";
echo "        web:\n";
echo "          handler: public/index.php\n";
echo "          layers:\n";
echo "            - \${runtime-bref:php-81}\n";
echo "          events:\n";
echo "            - httpApi: '*'\n\n";
echo "   3. 部署到 AWS Lambda:\n";
echo "      serverless deploy\n\n";

echo "=== 示例完成 ===\n";
