<?php

declare(strict_types=1);

/**
 * 简单的负载均衡测试
 */

require_once '/Volumes/data/git/php/tp/vendor/autoload.php';

use Workerman\Worker;

echo "=== 简单负载均衡测试 ===\n";

// 创建简单的测试服务器
$worker = new Worker('http://127.0.0.1:8091');
$worker->count = 4;
$worker->name = 'SimpleLoadTest';

// 测试不同的 reusePort 设置
$worker->reusePort = false;  // 先测试禁用 reusePort

echo "配置: 4个进程, reusePort=false, 端口8091\n";

$worker->onWorkerStart = function($worker) {
    echo "Worker #{$worker->id} started (PID: " . posix_getpid() . ")\n";
};

$worker->onMessage = function($connection, $request) use ($worker) {
    $response = json_encode([
        'worker_id' => $worker->id,
        'pid' => posix_getpid(),
        'timestamp' => microtime(true),
    ]);
    
    $connection->send("HTTP/1.1 200 OK\r\n");
    $connection->send("Content-Type: application/json\r\n");
    $connection->send("Content-Length: " . strlen($response) . "\r\n");
    $connection->send("Connection: close\r\n");
    $connection->send("\r\n");
    $connection->send($response);
};

echo "启动测试服务器...\n";
echo "测试命令: curl http://127.0.0.1:8091/\n";
echo "压测命令: wrk -t4 -c100 -d10s http://127.0.0.1:8091/\n";
echo "\n按 Ctrl+C 停止\n\n";

// 启动
Worker::runAll();
