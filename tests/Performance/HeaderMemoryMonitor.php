<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\Tests\Performance;

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use Psr\Log\NullLogger;

/**
 * 头部处理内存监控工具
 * 用于监控和分析头部去重服务的内存使用情况
 */
class HeaderMemoryMonitor
{
    private array $memorySnapshots = [];
    private array $performanceMetrics = [];
    private float $startTime;
    private int $startMemory;

    public function __construct()
    {
        $this->reset();
    }

    /**
     * 重置监控状态
     */
    public function reset(): void
    {
        $this->memorySnapshots = [];
        $this->performanceMetrics = [];
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * 记录内存快照
     */
    public function snapshot(string $label): void
    {
        $this->memorySnapshots[] = [
            'label' => $label,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'time_elapsed' => microtime(true) - $this->startTime,
        ];
    }

    /**
     * 监控头部去重操作
     */
    public function monitorDeduplication(HeaderDeduplicationService $service, array $headers, string $testName): array
    {
        $this->snapshot("Before {$testName}");
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $result = $service->deduplicateHeaders($headers);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $this->snapshot("After {$testName}");
        
        $metrics = [
            'test_name' => $testName,
            'input_headers' => count($headers),
            'output_headers' => count($result),
            'duration' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'memory_peak' => memory_get_peak_usage(true),
            'headers_per_second' => count($headers) / ($endTime - $startTime),
            'memory_per_header' => ($endMemory - $startMemory) / count($headers),
        ];
        
        $this->performanceMetrics[] = $metrics;
        
        return $metrics;
    }

    /**
     * 监控头部合并操作
     */
    public function monitorMerging(HeaderDeduplicationService $service, array $primary, array $secondary, string $testName): array
    {
        $this->snapshot("Before merge {$testName}");
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $result = $service->mergeHeaders($primary, $secondary);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $this->snapshot("After merge {$testName}");
        
        $metrics = [
            'test_name' => $testName,
            'primary_headers' => count($primary),
            'secondary_headers' => count($secondary),
            'output_headers' => count($result),
            'duration' => $endTime - $startTime,
            'memory_used' => $endMemory - $startMemory,
            'memory_peak' => memory_get_peak_usage(true),
            'total_headers_per_second' => (count($primary) + count($secondary)) / ($endTime - $startTime),
            'memory_per_header' => ($endMemory - $startMemory) / (count($primary) + count($secondary)),
        ];
        
        $this->performanceMetrics[] = $metrics;
        
        return $metrics;
    }

    /**
     * 运行内存泄漏检测
     */
    public function detectMemoryLeaks(HeaderDeduplicationService $service, array $headers, int $iterations = 1000): array
    {
        $this->snapshot("Memory leak test start");
        
        $memoryReadings = [];
        $timeReadings = [];
        $initialMemory = memory_get_usage(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $iterationStart = microtime(true);
            
            $service->deduplicateHeaders($headers);
            
            $iterationEnd = microtime(true);
            $currentMemory = memory_get_usage(true);
            
            // 每10次迭代记录一次
            if ($i % 10 === 0) {
                $memoryReadings[] = $currentMemory;
                $timeReadings[] = $iterationEnd - $iterationStart;
            }
        }
        
        $this->snapshot("Memory leak test end");
        
        // 分析内存增长趋势
        $memoryGrowth = end($memoryReadings) - $memoryReadings[0];
        $avgMemoryGrowthPerIteration = $memoryGrowth / $iterations;
        
        // 分析性能趋势
        $avgTimeFirst10 = array_sum(array_slice($timeReadings, 0, 10)) / 10;
        $avgTimeLast10 = array_sum(array_slice($timeReadings, -10)) / 10;
        $performanceDegradation = $avgTimeLast10 - $avgTimeFirst10;
        
        return [
            'iterations' => $iterations,
            'total_memory_growth' => $memoryGrowth,
            'avg_memory_growth_per_iteration' => $avgMemoryGrowthPerIteration,
            'memory_growth_mb' => $memoryGrowth / 1024 / 1024,
            'performance_degradation' => $performanceDegradation,
            'avg_time_first_10' => $avgTimeFirst10,
            'avg_time_last_10' => $avgTimeLast10,
            'memory_readings' => $memoryReadings,
            'time_readings' => $timeReadings,
            'leak_detected' => abs($avgMemoryGrowthPerIteration) > 1024, // 1KB per iteration threshold
        ];
    }

    /**
     * 运行并发内存监控
     */
    public function monitorConcurrentProcessing(HeaderDeduplicationService $service, array $headerSets, string $testName): array
    {
        $this->snapshot("Concurrent test start: {$testName}");
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $peakMemory = $startMemory;
        
        $results = [];
        $memorySnapshots = [];
        
        foreach ($headerSets as $index => $headers) {
            $iterationStart = microtime(true);
            $iterationStartMemory = memory_get_usage(true);
            
            $result = $service->deduplicateHeaders($headers);
            $results[] = $result;
            
            $iterationEnd = microtime(true);
            $iterationEndMemory = memory_get_usage(true);
            
            // 跟踪峰值内存
            if ($iterationEndMemory > $peakMemory) {
                $peakMemory = $iterationEndMemory;
            }
            
            // 每50次迭代记录内存快照
            if ($index % 50 === 0) {
                $memorySnapshots[] = [
                    'iteration' => $index,
                    'memory' => $iterationEndMemory,
                    'duration' => $iterationEnd - $iterationStart,
                ];
            }
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $this->snapshot("Concurrent test end: {$testName}");
        
        return [
            'test_name' => $testName,
            'total_requests' => count($headerSets),
            'total_duration' => $endTime - $startTime,
            'total_memory_used' => $endMemory - $startMemory,
            'peak_memory_used' => $peakMemory - $startMemory,
            'requests_per_second' => count($headerSets) / ($endTime - $startTime),
            'avg_memory_per_request' => ($endMemory - $startMemory) / count($headerSets),
            'memory_snapshots' => $memorySnapshots,
            'memory_efficiency' => ($peakMemory - $startMemory) / ($endMemory - $startMemory), // 峰值/最终内存比率
        ];
    }

    /**
     * 获取内存快照
     */
    public function getSnapshots(): array
    {
        return $this->memorySnapshots;
    }

    /**
     * 获取性能指标
     */
    public function getMetrics(): array
    {
        return $this->performanceMetrics;
    }

    /**
     * 生成内存使用报告
     */
    public function generateReport(): string
    {
        $report = "=== Header Deduplication Memory Report ===\n\n";
        
        // 快照摘要
        if (!empty($this->memorySnapshots)) {
            $report .= "Memory Snapshots:\n";
            foreach ($this->memorySnapshots as $snapshot) {
                $report .= sprintf(
                    "  %-30s: %8.2f MB (Peak: %8.2f MB) at %.3fs\n",
                    $snapshot['label'],
                    $snapshot['memory_usage'] / 1024 / 1024,
                    $snapshot['memory_peak'] / 1024 / 1024,
                    $snapshot['time_elapsed']
                );
            }
            $report .= "\n";
        }
        
        // 性能指标摘要
        if (!empty($this->performanceMetrics)) {
            $report .= "Performance Metrics:\n";
            foreach ($this->performanceMetrics as $metric) {
                $report .= sprintf(
                    "  %-30s: %6d headers in %.4fs (%.0f h/s, %.2f KB/header)\n",
                    $metric['test_name'],
                    $metric['input_headers'] ?? ($metric['primary_headers'] + $metric['secondary_headers']),
                    $metric['duration'],
                    $metric['headers_per_second'] ?? $metric['total_headers_per_second'],
                    $metric['memory_per_header'] / 1024
                );
            }
            $report .= "\n";
        }
        
        // 总体统计
        if (!empty($this->performanceMetrics)) {
            $totalHeaders = array_sum(array_map(function($m) {
                return $m['input_headers'] ?? ($m['primary_headers'] + $m['secondary_headers']);
            }, $this->performanceMetrics));
            
            $totalDuration = array_sum(array_column($this->performanceMetrics, 'duration'));
            $totalMemory = array_sum(array_column($this->performanceMetrics, 'memory_used'));
            
            $report .= "Overall Statistics:\n";
            $report .= sprintf("  Total Headers Processed: %d\n", $totalHeaders);
            $report .= sprintf("  Total Processing Time: %.4fs\n", $totalDuration);
            $report .= sprintf("  Total Memory Used: %.2f MB\n", $totalMemory / 1024 / 1024);
            $report .= sprintf("  Average Headers/Second: %.0f\n", $totalHeaders / $totalDuration);
            $report .= sprintf("  Average Memory/Header: %.2f KB\n", ($totalMemory / $totalHeaders) / 1024);
        }
        
        return $report;
    }

    /**
     * 导出详细数据为JSON
     */
    public function exportData(): array
    {
        return [
            'snapshots' => $this->memorySnapshots,
            'metrics' => $this->performanceMetrics,
            'summary' => [
                'start_time' => $this->startTime,
                'start_memory' => $this->startMemory,
                'current_memory' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'elapsed_time' => microtime(true) - $this->startTime,
            ]
        ];
    }

    /**
     * 保存报告到文件
     */
    public function saveReport(string $filename): void
    {
        $report = $this->generateReport();
        file_put_contents($filename, $report);
    }

    /**
     * 保存详细数据到JSON文件
     */
    public function saveData(string $filename): void
    {
        $data = $this->exportData();
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * 创建内存使用图表数据（用于可视化）
     */
    public function getChartData(): array
    {
        $chartData = [
            'labels' => [],
            'memory_usage' => [],
            'memory_peak' => [],
            'time_elapsed' => [],
        ];
        
        foreach ($this->memorySnapshots as $snapshot) {
            $chartData['labels'][] = $snapshot['label'];
            $chartData['memory_usage'][] = $snapshot['memory_usage'] / 1024 / 1024; // MB
            $chartData['memory_peak'][] = $snapshot['memory_peak'] / 1024 / 1024; // MB
            $chartData['time_elapsed'][] = $snapshot['time_elapsed'];
        }
        
        return $chartData;
    }

    /**
     * 分析内存使用模式
     */
    public function analyzeMemoryPatterns(): array
    {
        if (empty($this->memorySnapshots)) {
            return ['error' => 'No memory snapshots available'];
        }
        
        $memoryUsages = array_column($this->memorySnapshots, 'memory_usage');
        $timeElapsed = array_column($this->memorySnapshots, 'time_elapsed');
        
        $analysis = [
            'min_memory' => min($memoryUsages),
            'max_memory' => max($memoryUsages),
            'avg_memory' => array_sum($memoryUsages) / count($memoryUsages),
            'memory_variance' => $this->calculateVariance($memoryUsages),
            'memory_growth_rate' => 0,
            'memory_stability' => 'stable',
        ];
        
        // 计算内存增长率
        if (count($memoryUsages) > 1) {
            $firstMemory = $memoryUsages[0];
            $lastMemory = end($memoryUsages);
            $firstTime = $timeElapsed[0];
            $lastTime = end($timeElapsed);
            
            if ($lastTime > $firstTime) {
                $analysis['memory_growth_rate'] = ($lastMemory - $firstMemory) / ($lastTime - $firstTime);
            }
        }
        
        // 判断内存稳定性
        $memoryRange = $analysis['max_memory'] - $analysis['min_memory'];
        $memoryStdDev = sqrt($analysis['memory_variance']);
        
        if ($memoryStdDev / $analysis['avg_memory'] > 0.1) {
            $analysis['memory_stability'] = 'unstable';
        } elseif ($memoryStdDev / $analysis['avg_memory'] > 0.05) {
            $analysis['memory_stability'] = 'moderate';
        }
        
        return $analysis;
    }

    /**
     * 计算方差
     */
    private function calculateVariance(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(function($value) use ($mean) {
            return pow($value - $mean, 2);
        }, $values);
        
        return array_sum($squaredDiffs) / count($squaredDiffs);
    }
}