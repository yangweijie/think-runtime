<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Timer;

/**
 * 简单的 Workerman 内存泄漏测试服务器
 * 直接使用 Workerman 原生 API，测试我们修复的内存管理功能
 */

echo "=== 简单 Workerman 内存泄漏测试 ===\n";

// 创建 HTTP Worker
$worker = new Worker('http://127.0.0.1:8080');
$worker->count = 2;
$worker->name = 'memory-leak-test';

// 统计变量
$requestCount = 0;
$memoryStats = [
    'peak_usage' => 0,
    'request_count' => 0,
    'last_cleanup' => 0,
];

// 连接上下文存储（模拟我们修复的功能）
$connectionContext = [];

// 设置连接上下文（轻量化存储）
function setConnectionContext(TcpConnection $connection, Request $request, float $startTime): void
{
    global $connectionContext;
    
    $connectionId = spl_object_id($connection);
    
    // 只存储必要的信息，避免存储完整对象
    $connectionContext[$connectionId] = [
        'request_id' => uniqid(),
        'start_time' => $startTime,
        'method' => $request->method(),
        'uri' => $request->uri(),
        'created_at' => time(), // 使用 time() 便于清理比较
    ];
    
    // 检查上下文数量，防止无限增长
    if (count($connectionContext) > 100) {
        // 强制清理最旧的上下文
        uasort($connectionContext, function($a, $b) {
            return ($a['created_at'] ?? 0) <=> ($b['created_at'] ?? 0);
        });
        
        $toRemove = count($connectionContext) - 80; // 清理到80个
        $removed = 0;
        foreach ($connectionContext as $id => $context) {
            if ($removed >= $toRemove) {
                break;
            }
            unset($connectionContext[$id]);
            $removed++;
        }
        
        echo "Force cleaned {$removed} oldest contexts due to size limit\n";
    }
}

// 清理连接上下文
function clearConnectionContext(TcpConnection $connection): void
{
    global $connectionContext;
    $connectionId = spl_object_id($connection);
    unset($connectionContext[$connectionId]);
}

// 清理过期上下文
function cleanupExpiredContext(): void
{
    global $connectionContext;
    
    $now = time();
    $cleaned = 0;
    
    foreach ($connectionContext as $id => $context) {
        // 清理超过5分钟的上下文
        if ($now - ($context['created_at'] ?? $now) > 300) {
            unset($connectionContext[$id]);
            $cleaned++;
        }
    }
    
    if ($cleaned > 0) {
        echo "Cleaned {$cleaned} expired contexts, remaining: " . count($connectionContext) . "\n";
    }
    
    // 强制垃圾回收
    if ($cleaned > 10) {
        gc_collect_cycles();
    }
}

// 定期垃圾回收
function performPeriodicGC(): void
{
    global $memoryStats;
    
    if ($memoryStats['request_count'] % 50 === 0) { // 每50个请求GC一次
        $beforeMemory = memory_get_usage(true);
        gc_collect_cycles();
        $afterMemory = memory_get_usage(true);
        
        $freed = $beforeMemory - $afterMemory;
        if ($freed > 0) {
            echo "GC freed " . round($freed / 1024 / 1024, 2) . "MB memory\n";
        }
    }
}

// Worker 启动事件
$worker->onWorkerStart = function(Worker $worker) {
    echo "Worker #{$worker->id} started\n";
    
    // 设置定时器，每30秒输出统计信息
    Timer::add(30, function() {
        global $memoryStats, $connectionContext;
        
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        if ($peakMemory > $memoryStats['peak_usage']) {
            $memoryStats['peak_usage'] = $peakMemory;
        }
        
        echo "Memory Stats - Current: " . round($currentMemory / 1024 / 1024, 2) . "MB, ";
        echo "Peak: " . round($peakMemory / 1024 / 1024, 2) . "MB, ";
        echo "Contexts: " . count($connectionContext) . ", ";
        echo "Requests: " . $memoryStats['request_count'] . "\n";
        
        // 清理过期上下文
        cleanupExpiredContext();
    });
};

// 消息处理事件
$worker->onMessage = function(TcpConnection $connection, Request $request) {
    global $memoryStats, $requestCount;
    
    $startTime = microtime(true);
    $memoryStats['request_count']++;
    $requestCount++;
    
    // 定期强制垃圾回收
    performPeriodicGC();
    
    try {
        // 设置连接上下文
        setConnectionContext($connection, $request, $startTime);
        
        // 模拟一些内存操作
        $data = [
            'request_id' => uniqid(),
            'timestamp' => microtime(true),
            'data' => str_repeat('x', 1024), // 1KB 数据
            'count' => $requestCount,
        ];
        
        // 创建响应
        $response = new Response(200);
        $response->header('Content-Type', 'application/json');
        $response->header('Access-Control-Allow-Origin', '*');
        
        $responseData = [
            'message' => 'Hello from Workerman!',
            'request_count' => $requestCount,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'context_count' => count($GLOBALS['connectionContext']),
            'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
        ];
        
        $response->withBody(json_encode($responseData, JSON_UNESCAPED_UNICODE));
        $connection->send($response);
        
    } catch (Throwable $e) {
        $errorResponse = new Response(500);
        $errorResponse->header('Content-Type', 'application/json');
        $errorResponse->withBody(json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]));
        $connection->send($errorResponse);
    } finally {
        // 清理连接上下文
        clearConnectionContext($connection);
    }
};

// 连接关闭事件
$worker->onClose = function(TcpConnection $connection) {
    clearConnectionContext($connection);
};

echo "启动简单 Workerman 测试服务器...\n";
echo "服务器地址: http://127.0.0.1:8080\n";
echo "进程数: {$worker->count}\n";
echo "\n测试建议:\n";
echo "1. 使用 curl 测试: curl http://127.0.0.1:8080/\n";
echo "2. 使用 ab 压力测试: ab -n 1000 -c 50 http://127.0.0.1:8080/\n";
echo "3. 观察内存统计信息（每30秒输出一次）\n";
echo "4. 按 Ctrl+C 停止服务器\n\n";

// 启动服务器
Worker::runAll();
