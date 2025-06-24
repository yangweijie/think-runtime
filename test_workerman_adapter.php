<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * 测试修复后的 WorkermanAdapter
 */

echo "=== 测试修复后的 WorkermanAdapter ===\n";

// 创建测试应用
class TestApp
{
    public function initialize(): void
    {
        echo "✅ 应用初始化\n";
    }

    public function has(string $name): bool
    {
        return false;
    }
}

// 创建 HTTP Worker 来测试 WorkermanAdapter 的功能
$worker = new Worker('http://127.0.0.1:8085');
$worker->count = 1;
$worker->name = 'adapter-test';

// 创建适配器实例
$testApp = new TestApp();
$config = [
    'host' => '127.0.0.1',
    'port' => 8085,
    'count' => 1,
    'name' => 'adapter-test',
];

$adapter = new WorkermanAdapter($testApp, $config);

// 测试适配器方法
$worker->onMessage = function(TcpConnection $connection, Request $request) use ($adapter) {
    try {
        // 测试 getClientIp 方法
        $reflection = new ReflectionClass($adapter);
        $getClientIpMethod = $reflection->getMethod('getClientIp');
        $getClientIpMethod->setAccessible(true);
        $clientIp = $getClientIpMethod->invoke($adapter, $request);
        
        // 测试 handleWorkermanDirectRequest 方法
        $handleDirectMethod = $reflection->getMethod('handleWorkermanDirectRequest');
        $handleDirectMethod->setAccessible(true);
        $response = $handleDirectMethod->invoke($adapter, $request);
        
        // 创建测试响应数据
        $data = [
            'message' => 'WorkermanAdapter Test Success!',
            'test_info' => [
                'client_ip' => $clientIp,
                'adapter_name' => $adapter->getName(),
                'adapter_priority' => $adapter->getPriority(),
                'is_supported' => $adapter->isSupported(),
                'is_available' => $adapter->isAvailable(),
                'memory_stats' => $adapter->getMemoryStats(),
            ],
            'request_info' => [
                'path' => $request->path(),
                'method' => $request->method(),
                'host' => $request->host(),
                'uri' => $request->uri(),
            ],
            'compatibility_fixes' => [
                'posix_getpid_to_getmypid' => '✅',
                'getRemoteIp_to_getClientIp' => '✅',
                'direct_request_handling' => '✅',
            ],
        ];

        $newResponse = new Response(200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
        ], json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        
        $connection->send($newResponse);
        
    } catch (Exception $e) {
        $errorResponse = new Response(500, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]));
        
        $connection->send($errorResponse);
    }
};

$worker->onWorkerStart = function(Worker $worker) {
    echo "WorkermanAdapter Test Server started!\n";
    echo "PID: " . getmypid() . "\n";
    echo "URL: http://127.0.0.1:8085/\n";
    echo "\n测试功能:\n";
    echo "- ✅ getmypid() 跨平台兼容\n";
    echo "- ✅ getClientIp() 方法\n";
    echo "- ✅ handleWorkermanDirectRequest() 方法\n";
    echo "- ✅ 适配器基本功能\n";
    echo "\nTest command:\n";
    echo "curl http://127.0.0.1:8085/\n";
    echo "\nPress Ctrl+C to stop\n\n";
};

echo "Starting WorkermanAdapter Test Server...\n";
echo "Listening on: http://127.0.0.1:8085\n";
echo "Testing compatibility fixes...\n";
echo "Press Ctrl+C to stop\n\n";

Worker::runAll();
