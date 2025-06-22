<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * Workerman 内存泄漏测试脚本
 * 
 * 此脚本用于测试 Workerman 适配器的内存泄漏修复效果
 */

echo "=== Workerman 内存泄漏测试 ===\n";

// 创建模拟应用
class MockApp
{
    private array $data = [];
    
    public function initialize(): void
    {
        $this->data = range(1, 1000); // 模拟一些数据
    }
    
    public function handle($request): string
    {
        return "Hello World " . uniqid();
    }
    
    public function __destruct()
    {
        // 模拟析构
        $this->data = [];
    }
}

// 创建适配器
$app = new MockApp();
$config = [
    'host' => '127.0.0.1',
    'port' => 8080,
    'count' => 1,
    'memory' => [
        'enable_gc' => true,
        'gc_interval' => 10, // 每10个请求GC一次
        'context_cleanup_interval' => 5, // 5秒清理一次
        'max_context_size' => 100,
    ],
    'timer' => [
        'enable' => true,
        'interval' => 10, // 10秒输出一次统计
    ],
];

$adapter = new WorkermanAdapter($app, $config);

echo "适配器创建完成\n";
echo "配置信息:\n";
print_r($adapter->getConfig());

// 检查适配器可用性
if (!$adapter->isAvailable()) {
    echo "错误: Workerman 不可用，请安装 workerman/workerman\n";
    exit(1);
}

echo "Workerman 适配器可用\n";

// 模拟内存泄漏测试
echo "\n=== 开始内存泄漏测试 ===\n";

// 测试连接上下文管理
echo "测试连接上下文管理...\n";

// 使用反射访问私有方法进行测试
$reflection = new ReflectionClass($adapter);

// 测试连接上下文设置和清理
$setContextMethod = $reflection->getMethod('setConnectionContext');
$setContextMethod->setAccessible(true);

$clearContextMethod = $reflection->getMethod('clearConnectionContext');
$clearContextMethod->setAccessible(true);

$contextProperty = $reflection->getProperty('connectionContext');
$contextProperty->setAccessible(true);

// 模拟连接对象 - 继承真实的 TcpConnection
class MockConnection extends \Workerman\Connection\TcpConnection
{
    public static int $idCounter = 0;
    public int $mockId;

    public function __construct()
    {
        $this->mockId = ++self::$idCounter;
        // 不调用父类构造函数，避免需要真实的socket资源
    }

    public function send($data, $raw = false): bool
    {
        // 模拟发送，不做实际操作
        return true;
    }
}

// 模拟请求对象 - 继承真实的 WorkermanRequest
class MockRequest extends \Workerman\Protocols\Http\Request
{
    private array $mockData;

    public function __construct()
    {
        $this->mockData = [
            'method' => 'GET',
            'uri' => '/test',
        ];
    }

    public function method(): string { return $this->mockData['method']; }
    public function uri(): string { return $this->mockData['uri']; }
}

echo "创建大量连接上下文...\n";
$initialMemory = memory_get_usage(true);

for ($i = 0; $i < 1000; $i++) {
    $connection = new MockConnection();
    $request = new MockRequest();
    $setContextMethod->invoke($adapter, $connection, $request, microtime(true));
    
    if ($i % 100 === 0) {
        $currentMemory = memory_get_usage(true);
        $contextCount = count($contextProperty->getValue($adapter));
        echo "创建了 {$i} 个上下文，当前内存: " . round($currentMemory / 1024 / 1024, 2) . "MB，上下文数量: {$contextCount}\n";
    }
}

$afterCreateMemory = memory_get_usage(true);
$contextCount = count($contextProperty->getValue($adapter));

echo "创建完成 - 内存增长: " . round(($afterCreateMemory - $initialMemory) / 1024 / 1024, 2) . "MB，上下文数量: {$contextCount}\n";

// 测试清理功能
echo "\n测试清理功能...\n";

$cleanupMethod = $reflection->getMethod('cleanupExpiredContext');
$cleanupMethod->setAccessible(true);

// 等待一段时间让上下文过期
sleep(1);

// 手动触发清理
$cleanupMethod->invoke($adapter);

$afterCleanupMemory = memory_get_usage(true);
$contextCountAfterCleanup = count($contextProperty->getValue($adapter));

echo "清理后 - 内存: " . round($afterCleanupMemory / 1024 / 1024, 2) . "MB，上下文数量: {$contextCountAfterCleanup}\n";

// 测试强制GC
echo "\n测试强制垃圾回收...\n";
$gcMethod = $reflection->getMethod('performPeriodicGC');
$gcMethod->setAccessible(true);

// 设置请求计数以触发GC
$memoryStatsProperty = $reflection->getProperty('memoryStats');
$memoryStatsProperty->setAccessible(true);
$stats = $memoryStatsProperty->getValue($adapter);
$stats['request_count'] = 100; // 触发GC的阈值
$memoryStatsProperty->setValue($adapter, $stats);

$beforeGC = memory_get_usage(true);
$gcMethod->invoke($adapter);
$afterGC = memory_get_usage(true);

echo "GC前内存: " . round($beforeGC / 1024 / 1024, 2) . "MB\n";
echo "GC后内存: " . round($afterGC / 1024 / 1024, 2) . "MB\n";
echo "释放内存: " . round(($beforeGC - $afterGC) / 1024 / 1024, 2) . "MB\n";

// 获取内存统计
echo "\n=== 最终内存统计 ===\n";
$finalStats = $adapter->getMemoryStats();
print_r($finalStats);

echo "\n=== 测试完成 ===\n";
echo "初始内存: " . round($initialMemory / 1024 / 1024, 2) . "MB\n";
echo "最终内存: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n";
echo "内存增长: " . round((memory_get_usage(true) - $initialMemory) / 1024 / 1024, 2) . "MB\n";

// 如果要启动服务器进行实际测试，取消下面的注释
/*
echo "\n启动 Workerman 服务器进行实际测试...\n";
echo "访问 http://127.0.0.1:8080 进行测试\n";
echo "按 Ctrl+C 停止服务器\n";
$adapter->run();
*/
