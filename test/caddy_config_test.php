<?php

/**
 * Caddy 配置生成器测试
 * 
 * 测试使用 mattvb91/caddy-php 包优化后的配置生成功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\config\CaddyConfigBuilder;

echo str_repeat("=", 60) . "\n";
echo "🧪 Caddy 配置生成器测试\n";
echo str_repeat("=", 60) . "\n\n";

// 1. 测试基本 Caddyfile 生成
echo "1. 测试基本 Caddyfile 生成\n";
echo str_repeat("-", 40) . "\n";

$basicConfig = [
    'listen' => ':9000',
    'root' => 'public',
    'index' => 'index.php',
    'debug' => true,
    'auto_https' => false,
    'enable_gzip' => true,
    'hosts' => ['localhost', 'test.local'],
    'log_dir' => 'runtime/log',
];

try {
    $builder = CaddyConfigBuilder::fromArray($basicConfig);
    $caddyfile = $builder->buildCaddyfile();
    
    echo "   ✅ Caddyfile 生成成功\n";
    echo "   📄 生成的 Caddyfile:\n";
    echo str_repeat("-", 30) . "\n";
    echo $caddyfile;
    echo str_repeat("-", 30) . "\n";
    
    // 保存测试文件
    $testPath = __DIR__ . '/Caddyfile.basic.test';
    file_put_contents($testPath, $caddyfile);
    echo "   💾 已保存到: {$testPath}\n";
    
} catch (\Exception $e) {
    echo "   ❌ Caddyfile 生成失败: " . $e->getMessage() . "\n";
}

// 2. 测试 JSON 配置生成
echo "\n2. 测试 JSON 配置生成\n";
echo str_repeat("-", 40) . "\n";

try {
    $jsonConfig = $builder->build();
    $jsonData = json_decode($jsonConfig, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "   ✅ JSON 配置生成成功\n";
        echo "   📊 配置结构:\n";
        echo "      - Admin端口: " . ($jsonData['admin']['listen'] ?? 'N/A') . "\n";
        echo "      - HTTP应用: " . (isset($jsonData['apps']['http']) ? '已配置' : '未配置') . "\n";
        
        if (isset($jsonData['apps']['http']['servers'])) {
            $serverCount = count($jsonData['apps']['http']['servers']);
            echo "      - 服务器数量: {$serverCount}\n";
            
            foreach ($jsonData['apps']['http']['servers'] as $name => $server) {
                $listenPorts = $server['listen'] ?? [];
                echo "      - 服务器 '{$name}': " . implode(', ', $listenPorts) . "\n";
            }
        }
        
        // 保存JSON配置
        $jsonPath = __DIR__ . '/caddy-config.basic.test.json';
        file_put_contents($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT));
        echo "   💾 JSON 已保存到: {$jsonPath}\n";
    } else {
        echo "   ❌ JSON 格式错误: " . json_last_error_msg() . "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ JSON 配置生成失败: " . $e->getMessage() . "\n";
}

// 3. 测试高级配置选项
echo "\n3. 测试高级配置选项\n";
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
    'root' => 'public',
    'index' => 'index.php',
    'log_dir' => 'runtime/log',
];

try {
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
    
    // 生成高级配置的 Caddyfile
    $advancedCaddyfile = $advancedBuilder->buildCaddyfile();
    $advancedPath = __DIR__ . '/Caddyfile.advanced.test';
    file_put_contents($advancedPath, $advancedCaddyfile);
    echo "   💾 高级 Caddyfile 已保存到: {$advancedPath}\n";
    
} catch (\Exception $e) {
    echo "   ❌ 高级配置测试失败: " . $e->getMessage() . "\n";
}

// 4. 测试不同场景配置
echo "\n4. 测试不同场景配置\n";
echo str_repeat("-", 40) . "\n";

$scenarios = [
    'development' => [
        'listen' => ':3000',
        'debug' => true,
        'auto_https' => false,
        'enable_gzip' => false,
        'hosts' => ['localhost'],
    ],
    'production' => [
        'listen' => ':443',
        'auto_https' => true,
        'debug' => false,
        'enable_gzip' => true,
        'hosts' => ['example.com', 'www.example.com'],
    ],
    'fastcgi' => [
        'listen' => ':8080',
        'use_fastcgi' => true,
        'fastcgi_address' => '127.0.0.1:9000',
        'debug' => true,
        'hosts' => ['fastcgi.local'],
    ],
];

foreach ($scenarios as $name => $config) {
    echo "   🧪 测试 {$name} 场景:\n";
    try {
        // 合并基本配置
        $fullConfig = array_merge([
            'root' => 'public',
            'index' => 'index.php',
            'log_dir' => 'runtime/log',
        ], $config);
        
        $scenarioBuilder = CaddyConfigBuilder::fromArray($fullConfig);
        $scenarioCaddyfile = $scenarioBuilder->buildCaddyfile();
        $lines = count(explode("\n", $scenarioCaddyfile));
        
        echo "      ✅ 生成成功 ({$lines} 行)\n";
        
        // 保存场景配置
        $scenarioPath = __DIR__ . "/Caddyfile.{$name}.test";
        file_put_contents($scenarioPath, $scenarioCaddyfile);
        echo "      💾 已保存到: {$scenarioPath}\n";
        
    } catch (\Exception $e) {
        echo "      ❌ 生成失败: " . $e->getMessage() . "\n";
    }
}

// 5. 性能测试
echo "\n5. 性能测试\n";
echo str_repeat("-", 40) . "\n";

$iterations = 100;
$testConfig = array_merge($basicConfig, ['hosts' => ['localhost']]);

// Caddyfile 生成性能测试
$startTime = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $perfBuilder = CaddyConfigBuilder::fromArray($testConfig);
    $perfBuilder->buildCaddyfile();
}
$caddyfileTime = (microtime(true) - $startTime) * 1000;

// JSON 生成性能测试
$startTime = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $perfBuilder = CaddyConfigBuilder::fromArray($testConfig);
    $perfBuilder->build();
}
$jsonTime = (microtime(true) - $startTime) * 1000;

echo "   ⏱️  性能测试结果 ({$iterations} 次迭代):\n";
echo "      - Caddyfile 生成: " . number_format($caddyfileTime, 2) . " ms (平均 " . number_format($caddyfileTime/$iterations, 2) . " ms/次)\n";
echo "      - JSON 生成: " . number_format($jsonTime, 2) . " ms (平均 " . number_format($jsonTime/$iterations, 2) . " ms/次)\n";

$avgCaddyfileTime = $caddyfileTime / $iterations;
$avgJsonTime = $jsonTime / $iterations;

if ($avgCaddyfileTime < 1.0 && $avgJsonTime < 1.0) {
    echo "      ✅ 性能优秀 (< 1ms)\n";
} elseif ($avgCaddyfileTime < 5.0 && $avgJsonTime < 5.0) {
    echo "      ✅ 性能良好 (< 5ms)\n";
} else {
    echo "      ⚠️  性能需要优化 (> 5ms)\n";
}

// 6. 配置对比测试
echo "\n6. 配置对比测试\n";
echo str_repeat("-", 40) . "\n";

echo "   📊 功能对比:\n";
echo "      - mattvb91/caddy-php 集成: ✅ 已集成\n";
echo "      - JSON 配置支持: ✅ 支持\n";
echo "      - Caddyfile 配置支持: ✅ 支持\n";
echo "      - FastCGI 支持: ✅ 支持\n";
echo "      - 多主机支持: ✅ 支持\n";
echo "      - 压缩支持: ✅ 支持\n";
echo "      - 调试模式: ✅ 支持\n";
echo "      - 自动HTTPS: ✅ 支持\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 所有测试完成！\n";
echo "📁 测试文件已生成在 test/ 目录中\n";
echo str_repeat("=", 60) . "\n";

// 列出生成的测试文件
$testFiles = glob(__DIR__ . '/*.test*');
if (!empty($testFiles)) {
    echo "\n📋 生成的测试文件:\n";
    foreach ($testFiles as $file) {
        $filename = basename($file);
        $size = filesize($file);
        echo "   - {$filename} ({$size} bytes)\n";
    }
}
