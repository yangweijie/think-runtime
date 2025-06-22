<?php

/**
 * FrankenPHP Caddy 配置优化测试
 * 
 * 测试使用 mattvb91/caddy-php 包优化后的 FrankenPHP 配置生成
 */

require_once __DIR__ . '/../vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use yangweijie\thinkRuntime\config\CaddyConfigBuilder;

echo str_repeat("=", 60) . "\n";
echo "🧪 FrankenPHP Caddy 配置优化测试\n";
echo str_repeat("=", 60) . "\n\n";

// 1. 测试基本配置生成
echo "1. 测试基本配置生成\n";
echo str_repeat("-", 40) . "\n";

try {
    $app = new App();
    $app->initialize();
    echo "   ✅ ThinkPHP 应用初始化成功\n";
} catch (\Exception $e) {
    echo "   ❌ ThinkPHP 应用初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 创建适配器
$adapter = new FrankenphpAdapter($app);

// 测试基本配置
$basicConfig = [
    'listen' => ':9000',
    'worker_num' => 2,
    'debug' => true,
    'auto_https' => false,
    'enable_gzip' => true,
    'hosts' => ['localhost', 'test.local'],
];

$adapter->setConfig($basicConfig);
$mergedConfig = $adapter->getConfig();

echo "   📊 配置摘要:\n";
echo "      - 监听地址: {$mergedConfig['listen']}\n";
echo "      - Worker数量: {$mergedConfig['worker_num']}\n";
echo "      - 调试模式: " . ($mergedConfig['debug'] ? '启用' : '禁用') . "\n";
echo "      - Gzip压缩: " . ($mergedConfig['enable_gzip'] ? '启用' : '禁用') . "\n";
echo "      - 主机列表: " . implode(', ', $mergedConfig['hosts']) . "\n";

// 2. 测试 Caddyfile 生成
echo "\n2. 测试 Caddyfile 生成\n";
echo str_repeat("-", 40) . "\n";

$builder = CaddyConfigBuilder::fromArray($mergedConfig);
$caddyfile = $builder->buildCaddyfile();

echo "   📄 生成的 Caddyfile:\n";
echo str_repeat("-", 30) . "\n";
echo $caddyfile;
echo str_repeat("-", 30) . "\n";

// 保存测试用的 Caddyfile
$testCaddyfilePath = __DIR__ . '/Caddyfile.optimization.test';
file_put_contents($testCaddyfilePath, $caddyfile);
echo "   ✅ Caddyfile 已保存到: {$testCaddyfilePath}\n";

// 3. 测试 JSON 配置生成
echo "\n3. 测试 JSON 配置生成\n";
echo str_repeat("-", 40) . "\n";

try {
    $jsonConfig = $builder->build();
    $jsonData = json_decode($jsonConfig, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "   ✅ JSON 配置生成成功\n";
        echo "   📊 JSON 配置结构:\n";
        echo "      - Admin端口: " . ($jsonData['admin']['listen'] ?? 'N/A') . "\n";
        echo "      - HTTP应用: " . (isset($jsonData['apps']['http']) ? '已配置' : '未配置') . "\n";
        
        if (isset($jsonData['apps']['http']['servers'])) {
            $serverCount = count($jsonData['apps']['http']['servers']);
            echo "      - 服务器数量: {$serverCount}\n";
        }
        
        // 保存JSON配置
        $testJsonPath = __DIR__ . '/caddy-config.optimization.test.json';
        file_put_contents($testJsonPath, json_encode($jsonData, JSON_PRETTY_PRINT));
        echo "   ✅ JSON 配置已保存到: {$testJsonPath}\n";
    } else {
        echo "   ❌ JSON 配置格式错误: " . json_last_error_msg() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ JSON 配置生成失败: " . $e->getMessage() . "\n";
}

// 4. 测试高级配置选项
echo "\n4. 测试高级配置选项\n";
echo str_repeat("-", 40) . "\n";

$advancedConfig = [
    'listen' => ':8443',
    'auto_https' => true,
    'use_fastcgi' => true,
    'fastcgi_address' => '127.0.0.1:9000',
    'hosts' => ['secure.local', 'api.local'],
    'enable_gzip' => true,
    'enable_file_server' => true,
    'debug' => false,
];

$advancedBuilder = CaddyConfigBuilder::fromArray($advancedConfig);
$configSummary = $advancedBuilder->getConfigSummary();

echo "   📊 高级配置摘要:\n";
foreach ($configSummary as $key => $value) {
    if (is_array($value)) {
        $value = implode(', ', $value);
    } elseif (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    }
    echo "      - {$key}: {$value}\n";
}

// 5. 测试配置验证
echo "\n5. 测试配置验证\n";
echo str_repeat("-", 40) . "\n";

$testConfigs = [
    'minimal' => [
        'listen' => ':8080',
    ],
    'development' => [
        'listen' => ':3000',
        'debug' => true,
        'auto_https' => false,
        'worker_num' => 1,
    ],
    'production' => [
        'listen' => ':443',
        'auto_https' => true,
        'debug' => false,
        'worker_num' => 8,
        'enable_gzip' => true,
        'hosts' => ['example.com', 'www.example.com'],
    ],
];

foreach ($testConfigs as $name => $config) {
    echo "   🧪 测试 {$name} 配置:\n";
    try {
        $testBuilder = CaddyConfigBuilder::fromArray($config);
        $testCaddyfile = $testBuilder->buildCaddyfile();
        $lines = count(explode("\n", $testCaddyfile));
        echo "      ✅ 生成成功 ({$lines} 行)\n";
    } catch (\Exception $e) {
        echo "      ❌ 生成失败: " . $e->getMessage() . "\n";
    }
}

// 6. 性能测试
echo "\n6. 性能测试\n";
echo str_repeat("-", 40) . "\n";

$iterations = 100;
$startTime = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $perfBuilder = CaddyConfigBuilder::fromArray($mergedConfig);
    $perfBuilder->buildCaddyfile();
}

$endTime = microtime(true);
$totalTime = ($endTime - $startTime) * 1000; // 转换为毫秒
$avgTime = $totalTime / $iterations;

echo "   ⏱️  性能测试结果:\n";
echo "      - 总时间: " . number_format($totalTime, 2) . " ms\n";
echo "      - 平均时间: " . number_format($avgTime, 2) . " ms/次\n";
echo "      - 迭代次数: {$iterations}\n";

if ($avgTime < 1.0) {
    echo "      ✅ 性能优秀 (< 1ms)\n";
} elseif ($avgTime < 5.0) {
    echo "      ✅ 性能良好 (< 5ms)\n";
} else {
    echo "      ⚠️  性能需要优化 (> 5ms)\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 所有测试完成！\n";
echo str_repeat("=", 60) . "\n";

// 清理测试文件
$testFiles = [
    $testCaddyfilePath,
    __DIR__ . '/caddy-config.optimization.test.json'
];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

echo "🧹 清理测试文件完成\n";
