<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface;
use Psr\Log\NullLogger;

/**
 * 头部去重服务性能测试
 * 测试头部去重功能的性能表现和内存使用
 */
class HeaderDeduplicationPerformanceTest extends TestCase
{
    private HeaderDeduplicationService $service;
    private array $performanceResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_performance_logging' => true,
            'debug_logging' => false
        ]);
    }

    protected function tearDown(): void
    {
        // 输出性能结果摘要
        if (!empty($this->performanceResults)) {
            echo "\n=== Header Deduplication Performance Results ===\n";
            foreach ($this->performanceResults as $test => $result) {
                echo sprintf("%-40s: %s\n", $test, $result);
            }
            echo "================================================\n";
        }
        parent::tearDown();
    }

    /**
     * 测试基本头部去重性能
     */
    public function testBasicDeduplicationPerformance(): void
    {
        $headers = $this->generateTestHeaders(100);
        $iterations = 1000;

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $result = $this->service->deduplicateHeaders($headers);
            $this->assertIsArray($result);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgTime = $duration / $iterations;

        // 性能断言
        $this->assertLessThan(2.0, $duration, "Basic deduplication took too long: {$duration}s for {$iterations} iterations");
        $this->assertLessThan(0.002, $avgTime, "Average deduplication time too slow: {$avgTime}s");

        // 内存使用断言 (不超过10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, "Memory usage too high: " . ($memoryUsed / 1024 / 1024) . "MB");

        $this->performanceResults['Basic Deduplication'] = sprintf(
            "%.4fs total, %.6fs avg, %.2fMB memory",
            $duration, $avgTime, $memoryUsed / 1024 / 1024
        );
    }

    /**
     * 测试大量头部去重性能
     */
    public function testLargeHeaderSetPerformance(): void
    {
        $headerSizes = [500, 1000, 2000, 5000];
        $iterations = 100;

        foreach ($headerSizes as $size) {
            $headers = $this->generateTestHeaders($size);

            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            for ($i = 0; $i < $iterations; $i++) {
                $result = $this->service->deduplicateHeaders($headers);
                $this->assertIsArray($result);
            }

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $avgTime = $duration / $iterations;

            // 性能应该随头部数量线性增长
            $maxExpectedTime = $size * 0.00001; // 每个头部最多0.01ms
            $this->assertLessThan($maxExpectedTime, $avgTime, 
                "Large header set ({$size} headers) processing too slow: {$avgTime}s");

            $this->performanceResults["Large Headers ({$size})"] = sprintf(
                "%.4fs total, %.6fs avg, %.2fMB memory",
                $duration, $avgTime, $memoryUsed / 1024 / 1024
            );
        }
    }

    /**
     * 测试头部合并性能
     */
    public function testHeaderMergingPerformance(): void
    {
        $primaryHeaders = $this->generateTestHeaders(200);
        $secondaryHeaders = $this->generateTestHeaders(200, 'secondary-');
        $iterations = 500;

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $result = $this->service->mergeHeaders($primaryHeaders, $secondaryHeaders);
            $this->assertIsArray($result);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(1.0, $duration, "Header merging took too long: {$duration}s for {$iterations} iterations");
        $this->assertLessThan(0.002, $avgTime, "Average header merging time too slow: {$avgTime}s");

        $this->performanceResults['Header Merging'] = sprintf(
            "%.4fs total, %.6fs avg, %.2fMB memory",
            $duration, $avgTime, $memoryUsed / 1024 / 1024
        );
    }

    /**
     * 测试高冲突场景性能
     */
    public function testHighConflictScenarioPerformance(): void
    {
        // 创建大量重复头部
        $headers = [];
        $baseHeaders = ['Content-Type', 'Content-Length', 'Cache-Control', 'Set-Cookie'];
        
        for ($i = 0; $i < 1000; $i++) {
            foreach ($baseHeaders as $header) {
                $headers[strtolower($header) . "-{$i}"] = "value-{$i}";
                $headers[strtoupper($header) . "-{$i}"] = "VALUE-{$i}";
                $headers[ucfirst($header) . "-{$i}"] = "Value-{$i}";
            }
        }

        $iterations = 50;
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        for ($i = 0; $i < $iterations; $i++) {
            $result = $this->service->deduplicateHeaders($headers);
            $this->assertIsArray($result);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgTime = $duration / $iterations;

        $this->assertLessThan(5.0, $duration, "High conflict scenario took too long: {$duration}s");
        $this->assertLessThan(0.1, $avgTime, "Average high conflict processing too slow: {$avgTime}s");

        $this->performanceResults['High Conflict Scenario'] = sprintf(
            "%.4fs total, %.6fs avg, %.2fMB memory",
            $duration, $avgTime, $memoryUsed / 1024 / 1024
        );
    }

    /**
     * 测试并发模拟性能
     */
    public function testConcurrentSimulationPerformance(): void
    {
        $concurrentRequests = 100;
        $headersPerRequest = 50;
        
        // 预生成所有请求的头部
        $allHeaders = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $allHeaders[] = $this->generateTestHeaders($headersPerRequest, "req-{$i}-");
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // 模拟并发处理（顺序执行）
        $results = [];
        foreach ($allHeaders as $headers) {
            $results[] = $this->service->deduplicateHeaders($headers);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $avgTimePerRequest = $duration / $concurrentRequests;

        $this->assertCount($concurrentRequests, $results);
        $this->assertLessThan(5.0, $duration, "Concurrent simulation took too long: {$duration}s");
        $this->assertLessThan(0.05, $avgTimePerRequest, "Average per-request time too slow: {$avgTimePerRequest}s");

        $this->performanceResults['Concurrent Simulation'] = sprintf(
            "%.4fs total, %.6fs per request, %.2fMB memory",
            $duration, $avgTimePerRequest, $memoryUsed / 1024 / 1024
        );
    }

    /**
     * 测试内存泄漏检测
     */
    public function testMemoryLeakDetection(): void
    {
        $iterations = 1000;
        $headers = $this->generateTestHeaders(100);
        
        $initialMemory = memory_get_usage(true);
        $memoryReadings = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->service->deduplicateHeaders($headers);
            
            // 每100次迭代记录内存使用
            if ($i % 100 === 0) {
                $memoryReadings[] = memory_get_usage(true);
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryGrowth = $finalMemory - $initialMemory;

        // 检查内存增长趋势
        $memoryGrowthTrend = 0;
        for ($i = 1; $i < count($memoryReadings); $i++) {
            $memoryGrowthTrend += $memoryReadings[$i] - $memoryReadings[$i-1];
        }

        // 内存增长应该很小（不超过5MB）
        $this->assertLessThan(5 * 1024 * 1024, $memoryGrowth, 
            "Potential memory leak detected: " . ($memoryGrowth / 1024 / 1024) . "MB growth");

        // 内存增长趋势应该稳定（不应该持续增长）
        $avgGrowthPerInterval = $memoryGrowthTrend / (count($memoryReadings) - 1);
        $this->assertLessThan(100 * 1024, abs($avgGrowthPerInterval), 
            "Memory growth trend too high: " . ($avgGrowthPerInterval / 1024) . "KB per interval");

        $this->performanceResults['Memory Leak Test'] = sprintf(
            "%.2fMB total growth, %.2fKB avg per interval",
            $memoryGrowth / 1024 / 1024, $avgGrowthPerInterval / 1024
        );
    }

    /**
     * 测试不同配置选项的性能影响
     */
    public function testConfigurationPerformanceImpact(): void
    {
        $headers = $this->generateTestHeaders(200);
        $iterations = 200;
        $configs = [
            'minimal' => ['debug_logging' => false, 'enable_performance_logging' => false],
            'debug' => ['debug_logging' => true, 'enable_performance_logging' => false],
            'performance' => ['debug_logging' => false, 'enable_performance_logging' => true],
            'full' => ['debug_logging' => true, 'enable_performance_logging' => true, 'strict_mode' => true],
        ];

        foreach ($configs as $configName => $config) {
            $service = new HeaderDeduplicationService(new NullLogger(), $config);
            
            $startTime = microtime(true);
            
            for ($i = 0; $i < $iterations; $i++) {
                $service->deduplicateHeaders($headers);
            }
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            $avgTime = $duration / $iterations;

            $this->assertLessThan(2.0, $duration, "Config '{$configName}' took too long: {$duration}s");

            $this->performanceResults["Config: {$configName}"] = sprintf(
                "%.4fs total, %.6fs avg",
                $duration, $avgTime
            );
        }
    }

    /**
     * 压力测试：极限场景
     */
    public function testStressTestScenarios(): void
    {
        $scenarios = [
            'many_small_headers' => ['count' => 10000, 'value_size' => 10],
            'few_large_headers' => ['count' => 10, 'value_size' => 8000],
            'mixed_headers' => ['count' => 1000, 'value_size' => 100],
        ];

        foreach ($scenarios as $scenarioName => $params) {
            $headers = $this->generateVariableSizeHeaders($params['count'], $params['value_size']);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $result = $this->service->deduplicateHeaders($headers);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;

            $this->assertIsArray($result);
            $this->assertLessThan(10.0, $duration, "Stress test '{$scenarioName}' took too long: {$duration}s");
            $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, 
                "Stress test '{$scenarioName}' used too much memory: " . ($memoryUsed / 1024 / 1024) . "MB");

            $this->performanceResults["Stress: {$scenarioName}"] = sprintf(
                "%.4fs, %.2fMB memory",
                $duration, $memoryUsed / 1024 / 1024
            );
        }
    }

    /**
     * 基准测试：与简单实现对比
     */
    public function testBenchmarkAgainstSimpleImplementation(): void
    {
        $headers = $this->generateTestHeaders(500);
        $iterations = 100;

        // 测试优化后的实现
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->service->deduplicateHeaders($headers);
        }
        $optimizedTime = microtime(true) - $startTime;

        // 测试简单实现
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->simpleDeduplication($headers);
        }
        $simpleTime = microtime(true) - $startTime;

        // 优化实现应该不会比简单实现慢太多（允许2倍开销用于额外功能）
        $this->assertLessThan($simpleTime * 3, $optimizedTime, 
            "Optimized implementation too slow compared to simple: {$optimizedTime}s vs {$simpleTime}s");

        $this->performanceResults['Benchmark Comparison'] = sprintf(
            "Optimized: %.4fs, Simple: %.4fs, Ratio: %.2fx",
            $optimizedTime, $simpleTime, $optimizedTime / $simpleTime
        );
    }

    /**
     * 生成测试头部
     */
    private function generateTestHeaders(int $count, string $prefix = ''): array
    {
        $headers = [];
        $commonHeaders = [
            'Content-Type', 'Content-Length', 'Cache-Control', 'Accept',
            'User-Agent', 'Authorization', 'Cookie', 'Set-Cookie',
            'X-Powered-By', 'Server', 'Date', 'Expires'
        ];

        for ($i = 0; $i < $count; $i++) {
            if ($i < count($commonHeaders)) {
                $name = $prefix . $commonHeaders[$i % count($commonHeaders)];
            } else {
                $name = $prefix . "Custom-Header-{$i}";
            }
            
            $headers[$name] = "value-{$i}";
            
            // 添加一些重复头部（不同大小写）
            if ($i % 10 === 0) {
                $headers[strtolower($name)] = "lowercase-value-{$i}";
                $headers[strtoupper($name)] = "UPPERCASE-VALUE-{$i}";
            }
        }

        return $headers;
    }

    /**
     * 生成可变大小的测试头部
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
     * 简单的头部去重实现（用于基准对比）
     */
    private function simpleDeduplication(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            $normalizedName = ucwords(strtolower($name), '-');
            $result[$normalizedName] = $value;
        }
        return $result;
    }
}