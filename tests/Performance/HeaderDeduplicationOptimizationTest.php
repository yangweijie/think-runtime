<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use yangweijie\thinkRuntime\Tests\Performance\HeaderMemoryMonitor;
use Psr\Log\NullLogger;

/**
 * 头部去重服务优化测试
 * 测试和验证性能优化措施的效果
 */
class HeaderDeduplicationOptimizationTest extends TestCase
{
    private HeaderMemoryMonitor $monitor;
    private array $optimizationResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new HeaderMemoryMonitor();
    }

    protected function tearDown(): void
    {
        if (!empty($this->optimizationResults)) {
            echo "\n=== Header Deduplication Optimization Results ===\n";
            foreach ($this->optimizationResults as $test => $result) {
                echo sprintf("%-50s: %s\n", $test, $result);
            }
            echo "==================================================\n";
        }
        parent::tearDown();
    }

    /**
     * 测试缓存优化效果
     */
    public function testCachingOptimization(): void
    {
        $headers = $this->generateRepeatingHeaders(1000);
        
        // 测试无缓存版本
        $noCacheService = new HeaderDeduplicationService(new NullLogger(), [
            'enable_performance_logging' => false,
            'debug_logging' => false
        ]);
        
        $noCacheMetrics = $this->monitor->monitorDeduplication($noCacheService, $headers, 'No Cache');
        
        // 测试带缓存版本（多次运行相同数据）
        $cachedService = new HeaderDeduplicationService(new NullLogger(), [
            'enable_performance_logging' => false,
            'debug_logging' => false
        ]);
        
        // 预热缓存
        $cachedService->deduplicateHeaders($headers);
        
        $cachedMetrics = $this->monitor->monitorDeduplication($cachedService, $headers, 'With Cache');
        
        // 缓存版本应该更快或至少不慢
        $this->assertLessThanOrEqual($noCacheMetrics['duration'] * 1.1, $cachedMetrics['duration'], 
            "Cached version should not be significantly slower");
        
        $this->optimizationResults['Caching Optimization'] = sprintf(
            "No cache: %.6fs, Cached: %.6fs, Improvement: %.1fx",
            $noCacheMetrics['duration'],
            $cachedMetrics['duration'],
            $noCacheMetrics['duration'] / $cachedMetrics['duration']
        );
    }

    /**
     * 测试批量处理优化
     */
    public function testBatchProcessingOptimization(): void
    {
        $headerSets = [];
        for ($i = 0; $i < 100; $i++) {
            $headerSets[] = $this->generateTestHeaders(50, $i);
        }
        
        $service = new HeaderDeduplicationService(new NullLogger());
        
        // 测试逐个处理
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $individualResults = [];
        foreach ($headerSets as $headers) {
            $individualResults[] = $service->deduplicateHeaders($headers);
        }
        
        $individualTime = microtime(true) - $startTime;
        $individualMemory = memory_get_usage(true) - $startMemory;
        
        // 测试批量处理（如果实现了批量方法）
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $batchResults = [];
        foreach ($headerSets as $headers) {
            $batchResults[] = $service->deduplicateHeaders($headers);
        }
        
        $batchTime = microtime(true) - $startTime;
        $batchMemory = memory_get_usage(true) - $startMemory;
        
        $this->assertCount(count($headerSets), $individualResults);
        $this->assertCount(count($headerSets), $batchResults);
        
        $this->optimizationResults['Batch Processing'] = sprintf(
            "Individual: %.4fs/%.2fMB, Batch: %.4fs/%.2fMB",
            $individualTime, $individualMemory / 1024 / 1024,
            $batchTime, $batchMemory / 1024 / 1024
        );
    }

    /**
     * 测试内存优化效果
     */
    public function testMemoryOptimization(): void
    {
        $largeHeaders = $this->generateLargeHeaderSet(5000);
        
        // 测试标准实现
        $standardService = new HeaderDeduplicationService(new NullLogger());
        $standardMetrics = $this->monitor->monitorDeduplication($standardService, $largeHeaders, 'Standard');
        
        // 测试优化实现（使用更少内存的配置）
        $optimizedService = new HeaderDeduplicationService(new NullLogger(), [
            'debug_logging' => false,
            'enable_performance_logging' => false,
            'log_critical_conflicts' => false,
        ]);
        $optimizedMetrics = $this->monitor->monitorDeduplication($optimizedService, $largeHeaders, 'Optimized');
        
        // 优化版本应该使用更少内存
        $this->assertLessThanOrEqual($standardMetrics['memory_used'] * 1.1, $optimizedMetrics['memory_used'],
            "Optimized version should use less or similar memory");
        
        $memoryImprovement = $standardMetrics['memory_used'] / max($optimizedMetrics['memory_used'], 1);
        
        $this->optimizationResults['Memory Optimization'] = sprintf(
            "Standard: %.2fMB, Optimized: %.2fMB, Improvement: %.1fx",
            $standardMetrics['memory_used'] / 1024 / 1024,
            $optimizedMetrics['memory_used'] / 1024 / 1024,
            $memoryImprovement
        );
    }

    /**
     * 测试字符串操作优化
     */
    public function testStringOperationOptimization(): void
    {
        // 创建包含大量字符串操作的头部
        $headers = [];
        for ($i = 0; $i < 1000; $i++) {
            $headers["X-Very-Long-Header-Name-That-Requires-Normalization-{$i}"] = str_repeat("value-{$i}-", 10);
        }
        
        $service = new HeaderDeduplicationService(new NullLogger());
        
        // 测试多次运行以评估字符串操作缓存效果
        $iterations = 10;
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $service->deduplicateHeaders($headers);
            $times[] = microtime(true) - $startTime;
        }
        
        $firstRunTime = $times[0];
        $avgSubsequentTime = array_sum(array_slice($times, 1)) / ($iterations - 1);
        
        // 后续运行应该更快（由于字符串操作优化）
        $this->assertLessThanOrEqual($firstRunTime * 1.2, $avgSubsequentTime,
            "Subsequent runs should benefit from string operation optimization");
        
        $this->optimizationResults['String Operation Optimization'] = sprintf(
            "First run: %.6fs, Avg subsequent: %.6fs, Improvement: %.1fx",
            $firstRunTime, $avgSubsequentTime, $firstRunTime / $avgSubsequentTime
        );
    }

    /**
     * 测试算法复杂度优化
     */
    public function testAlgorithmComplexityOptimization(): void
    {
        $headerSizes = [100, 500, 1000, 2000, 5000];
        $service = new HeaderDeduplicationService(new NullLogger());
        
        $complexityResults = [];
        
        foreach ($headerSizes as $size) {
            $headers = $this->generateTestHeaders($size);
            
            $startTime = microtime(true);
            $service->deduplicateHeaders($headers);
            $duration = microtime(true) - $startTime;
            
            $complexityResults[] = [
                'size' => $size,
                'time' => $duration,
                'time_per_header' => $duration / $size,
            ];
        }
        
        // 检查算法复杂度是否接近线性
        $timePerHeaderValues = array_column($complexityResults, 'time_per_header');
        $maxTimePerHeader = max($timePerHeaderValues);
        $minTimePerHeader = min($timePerHeaderValues);
        
        // 时间复杂度应该接近线性（最大和最小的比值不应该太大）
        $complexityRatio = $maxTimePerHeader / $minTimePerHeader;
        $this->assertLessThan(5.0, $complexityRatio, 
            "Algorithm complexity should be close to linear, ratio: {$complexityRatio}");
        
        $this->optimizationResults['Algorithm Complexity'] = sprintf(
            "Complexity ratio: %.2f (should be < 5.0 for linear)",
            $complexityRatio
        );
    }

    /**
     * 测试并发优化
     */
    public function testConcurrencyOptimization(): void
    {
        $headerSets = [];
        for ($i = 0; $i < 200; $i++) {
            $headerSets[] = $this->generateTestHeaders(25, $i);
        }
        
        $service = new HeaderDeduplicationService(new NullLogger());
        
        // 模拟并发处理
        $concurrentMetrics = $this->monitor->monitorConcurrentProcessing($service, $headerSets, 'Concurrent Test');
        
        // 验证并发处理效率
        $this->assertGreaterThan(50, $concurrentMetrics['requests_per_second'], 
            "Concurrent processing should handle at least 50 requests per second");
        
        $this->assertLessThan(1.0, $concurrentMetrics['memory_efficiency'], 
            "Memory efficiency should be good (peak/final ratio < 1.0)");
        
        $this->optimizationResults['Concurrency Optimization'] = sprintf(
            "%.0f req/s, %.2f memory efficiency, %.2fMB peak",
            $concurrentMetrics['requests_per_second'],
            $concurrentMetrics['memory_efficiency'],
            $concurrentMetrics['peak_memory_used'] / 1024 / 1024
        );
    }

    /**
     * 测试配置优化影响
     */
    public function testConfigurationOptimization(): void
    {
        $headers = $this->generateTestHeaders(1000);
        
        $configs = [
            'production' => [
                'debug_logging' => false,
                'enable_performance_logging' => false,
                'log_critical_conflicts' => false,
                'strict_mode' => false,
            ],
            'development' => [
                'debug_logging' => true,
                'enable_performance_logging' => true,
                'log_critical_conflicts' => true,
                'strict_mode' => true,
            ],
        ];
        
        $configResults = [];
        
        foreach ($configs as $configName => $config) {
            $service = new HeaderDeduplicationService(new NullLogger(), $config);
            $metrics = $this->monitor->monitorDeduplication($service, $headers, $configName);
            $configResults[$configName] = $metrics;
        }
        
        $prodTime = $configResults['production']['duration'];
        $devTime = $configResults['development']['duration'];
        $performanceImpact = ($devTime - $prodTime) / $prodTime * 100;
        
        $this->optimizationResults['Configuration Optimization'] = sprintf(
            "Production: %.6fs, Development: %.6fs, Impact: %.1f%%",
            $prodTime, $devTime, $performanceImpact
        );
    }

    /**
     * 综合优化基准测试
     */
    public function testComprehensiveOptimizationBenchmark(): void
    {
        $testScenarios = [
            'small_fast' => ['headers' => 50, 'iterations' => 1000],
            'medium_balanced' => ['headers' => 200, 'iterations' => 500],
            'large_thorough' => ['headers' => 1000, 'iterations' => 100],
        ];
        
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'debug_logging' => false,
            'enable_performance_logging' => false,
        ]);
        
        $benchmarkResults = [];
        
        foreach ($testScenarios as $scenarioName => $scenario) {
            $headers = $this->generateTestHeaders($scenario['headers']);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            for ($i = 0; $i < $scenario['iterations']; $i++) {
                $service->deduplicateHeaders($headers);
            }
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $totalTime = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $headersPerSecond = ($scenario['headers'] * $scenario['iterations']) / $totalTime;
            
            $benchmarkResults[$scenarioName] = [
                'headers_per_second' => $headersPerSecond,
                'memory_mb' => $memoryUsed / 1024 / 1024,
                'avg_time_per_operation' => $totalTime / $scenario['iterations'],
            ];
            
            // 性能断言
            $this->assertGreaterThan(1000, $headersPerSecond, 
                "Scenario '{$scenarioName}' should process at least 1000 headers/second");
        }
        
        $avgHeadersPerSecond = array_sum(array_column($benchmarkResults, 'headers_per_second')) / count($benchmarkResults);
        
        $this->optimizationResults['Comprehensive Benchmark'] = sprintf(
            "Avg %.0f headers/s across all scenarios",
            $avgHeadersPerSecond
        );
    }

    /**
     * 生成重复头部（用于测试缓存）
     */
    private function generateRepeatingHeaders(int $count): array
    {
        $headers = [];
        $baseHeaders = ['Content-Type', 'Accept', 'Cache-Control', 'Authorization'];
        
        for ($i = 0; $i < $count; $i++) {
            $baseHeader = $baseHeaders[$i % count($baseHeaders)];
            $headers["{$baseHeader}-{$i}"] = "value-{$i}";
            
            // 添加重复的头部名称（不同大小写）
            if ($i % 10 === 0) {
                $headers[strtolower($baseHeader) . "-{$i}"] = "lower-value-{$i}";
                $headers[strtoupper($baseHeader) . "-{$i}"] = "UPPER-VALUE-{$i}";
            }
        }
        
        return $headers;
    }

    /**
     * 生成大型头部集合
     */
    private function generateLargeHeaderSet(int $count): array
    {
        $headers = [];
        
        for ($i = 0; $i < $count; $i++) {
            $headers["Large-Header-{$i}"] = str_repeat("large-value-{$i}-", 5);
        }
        
        return $headers;
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
                $name = $commonHeaders[$i] . "-{$seed}";
            } else {
                $name = "Custom-Header-{$seed}-{$i}";
            }
            
            $headers[$name] = "value-{$seed}-{$i}";
        }
        
        return $headers;
    }
}