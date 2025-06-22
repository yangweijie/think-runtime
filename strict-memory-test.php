<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * 严格的内存泄漏测试
 * 
 * 测试最简单的场景，找出真正的内存泄漏源
 */

echo "=== 严格内存泄漏测试 ===\n";

// 最简单的应用模拟
class MinimalApp
{
    private bool $initialized = false;
    public $http;
    public $request;
    
    public function __construct()
    {
        $this->http = new class {
            public function run($request) {
                return new class {
                    public function getCode() { return 200; }
                    public function getHeader() { return ['Content-Type' => 'text/plain']; }
                    public function getContent() { return 'OK'; }
                };
            }
        };
        
        $this->request = new class {
            public $server = [];
            public $host = null;
        };
    }
    
    public function initialize(): void
    {
        $this->initialized = true;
    }
    
    public function has(string $name): bool
    {
        return $name === 'request';
    }
    
    public function get(string $name)
    {
        return $name === 'request' ? $this->request : null;
    }
    
    public function make(string $name, array $vars = [])
    {
        return $this->get($name);
    }
    
    public function bind(string $name, $value): void
    {
        // 空实现
    }
}

// 测试不同的配置
$configs = [
    'no_gc' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'count' => 1,
        'memory' => [
            'enable_gc' => false,
            'gc_interval' => 0,
            'context_cleanup_interval' => 0,
            'max_context_size' => 10000,
        ],
    ],
    'with_gc' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'count' => 1,
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 10,
            'context_cleanup_interval' => 5,
            'max_context_size' => 100,
        ],
    ],
];

foreach ($configs as $configName => $config) {
    echo "\n=== 测试配置: {$configName} ===\n";
    
    $app = new MinimalApp();
    $adapter = new WorkermanAdapter($app, $config);
    
    if (!$adapter->isAvailable()) {
        echo "❌ Workerman 不可用\n";
        continue;
    }
    
    $initialMemory = memory_get_usage(true);
    echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";
    
    // 模拟连接上下文操作
    $reflection = new ReflectionClass($adapter);
    
    // 测试连接上下文设置
    if ($reflection->hasMethod('setConnectionContext')) {
        $setContextMethod = $reflection->getMethod('setConnectionContext');
        $setContextMethod->setAccessible(true);
        
        $clearContextMethod = $reflection->getMethod('clearConnectionContext');
        $clearContextMethod->setAccessible(true);
        
        $contextProperty = $reflection->getProperty('connectionContext');
        $contextProperty->setAccessible(true);
        
        // 模拟连接和请求
        $mockConnection = new class {
            public $id;
            public function __construct() {
                $this->id = uniqid();
            }
        };
        
        $mockRequest = new class {
            public function method() { return 'GET'; }
            public function uri() { return '/test'; }
        };
        
        // 创建大量上下文
        $requestCount = 500;
        echo "创建 {$requestCount} 个连接上下文...\n";
        
        for ($i = 0; $i < $requestCount; $i++) {
            $connection = new $mockConnection();
            $request = new $mockRequest();
            
            $setContextMethod->invoke($adapter, $connection, $request, microtime(true));
            
            // 立即清理（模拟正常请求流程）
            $clearContextMethod->invoke($adapter, $connection);
            
            if ($i % 100 === 0) {
                $currentMemory = memory_get_usage(true);
                $contextCount = count($contextProperty->getValue($adapter));
                echo "请求 {$i}: 内存 " . round($currentMemory / 1024 / 1024, 2) . "MB, 上下文数量: {$contextCount}\n";
                
                // 手动触发GC
                if ($config['memory']['enable_gc']) {
                    gc_collect_cycles();
                }
            }
        }
        
        $finalMemory = memory_get_usage(true);
        $finalContextCount = count($contextProperty->getValue($adapter));
        
        echo "最终内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
        echo "内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";
        echo "最终上下文数量: {$finalContextCount}\n";
        
        $avgGrowthPerRequest = ($finalMemory - $initialMemory) / $requestCount;
        echo "平均每请求增长: " . round($avgGrowthPerRequest, 2) . " bytes\n";
        
        if ($avgGrowthPerRequest < 100) {
            echo "✅ 内存使用非常稳定\n";
        } elseif ($avgGrowthPerRequest < 1024) {
            echo "✅ 内存使用稳定\n";
        } elseif ($avgGrowthPerRequest < 10240) {
            echo "⚠️  内存有轻微增长\n";
        } else {
            echo "❌ 内存增长过快\n";
        }
    }
}

// 测试内存统计功能
echo "\n=== 测试内存统计功能 ===\n";
$app = new MinimalApp();
$adapter = new WorkermanAdapter($app, $configs['with_gc']);

if ($adapter->isAvailable()) {
    $stats = $adapter->getMemoryStats();
    echo "内存统计: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
    
    // 测试多次调用是否稳定
    for ($i = 0; $i < 10; $i++) {
        $stats = $adapter->getMemoryStats();
        echo "调用 {$i}: 当前内存 " . $stats['current_memory_mb'] . "MB\n";
    }
}

echo "\n=== 基准测试：纯PHP内存使用 ===\n";
$initialMemory = memory_get_usage(true);
echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";

// 纯PHP操作，不使用适配器
$data = [];
for ($i = 0; $i < 500; $i++) {
    $data[$i] = [
        'id' => $i,
        'data' => str_repeat('x', 100),
        'time' => microtime(true),
    ];
    
    // 立即清理
    unset($data[$i]);
    
    if ($i % 100 === 0) {
        $currentMemory = memory_get_usage(true);
        echo "操作 {$i}: 内存 " . round($currentMemory / 1024 / 1024, 2) . "MB\n";
        gc_collect_cycles();
    }
}

$finalMemory = memory_get_usage(true);
echo "最终内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "纯PHP内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";

echo "\n严格测试完成！\n";
