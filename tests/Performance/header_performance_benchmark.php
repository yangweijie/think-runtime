<?php

declare(strict_types=1);

/**
 * 头部去重性能基准测试脚本
 * 独立运行的性能测试工具，用于评估头部去重服务的性能表现
 * 
 * 使用方法:
 * php tests/Performance/header_performance_benchmark.php
 * php tests/Performance/header_performance_benchmark.php --scenario=stress
 * php tests/Performance/header_performance_benchmark.php --headers=1000 --iterations=100
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use yangweijie\thinkRuntime\Tests\Performance\HeaderMemoryMonitor;
use Psr\Log\NullLogger;

class HeaderPerformanceBenchmark
{
    private HeaderDeduplicationService $service;
    private HeaderMemoryMonitor $monitor;
    private array $results = [];

    public function __construct()
    {
        $this->service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_performance_logging' => true,
            'debug_logging' => false,
        ]);
        $this->monitor = new HeaderMemoryMonitor();
    }

    /**
     * 运行所有基准测试
     */
    public function runAllBenchmarks(): void
    {
        echo "=== Header Deduplication Performance Benchmark ===\n\n";
        echo "Starting comprehensive performance tests...\n\n";

        $this->runBasicPerformanceTest();
        $this->runScalabilityTest();
        $this->runMemoryEfficiencyTest();
        $this->runConcurrencyTest();
        $this->runStressTest();
        $this->runOptimizationTest();

        $this->printSummary();
    }

    /**
     * 基础性能测试
     */
    public function runBasicPerformanceTest(): void
    {
        echo "1. Basic Performance Test\n";
        echo str_repeat("-", 50) . "\n";

        $headerSizes = [10, 50, 100, 500, 1000];
        $iterations = 1000;

        foreach ($headerSizes as $size) {
            $headers = $this->generateTestHeaders($size);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            for ($i = 0; $i < $iterations; $i++) {
                $this->service->deduplicateHeaders($headers);
            }
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $headersPerSecond = ($size * $iterations) / $duration;
            $avgTimePerOperation = $duration / $iterations;
            
            printf("  %4d headers: %8.0f h/s, %6.3fms avg, %5.2fMB memory\n",
                $size, $headersPerSecond, $avgTimePerOperation * 1000, $memoryUsed / 1024 / 1024);
            
            $this->results['basic'][$size] = [
                'headers_per_second' => $headersPerSecond,
                'avg_time_ms' => $avgTimePerOperation * 1000,
                'memory_mb' => $memoryUsed / 1024 / 1024,
            ];
        }
        echo "\n";
    }

    /**
     * 可扩展性测试
     */
    public function runScalabilityTest(): void
    {
        echo "2. Scalability Test\n";
        echo str_repeat("-", 50) . "\n";

        $headerCounts = [1000, 2000, 5000, 10000, 20000];
        
        foreach ($headerCounts as $count) {
            $headers = $this->generateTestHeaders($count);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $result = $this->service->deduplicateHeaders($headers);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $headersPerSecond = $count / $duration;
            
            printf("  %5d headers: %8.0f h/s, %6.3fs total, %6.2fMB memory\n",
                $count, $headersPerSecond, $duration, $memoryUsed / 1024 / 1024);
            
            $this->results['scalability'][$count] = [
                'headers_per_second' => $headersPerSecond,
                'total_time' => $duration,
                'memory_mb' => $memoryUsed / 1024 / 1024,
            ];
        }
        echo "\n";
    }

    /**
     * 内存效率测试
     */
    public function runMemoryEfficiencyTest(): void
    {
        echo "3. Memory Efficiency Test\n";
        echo str_repeat("-", 50) . "\n";

        $headers = $this->generateTestHeaders(1000);
        
        // 内存泄漏检测
        $leakResults = $this->monitor->detectMemoryLeaks($this->service, $headers, 1000);
        
        printf("  Memory leak test: %s\n", $leakResults['leak_detected'] ? 'LEAK DETECTED' : 'PASSED');
        printf("  Total memory growth: %.2f MB\n", $leakResults['memory_growth_mb']);
        printf("  Avg growth per iteration: %.2f KB\n", $leakResults['avg_memory_growth_per_iteration'] / 1024);
        printf("  Performance degradation: %.6f seconds\n", $leakResults['performance_degradation']);
        
        $this->results['memory_efficiency'] = $leakResults;
        echo "\n";
    }

    /**
     * 并发测试
     */
    public function runConcurrencyTest(): void
    {
        echo "4. Concurrency Simulation Test\n";
        echo str_repeat("-", 50) . "\n";

        $concurrentRequests = [50, 100, 200, 500, 1000];
        
        foreach ($concurrentRequests as $requestCount) {
            $headerSets = [];
            for ($i = 0; $i < $requestCount; $i++) {
                $headerSets[] = $this->generateTestHeaders(25, $i);
            }
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $results = [];
            foreach ($headerSets as $headers) {
                $results[] = $this->service->deduplicateHeaders($headers);
            }
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $requestsPerSecond = $requestCount / $duration;
            
            printf("  %4d requests: %8.0f req/s, %6.3fs total, %5.2fMB memory\n",
                $requestCount, $requestsPerSecond, $duration, $memoryUsed / 1024 / 1024);
            
            $this->results['concurrency'][$requestCount] = [
                'requests_per_second' => $requestsPerSecond,
                'total_time' => $duration,
                'memory_mb' => $memoryUsed / 1024 / 1024,
            ];
        }
        echo "\n";
    }

    /**
     * 压力测试
     */
    public function runStressTest(): void
    {
        echo "5. Stress Test\n";
        echo str_repeat("-", 50) . "\n";

        $stressScenarios = [
            'many_small' => ['count' => 10000, 'value_size' => 10],
            'few_large' => ['count' => 100, 'value_size' => 8000],
            'high_conflict' => ['count' => 1000, 'conflicts' => true],
        ];

        foreach ($stressScenarios as $scenarioName => $params) {
            if ($scenarioName === 'high_conflict') {
                $headers = $this->generateConflictingHeaders($params['count']);
            } else {
                $headers = $this->generateVariableSizeHeaders($params['count'], $params['value_size']);
            }
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $result = $this->service->deduplicateHeaders($headers);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            
            printf("  %-12s: %6.3fs, %6.2fMB memory, %d->%d headers\n",
                $scenarioName, $duration, $memoryUsed / 1024 / 1024, count($headers), count($result));
            
            $this->results['stress'][$scenarioName] = [
                'duration' => $duration,
                'memory_mb' => $memoryUsed / 1024 / 1024,
                'input_headers' => count($headers),
                'output_headers' => count($result),
            ];
        }
        echo "\n";
    }

    /**
     * 优化测试
     */
    public function runOptimizationTest(): void
    {
        echo "6. Optimization Test\n";
        echo str_repeat("-", 50) . "\n";

        $headers = $this->generateTestHeaders(500);
        
        // 测试不同配置的性能影响
        $configs = [
            'minimal' => ['debug_logging' => false, 'enable_performance_logging' => false],
            'debug' => ['debug_logging' => true, 'enable_performance_logging' => false],
            'full' => ['debug_logging' => true, 'enable_performance_logging' => true, 'strict_mode' => true],
        ];
        
        $iterations = 200;
        
        foreach ($configs as $configName => $config) {
            $service = new HeaderDeduplicationService(new NullLogger(), $config);
            
            $startTime = microtime(true);
            
            for ($i = 0; $i < $iterations; $i++) {
                $service->deduplicateHeaders($headers);
            }
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            $avgTime = $duration / $iterations;
            
            printf("  %-8s config: %6.3fms avg, %6.3fs total\n",
                $configName, $avgTime * 1000, $duration);
            
            $this->results['optimization'][$configName] = [
                'avg_time_ms' => $avgTime * 1000,
                'total_time' => $duration,
            ];
        }
        echo "\n";
    }

    /**
     * 打印测试摘要
     */
    public function printSummary(): void
    {
        echo "=== Performance Summary ===\n";
        
        // 基础性能摘要
        if (isset($this->results['basic'])) {
            $basicResults = $this->results['basic'];
            $avgHeadersPerSecond = array_sum(array_column($basicResults, 'headers_per_second')) / count($basicResults);
            printf("Average processing rate: %.0f headers/second\n", $avgHeadersPerSecond);
        }
        
        // 可扩展性摘要
        if (isset($this->results['scalability'])) {
            $scalabilityResults = $this->results['scalability'];
            $maxHeaders = max(array_keys($scalabilityResults));
            $maxHeadersPerSecond = $scalabilityResults[$maxHeaders]['headers_per_second'];
            printf("Max scale tested: %d headers at %.0f h/s\n", $maxHeaders, $maxHeadersPerSecond);
        }
        
        // 内存效率摘要
        if (isset($this->results['memory_efficiency'])) {
            $memoryResults = $this->results['memory_efficiency'];
            printf("Memory leak status: %s\n", $memoryResults['leak_detected'] ? 'DETECTED' : 'CLEAN');
        }
        
        // 并发性能摘要
        if (isset($this->results['concurrency'])) {
            $concurrencyResults = $this->results['concurrency'];
            $maxRequests = max(array_keys($concurrencyResults));
            $maxRequestsPerSecond = $concurrencyResults[$maxRequests]['requests_per_second'];
            printf("Max concurrency: %d requests at %.0f req/s\n", $maxRequests, $maxRequestsPerSecond);
        }
        
        echo "\n";
        
        // 性能评级
        $this->printPerformanceRating();
    }

    /**
     * 打印性能评级
     */
    private function printPerformanceRating(): void
    {
        echo "=== Performance Rating ===\n";
        
        $rating = 'A'; // 默认评级
        $issues = [];
        
        // 检查基础性能
        if (isset($this->results['basic'])) {
            $avgHeadersPerSecond = array_sum(array_column($this->results['basic'], 'headers_per_second')) / count($this->results['basic']);
            if ($avgHeadersPerSecond < 10000) {
                $rating = 'B';
                $issues[] = 'Basic performance below 10k headers/second';
            }
            if ($avgHeadersPerSecond < 5000) {
                $rating = 'C';
                $issues[] = 'Basic performance below 5k headers/second';
            }
        }
        
        // 检查内存泄漏
        if (isset($this->results['memory_efficiency']) && $this->results['memory_efficiency']['leak_detected']) {
            $rating = 'D';
            $issues[] = 'Memory leak detected';
        }
        
        // 检查可扩展性
        if (isset($this->results['scalability'])) {
            $scalabilityResults = $this->results['scalability'];
            $largestTest = max(array_keys($scalabilityResults));
            if ($largestTest >= 10000 && $scalabilityResults[$largestTest]['headers_per_second'] < 1000) {
                $rating = 'C';
                $issues[] = 'Poor scalability for large header sets';
            }
        }
        
        printf("Overall Performance Rating: %s\n", $rating);
        
        if (!empty($issues)) {
            echo "Issues identified:\n";
            foreach ($issues as $issue) {
                echo "  - {$issue}\n";
            }
        } else {
            echo "No performance issues identified.\n";
        }
        
        echo "\n";
    }

    /**
     * 生成测试头部
     */
    private function generateTestHeaders(int $count, int $seed = 0): array
    {
        $headers = [];
        $commonHeaders = [
            'Content-Type', 'Accept', 'Cache-Control', 'Authorization',
            'User-Agent', 'Host', 'Connection', 'Accept-Encoding'
        ];
        
        for ($i = 0; $i < $count; $i++) {
            if ($i < count($commonHeaders)) {
                $name = $commonHeaders[$i] . ($seed > 0 ? "-{$seed}" : '');
            } else {
                $name = "Custom-Header-{$seed}-{$i}";
            }
            
            $headers[$name] = "value-{$seed}-{$i}";
        }
        
        return $headers;
    }

    /**
     * 生成可变大小的头部
     */
    private function generateVariableSizeHeaders(int $count, int $valueSize): array
    {
        $headers = [];
        
        for ($i = 0; $i < $count; $i++) {
            $name = "Header-{$i}";
            $value = str_repeat("x", $valueSize) . "-{$i}";
            $headers[$name] = $value;
        }
        
        return $headers;
    }

    /**
     * 生成冲突头部
     */
    private function generateConflictingHeaders(int $count): array
    {
        $headers = [];
        $baseHeaders = ['Content-Type', 'Accept', 'Cache-Control'];
        
        for ($i = 0; $i < $count; $i++) {
            $baseHeader = $baseHeaders[$i % count($baseHeaders)];
            $headers["{$baseHeader}-{$i}"] = "value-{$i}";
            
            // 添加冲突头部
            if ($i % 5 === 0) {
                $headers[strtolower($baseHeader) . "-{$i}"] = "lower-value-{$i}";
                $headers[strtoupper($baseHeader) . "-{$i}"] = "UPPER-VALUE-{$i}";
            }
        }
        
        return $headers;
    }

    /**
     * 保存结果到文件
     */
    public function saveResults(string $filename): void
    {
        $data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'results' => $this->results,
        ];
        
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        echo "Results saved to: {$filename}\n";
    }
}

// 命令行参数处理
$options = getopt('', ['scenario:', 'headers:', 'iterations:', 'save:', 'help']);

if (isset($options['help'])) {
    echo "Header Deduplication Performance Benchmark\n\n";
    echo "Usage: php header_performance_benchmark.php [options]\n\n";
    echo "Options:\n";
    echo "  --scenario=<name>    Run specific scenario (basic|scalability|memory|concurrency|stress|optimization)\n";
    echo "  --headers=<count>    Number of headers for custom test\n";
    echo "  --iterations=<count> Number of iterations for custom test\n";
    echo "  --save=<filename>    Save results to JSON file\n";
    echo "  --help               Show this help message\n";
    exit(0);
}

// 运行基准测试
$benchmark = new HeaderPerformanceBenchmark();

if (isset($options['scenario'])) {
    $scenario = $options['scenario'];
    switch ($scenario) {
        case 'basic':
            $benchmark->runBasicPerformanceTest();
            break;
        case 'scalability':
            $benchmark->runScalabilityTest();
            break;
        case 'memory':
            $benchmark->runMemoryEfficiencyTest();
            break;
        case 'concurrency':
            $benchmark->runConcurrencyTest();
            break;
        case 'stress':
            $benchmark->runStressTest();
            break;
        case 'optimization':
            $benchmark->runOptimizationTest();
            break;
        default:
            echo "Unknown scenario: {$scenario}\n";
            exit(1);
    }
} else {
    $benchmark->runAllBenchmarks();
}

// 保存结果
if (isset($options['save'])) {
    $benchmark->saveResults($options['save']);
}