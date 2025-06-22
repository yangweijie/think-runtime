<?php

declare(strict_types=1);

/**
 * Event 扩展优化测试
 * 
 * 专门测试使用 Event 扩展的 Workerman 性能
 */

require_once '/Volumes/data/git/php/tp/vendor/autoload.php';

echo "=== Workerman Event 扩展优化测试 ===\n";

// 检查 Event 扩展
if (!extension_loaded('event')) {
    echo "❌ Event 扩展未安装\n";
    exit(1);
}

echo "✅ Event 扩展已安装\n";

// 显示 Event 扩展信息
$eventVersion = phpversion('event');
echo "Event 扩展版本: {$eventVersion}\n";

// 检查 libevent 版本
if (function_exists('event_get_version')) {
    $libeventVersion = event_get_version();
    echo "Libevent 版本: {$libeventVersion}\n";
}

echo "\n=== Workerman 事件循环测试 ===\n";

use Workerman\Worker;
use Workerman\Timer;

// 创建一个简单的 HTTP 服务器来测试事件循环
$worker = new Worker('http://127.0.0.1:8081');
$worker->count = 4;
$worker->name = 'EventTest';

// 统计变量
$requestCount = 0;
$startTime = time();

$worker->onWorkerStart = function($worker) {
    echo "Worker #{$worker->id} started with Event loop\n";
    
    // 每秒输出统计信息
    Timer::add(1, function() use (&$requestCount, $startTime) {
        $runtime = time() - $startTime;
        $qps = $runtime > 0 ? round($requestCount / $runtime, 2) : 0;
        echo "运行时间: {$runtime}s, 总请求: {$requestCount}, 平均QPS: {$qps}\n";
    });
};

$worker->onMessage = function($connection, $request) use (&$requestCount) {
    $requestCount++;
    
    // 模拟一些处理
    $data = [
        'message' => 'Hello from Event-powered Workerman!',
        'request_id' => $requestCount,
        'timestamp' => microtime(true),
        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        'event_loop' => 'Event (libevent)',
    ];
    
    $response = json_encode($data);
    
    $connection->send("HTTP/1.1 200 OK\r\n");
    $connection->send("Content-Type: application/json\r\n");
    $connection->send("Content-Length: " . strlen($response) . "\r\n");
    $connection->send("Connection: keep-alive\r\n");
    $connection->send("\r\n");
    $connection->send($response);
};

$worker->onWorkerStop = function($worker) use (&$requestCount, $startTime) {
    $runtime = time() - $startTime;
    $avgQps = $runtime > 0 ? round($requestCount / $runtime, 2) : 0;
    echo "Worker #{$worker->id} stopped. 总请求: {$requestCount}, 平均QPS: {$avgQps}\n";
};

echo "启动 Event 优化的 Workerman 服务器...\n";
echo "监听地址: http://127.0.0.1:8081\n";
echo "事件循环: Event (libevent)\n";
echo "进程数: 4\n";
echo "\n按 Ctrl+C 停止服务\n\n";

// 设置信号处理
pcntl_signal(SIGINT, function() use (&$requestCount, $startTime) {
    $runtime = time() - $startTime;
    $avgQps = $runtime > 0 ? round($requestCount / $runtime, 2) : 0;
    
    echo "\n\n=== 测试结果 ===\n";
    echo "运行时间: {$runtime} 秒\n";
    echo "总请求数: {$requestCount}\n";
    echo "平均QPS: {$avgQps}\n";
    echo "事件循环: Event (libevent)\n";
    echo "内存使用: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n";
    
    Worker::stopAll();
    exit(0);
});

// 启动服务器
Worker::runAll();
