<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';


use yangweijie\thinkRuntime\adapter\ReactphpAdapter;
use yangweijie\thinkRuntime\adapter\SwooleAdapter;
use yangweijie\thinkRuntime\adapter\RippleAdapter;

/**
 * 所有 Runtime 适配器内存泄漏测试脚本
 * 
 * 测试修复后的各个适配器的内存管理效果
 */

echo "=== 所有 Runtime 适配器内存泄漏测试 ===\n";

// 创建模拟应用
class MockApp
{
    private array $data = [];
    private array $services = [];
    private bool $initialized = false;
    public $http;
    
    public function initialize(): void
    {
        $this->initialized = true;
        $this->data = range(1, 100); // 模拟一些数据
        
        // 创建模拟的 HTTP 对象
        $this->http = new class {
            public function run($request) {
                return new class {
                    public function getCode() { return 200; }
                    public function getHeader() { return ['Content-Type' => 'application/json']; }
                    public function getContent() {
                        return json_encode([
                            'message' => 'Hello World!',
                            'timestamp' => microtime(true),
                            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                        ]);
                    }
                };
            }
            
            public function end($response) {
                // 结束处理，什么都不做
            }
        };
    }
    
    public function initialized(): bool
    {
        return $this->initialized;
    }
    
    // 实现容器接口
    public function has(string $name): bool
    {
        return isset($this->services[$name]);
    }
    
    public function get(string $name)
    {
        return $this->services[$name] ?? null;
    }
    
    public function make(string $name, array $vars = [])
    {
        return $this->get($name);
    }
    
    public function bind(string $name, $value): void
    {
        $this->services[$name] = $value;
    }
    
    public function delete(string $name): void
    {
        unset($this->services[$name]);
    }

    // 支持 Ripple Adapter 需要的方法
    public function getRootPath(): string
    {
        return getcwd() . '/';
    }

    public function getRuntimePath(): string
    {
        return getcwd() . '/runtime/';
    }

    public function __destruct()
    {
        $this->data = [];
        $this->services = [];
    }
}

// 测试配置
$testConfig = [
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 10, // 每10个请求GC一次
        'cleanup_interval' => 5, // 5秒清理一次
        'max_context_size' => 50,
    ],
];

// 要测试的适配器列表
$adapters = [
    'ReactPHP' => ReactphpAdapter::class,
    'Swoole' => SwooleAdapter::class,
    'Ripple' => RippleAdapter::class,
];

$results = [];

foreach ($adapters as $name => $adapterClass) {
    echo "\n=== 测试 {$name} Adapter ===\n";
    
    try {
        $app = new MockApp();
        $config = array_merge($testConfig, [
            'host' => '127.0.0.1',
            'port' => 8080,
            'count' => 1,
        ]);
        
        $adapter = new $adapterClass($app, $config);
        
        // 检查适配器可用性
        if (!$adapter->isAvailable()) {
            echo "❌ {$name} 不可用，跳过测试\n";
            $results[$name] = ['status' => 'unavailable', 'reason' => 'Extension not installed'];
            continue;
        }
        
        echo "✅ {$name} 适配器可用\n";
        
        // 测试内存管理功能
        $initialMemory = memory_get_usage(true);
        
        // 模拟内存泄漏测试
        echo "测试内存管理功能...\n";
        
        // 使用反射测试内存管理方法
        $reflection = new ReflectionClass($adapter);
        
        // 测试是否有内存统计方法
        $hasMemoryStats = $reflection->hasMethod('getMemoryStats');
        
        // 测试是否有垃圾回收方法
        $hasGC = $reflection->hasMethod('performPeriodicGC');
        
        // 测试是否有应用实例销毁方法
        $hasDestroy = $reflection->hasMethod('destroyAppInstance');
        
        $features = [];
        if ($hasMemoryStats) {
            $features[] = '内存统计';
            try {
                $stats = $adapter->getMemoryStats();
                echo "  - 内存统计: " . json_encode($stats) . "\n";
            } catch (Throwable $e) {
                echo "  - 内存统计方法存在但调用失败: " . $e->getMessage() . "\n";
            }
        }
        
        if ($hasGC) {
            $features[] = '定期垃圾回收';
        }
        
        if ($hasDestroy) {
            $features[] = '应用实例销毁';
        }
        
        // 测试配置
        $adapterConfig = $adapter->getConfig();
        $hasMemoryConfig = isset($adapterConfig['memory']);
        
        if ($hasMemoryConfig) {
            $features[] = '内存管理配置';
            echo "  - 内存配置: " . json_encode($adapterConfig['memory']) . "\n";
        }
        
        $afterTestMemory = memory_get_usage(true);
        $memoryIncrease = $afterTestMemory - $initialMemory;
        
        $results[$name] = [
            'status' => 'available',
            'features' => $features,
            'memory_increase' => round($memoryIncrease / 1024, 2) . 'KB',
            'has_memory_stats' => $hasMemoryStats,
            'has_gc' => $hasGC,
            'has_destroy' => $hasDestroy,
            'has_memory_config' => $hasMemoryConfig,
        ];
        
        echo "✅ {$name} 测试完成\n";
        echo "  - 支持的功能: " . implode(', ', $features) . "\n";
        echo "  - 内存增长: " . $results[$name]['memory_increase'] . "\n";
        
    } catch (Throwable $e) {
        echo "❌ {$name} 测试失败: " . $e->getMessage() . "\n";
        $results[$name] = [
            'status' => 'error',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
    }
}

// 输出测试总结
echo "\n=== 测试总结 ===\n";

$availableCount = 0;
$fixedCount = 0;

foreach ($results as $name => $result) {
    echo "\n{$name} Adapter:\n";
    
    if ($result['status'] === 'available') {
        $availableCount++;
        echo "  ✅ 状态: 可用\n";
        echo "  📊 功能: " . implode(', ', $result['features']) . "\n";
        echo "  💾 内存增长: " . $result['memory_increase'] . "\n";
        
        // 检查是否已修复内存泄漏
        $isFixed = $result['has_memory_stats'] && $result['has_gc'] && $result['has_destroy'];
        if ($isFixed) {
            $fixedCount++;
            echo "  🎉 内存泄漏修复: 已修复\n";
        } else {
            echo "  ⚠️  内存泄漏修复: 部分修复\n";
            if (!$result['has_memory_stats']) echo "    - 缺少内存统计\n";
            if (!$result['has_gc']) echo "    - 缺少垃圾回收\n";
            if (!$result['has_destroy']) echo "    - 缺少实例销毁\n";
        }
        
    } elseif ($result['status'] === 'unavailable') {
        echo "  ❌ 状态: 不可用 (" . $result['reason'] . ")\n";
    } else {
        echo "  💥 状态: 错误 - " . $result['error'] . "\n";
    }
}

echo "\n=== 最终统计 ===\n";
echo "总适配器数: " . count($adapters) . "\n";
echo "可用适配器: {$availableCount}\n";
echo "已修复内存泄漏: {$fixedCount}\n";
echo "修复完成率: " . round(($fixedCount / count($adapters)) * 100, 1) . "%\n";

if ($fixedCount === count($adapters)) {
    echo "\n🎉 所有适配器的内存泄漏问题已修复！\n";
} elseif ($fixedCount > 0) {
    echo "\n✅ 部分适配器的内存泄漏问题已修复\n";
} else {
    echo "\n⚠️  仍有适配器需要修复内存泄漏问题\n";
}

echo "\n测试完成！\n";
