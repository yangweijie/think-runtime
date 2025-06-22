<?php

declare(strict_types=1);

/**
 * 真实项目内存监控脚本
 * 
 * 使用方法：
 * 1. 将此文件复制到您的 ThinkPHP 项目根目录 /Volumes/data/git/php/tp/
 * 2. 在一个终端启动 workerman: php think-runtime workerman
 * 3. 在另一个终端运行此监控脚本: php real-project-memory-monitor.php
 * 4. 在第三个终端进行压测: wrk -t4 -c100 -d30s http://127.0.0.1:8080/
 */

echo "=== ThinkPHP Workerman 内存监控 ===\n";
echo "监控目标: Workerman 进程内存使用情况\n";
echo "按 Ctrl+C 停止监控\n\n";

// 配置
$monitorInterval = 2; // 监控间隔（秒）
$logFile = 'runtime/memory_monitor.log';
$maxLogSize = 10 * 1024 * 1024; // 10MB

// 确保日志目录存在
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// 初始化日志
if (file_exists($logFile) && filesize($logFile) > $maxLogSize) {
    rename($logFile, $logFile . '.old');
}

$startTime = time();
$logData = [];

echo sprintf("%-8s %-12s %-12s %-12s %-8s %-8s %-10s\n", 
    "时间", "进程PID", "内存使用", "内存峰值", "CPU%", "连接数", "状态");
echo str_repeat("-", 80) . "\n";

// 信号处理
pcntl_signal(SIGINT, function() use ($logFile, $logData) {
    echo "\n\n=== 监控结束，生成报告 ===\n";
    
    if (!empty($logData)) {
        $report = generateReport($logData);
        echo $report;
        
        // 保存报告
        file_put_contents($logFile . '.report', $report);
        echo "详细报告已保存到: {$logFile}.report\n";
    }
    
    exit(0);
});

while (true) {
    $currentTime = time();
    $timeStr = date('H:i:s');
    
    // 查找 Workerman 进程
    $processes = findWorkermanProcesses();
    
    if (empty($processes)) {
        echo sprintf("%-8s %-12s %-12s %-12s %-8s %-8s %-10s\n", 
            $timeStr, "N/A", "N/A", "N/A", "N/A", "N/A", "未运行");
        
        // 记录到日志
        $logEntry = [
            'time' => $currentTime,
            'timestamp' => $timeStr,
            'status' => 'not_running',
            'processes' => []
        ];
        $logData[] = $logEntry;
        file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
        
    } else {
        foreach ($processes as $process) {
            echo sprintf("%-8s %-12s %-12s %-12s %-8s %-8s %-10s\n", 
                $timeStr, 
                $process['pid'],
                formatBytes($process['memory']),
                formatBytes($process['peak_memory']),
                $process['cpu'] . '%',
                $process['connections'] ?? 'N/A',
                $process['status']
            );
            
            // 记录到日志
            $logEntry = [
                'time' => $currentTime,
                'timestamp' => $timeStr,
                'status' => 'running',
                'processes' => $processes
            ];
            $logData[] = $logEntry;
            file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND);
            
            // 内存警告
            if ($process['memory'] > 100 * 1024 * 1024) { // 100MB
                echo "⚠️  警告: 进程 {$process['pid']} 内存使用过高!\n";
            }
        }
    }
    
    // 保持最近1000条记录
    if (count($logData) > 1000) {
        $logData = array_slice($logData, -500);
    }
    
    sleep($monitorInterval);
    pcntl_signal_dispatch();
}

/**
 * 查找 Workerman 进程
 */
function findWorkermanProcesses(): array
{
    $processes = [];
    
    // 使用 ps 命令查找进程
    $cmd = "ps aux | grep -E '(workerman|think-runtime)' | grep -v grep";
    $output = shell_exec($cmd);
    
    if (!$output) {
        return $processes;
    }
    
    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 11) continue;
        
        $pid = (int)$parts[1];
        $cpu = (float)$parts[2];
        $mem = (float)$parts[3];
        $command = implode(' ', array_slice($parts, 10));
        
        // 获取详细内存信息
        $memInfo = getProcessMemoryInfo($pid);
        
        $processes[] = [
            'pid' => $pid,
            'cpu' => $cpu,
            'mem_percent' => $mem,
            'memory' => $memInfo['memory'],
            'peak_memory' => $memInfo['peak_memory'],
            'connections' => getProcessConnections($pid),
            'command' => $command,
            'status' => 'running'
        ];
    }
    
    return $processes;
}

/**
 * 获取进程内存详细信息
 */
function getProcessMemoryInfo(int $pid): array
{
    $statusFile = "/proc/{$pid}/status";
    $memory = 0;
    $peakMemory = 0;
    
    if (file_exists($statusFile)) {
        $content = file_get_contents($statusFile);
        
        // 解析 VmRSS (实际内存使用)
        if (preg_match('/VmRSS:\s+(\d+)\s+kB/', $content, $matches)) {
            $memory = (int)$matches[1] * 1024; // 转换为字节
        }
        
        // 解析 VmHWM (峰值内存使用)
        if (preg_match('/VmHWM:\s+(\d+)\s+kB/', $content, $matches)) {
            $peakMemory = (int)$matches[1] * 1024; // 转换为字节
        }
    }
    
    // 如果无法读取 /proc，使用 ps 命令
    if ($memory === 0) {
        $cmd = "ps -o rss,vsz -p {$pid} | tail -n 1";
        $output = shell_exec($cmd);
        if ($output) {
            $parts = preg_split('/\s+/', trim($output));
            if (count($parts) >= 1) {
                $memory = (int)$parts[0] * 1024; // RSS in KB, 转换为字节
            }
        }
    }
    
    return [
        'memory' => $memory,
        'peak_memory' => $peakMemory ?: $memory
    ];
}

/**
 * 获取进程连接数
 */
function getProcessConnections(int $pid): int
{
    $cmd = "lsof -p {$pid} 2>/dev/null | grep -c ESTABLISHED";
    $output = shell_exec($cmd);
    return (int)trim($output ?: '0');
}

/**
 * 格式化字节数
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) return '0B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor(log($bytes, 1024));
    
    return sprintf('%.1f%s', $bytes / pow(1024, $factor), $units[$factor]);
}

/**
 * 生成监控报告
 */
function generateReport(array $logData): string
{
    if (empty($logData)) {
        return "没有监控数据\n";
    }
    
    $report = "=== Workerman 内存监控报告 ===\n\n";
    
    // 基本统计
    $runningData = array_filter($logData, fn($entry) => $entry['status'] === 'running');
    $totalEntries = count($logData);
    $runningEntries = count($runningData);
    
    $report .= "监控时长: " . count($logData) * 2 . " 秒\n";
    $report .= "总记录数: {$totalEntries}\n";
    $report .= "运行记录: {$runningEntries}\n";
    $report .= "运行率: " . round(($runningEntries / $totalEntries) * 100, 2) . "%\n\n";
    
    if (!empty($runningData)) {
        // 内存统计
        $memoryUsages = [];
        foreach ($runningData as $entry) {
            foreach ($entry['processes'] as $process) {
                $memoryUsages[] = $process['memory'];
            }
        }
        
        if (!empty($memoryUsages)) {
            $report .= "=== 内存使用统计 ===\n";
            $report .= "最小内存: " . formatBytes(min($memoryUsages)) . "\n";
            $report .= "最大内存: " . formatBytes(max($memoryUsages)) . "\n";
            $report .= "平均内存: " . formatBytes(array_sum($memoryUsages) / count($memoryUsages)) . "\n";
            $report .= "内存增长: " . formatBytes(max($memoryUsages) - min($memoryUsages)) . "\n\n";
        }
        
        // 最近10条记录
        $report .= "=== 最近10条记录 ===\n";
        $recentData = array_slice($runningData, -10);
        foreach ($recentData as $entry) {
            $report .= $entry['timestamp'] . " - ";
            foreach ($entry['processes'] as $process) {
                $report .= "PID:{$process['pid']} 内存:" . formatBytes($process['memory']) . " ";
            }
            $report .= "\n";
        }
    }
    
    return $report;
}
