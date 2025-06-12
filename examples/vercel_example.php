<?php

/**
 * Vercel Runtime 示例
 * 
 * 这个示例展示了如何在 Vercel 环境中使用 ThinkPHP 和 Vercel Runtime
 */

require_once __DIR__ . '/../vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\adapter\VercelAdapter;
use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

echo "=== Vercel Runtime 示例 ===\n\n";

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

// 2. 创建 Vercel 适配器
echo "\n2. 创建 Vercel 适配器\n";

$vercelConfig = [
    'vercel' => [
        'timeout' => 10,
        'memory' => 1024,
        'region' => 'auto',
        'runtime' => 'php-8.1',
    ],
    'http' => [
        'enable_cors' => true,
        'cors_origin' => '*',
        'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
        'max_body_size' => '5mb',
    ],
    'error' => [
        'display_errors' => true, // 开发环境显示错误
        'log_errors' => true,
        'error_reporting' => E_ALL,
    ],
    'monitor' => [
        'enable' => true,
        'slow_request_threshold' => 1000,
        'memory_threshold' => 80,
    ],
    'static' => [
        'enable' => false,
        'extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'],
    ],
];

try {
    $adapter = new VercelAdapter($app, $vercelConfig);
    echo "   ✅ Vercel 适配器创建成功\n";
} catch (\Exception $e) {
    echo "   ❌ Vercel 适配器创建失败: " . $e->getMessage() . "\n";
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
echo "   - Vercel 超时: " . ($config['vercel']['timeout'] ?? 'N/A') . " 秒\n";
echo "   - Vercel 内存: " . ($config['vercel']['memory'] ?? 'N/A') . " MB\n";
echo "   - Vercel 区域: " . ($config['vercel']['region'] ?? 'N/A') . "\n";
echo "   - CORS 启用: " . ($config['http']['enable_cors'] ? '是' : '否') . "\n";
echo "   - 错误显示: " . ($config['error']['display_errors'] ? '是' : '否') . "\n";
echo "   - 静态文件处理: " . ($config['static']['enable'] ? '是' : '否') . "\n";

// 5. 模拟 Vercel 环境变量
echo "\n5. 模拟 Vercel 环境\n";

// 设置模拟的 Vercel 环境变量
$_ENV['VERCEL'] = '1';
$_ENV['VERCEL_ENV'] = 'development';
$_ENV['VERCEL_URL'] = 'my-app-123.vercel.app';
$_ENV['VERCEL_REGION'] = 'iad1';
$_SERVER['HTTP_X_VERCEL_ID'] = 'iad1::12345';

echo "   - Vercel 环境: " . ($_ENV['VERCEL'] ?? 'N/A') . "\n";
echo "   - 环境类型: " . ($_ENV['VERCEL_ENV'] ?? 'N/A') . "\n";
echo "   - 部署URL: " . ($_ENV['VERCEL_URL'] ?? 'N/A') . "\n";
echo "   - 区域: " . ($_ENV['VERCEL_REGION'] ?? 'N/A') . "\n";
echo "   - Vercel ID: " . ($_SERVER['HTTP_X_VERCEL_ID'] ?? 'N/A') . "\n";

// 6. 测试运行时管理器
echo "\n6. 测试运行时管理器\n";

try {
    // 创建运行时配置
    $runtimeConfigData = [
        'default' => 'vercel',
        'auto_detect_order' => ['vercel', 'bref', 'swoole'],
        'runtimes' => [
            'vercel' => $vercelConfig
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
echo "\n7. 在 Vercel 中使用 Vercel Runtime\n";
echo "   要在 Vercel 中使用此适配器，请按以下步骤操作：\n\n";
echo "   1. 安装 vercel 依赖:\n";
echo "      composer require vercel/php\n\n";
echo "   2. 创建 vercel.json 配置文件:\n";
echo "      {\n";
echo "        \"functions\": {\n";
echo "          \"api/*.php\": {\n";
echo "            \"runtime\": \"vercel-php@0.6.0\"\n";
echo "          }\n";
echo "        },\n";
echo "        \"routes\": [\n";
echo "          {\n";
echo "            \"src\": \"/(.*)\",\n";
echo "            \"dest\": \"/api/index.php\"\n";
echo "          }\n";
echo "        ]\n";
echo "      }\n\n";
echo "   3. 创建 api/index.php 文件:\n";
echo "      <?php\n";
echo "      require_once __DIR__ . '/../vendor/autoload.php';\n";
echo "      use yangweijie\\thinkRuntime\\adapter\\VercelAdapter;\n";
echo "      \$app = new think\\App();\n";
echo "      \$adapter = new VercelAdapter(\$app);\n";
echo "      \$adapter->run();\n\n";
echo "   4. 部署到 Vercel:\n";
echo "      vercel --prod\n\n";

// 清理环境变量
unset($_ENV['VERCEL']);
unset($_ENV['VERCEL_ENV']);
unset($_ENV['VERCEL_URL']);
unset($_ENV['VERCEL_REGION']);
unset($_SERVER['HTTP_X_VERCEL_ID']);

echo "=== 示例完成 ===\n";
