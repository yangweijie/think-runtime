<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;
use Psr\Log\NullLogger;

/**
 * 头部去重优化验证测试
 * 验证所有性能优化措施的有效性
 */
class HeaderOptimizationValidationTest extends TestCase
{
    /**
     * 测试缓存功能
     */
    public function testCachingFunctionality(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_header_name_cache' => true,
            'max_cache_size' => 100,
        ]);

        // 预热缓存
        $service->warmupCache();
        
        // 验证缓存统计
        $initialStats = $service->getCacheStats();
        $this->assertGreaterThan(0, $initialStats['cache_size']);
        
        // 测试缓存命中
        $headers = [
            'content-type' => 'application/json',
            'Content-Type' => 'text/html',
            'CONTENT-TYPE' => 'application/xml',
        ];
        
        $service->deduplicateHeaders($headers);
        
        $finalStats = $service->getCacheStats();
        $this->assertGreaterThan($initialStats['hits'], $finalStats['hits']);
        $this->assertGreaterThan(0, $finalStats['hit_rate_percent']);
    }

    /**
     * 测试批量处理功能
     */
    public function testBatchProcessingFunctionality(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_batch_processing' => true,
        ]);

        $headerSets = [];
        for ($i = 0; $i < 10; $i++) {
            $headerSets[] = [
                "Header-{$i}" => "value-{$i}",
                'Content-Type' => 'application/json',
            ];
        }

        $results = $service->batchDeduplicateHeaders($headerSets);
        
        $this->assertCount(10, $results);
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('Content-Type', $result);
        }
    }

    /**
     * 测试性能指标收集
     */
    public function testPerformanceMetricsCollection(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_performance_logging' => true,
            'enable_header_name_cache' => true,
        ]);

        $headers = ['Content-Type' => 'application/json'];
        $service->deduplicateHeaders($headers);

        $metrics = $service->getPerformanceMetrics();
        
        $this->assertArrayHasKey('cache_stats', $metrics);
        $this->assertArrayHasKey('config', $metrics);
        $this->assertTrue($metrics['config']['cache_enabled']);
        $this->assertTrue($metrics['config']['performance_logging_enabled']);
    }

    /**
     * 测试优化建议功能
     */
    public function testOptimizationSuggestions(): void
    {
        // 测试低缓存命中率场景
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_header_name_cache' => true,
            'max_cache_size' => 5, // 很小的缓存
        ]);

        // 生成大量不同的头部名称
        for ($i = 0; $i < 50; $i++) {
            $headers = ["Unique-Header-{$i}" => "value-{$i}"];
            $service->deduplicateHeaders($headers);
        }

        $suggestions = $service->getOptimizationSuggestions();
        $this->assertIsArray($suggestions);
        
        // 应该有关于缓存的建议
        $cacheRelatedSuggestions = array_filter($suggestions, function($suggestion) {
            return $suggestion['type'] === 'cache';
        });
        
        $this->assertNotEmpty($cacheRelatedSuggestions);
    }

    /**
     * 测试缓存清理功能
     */
    public function testCacheClearFunctionality(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_header_name_cache' => true,
        ]);

        // 添加一些缓存条目
        $service->normalizeHeaderName('Content-Type');
        $service->normalizeHeaderName('Accept');
        
        $statsBeforeClear = $service->getCacheStats();
        $this->assertGreaterThan(0, $statsBeforeClear['cache_size']);
        
        // 清空缓存
        $service->clearCache();
        
        $statsAfterClear = $service->getCacheStats();
        $this->assertEquals(0, $statsAfterClear['cache_size']);
        $this->assertEquals(0, $statsAfterClear['hits']);
        $this->assertEquals(0, $statsAfterClear['misses']);
    }

    /**
     * 测试缓存驱逐策略
     */
    public function testCacheEvictionStrategy(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_header_name_cache' => true,
            'max_cache_size' => 3, // 很小的缓存限制
        ]);

        // 添加超过缓存限制的条目
        $service->normalizeHeaderName('Header-1');
        $service->normalizeHeaderName('Header-2');
        $service->normalizeHeaderName('Header-3');
        
        $statsBeforeEviction = $service->getCacheStats();
        $this->assertEquals(3, $statsBeforeEviction['cache_size']);
        $this->assertEquals(0, $statsBeforeEviction['evictions']);
        
        // 添加第四个条目，应该触发驱逐
        $service->normalizeHeaderName('Header-4');
        
        $statsAfterEviction = $service->getCacheStats();
        $this->assertEquals(3, $statsAfterEviction['cache_size']); // 缓存大小保持不变
        $this->assertEquals(1, $statsAfterEviction['evictions']); // 应该有一次驱逐
    }

    /**
     * 测试配置对性能的影响
     */
    public function testConfigurationPerformanceImpact(): void
    {
        $headers = $this->generateTestHeaders(100);
        
        // 最小配置
        $minimalService = new HeaderDeduplicationService(new NullLogger(), [
            'debug_logging' => false,
            'enable_performance_logging' => false,
            'log_critical_conflicts' => false,
        ]);
        
        // 完整配置
        $fullService = new HeaderDeduplicationService(new NullLogger(), [
            'debug_logging' => true,
            'enable_performance_logging' => true,
            'log_critical_conflicts' => true,
            'strict_mode' => true,
        ]);
        
        $iterations = 50;
        
        // 测试最小配置性能
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $minimalService->deduplicateHeaders($headers);
        }
        $minimalTime = microtime(true) - $startTime;
        
        // 测试完整配置性能
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $fullService->deduplicateHeaders($headers);
        }
        $fullTime = microtime(true) - $startTime;
        
        // 最小配置应该更快
        $this->assertLessThan($fullTime, $minimalTime);
        
        // 性能差异不应该太大（允许10倍差异，因为调试模式会有显著开销）
        $performanceRatio = $fullTime / $minimalTime;
        $this->assertLessThan(10.0, $performanceRatio, 
            "Performance difference too large: {$performanceRatio}x");
    }

    /**
     * 测试内存使用优化
     */
    public function testMemoryUsageOptimization(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_header_name_cache' => true,
            'max_cache_size' => 100,
        ]);

        $initialMemory = memory_get_usage(true);
        
        // 处理大量头部
        for ($i = 0; $i < 1000; $i++) {
            $headers = $this->generateTestHeaders(10, $i);
            $service->deduplicateHeaders($headers);
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        
        // 内存使用应该合理（不超过10MB）
        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 
            "Memory usage too high: " . ($memoryUsed / 1024 / 1024) . "MB");
        
        // 缓存大小应该受到限制
        $cacheStats = $service->getCacheStats();
        $this->assertLessThanOrEqual(100, $cacheStats['cache_size']);
    }

    /**
     * 测试算法复杂度优化
     */
    public function testAlgorithmComplexityOptimization(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger());
        
        $headerSizes = [100, 200, 400, 800];
        $times = [];
        
        foreach ($headerSizes as $size) {
            $headers = $this->generateTestHeaders($size);
            
            $startTime = microtime(true);
            $service->deduplicateHeaders($headers);
            $endTime = microtime(true);
            
            $times[$size] = $endTime - $startTime;
        }
        
        // 检查时间复杂度是否接近线性
        // 800个头部的处理时间不应该超过100个头部的16倍（理想情况下应该是8倍）
        $complexityRatio = $times[800] / $times[100];
        $this->assertLessThan(16.0, $complexityRatio, 
            "Algorithm complexity too high: {$complexityRatio}x for 8x input size");
    }

    /**
     * 综合优化验证测试
     */
    public function testComprehensiveOptimizationValidation(): void
    {
        $service = new HeaderDeduplicationService(new NullLogger(), [
            'enable_header_name_cache' => true,
            'enable_batch_processing' => true,
            'enable_performance_logging' => true,
            'max_cache_size' => 200,
        ]);

        // 预热缓存
        $service->warmupCache();
        
        // 批量处理测试（使用重复的头部名称以提高缓存命中率）
        $headerSets = [];
        for ($i = 0; $i < 50; $i++) {
            $headerSets[] = $this->generateRepeatingHeaders(20);
        }
        
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        $results = $service->batchDeduplicateHeaders($headerSets);
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        // 验证结果
        $this->assertCount(50, $results);
        $this->assertLessThan(1.0, $duration, "Batch processing took too long: {$duration}s");
        $this->assertLessThan(5 * 1024 * 1024, $memoryUsed, "Memory usage too high: " . ($memoryUsed / 1024 / 1024) . "MB");
        
        // 验证缓存效果
        $cacheStats = $service->getCacheStats();
        $this->assertGreaterThan(30, $cacheStats['hit_rate_percent'], "Cache hit rate too low: {$cacheStats['hit_rate_percent']}%");
        
        // 验证性能指标
        $metrics = $service->getPerformanceMetrics();
        $this->assertArrayHasKey('cache_stats', $metrics);
        $this->assertTrue($metrics['config']['cache_enabled']);
        $this->assertTrue($metrics['config']['batch_processing_enabled']);
        
        // 验证优化建议
        $suggestions = $service->getOptimizationSuggestions();
        $this->assertIsArray($suggestions);
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
     * 生成重复头部（用于提高缓存命中率）
     */
    private function generateRepeatingHeaders(int $count): array
    {
        $headers = [];
        $commonHeaders = [
            'Content-Type', 'Accept', 'Cache-Control', 'Authorization',
            'User-Agent', 'Host', 'Connection', 'Accept-Encoding'
        ];
        
        for ($i = 0; $i < $count; $i++) {
            // 使用重复的头部名称以提高缓存命中率
            $name = $commonHeaders[$i % count($commonHeaders)];
            $headers[$name] = "value-{$i}";
            
            // 添加一些大小写变化以测试缓存
            if ($i % 3 === 0) {
                $headers[strtolower($name)] = "lower-value-{$i}";
            }
            if ($i % 5 === 0) {
                $headers[strtoupper($name)] = "UPPER-VALUE-{$i}";
            }
        }
        
        return $headers;
    }
}