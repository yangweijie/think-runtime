<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * 真实内存泄漏测试脚本
 * 
 * 模拟真实的 ThinkPHP 应用环境进行内存测试
 */

echo "=== 真实内存泄漏测试 ===\n";

// 创建真实的 ThinkPHP 应用模拟
class RealThinkApp
{
    private array $services = [];
    private array $instances = [];
    private array $bindings = [];
    private bool $initialized = false;
    public $http;
    public $request;
    public $response;
    
    public function __construct()
    {
        // 模拟 ThinkPHP 应用的复杂结构
        $this->services = [
            'config' => new class {
                public function get($key, $default = null) {
                    return $default;
                }
            },
            'cache' => new class {
                private $data = [];
                public function get($key) { return $this->data[$key] ?? null; }
                public function set($key, $value) { $this->data[$key] = $value; }
            },
            'log' => new class {
                public function info($message) { /* 模拟日志 */ }
                public function error($message) { /* 模拟日志 */ }
            },
        ];
        
        // 创建模拟的 HTTP 对象
        $this->http = new class {
            public function run($request) {
                // 模拟复杂的请求处理
                $data = [];
                for ($i = 0; $i < 100; $i++) {
                    $data[] = [
                        'id' => $i,
                        'name' => 'Item ' . $i,
                        'data' => str_repeat('x', 1000), // 1KB 数据
                        'timestamp' => microtime(true),
                    ];
                }
                
                return new class($data) {
                    private $data;
                    public function __construct($data) { $this->data = $data; }
                    public function getCode() { return 200; }
                    public function getHeader() { return ['Content-Type' => 'application/json']; }
                    public function getContent() {
                        return json_encode([
                            'success' => true,
                            'data' => $this->data,
                            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
                            'timestamp' => microtime(true),
                        ]);
                    }
                };
            }
        };
        
        // 创建模拟的 Request 对象
        $this->request = new class {
            public $server = [];
            public $host = null;
        };
    }
    
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        
        $this->initialized = true;
        
        // 模拟初始化过程中的内存分配
        for ($i = 0; $i < 50; $i++) {
            $this->instances['service_' . $i] = new class {
                private $data;
                public function __construct() {
                    $this->data = str_repeat('init_data_', 100);
                }
            };
        }
    }
    
    public function has(string $name): bool
    {
        return isset($this->services[$name]) || isset($this->instances[$name]);
    }
    
    public function get(string $name)
    {
        return $this->services[$name] ?? $this->instances[$name] ?? null;
    }
    
    public function make(string $name, array $vars = [])
    {
        return $this->get($name);
    }
    
    public function bind(string $name, $value): void
    {
        $this->bindings[$name] = $value;
    }
    
    public function __destruct()
    {
        // 模拟析构过程
        $this->services = [];
        $this->instances = [];
        $this->bindings = [];
    }
}

// 测试配置
$config = [
    'host' => '127.0.0.1',
    'port' => 8080,
    'count' => 1, // 单进程测试
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 50, // 每50个请求GC一次
        'context_cleanup_interval' => 30, // 30秒清理一次
        'max_context_size' => 100,
    ],
];

echo "创建真实应用实例...\n";
$app = new RealThinkApp();

echo "创建 Workerman 适配器...\n";
$adapter = new WorkermanAdapter($app, $config);

// 检查适配器可用性
if (!$adapter->isAvailable()) {
    echo "❌ Workerman 不可用，请安装 workerman/workerman\n";
    exit(1);
}

echo "✅ Workerman 适配器可用\n";

// 模拟内存压力测试
echo "\n=== 开始内存压力测试 ===\n";

$initialMemory = memory_get_usage(true);
$initialPeak = memory_get_peak_usage(true);

echo "初始内存使用: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";
echo "初始峰值内存: " . round($initialPeak / 1024 / 1024, 2) . " MB\n\n";

// 模拟大量请求处理
$requestCount = 1000;
$memorySnapshots = [];

echo "模拟处理 {$requestCount} 个请求...\n";

for ($i = 1; $i <= $requestCount; $i++) {
    // 模拟请求处理过程中的内存分配
    $requestData = [];
    for ($j = 0; $j < 10; $j++) {
        $requestData[] = str_repeat('request_data_', 50);
    }
    
    // 模拟应用处理
    $app->initialize();
    
    // 模拟一些服务调用
    $app->get('cache')->set('request_' . $i, $requestData);
    $app->get('log')->info('Processing request ' . $i);
    
    // 清理请求数据
    unset($requestData);
    
    // 每100个请求记录一次内存使用
    if ($i % 100 === 0) {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $memorySnapshots[] = [
            'request' => $i,
            'memory' => $currentMemory,
            'peak' => $peakMemory,
            'memory_mb' => round($currentMemory / 1024 / 1024, 2),
            'peak_mb' => round($peakMemory / 1024 / 1024, 2),
        ];
        
        echo "请求 {$i}: 内存 " . round($currentMemory / 1024 / 1024, 2) . "MB, 峰值 " . round($peakMemory / 1024 / 1024, 2) . "MB\n";
        
        // 手动触发垃圾回收
        gc_collect_cycles();
    }
}

$finalMemory = memory_get_usage(true);
$finalPeak = memory_get_peak_usage(true);

echo "\n=== 测试结果 ===\n";
echo "处理请求数: {$requestCount}\n";
echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . " MB\n";
echo "最终内存: " . round($finalMemory / 1024 / 1024, 2) . " MB\n";
echo "内存增长: " . round(($finalMemory - $initialMemory) / 1024 / 1024, 2) . " MB\n";
echo "最终峰值: " . round($finalPeak / 1024 / 1024, 2) . " MB\n";

// 分析内存趋势
echo "\n=== 内存趋势分析 ===\n";
$firstSnapshot = $memorySnapshots[0];
$lastSnapshot = end($memorySnapshots);

$memoryGrowth = $lastSnapshot['memory'] - $firstSnapshot['memory'];
$avgGrowthPerRequest = $memoryGrowth / ($lastSnapshot['request'] - $firstSnapshot['request']);

echo "内存增长趋势: " . round($memoryGrowth / 1024 / 1024, 2) . " MB\n";
echo "平均每请求增长: " . round($avgGrowthPerRequest / 1024, 2) . " KB\n";

if ($avgGrowthPerRequest < 1024) { // 小于1KB
    echo "✅ 内存使用稳定，无明显泄漏\n";
} elseif ($avgGrowthPerRequest < 10240) { // 小于10KB
    echo "⚠️  内存有轻微增长，需要关注\n";
} else {
    echo "❌ 内存增长过快，存在泄漏风险\n";
}

// 输出详细的内存快照
echo "\n=== 详细内存快照 ===\n";
foreach ($memorySnapshots as $snapshot) {
    echo sprintf(
        "请求 %4d: %6.2f MB (峰值: %6.2f MB)\n",
        $snapshot['request'],
        $snapshot['memory_mb'],
        $snapshot['peak_mb']
    );
}

echo "\n测试完成！\n";
