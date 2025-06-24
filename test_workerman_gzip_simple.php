<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;

/**
 * 简化的 Workerman Gzip 测试
 * 使用 WorkermanAdapter 的默认 gzip 功能
 */

echo "=== 简化 Workerman Gzip 测试 ===\n";

// 创建测试应用
class SimpleGzipApp
{
    public function initialize(): void
    {
        echo "✅ 应用初始化 (默认 Gzip)\n";
    }

    public function has(string $name): bool
    {
        return false;
    }
}

// 创建 HTTP Worker
$worker = new Worker('http://127.0.0.1:8089');
$worker->count = 1;
$worker->name = 'simple-gzip-test';

// 创建适配器实例 - 使用默认 gzip 配置
$testApp = new SimpleGzipApp();
$config = [
    'host' => '127.0.0.1',
    'port' => 8089,
    'count' => 1,
    'name' => 'simple-gzip-test',
    // 使用默认的 gzip 配置
];

$adapter = new WorkermanAdapter($testApp, $config);

// 请求处理 - 直接使用适配器的处理方法
$worker->onMessage = function(TcpConnection $connection, Request $request) use ($adapter) {
    try {
        // 使用反射调用适配器的直接请求处理方法
        $reflection = new ReflectionClass($adapter);
        $handleDirectMethod = $reflection->getMethod('handleWorkermanDirectRequest');
        $handleDirectMethod->setAccessible(true);
        
        $response = $handleDirectMethod->invoke($adapter, $request);
        $connection->send($response);
        
    } catch (Exception $e) {
        $errorResponse = new \Workerman\Protocols\Http\Response(500, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'error' => true,
            'message' => $e->getMessage(),
        ]));
        
        $connection->send($errorResponse);
    }
};

$worker->onWorkerStart = function(Worker $worker) use ($adapter) {
    echo "Simple Workerman Gzip Test Server started!\n";
    echo "PID: " . getmypid() . "\n";
    echo "URL: http://127.0.0.1:8089/\n";
    
    // 显示压缩配置
    $config = $adapter->getConfig();
    $compressionConfig = $config['compression'] ?? [];
    
    echo "\nGzip 配置:\n";
    echo "- 启用: " . (($compressionConfig['enable'] ?? true) ? '✅' : '❌') . "\n";
    echo "- 类型: " . ($compressionConfig['type'] ?? 'gzip') . "\n";
    echo "- 级别: " . ($compressionConfig['level'] ?? 6) . "\n";
    echo "- 最小长度: " . ($compressionConfig['min_length'] ?? 1024) . " 字节\n";
    echo "- 支持类型: " . implode(', ', array_slice($compressionConfig['types'] ?? [], 0, 3)) . "...\n";
    
    echo "\n测试命令:\n";
    echo "# 支持 gzip 的请求\n";
    echo "curl -H 'Accept-Encoding: gzip' -v http://127.0.0.1:8089/\n\n";
    echo "# 不支持 gzip 的请求\n";
    echo "curl -v http://127.0.0.1:8089/\n\n";
    echo "# 查看压缩统计\n";
    echo "curl -H 'Accept-Encoding: gzip' -I http://127.0.0.1:8089/\n\n";
    
    echo "Press Ctrl+C to stop\n\n";
};

echo "Starting Simple Workerman Gzip Test Server...\n";
echo "Listening on: http://127.0.0.1:8089\n";
echo "Using WorkermanAdapter default gzip settings\n";
echo "Press Ctrl+C to stop\n\n";

Worker::runAll();
