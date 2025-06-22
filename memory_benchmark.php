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
