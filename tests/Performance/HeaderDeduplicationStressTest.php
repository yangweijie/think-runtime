<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use Psr\Log\NullLogger;

/**
 * 头部去重服务压力测试
 * 专门测试高并发和极限场景下的性能表现
 */
class HeaderDeduplicationStressTest extends TestCase
{
    private HeaderDeduplicationService $service;
    private array $stressResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_performance_logging' => false,
            'debug_logging' => false,
            'strict_mode' => false
        ]);
    }

    protected function tearDown(): void
    {
        if (!empty($this->stressResults)) {
            echo "\n=== Header Deduplication Stress Test Results ===\n";
            foreach ($this->stressResults as $test => $result) {
                echo sprintf("%-50s: %s\n", $test, $result);
            }
            echo "=================================================\n";
        }
        parent::tearDown();
    }

    /**
     * 高并发请求模拟测试
     */
    public function testHighConcurrencySimulation(): void
    {
        $concurrentRequests = 1000;
        $headersPerRequest = 25;
        
        // 预生成所有请求数据
        $requestData = [];
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $requestData[] = $this->generateRealisticHeaders($headersPerRequest, $i);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $peakMemory = $startMemory;

        $processedRequests = 0;
        $totalHeadersProcessed = 0;

        foreach ($requestData as $headers) {
            $result = $this->service->deduplicateHeaders($headers);
            $processedRequests++;
            $totalHeadersProcessed += count($headers);
            
            // 监控内存峰值
            $currentMemory = memory_get_usage(true);
            if ($currentMemory > $peakMemory) {
                $peakMemory = $currentMemory;
            }
            
            $this->assertIsArray($result);
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        $peakMemoryUsed = $peakMemory - $startMemory;
        $requestsPerSecond = $processedRequests / $duration;
        $headersPerSecond = $totalHeadersProcessed / $duration;

        // 性能断言
        $this->assertEquals($concurrentRequests, $processedRequests);
        $this->assertGreaterThan(100, $requestsPerSecond, "Request processing rate too low: {$requestsPerSecond} req/s");
        $this->assertLessThan(100 * 1024 * 1024, $peakMemoryUsed, "Peak memory usage too high: " . ($peakMemoryUsed / 1024 / 1024) . "MB");

        $this->stressResults['High Concurrency Simulation'] = sprintf(
            "%.0f req/s, %.0f headers/s, %.2fMB peak memory",
            $requestsPerSecond, $headersPerSecond, $peakMemoryUsed / 1024 / 1024
        );
    }

    /**
     * 长时间运行稳定性测试
     */
    public function testLongRunningStability(): void
    {
        $runDuration = 30; // 30秒测试
        $headers = $this->generateRealisticHeaders(50);
        
        $startTime = microtime(true);
        $iterations = 0;
        $memoryReadings = [];
        $lastMemoryCheck = $startTime;

        while ((microtime(true) - $startTime) < $runDuration) {
            $this->service->deduplicateHeaders($headers);
            $iterations++;
            
            // 每秒记录一次内存使用
            $currentTime = microtime(true);
            if ($currentTime - $lastMemoryCheck >= 1.0) {
                $memoryReadings[] = memory_get_usage(true);
                $lastMemoryCheck = $currentTime;
            }
        }

        $endTime = microtime(true);
        $actualDuration = $endTime - $startTime;
        $iterationsPerSecond = $iterations / $actualDuration;

        // 检查内存稳定性
        $memoryGrowth = 0;
        if (count($memoryReadings) > 1) {
            $memoryGrowth = end($memoryReadings) - $memoryReadings[0];
        }

        $this->assertGreaterThan(50, $iterationsPerSecond, "Processing rate too low during long run: {$iterationsPerSecond} iter/s");
        $this->assertLessThan(10 * 1024 * 1024, abs($memoryGrowth), "Memory growth too high during long run: " . ($memoryGrowth / 1024 / 1024) . "MB");

        $this->stressResults['Long Running Stability'] = sprintf(
            "%.0f iter/s for %.1fs, %.2fMB memory growth",
            $iterationsPerSecond, $actualDuration, $memoryGrowth / 1024 / 1024
        );
    }

    /**
     * 极大头部集合测试
     */
    public function testExtremelyLargeHeaderSets(): void
    {
        $headerCounts = [5000, 10000, 20000];
        
        foreach ($headerCounts as $count) {
            $headers = $this->generateMassiveHeaderSet($count);
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $result = $this->service->deduplicateHeaders($headers);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $duration = $endTime - $startTime;
            $memoryUsed = $endMemory - $startMemory;
            $headersPerSecond = $count / $duration;

            $this->assertIsArray($result);
            $this->assertLessThan(30.0, $duration, "Extremely large header set ({$count}) took too long: {$duration}s");
            $this->assertGreaterThan(100, $headersPerSecond, "Processing rate too low for {$count} headers: {$headersPerSecond} headers/s");

            $this->stressResults["Extreme Headers ({$count})"] = sprintf(
                "%.2fs, %.0f headers/s, %.2fMB memory",
                $duration, $headersPerSecond, $memoryUsed / 1024 / 1024
            );
        }
    }

    /**
     * 复杂冲突场景压力测试
     */
    public function testComplexConflictStress(): void
    {
        $scenarios = [
            'many_duplicates' => $this->generateManyDuplicatesScenario(),
            'case_variations' => $this->generateCaseVariationsScenario(),
            'mixed_conflicts' => $this->generateMixedConflictsScenario(),
        ];

        foreach ($scenarios as $scenarioName => $headers) {
            $iterations = 100;
            
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

            $this->assertLessThan(0.1, $avgTime, "Complex conflict scenario '{$scenarioName}' too slow: {$avgTime}s avg");

            $this->stressResults["Complex Conflicts: {$scenarioName}"] = sprintf(
                "%.6fs avg, %.2fMB memory",
                $avgTime, $memoryUsed / 1024 / 1024
            );
        }
    }

    /**
     * 内存压力测试
     */
    public function testMemoryPressure(): void
    {
        $initialMemory = memory_get_usage(true);
        $services = [];
        $headerSets = [];

        // 创建多个服务实例和大量头部数据
        for ($i = 0; $i < 50; $i++) {
            $services[] = new HeaderDeduplicationService(new NullLogger());
            $headerSets[] = $this->generateRealisticHeaders(200, $i);
        }

        $afterSetupMemory = memory_get_usage(true);
        $setupMemory = $afterSetupMemory - $initialMemory;

        // 并行处理所有头部集合
        $startTime = microtime(true);
        $results = [];
        
        foreach ($services as $index => $service) {
            $headers = $headerSets[$index % count($headerSets)];
            $results[] = $service->deduplicateHeaders($headers);
        }

        $endTime = microtime(true);
        $finalMemory = memory_get_usage(true);
        
        $duration = $endTime - $startTime;
        $totalMemoryUsed = $finalMemory - $initialMemory;
        $processingMemory = $finalMemory - $afterSetupMemory;

        $this->assertCount(50, $results);
        $this->assertLessThan(200 * 1024 * 1024, $totalMemoryUsed, "Total memory usage too high: " . ($totalMemoryUsed / 1024 / 1024) . "MB");
        $this->assertLessThan(10.0, $duration, "Memory pressure test took too long: {$duration}s");

        $this->stressResults['Memory Pressure Test'] = sprintf(
            "%.2fs, %.2fMB total, %.2fMB processing",
            $duration, $totalMemoryUsed / 1024 / 1024, $processingMemory / 1024 / 1024
        );
    }

    /**
     * 边界条件压力测试
     */
    public function testBoundaryConditionsStress(): void
    {
        $boundaryTests = [
            'empty_headers' => [],
            'single_header' => ['Content-Type' => 'application/json'],
            'max_header_name_length' => [str_repeat('X', 100) => 'value'],
            'max_header_value_length' => ['Header' => str_repeat('x', 8000)],
            'special_characters' => ['X-Special-Chars' => "value\twith\nspecial\rchars"],
            'unicode_headers' => ['X-Unicode' => 'value with émojis 🚀 and ñoñó'],
        ];

        foreach ($boundaryTests as $testName => $headers) {
            $iterations = 1000;
            
            $startTime = microtime(true);
            $errors = 0;
            
            for ($i = 0; $i < $iterations; $i++) {
                try {
                    $result = $this->service->deduplicateHeaders($headers);
                    $this->assertIsArray($result);
                } catch (\Exception $e) {
                    $errors++;
                    // 某些边界条件可能会抛出异常，这是预期的
                }
            }
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            $avgTime = $duration / $iterations;
            $successRate = (($iterations - $errors) / $iterations) * 100;

            $this->assertLessThan(0.001, $avgTime, "Boundary test '{$testName}' too slow: {$avgTime}s avg");

            $this->stressResults["Boundary: {$testName}"] = sprintf(
                "%.6fs avg, %.1f%% success rate",
                $avgTime, $successRate
            );
        }
    }

    /**
     * 生成现实场景的头部
     */
    private function generateRealisticHeaders(int $count, int $seed = 0): array
    {
        $commonHeaders = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Content-Type' => 'application/json',
            'Host' => 'example.com',
            'User-Agent' => 'Mozilla/5.0 (compatible; TestAgent/1.0)',
            'X-Forwarded-For' => '192.168.1.1',
            'X-Real-IP' => '192.168.1.1',
        ];

        $headers = $commonHeaders;
        
        // 添加自定义头部
        for ($i = 0; $i < $count - count($commonHeaders); $i++) {
            $headers["X-Custom-{$seed}-{$i}"] = "value-{$seed}-{$i}";
        }

        // 添加一些重复头部（不同大小写）
        if ($seed % 3 === 0) {
            $headers['content-type'] = 'text/html';
            $headers['CONTENT-TYPE'] = 'application/xml';
        }

        return $headers;
    }

    /**
     * 生成大量头部集合
     */
    private function generateMassiveHeaderSet(int $count): array
    {
        $headers = [];
        $baseHeaders = ['Content', 'Accept', 'Cache', 'X-Custom', 'Authorization'];
        
        for ($i = 0; $i < $count; $i++) {
            $base = $baseHeaders[$i % count($baseHeaders)];
            $headers["{$base}-Header-{$i}"] = "value-{$i}";
            
            // 每100个头部添加一些重复
            if ($i % 100 === 0) {
                $headers[strtolower("{$base}-Header-{$i}")] = "lowercase-value-{$i}";
            }
        }

        return $headers;
    }

    /**
     * 生成多重复场景
     */
    private function generateManyDuplicatesScenario(): array
    {
        $headers = [];
        $baseNames = ['Content-Type', 'Accept', 'Cache-Control'];
        
        foreach ($baseNames as $baseName) {
            for ($i = 0; $i < 100; $i++) {
                $headers[$baseName . "-{$i}"] = "value-{$i}";
                $headers[strtolower($baseName) . "-{$i}"] = "lower-value-{$i}";
                $headers[strtoupper($baseName) . "-{$i}"] = "UPPER-VALUE-{$i}";
            }
        }

        return $headers;
    }

    /**
     * 生成大小写变化场景
     */
    private function generateCaseVariationsScenario(): array
    {
        $headers = [];
        $baseHeaders = [
            'content-type' => 'application/json',
            'Content-Type' => 'text/html',
            'CONTENT-TYPE' => 'application/xml',
            'Content-type' => 'text/plain',
            'content-Type' => 'application/pdf',
        ];

        // 重复多次以增加压力
        for ($i = 0; $i < 200; $i++) {
            foreach ($baseHeaders as $name => $value) {
                $headers["{$name}-{$i}"] = "{$value}-{$i}";
            }
        }

        return $headers;
    }

    /**
     * 生成混合冲突场景
     */
    private function generateMixedConflictsScenario(): array
    {
        $headers = [];
        
        // 添加各种类型的冲突
        for ($i = 0; $i < 500; $i++) {
            // 标准头部
            $headers["Header-{$i}"] = "value-{$i}";
            
            // 大小写变化
            if ($i % 5 === 0) {
                $headers[strtolower("Header-{$i}")] = "lower-{$i}";
                $headers[strtoupper("Header-{$i}")] = "UPPER-{$i}";
            }
            
            // 特殊字符
            if ($i % 10 === 0) {
                $headers["X-Special-{$i}"] = "value with spaces and-dashes_{$i}";
            }
            
            // 长值
            if ($i % 20 === 0) {
                $headers["X-Long-{$i}"] = str_repeat("long-value-{$i}-", 50);
            }
        }

        return $headers;
    }
}