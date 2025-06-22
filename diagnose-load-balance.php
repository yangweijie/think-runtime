<?php

declare(strict_types=1);

/**
 * 诊断 Workerman 负载均衡问题
 * 
 * 分析为什么4个worker进程中只有1个在处理请求
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';

use Workerman\Worker;

echo "=== Workerman 负载均衡问题诊断 ===\n";

echo "\n1. 检查 Workerman 配置\n";

// 检查 reusePort 支持
echo "检查 reusePort 支持:\n";

// 检查系统是否支持 SO_REUSEPORT
$testSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($testSocket) {
    $reusePortSupported = socket_set_option($testSocket, SOL_SOCKET, SO_REUSEPORT, 1);
    socket_close($testSocket);
    echo "- SO_REUSEPORT 支持: " . ($reusePortSupported ? '✅ 支持' : '❌ 不支持') . "\n";
} else {
    echo "- SO_REUSEPORT 检测失败\n";
}

// 检查系统版本
$osInfo = php_uname();
echo "- 操作系统: {$osInfo}\n";

// 检查 PHP 版本
echo "- PHP 版本: " . PHP_VERSION . "\n";

// 检查 Workerman 版本
if (class_exists('Workerman\Worker')) {
    $reflection = new ReflectionClass('Workerman\Worker');
    $constants = $reflection->getConstants();
    if (isset($constants['VERSION'])) {
        echo "- Workerman 版本: " . $constants['VERSION'] . "\n";
    }
}

echo "\n2. 负载均衡机制分析\n";

echo "Workerman 负载均衡机制:\n";
echo "1. 默认情况下，多个进程监听同一端口\n";
echo "2. 操作系统内核负责将连接分发给不同进程\n";
echo "3. 在某些系统上，可能出现'惊群效应'\n";
echo "4. reusePort 可以改善负载分发\n\n";

echo "可能的问题原因:\n";
echo "1. ❌ 系统不支持 SO_REUSEPORT\n";
echo "2. ❌ 内核负载均衡算法问题\n";
echo "3. ❌ 连接保持导致的粘性\n";
echo "4. ❌ 进程启动时序问题\n";

echo "\n3. 创建负载均衡测试\n";

// 创建测试 Worker 来验证负载均衡
$worker = new Worker('http://127.0.0.1:8090');
$worker->count = 4;
$worker->name = 'LoadBalanceTest';

// 关键：测试不同的 reusePort 设置
$worker->reusePort = true;  // 先测试启用 reusePort

// 统计每个进程的请求数
$requestCounts = [];

$worker->onWorkerStart = function($worker) use (&$requestCounts) {
    $requestCounts[$worker->id] = 0;
    echo "Worker #{$worker->id} started (PID: " . posix_getpid() . ")\n";
};

$worker->onMessage = function($connection, $request) use (&$requestCounts, $worker) {
    $requestCounts[$worker->id]++;
    
    $response = [
        'worker_id' => $worker->id,
        'pid' => posix_getpid(),
        'request_count' => $requestCounts[$worker->id],
        'timestamp' => microtime(true),
        'message' => "Handled by worker {$worker->id}"
    ];
    
    $connection->send("HTTP/1.1 200 OK\r\n");
    $connection->send("Content-Type: application/json\r\n");
    $connection->send("Connection: close\r\n");  // 强制关闭连接，避免连接复用
    $connection->send("\r\n");
    $connection->send(json_encode($response));
};

// 添加统计定时器
$worker->onWorkerStart = function($worker) use (&$requestCounts) {
    $requestCounts[$worker->id] = 0;
    echo "Worker #{$worker->id} started (PID: " . posix_getpid() . ")\n";
    
    // 每10秒输出统计
    \Workerman\Lib\Timer::add(10, function() use (&$requestCounts, $worker) {
        echo "Worker #{$worker->id} 处理了 {$requestCounts[$worker->id]} 个请求\n";
    });
};

echo "启动负载均衡测试服务器...\n";
echo "配置: 4个进程, reusePort=true, 端口8090\n";
echo "测试方法:\n";
echo "1. 在另一个终端运行: curl http://127.0.0.1:8090/\n";
echo "2. 或运行压测: wrk -t4 -c100 -d30s http://127.0.0.1:8090/\n";
echo "3. 观察哪些 worker 在处理请求\n";
echo "\n按 Ctrl+C 停止测试\n\n";

// 设置信号处理
pcntl_signal(SIGINT, function() use (&$requestCounts) {
    echo "\n\n=== 负载均衡测试结果 ===\n";
    
    $totalRequests = array_sum($requestCounts);
    echo "总请求数: {$totalRequests}\n";
    
    foreach ($requestCounts as $workerId => $count) {
        $percentage = $totalRequests > 0 ? round(($count / $totalRequests) * 100, 2) : 0;
        echo "Worker #{$workerId}: {$count} 请求 ({$percentage}%)\n";
    }
    
    // 分析负载均衡效果
    if ($totalRequests > 0) {
        $maxRequests = max($requestCounts);
        $minRequests = min($requestCounts);
        $imbalance = $maxRequests > 0 ? round((($maxRequests - $minRequests) / $maxRequests) * 100, 2) : 0;
        
        echo "\n负载均衡分析:\n";
        echo "最大请求数: {$maxRequests}\n";
        echo "最小请求数: {$minRequests}\n";
        echo "不均衡度: {$imbalance}%\n";
        
        if ($imbalance < 20) {
            echo "✅ 负载均衡良好\n";
        } elseif ($imbalance < 50) {
            echo "⚠️  负载均衡一般\n";
        } else {
            echo "❌ 负载均衡有问题\n";
        }
        
        // 检查是否只有一个进程在工作
        $workingProcesses = count(array_filter($requestCounts, fn($count) => $count > 0));
        echo "工作进程数: {$workingProcesses}/4\n";
        
        if ($workingProcesses == 1) {
            echo "🚨 只有一个进程在工作！\n";
            echo "\n可能的解决方案:\n";
            echo "1. 禁用 reusePort: \$worker->reusePort = false;\n";
            echo "2. 使用不同的端口绑定策略\n";
            echo "3. 检查系统内核版本和配置\n";
            echo "4. 使用 nginx 等反向代理进行负载均衡\n";
        }
    }
    
    Worker::stopAll();
    exit(0);
});

// 启动测试
Worker::runAll();
