<?php

declare(strict_types=1);

/**
 * 测试修复后的负载均衡
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

require_once 'vendor/autoload.php';

use Workerman\Worker;

echo "=== 测试修复后的负载均衡 ===\n";

// 创建测试服务器，使用修复后的配置
$worker = new Worker('http://127.0.0.1:8092');
$worker->count = 4;
$worker->name = 'LoadBalanceFixed';
$worker->reusePort = false;  // 关键：禁用 reusePort

// 统计每个进程的请求数
$requestCounts = [];

$worker->onWorkerStart = function($worker) use (&$requestCounts) {
    $requestCounts[$worker->id] = 0;
    echo "Worker #{$worker->id} started (PID: " . posix_getpid() . ") - reusePort=false\n";
    
    // 每5秒输出统计
    \Workerman\Lib\Timer::add(5, function() use (&$requestCounts, $worker) {
        echo "[Worker #{$worker->id}] 处理了 {$requestCounts[$worker->id]} 个请求\n";
    });
};

$worker->onMessage = function($connection, $request) use (&$requestCounts, $worker) {
    $requestCounts[$worker->id]++;
    
    $response = [
        'worker_id' => $worker->id,
        'pid' => posix_getpid(),
        'request_count' => $requestCounts[$worker->id],
        'timestamp' => microtime(true),
        'reusePort' => 'false',
        'message' => "Request #{$requestCounts[$worker->id]} handled by worker {$worker->id}"
    ];
    
    $responseBody = json_encode($response, JSON_PRETTY_PRINT);
    
    $connection->send("HTTP/1.1 200 OK\r\n");
    $connection->send("Content-Type: application/json\r\n");
    $connection->send("Content-Length: " . strlen($responseBody) . "\r\n");
    $connection->send("Connection: close\r\n");  // 强制关闭连接
    $connection->send("\r\n");
    $connection->send($responseBody);
};

echo "配置: 4个进程, reusePort=false, 端口8092\n";
echo "测试方法:\n";
echo "1. 单个请求测试: curl http://127.0.0.1:8092/\n";
echo "2. 压力测试: wrk -t4 -c100 -d30s http://127.0.0.1:8092/\n";
echo "3. 观察各 worker 的请求分布\n";
echo "\n按 Ctrl+C 查看最终统计\n\n";

// 设置信号处理
pcntl_signal(SIGINT, function() use (&$requestCounts) {
    echo "\n\n=== 负载均衡测试结果 ===\n";
    
    $totalRequests = array_sum($requestCounts);
    echo "总请求数: {$totalRequests}\n\n";
    
    if ($totalRequests > 0) {
        foreach ($requestCounts as $workerId => $count) {
            $percentage = round(($count / $totalRequests) * 100, 2);
            echo "Worker #{$workerId}: {$count} 请求 ({$percentage}%)\n";
        }
        
        // 分析负载均衡效果
        $maxRequests = max($requestCounts);
        $minRequests = min($requestCounts);
        $imbalance = $maxRequests > 0 ? round((($maxRequests - $minRequests) / $maxRequests) * 100, 2) : 0;
        
        echo "\n负载均衡分析:\n";
        echo "最大请求数: {$maxRequests}\n";
        echo "最小请求数: {$minRequests}\n";
        echo "不均衡度: {$imbalance}%\n";
        
        if ($imbalance < 20) {
            echo "✅ 负载均衡优秀 (不均衡度 < 20%)\n";
        } elseif ($imbalance < 40) {
            echo "✅ 负载均衡良好 (不均衡度 < 40%)\n";
        } elseif ($imbalance < 60) {
            echo "⚠️  负载均衡一般 (不均衡度 < 60%)\n";
        } else {
            echo "❌ 负载均衡有问题 (不均衡度 > 60%)\n";
        }
        
        // 检查工作进程数
        $workingProcesses = count(array_filter($requestCounts, fn($count) => $count > 0));
        echo "工作进程数: {$workingProcesses}/4\n";
        
        if ($workingProcesses == 4) {
            echo "🎉 所有4个进程都在工作！负载均衡修复成功！\n";
        } elseif ($workingProcesses > 1) {
            echo "✅ 有{$workingProcesses}个进程在工作，比之前有改善\n";
        } else {
            echo "❌ 仍然只有1个进程在工作，需要尝试其他解决方案\n";
        }
        
        // 计算理论性能提升
        if ($workingProcesses > 1) {
            $theoreticalImprovement = ($workingProcesses / 1) * 100;
            echo "\n📈 理论性能提升: {$theoreticalImprovement}% (基于工作进程数)\n";
            echo "如果之前 QPS 是 880，现在理论上可以达到: " . round(880 * $workingProcesses) . "\n";
        }
    }
    
    echo "\n=== 修复效果评估 ===\n";
    echo "reusePort = false 的效果:\n";
    echo "- 如果4个进程都在工作：✅ 修复成功\n";
    echo "- 如果仍然负载不均：需要尝试其他方案\n";
    echo "\n下一步建议:\n";
    echo "1. 如果修复成功，应用到真实项目配置\n";
    echo "2. 如果仍有问题，考虑单进程模式或 Nginx 代理\n";
    
    Worker::stopAll();
    exit(0);
});

// 启动测试
Worker::runAll();
