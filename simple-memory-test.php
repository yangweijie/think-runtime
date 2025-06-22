<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * 简单内存测试
 * 
 * 测试修复后的适配器是否真的解决了内存问题
 */

echo "=== 简单内存测试 ===\n";

// 简单应用
class SimpleApp
{
    public $http;
    public $request;
    
    public function __construct()
    {
        $this->http = new class {
            public function run($request) {
                return new class {
                    public function getCode() { return 200; }
                    public function getHeader() { return []; }
                    public function getContent() { return 'Hello World'; }
                };
            }
        };
        
        $this->request = new class {
            public $server = [];
            public $host = null;
        };
    }
    
    public function initialize(): void {}
    public function has(string $name): bool { return $name === 'request'; }
    public function get(string $name) { return $name === 'request' ? $this->request : null; }
    public function make(string $name, array $vars = []) { return $this->get($name); }
    public function bind(string $name, $value): void {}
}

echo "创建应用和适配器...\n";
$app = new SimpleApp();
$adapter = new WorkermanAdapter($app, [
    'host' => '127.0.0.1',
    'port' => 8080,
    'count' => 1,
]);

if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用\n";
    exit(1);
}

echo "✅ Workerman 适配器可用\n";

// 测试内存统计功能
echo "\n=== 测试内存统计 ===\n";
$stats = $adapter->getMemoryStats();
echo "初始统计: " . json_encode($stats) . "\n";

// 模拟内存使用
echo "\n=== 模拟内存使用 ===\n";
$initialMemory = memory_get_usage(true);
echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";

// 创建一些数据
$data = [];
for ($i = 0; $i < 1000; $i++) {
    $data[] = str_repeat('test_data_', 100);
}

$afterCreateMemory = memory_get_usage(true);
echo "创建数据后内存: " . round($afterCreateMemory / 1024 / 1024, 2) . " MB\n";
echo "内存增长: " . round(($afterCreateMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";

// 清理数据
unset($data);
gc_collect_cycles();

$afterCleanMemory = memory_get_usage(true);
echo "清理后内存: " . round($afterCleanMemory / 1024 / 1024, 2) . " MB\n";
echo "清理效果: " . round(($afterCreateMemory - $afterCleanMemory) / 1024 / 1024, 2) . " MB\n";

// 测试适配器的内存管理方法
echo "\n=== 测试适配器内存管理 ===\n";

$reflection = new ReflectionClass($adapter);

// 测试垃圾回收方法
if ($reflection->hasMethod('performPeriodicGC')) {
    $gcMethod = $reflection->getMethod('performPeriodicGC');
    $gcMethod->setAccessible(true);
    
    $beforeGC = memory_get_usage(true);
    $gcMethod->invoke($adapter);
    $afterGC = memory_get_usage(true);
    
    echo "GC前内存: " . round($beforeGC / 1024 / 1024, 2) . " MB\n";
    echo "GC后内存: " . round($afterGC / 1024 / 1024, 2) . " MB\n";
    echo "GC释放: " . round(($beforeGC - $afterGC) / 1024, 2) . " KB\n";
}

// 测试内存统计更新
if ($reflection->hasMethod('updateMemoryStats')) {
    $updateMethod = $reflection->getMethod('updateMemoryStats');
    $updateMethod->setAccessible(true);
    
    $updateMethod->invoke($adapter);
    $updatedStats = $adapter->getMemoryStats();
    echo "更新后统计: " . json_encode($updatedStats) . "\n";
}

// 测试应用实例销毁
if ($reflection->hasMethod('destroyAppInstance')) {
    $destroyMethod = $reflection->getMethod('destroyAppInstance');
    $destroyMethod->setAccessible(true);
    
    // 创建一个测试应用实例
    $testApp = new SimpleApp();
    $beforeDestroy = memory_get_usage(true);
    
    $destroyMethod->invoke($adapter, $testApp);
    unset($testApp);
    gc_collect_cycles();
    
    $afterDestroy = memory_get_usage(true);
    echo "销毁前内存: " . round($beforeDestroy / 1024 / 1024, 2) . " MB\n";
    echo "销毁后内存: " . round($afterDestroy / 1024 / 1024, 2) . " MB\n";
}

// 最终内存检查
echo "\n=== 最终内存检查 ===\n";
$finalMemory = memory_get_usage(true);
$finalPeak = memory_get_peak_usage(true);

echo "最终内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "峰值内存: " . round($finalPeak / 1024 / 1024, 2) . " MB\n";
echo "总内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";

if (($finalMemory - $initialMemory) < 1024 * 1024) { // 小于1MB
    echo "✅ 内存使用非常稳定\n";
} elseif (($finalMemory - $initialMemory) < 5 * 1024 * 1024) { // 小于5MB
    echo "✅ 内存使用基本稳定\n";
} else {
    echo "⚠️  内存增长较多，需要进一步优化\n";
}

echo "\n=== 配置检查 ===\n";
$config = $adapter->getConfig();
if (isset($config['memory'])) {
    echo "内存管理配置: " . json_encode($config['memory']) . "\n";
} else {
    echo "❌ 缺少内存管理配置\n";
}

echo "\n简单测试完成！\n";
