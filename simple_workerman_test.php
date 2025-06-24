<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;

/**
 * 简化的 Workerman 测试
 * 单进程，专注于功能验证
 */

echo "=== 简化 Workerman 测试 ===\n";

// 创建单进程 HTTP Worker
$worker = new Worker('http://127.0.0.1:8083');
$worker->count = 1; // 单进程避免复杂性
$worker->name = 'simple-test';

// 统计变量
$stats = [
    'requests' => 0,
    'start_time' => 0,
    'memory_start' => 0,
];

// 请求处理
$worker->onMessage = function(TcpConnection $connection, Request $request) use (&$stats) {
    $stats['requests']++;
    
    $data = [
        'message' => 'Hello from Workerman!',
        'request_count' => $stats['requests'],
        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        'uptime' => time() - $stats['start_time'],
        'path' => $request->path(),
        'method' => $request->method(),
    ];
    
    $response = new Response(200, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*',
    ], json_encode($data, JSON_UNESCAPED_UNICODE));
    
    $connection->send($response);
};

// Worker 启动
$worker->onWorkerStart = function(Worker $worker) use (&$stats) {
    $stats['start_time'] = time();
    $stats['memory_start'] = memory_get_usage(true);
    
    echo "Simple Workerman Test Server started!\n";
    echo "PID: " . getmypid() . "\n";
    echo "URL: http://127.0.0.1:8083/\n";
    echo "Memory: " . round($stats['memory_start'] / 1024 / 1024, 2) . "MB\n";
    echo "\nTest commands:\n";
    echo "curl http://127.0.0.1:8083/\n";
    echo "curl http://127.0.0.1:8083/test\n";
    echo "\nPress Ctrl+C to stop\n\n";
    
    // 每10秒输出统计
    Timer::add(10, function() use (&$stats) {
        $current_memory = memory_get_usage(true);
        $memory_increase = $current_memory - $stats['memory_start'];
        
        echo "Stats: ";
        echo "Requests={$stats['requests']}, ";
        echo "Memory=" . round($current_memory / 1024 / 1024, 2) . "MB, ";
        echo "Increase=" . round($memory_increase / 1024 / 1024, 2) . "MB, ";
        echo "Uptime=" . (time() - $stats['start_time']) . "s\n";
    });
};

echo "Starting Simple Workerman Test...\n";
echo "Listening on: http://127.0.0.1:8083\n";
echo "Worker processes: 1\n";
echo "Press Ctrl+C to stop\n\n";

// 启动
Worker::runAll();
