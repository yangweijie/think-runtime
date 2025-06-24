<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;

/**
 * Workerman 内存泄漏测试
 * 模拟高并发请求，监控内存使用情况
 */

echo "=== Workerman 内存泄漏测试 ===\n";

// 创建 HTTP Worker
$worker = new Worker('http://127.0.0.1:8081');
$worker->count = 2;
$worker->name = 'memory-leak-test';

// 统计变量
$requestCount = 0;
$memoryStats = [
    'peak_usage' => 0,
    'request_count' => 0,
    'last_cleanup' => 0,
    'start_memory' => 0,
];

// 模拟数据存储（可能导致内存泄漏）
$dataCache = [];
$connectionContexts = [];

// 请求处理
$worker->onMessage = function(TcpConnection $connection, Request $request) use (&$requestCount, &$memoryStats, &$dataCache, &$connectionContexts) {
    $startTime = microtime(true);
    $requestCount++;
    $memoryStats['request_count']++;
    
    // 模拟一些内存操作
    $requestId = uniqid();
    $connectionId = spl_object_hash($connection);
    
    // 模拟缓存数据（可能导致内存泄漏）
    $dataCache[$requestId] = [
        'id' => $requestId,
        'timestamp' => microtime(true),
        'data' => str_repeat('test_data_', 100), // 约1KB数据
        'request_count' => $requestCount,
        'connection_id' => $connectionId,
    ];
    
    // 模拟连接上下文（可能导致内存泄漏）
    $connectionContexts[$connectionId] = [
        'last_request' => time(),
        'request_count' => ($connectionContexts[$connectionId]['request_count'] ?? 0) + 1,
        'data' => str_repeat('context_', 50),
    ];
    
    // 限制缓存大小（防止无限增长）
    if (count($dataCache) > 1000) {
        $dataCache = array_slice($dataCache, -500, null, true);
    }
    
    // 清理过期连接上下文
    if (count($connectionContexts) > 100) {
        $now = time();
        foreach ($connectionContexts as $id => $context) {
            if ($now - $context['last_request'] > 60) { // 60秒过期
                unset($connectionContexts[$id]);
            }
        }
    }
    
    // 定期垃圾回收
    if ($requestCount % 100 === 0) {
        $beforeMemory = memory_get_usage(true);
        gc_collect_cycles();
        $afterMemory = memory_get_usage(true);
        $freed = $beforeMemory - $afterMemory;
        
        if ($freed > 0) {
            echo "GC freed: " . round($freed / 1024 / 1024, 2) . "MB\n";
        }
    }
    
    // 构建响应数据
    $currentMemory = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    
    if ($peakMemory > $memoryStats['peak_usage']) {
        $memoryStats['peak_usage'] = $peakMemory;
    }
    
    $responseData = [
        'message' => 'Hello from Workerman Memory Test!',
        'request_id' => $requestId,
        'request_count' => $requestCount,
        'memory_usage' => round($currentMemory / 1024 / 1024, 2) . 'MB',
        'peak_memory' => round($peakMemory / 1024 / 1024, 2) . 'MB',
        'cache_size' => count($dataCache),
        'context_size' => count($connectionContexts),
        'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
    ];
    
    $response = new Response(200, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
    ], json_encode($responseData, JSON_UNESCAPED_UNICODE));
    
    $connection->send($response);
};

// Worker 启动事件
$worker->onWorkerStart = function(Worker $worker) use (&$memoryStats) {
    echo "Worker #{$worker->id} started (PID: " . getmypid() . ")\n";
    
    $memoryStats['start_memory'] = memory_get_usage(true);
    
    // 设置定时器，每10秒输出统计信息
    Timer::add(10, function() use (&$memoryStats, &$dataCache, &$connectionContexts) {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $startMemory = $memoryStats['start_memory'];
        
        if ($peakMemory > $memoryStats['peak_usage']) {
            $memoryStats['peak_usage'] = $peakMemory;
        }
        
        $memoryIncrease = $currentMemory - $startMemory;
        
        echo "\n=== Memory Stats ===\n";
        echo "Requests: {$memoryStats['request_count']}\n";
        echo "Current Memory: " . round($currentMemory / 1024 / 1024, 2) . "MB\n";
        echo "Peak Memory: " . round($peakMemory / 1024 / 1024, 2) . "MB\n";
        echo "Start Memory: " . round($startMemory / 1024 / 1024, 2) . "MB\n";
        echo "Memory Increase: " . round($memoryIncrease / 1024 / 1024, 2) . "MB\n";
        echo "Cache Size: " . count($dataCache ?? []) . "\n";
        echo "Context Size: " . count($connectionContexts ?? []) . "\n";
        echo "QPS (last 10s): " . round($memoryStats['request_count'] / 10, 2) . "\n";
        echo "==================\n\n";
        
        // 重置请求计数
        $memoryStats['request_count'] = 0;
    });
    
    // 设置内存监控定时器
    Timer::add(30, function() use (&$memoryStats) {
        $currentMemory = memory_get_usage(true);
        $limitBytes = 256 * 1024 * 1024; // 256MB
        
        if ($currentMemory > $limitBytes * 0.8) {
            echo "⚠️  Memory usage is high: " . round($currentMemory / 1024 / 1024, 2) . "MB\n";
            
            // 强制垃圾回收
            $beforeMemory = memory_get_usage(true);
            gc_collect_cycles();
            $afterMemory = memory_get_usage(true);
            $freed = $beforeMemory - $afterMemory;
            
            if ($freed > 0) {
                echo "🔄 Forced GC freed: " . round($freed / 1024 / 1024, 2) . "MB\n";
            }
        }
    });
};

// 连接关闭事件
$worker->onClose = function(TcpConnection $connection) use (&$connectionContexts) {
    $connectionId = spl_object_hash($connection);
    if (isset($connectionContexts[$connectionId])) {
        unset($connectionContexts[$connectionId]);
    }
};

echo "Starting Workerman Memory Leak Test Server...\n";
echo "Listening on: http://127.0.0.1:8081\n";
echo "Worker processes: 2\n";
echo "Memory monitoring: Enabled\n";
echo "GC interval: Every 100 requests\n";
echo "\nTest URLs:\n";
echo "- http://127.0.0.1:8081/\n";
echo "- Use wrk or ab for load testing\n";
echo "\nExample load test:\n";
echo "wrk -t4 -c100 -d30s http://127.0.0.1:8081/\n";
echo "ab -n 10000 -c 100 http://127.0.0.1:8081/\n";
echo "\nPress Ctrl+C to stop the server\n\n";

// 启动Worker
Worker::runAll();
