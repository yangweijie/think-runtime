<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;

/**
 * Workerman QPS 性能测试服务器
 * 优化版本，专注于高性能和低延迟
 */

echo "=== Workerman QPS 性能测试 ===\n";

// 创建 HTTP Worker - 优化配置
$worker = new Worker('http://0.0.0.0:8082');
$worker->count = 4; // 4个进程
$worker->name = 'qps-test-server';
$worker->reusePort = true; // 启用端口复用

// 性能统计
$stats = [
    'requests' => 0,
    'start_time' => 0,
    'last_report' => 0,
    'total_requests' => 0,
];

// 简化的请求处理 - 最小化内存分配
$worker->onMessage = function(TcpConnection $connection, Request $request) use (&$stats) {
    $stats['requests']++;
    $stats['total_requests']++;
    
    // 最简单的响应 - 避免复杂的数据结构
    $data = [
        'status' => 'ok',
        'timestamp' => time(),
        'worker_id' => $GLOBALS['worker_id'] ?? 0,
        'requests' => $stats['total_requests'],
    ];
    
    // 直接创建响应，避免额外的内存分配
    $response = new Response(200, [
        'Content-Type' => 'application/json',
        'Connection' => 'keep-alive',
    ], json_encode($data));
    
    $connection->send($response);
};

// Worker 启动事件
$worker->onWorkerStart = function(Worker $worker) use (&$stats) {
    $GLOBALS['worker_id'] = $worker->id;
    $stats['start_time'] = time();
    $stats['last_report'] = time();
    
    echo "QPS Worker #{$worker->id} started (PID: " . posix_getpid() . ")\n";
    
    // 每5秒报告一次QPS
    Timer::add(5, function() use (&$stats, $worker) {
        $now = time();
        $duration = $now - $stats['last_report'];
        
        if ($duration > 0) {
            $qps = round($stats['requests'] / $duration, 2);
            $totalDuration = $now - $stats['start_time'];
            $avgQps = $totalDuration > 0 ? round($stats['total_requests'] / $totalDuration, 2) : 0;
            
            echo "Worker #{$worker->id} - ";
            echo "QPS (last 5s): {$qps}, ";
            echo "Avg QPS: {$avgQps}, ";
            echo "Total: {$stats['total_requests']}, ";
            echo "Memory: " . round(memory_get_usage(true) / 1024 / 1024, 1) . "MB\n";
            
            // 重置计数器
            $stats['requests'] = 0;
            $stats['last_report'] = $now;
        }
    });
    
    // 每30秒强制GC一次
    Timer::add(30, function() {
        $before = memory_get_usage(true);
        gc_collect_cycles();
        $after = memory_get_usage(true);
        $freed = $before - $after;
        
        if ($freed > 1024 * 1024) { // 只有释放超过1MB才报告
            echo "GC freed: " . round($freed / 1024 / 1024, 2) . "MB\n";
        }
    });
};

echo "Starting Workerman QPS Test Server...\n";
echo "Listening on: http://0.0.0.0:8082\n";
echo "Worker processes: 4\n";
echo "Port reuse: Enabled\n";
echo "Optimized for: High QPS\n";
echo "\n=== Load Testing Commands ===\n";

// wrk 测试命令
echo "1. wrk (推荐):\n";
echo "   wrk -t8 -c200 -d60s --latency http://127.0.0.1:8082/\n";
echo "   wrk -t4 -c100 -d30s http://127.0.0.1:8082/\n\n";

// ab 测试命令
echo "2. Apache Bench:\n";
echo "   ab -n 100000 -c 200 -k http://127.0.0.1:8082/\n";
echo "   ab -n 50000 -c 100 http://127.0.0.1:8082/\n\n";

// curl 简单测试
echo "3. Simple test:\n";
echo "   curl http://127.0.0.1:8082/\n\n";

echo "=== Expected Performance ===\n";
echo "- QPS: 10,000 - 50,000+ (depending on hardware)\n";
echo "- Latency: < 10ms (99th percentile)\n";
echo "- Memory: Stable, no leaks\n";
echo "- CPU: Efficient utilization\n\n";

echo "Press Ctrl+C to stop the server\n\n";

// 启动Worker
Worker::runAll();
