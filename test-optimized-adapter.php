<?php

declare(strict_types=1);

/**
 * 测试优化后的 ThinkWorker 适配器
 */

require_once '/Volumes/data/git/php/tp/vendor/autoload.php';
require_once 'ThinkWorkerOptimizedAdapter.php';

echo "=== ThinkWorker 优化适配器测试 ===\n";

use yangweijie\thinkRuntime\adapter\ThinkWorkerOptimizedAdapter;

// 创建简单的测试应用
class TestApp
{
    private array $instances = [];
    private array $services = [];
    public $config;
    public $request;
    public $response;
    public $http;
    
    public function __construct()
    {
        $this->config = new class {
            public function get($key, $default = null) {
                return $default;
            }
        };
        
        $this->request = new class {
            public $server = [];
            public $host = null;
        };
        
        $this->response = new class {
            public function getCode() { return 200; }
            public function getHeader() { return []; }
            public function getContent() { return 'Hello from Optimized Adapter!'; }
        };
        
        $this->http = new class {
            public function run($request) {
                // 模拟一些处理
                $data = [
                    'message' => 'Success',
                    'timestamp' => microtime(true),
                    'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                ];
                
                return new class($data) {
                    private $data;
                    public function __construct($data) { $this->data = $data; }
                    public function getCode() { return 200; }
                    public function getHeader() { return ['Content-Type' => 'application/json']; }
                    public function getContent() { return json_encode($this->data); }
                    public function getBody() { return $this->getContent(); }
                    public function getStatusCode() { return 200; }
                    public function getHeaders() { return $this->getHeader(); }
                };
            }
        };
    }
    
    public function initialize(): void
    {
        // 模拟初始化
        $this->instances['initialized'] = true;
    }
    
    public function has(string $name): bool
    {
        return isset($this->instances[$name]) || property_exists($this, $name);
    }
    
    public function get(string $name)
    {
        return $this->instances[$name] ?? $this->{$name} ?? null;
    }
    
    public function make(string $name, array $vars = [], bool $newInstance = false)
    {
        return $this->get($name);
    }
    
    public function bind(string $name, $value): void
    {
        $this->instances[$name] = $value;
    }
    
    public function instance(string $name, $value): void
    {
        $this->instances[$name] = $value;
    }
    
    public function delete(string $name): void
    {
        unset($this->instances[$name]);
    }
    
    public function clearInstances(): void
    {
        $this->instances = [];
    }
    
    public function invoke(callable $callable, array $vars = []): mixed
    {
        return call_user_func_array($callable, $vars);
    }
}

// 测试配置
$config = [
    'host' => '127.0.0.1',
    'port' => 8085,
    'count' => 1, // 单进程测试
    'sandbox' => [
        'enable' => true,
        'reset_instances' => ['log', 'session', 'view', 'response', 'cookie', 'request'],
        'clone_services' => true,
    ],
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 20,
        'reset_interval' => 50,
        'memory_limit' => '256M',
    ],
];

echo "创建测试应用...\n";
$app = new TestApp();

echo "创建优化适配器...\n";
$adapter = new ThinkWorkerOptimizedAdapter($app, $config);

// 检查适配器可用性
if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用\n";
    exit(1);
}

echo "✅ 优化适配器可用\n";

// 内存基准测试
echo "\n=== 内存基准测试 ===\n";

$initialMemory = memory_get_usage(true);
echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";

// 模拟沙盒操作
echo "\n测试沙盒机制...\n";

$reflection = new ReflectionClass($adapter);

// 测试应用快照创建
if ($reflection->hasMethod('createAppSnapshot')) {
    $snapshotMethod = $reflection->getMethod('createAppSnapshot');
    $snapshotMethod->setAccessible(true);
    
    $beforeSnapshot = memory_get_usage(true);
    $snapshotMethod->invoke($adapter);
    $afterSnapshot = memory_get_usage(true);
    
    echo "快照创建内存开销: " . round(($afterSnapshot - $beforeSnapshot) / 1024, 2) . " KB\n";
}

// 测试沙盒应用创建
if ($reflection->hasMethod('createSandboxApp')) {
    $sandboxMethod = $reflection->getMethod('createSandboxApp');
    $sandboxMethod->setAccessible(true);
    
    $iterations = 100;
    echo "测试 {$iterations} 次沙盒应用创建...\n";
    
    $startTime = microtime(true);
    $startMemory = memory_get_usage(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $sandboxApp = $sandboxMethod->invoke($adapter);
        
        // 模拟使用
        $sandboxApp->get('config');
        $sandboxApp->get('request');
        
        // 清理
        if ($reflection->hasMethod('cleanupSandboxApp')) {
            $cleanupMethod = $reflection->getMethod('cleanupSandboxApp');
            $cleanupMethod->setAccessible(true);
            $cleanupMethod->invoke($adapter, $sandboxApp);
        }
        
        unset($sandboxApp);
        
        if ($i % 20 === 0) {
            gc_collect_cycles();
        }
    }
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $duration = ($endTime - $startTime) * 1000;
    $memoryGrowth = $endMemory - $startMemory;
    $avgTime = $duration / $iterations;
    $avgMemory = $memoryGrowth / $iterations;
    
    echo "总耗时: " . round($duration, 2) . " ms\n";
    echo "平均每次: " . round($avgTime, 3) . " ms\n";
    echo "内存增长: " . round($memoryGrowth / 1024, 2) . " KB\n";
    echo "平均每次内存: " . round($avgMemory, 2) . " bytes\n";
    
    if ($avgTime < 1.0) {
        echo "✅ 沙盒性能优秀 (< 1ms)\n";
    } elseif ($avgTime < 5.0) {
        echo "✅ 沙盒性能良好 (< 5ms)\n";
    } else {
        echo "⚠️  沙盒性能需要优化 (> 5ms)\n";
    }
    
    if ($avgMemory < 1024) {
        echo "✅ 内存使用优秀 (< 1KB)\n";
    } elseif ($avgMemory < 10240) {
        echo "✅ 内存使用良好 (< 10KB)\n";
    } else {
        echo "⚠️  内存使用需要优化 (> 10KB)\n";
    }
}

// 测试实例重置
echo "\n=== 测试实例重置机制 ===\n";

if ($reflection->hasMethod('resetAppInstances')) {
    $resetMethod = $reflection->getMethod('resetAppInstances');
    $resetMethod->setAccessible(true);
    
    // 创建测试应用
    $testApp = new TestApp();
    $testApp->instance('log', 'test_log');
    $testApp->instance('session', 'test_session');
    $testApp->instance('view', 'test_view');
    
    echo "重置前实例: log=" . ($testApp->has('log') ? '✅' : '❌') . 
         ", session=" . ($testApp->has('session') ? '✅' : '❌') . 
         ", view=" . ($testApp->has('view') ? '✅' : '❌') . "\n";
    
    $resetMethod->invoke($adapter, $testApp);
    
    echo "重置后实例: log=" . ($testApp->has('log') ? '✅' : '❌') . 
         ", session=" . ($testApp->has('session') ? '✅' : '❌') . 
         ", view=" . ($testApp->has('view') ? '✅' : '❌') . "\n";
    
    if (!$testApp->has('log') && !$testApp->has('session') && !$testApp->has('view')) {
        echo "✅ 实例重置机制工作正常\n";
    } else {
        echo "❌ 实例重置机制有问题\n";
    }
}

// 性能对比测试
echo "\n=== 性能对比测试 ===\n";

// 模拟传统方式（每次new）
echo "测试传统方式（每次创建新应用）...\n";
$traditionalStart = microtime(true);
$traditionalMemStart = memory_get_usage(true);

for ($i = 0; $i < 100; $i++) {
    $newApp = new TestApp();
    $newApp->initialize();
    $newApp->get('config');
    $newApp->get('request');
    unset($newApp);
}

$traditionalEnd = microtime(true);
$traditionalMemEnd = memory_get_usage(true);

$traditionalTime = ($traditionalEnd - $traditionalStart) * 1000;
$traditionalMem = $traditionalMemEnd - $traditionalMemStart;

echo "传统方式 - 时间: " . round($traditionalTime, 2) . "ms, 内存: " . round($traditionalMem / 1024, 2) . "KB\n";

// 模拟优化方式（clone）
echo "测试优化方式（clone + 重置）...\n";
$optimizedStart = microtime(true);
$optimizedMemStart = memory_get_usage(true);

$baseApp = new TestApp();
$baseApp->initialize();

for ($i = 0; $i < 100; $i++) {
    $clonedApp = clone $baseApp;
    $clonedApp->delete('log');
    $clonedApp->delete('session');
    $clonedApp->delete('view');
    $clonedApp->get('config');
    $clonedApp->get('request');
    unset($clonedApp);
}

$optimizedEnd = microtime(true);
$optimizedMemEnd = memory_get_usage(true);

$optimizedTime = ($optimizedEnd - $optimizedStart) * 1000;
$optimizedMem = $optimizedMemEnd - $optimizedMemStart;

echo "优化方式 - 时间: " . round($optimizedTime, 2) . "ms, 内存: " . round($optimizedMem / 1024, 2) . "KB\n";

// 计算改善
$timeImprovement = $traditionalTime > 0 ? (($traditionalTime - $optimizedTime) / $traditionalTime) * 100 : 0;
$memImprovement = $traditionalMem > 0 ? (($traditionalMem - $optimizedMem) / $traditionalMem) * 100 : 0;

echo "\n性能改善:\n";
echo "时间提升: " . round($timeImprovement, 1) . "%\n";
echo "内存节省: " . round($memImprovement, 1) . "%\n";

if ($timeImprovement > 20) {
    echo "✅ 时间性能显著提升\n";
} elseif ($timeImprovement > 0) {
    echo "✅ 时间性能有所提升\n";
} else {
    echo "❌ 时间性能无改善\n";
}

if ($memImprovement > 10) {
    echo "✅ 内存使用显著改善\n";
} elseif ($memImprovement > 0) {
    echo "✅ 内存使用有所改善\n";
} else {
    echo "❌ 内存使用无改善\n";
}

// 最终内存检查
$finalMemory = memory_get_usage(true);
echo "\n=== 最终内存状态 ===\n";
echo "最终内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "总内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";

echo "\n✅ 优化适配器测试完成！\n";
echo "\n下一步: 在真实项目中测试这个优化适配器\n";
