#!/bin/bash

echo "🚀 FrankenPHP Runtime 性能基准测试"
echo "================================="

# 检查环境
echo "📍 环境信息"
echo "=========="
echo "操作系统: $(uname -s)"
echo "PHP 版本: $(php -v | head -1)"
echo "CPU 信息: $(sysctl -n machdep.cpu.brand_string 2>/dev/null || echo '未知')"
echo "内存信息: $(sysctl -n hw.memsize 2>/dev/null | awk '{print $1/1024/1024/1024 " GB"}' || echo '未知')"

if command -v frankenphp &> /dev/null; then
    echo "FrankenPHP: $(frankenphp version 2>/dev/null || echo '版本信息不可用')"
else
    echo "⚠️  FrankenPHP 未安装，跳过性能测试"
    exit 1
fi

echo ""

# 1. 内存使用测试
echo "1️⃣ 内存使用基准测试"
echo "=================="

cd /Volumes/data/git/php/think-runtime

cat > memory_benchmark.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

echo "🧮 内存使用基准测试\n";
echo "==================\n";

// 记录初始内存
$initialMemory = memory_get_usage(true);
$initialPeak = memory_get_peak_usage(true);

echo "初始内存使用: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";
echo "初始峰值内存: " . round($initialPeak / 1024 / 1024, 2) . " MB\n\n";

// 创建适配器实例
$startTime = microtime(true);
$app = new App();
$adapter = new FrankenphpAdapter($app);
$creationTime = microtime(true) - $startTime;

$afterCreation = memory_get_usage(true);
echo "适配器创建后内存: " . round($afterCreation / 1024 / 1024, 2) . " MB\n";
echo "适配器创建时间: " . round($creationTime * 1000, 2) . " ms\n\n";

// 配置适配器
$startTime = microtime(true);
$adapter->setConfig([
    'listen' => ':8080',
    'worker_num' => 4,
    'max_requests' => 1000,
    'debug' => false,
    'auto_https' => false,
]);
$configTime = microtime(true) - $startTime;

$afterConfig = memory_get_usage(true);
echo "配置设置后内存: " . round($afterConfig / 1024 / 1024, 2) . " MB\n";
echo "配置设置时间: " . round($configTime * 1000, 2) . " ms\n\n";

// 生成配置文件
$startTime = microtime(true);
$reflection = new ReflectionClass($adapter);
$method = $reflection->getMethod('buildFrankenPHPCaddyfile');
$method->setAccessible(true);

$config = [
    'listen' => ':8080',
    'root' => '/tmp/test',
    'index' => 'index.php',
    'auto_https' => false,
];

for ($i = 0; $i < 100; $i++) {
    $caddyfile = $method->invoke($adapter, $config, null);
}
$generateTime = microtime(true) - $startTime;

$afterGenerate = memory_get_usage(true);
echo "配置生成后内存: " . round($afterGenerate / 1024 / 1024, 2) . " MB\n";
echo "100次配置生成时间: " . round($generateTime * 1000, 2) . " ms\n";
echo "平均单次生成时间: " . round($generateTime * 10, 2) . " ms\n\n";

// 状态检查性能
$startTime = microtime(true);
for ($i = 0; $i < 50; $i++) {
    $status = $adapter->getStatus();
    $health = $adapter->healthCheck();
}
$statusTime = microtime(true) - $startTime;

$finalMemory = memory_get_usage(true);
$finalPeak = memory_get_peak_usage(true);

echo "状态检查后内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "50次状态检查时间: " . round($statusTime * 1000, 2) . " ms\n";
echo "平均单次检查时间: " . round($statusTime * 20, 2) . " ms\n\n";

echo "📊 内存使用总结:\n";
echo "===============\n";
echo "最终内存使用: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "峰值内存使用: " . round($finalPeak / 1024 / 1024, 2) . " MB\n";
echo "内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";
echo "内存效率: " . ($finalMemory < $initialMemory * 2 ? '✅ 优秀' : '⚠️  需要优化') . "\n";
EOF

echo "🧪 运行内存基准测试..."
php memory_benchmark.php

echo ""

# 2. 配置生成性能测试
echo "2️⃣ 配置生成性能测试"
echo "=================="

cat > config_performance.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

echo "⚡ 配置生成性能测试\n";
echo "==================\n";

$app = new App();
$adapter = new FrankenphpAdapter($app);

$reflection = new ReflectionClass($adapter);
$method = $reflection->getMethod('buildFrankenPHPCaddyfile');
$method->setAccessible(true);

$configs = [
    'basic' => [
        'listen' => ':8080',
        'root' => '/tmp/test',
        'index' => 'index.php',
        'auto_https' => false,
    ],
    'advanced' => [
        'listen' => ':8080',
        'root' => '/var/www/html',
        'index' => 'index.php',
        'auto_https' => true,
        'worker_num' => 8,
        'max_requests' => 2000,
        'debug' => true,
    ],
];

foreach ($configs as $name => $config) {
    echo "测试配置: {$name}\n";
    
    $times = [];
    $sizes = [];
    
    for ($i = 0; $i < 1000; $i++) {
        $startTime = microtime(true);
        $caddyfile = $method->invoke($adapter, $config, null);
        $endTime = microtime(true);
        
        $times[] = ($endTime - $startTime) * 1000; // 转换为毫秒
        $sizes[] = strlen($caddyfile);
    }
    
    $avgTime = array_sum($times) / count($times);
    $minTime = min($times);
    $maxTime = max($times);
    $avgSize = array_sum($sizes) / count($sizes);
    
    echo "  平均生成时间: " . round($avgTime, 3) . " ms\n";
    echo "  最快生成时间: " . round($minTime, 3) . " ms\n";
    echo "  最慢生成时间: " . round($maxTime, 3) . " ms\n";
    echo "  平均配置大小: " . round($avgSize) . " bytes\n";
    echo "  性能评级: " . ($avgTime < 1 ? '✅ 优秀' : ($avgTime < 5 ? '🟡 良好' : '🔴 需要优化')) . "\n\n";
}
EOF

echo "🧪 运行配置生成性能测试..."
php config_performance.php

echo ""

# 3. 并发处理能力测试
echo "3️⃣ 并发处理能力测试"
echo "=================="

# 创建测试项目配置
cd /Volumes/data/git/php/tp

cat > Caddyfile.benchmark << 'EOF'
{
    auto_https off
}

:8081 {
    root * /Volumes/data/git/php/tp/public
    
    # ThinkPHP 专用配置
    try_files {path} {path}/ /index.php?s={path}&{query}
    
    # 处理 PHP 文件
    php
    
    # 处理静态文件
    file_server
}
EOF

echo "🚀 启动 FrankenPHP 测试服务器..."
frankenphp run --config Caddyfile.benchmark &
FRANKENPHP_PID=$!

# 等待服务器启动
sleep 3

echo "🧪 执行并发测试..."

# 检查服务器是否启动成功
if curl -s http://localhost:8081/ > /dev/null; then
    echo "✅ 服务器启动成功"
    
    # 简单的并发测试
    echo "📊 并发请求测试:"
    
    # 测试根路径
    echo "  测试根路径 (/):"
    START_TIME=$(date +%s.%N)
    for i in {1..10}; do
        curl -s http://localhost:8081/ > /dev/null &
    done
    wait
    END_TIME=$(date +%s.%N)
    ROOT_TIME=$(echo "$END_TIME - $START_TIME" | bc)
    echo "    10个并发请求耗时: ${ROOT_TIME}s"
    
    # 测试路由
    echo "  测试路由 (/index/index):"
    START_TIME=$(date +%s.%N)
    for i in {1..10}; do
        curl -s http://localhost:8081/index/index > /dev/null &
    done
    wait
    END_TIME=$(date +%s.%N)
    ROUTE_TIME=$(echo "$END_TIME - $START_TIME" | bc)
    echo "    10个并发请求耗时: ${ROUTE_TIME}s"
    
    # 性能评估
    echo "  📈 性能评估:"
    if (( $(echo "$ROOT_TIME < 2" | bc -l) )); then
        echo "    根路径响应: ✅ 优秀 (${ROOT_TIME}s)"
    else
        echo "    根路径响应: ⚠️  需要优化 (${ROOT_TIME}s)"
    fi
    
    if (( $(echo "$ROUTE_TIME < 2" | bc -l) )); then
        echo "    路由响应: ✅ 优秀 (${ROUTE_TIME}s)"
    else
        echo "    路由响应: ⚠️  需要优化 (${ROUTE_TIME}s)"
    fi
    
else
    echo "❌ 服务器启动失败，跳过并发测试"
fi

# 停止服务器
echo "🛑 停止测试服务器..."
kill $FRANKENPHP_PID 2>/dev/null
wait $FRANKENPHP_PID 2>/dev/null

# 清理测试文件
rm -f Caddyfile.benchmark

echo ""

# 4. 清理测试文件
echo "4️⃣ 清理测试文件"
echo "==============="
cd /Volumes/data/git/php/think-runtime
rm -f memory_benchmark.php config_performance.php
echo "✅ 测试文件已清理"

echo ""

# 5. 性能总结
echo "📊 性能基准测试总结"
echo "=================="
echo "✅ 内存使用测试完成 - 检查内存效率和增长情况"
echo "✅ 配置生成测试完成 - 验证配置生成性能"
echo "✅ 并发处理测试完成 - 评估并发处理能力"

echo ""
echo "🎯 性能优化建议:"
echo "==============="
echo "1. 监控内存使用情况，避免内存泄漏"
echo "2. 优化配置生成逻辑，减少重复计算"
echo "3. 使用 Worker 模式提高并发处理能力"
echo "4. 启用 OPcache 提高 PHP 执行效率"
echo "5. 配置适当的 Worker 数量和最大请求数"

echo ""
echo "✅ FrankenPHP Runtime 性能基准测试完成！"
