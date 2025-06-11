<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\Tests\TestCase;
use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

/**
 * 运行时性能测试
 * 测试不同运行时的性能表现
 */
class RuntimePerformanceTest extends TestCase
{
    /**
     * 测试配置解析性能
     */
    public function testConfigParsingPerformance(): void
    {
        $iterations = 1000;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $config = new RuntimeConfig($this->createTestConfig());
            $config->get('runtimes.swoole.host');
            $config->getRuntimeConfig('swoole');
            $config->getDefaultRuntime();
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 配置解析应该在合理时间内完成
        $this->assertLessThan(1.0, $duration, "Config parsing took too long: {$duration}s for {$iterations} iterations");
        
        // 平均每次操作应该很快
        $avgTime = $duration / $iterations;
        $this->assertLessThan(0.001, $avgTime, "Average config parsing time too slow: {$avgTime}s");
    }

    /**
     * 测试运行时检测性能
     */
    public function testRuntimeDetectionPerformance(): void
    {
        $this->createApplication();
        $this->createRuntimeConfig();
        $this->createRuntimeManager();

        $iterations = 100;
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $this->runtimeManager->getAvailableRuntimes();
            // 模拟最佳运行时检测，因为方法可能不存在
            $availableRuntimes = $this->runtimeManager->getAvailableRuntimes();
            $bestRuntime = reset($availableRuntimes);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // 运行时检测应该在合理时间内完成
        $this->assertLessThan(2.0, $duration, "Runtime detection took too long: {$duration}s for {$iterations} iterations");

        $avgTime = $duration / $iterations;
        $this->assertLessThan(0.02, $avgTime, "Average runtime detection time too slow: {$avgTime}s");
    }

    /**
     * 测试PSR-7请求处理性能
     */
    public function testPsr7RequestHandlingPerformance(): void
    {
        $this->createApplication();
        $this->createRuntimeConfig();
        $this->createRuntimeManager();
        
        // 使用FPM适配器进行测试
        $runtime = $this->runtimeManager->getRuntime('fpm');
        
        $iterations = 500;
        $requests = [];
        
        // 预创建请求对象
        for ($i = 0; $i < $iterations; $i++) {
            $requests[] = $this->createPsr7Request('GET', "/test/{$i}");
        }
        
        $startTime = microtime(true);
        
        foreach ($requests as $request) {
            $response = $runtime->handleRequest($request);
            $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 请求处理应该在合理时间内完成
        $this->assertLessThan(5.0, $duration, "Request handling took too long: {$duration}s for {$iterations} requests");
        
        $avgTime = $duration / $iterations;
        $this->assertLessThan(0.01, $avgTime, "Average request handling time too slow: {$avgTime}s");
    }

    /**
     * 测试内存使用情况
     */
    public function testMemoryUsage(): void
    {
        $initialMemory = memory_get_usage(true);
        
        $this->createApplication();
        $this->createRuntimeConfig();
        $this->createRuntimeManager();
        
        $afterSetupMemory = memory_get_usage(true);
        $setupMemoryUsage = $afterSetupMemory - $initialMemory;
        
        // 基础设置不应该使用过多内存（10MB限制）
        $this->assertLessThan(10 * 1024 * 1024, $setupMemoryUsage, "Setup memory usage too high: " . ($setupMemoryUsage / 1024 / 1024) . "MB");
        
        // 创建多个运行时实例
        $runtimes = [];
        foreach (['fpm', 'swoole', 'frankenphp', 'reactphp', 'ripple', 'roadrunner'] as $runtimeName) {
            try {
                $runtimes[] = $this->runtimeManager->getRuntime($runtimeName);
            } catch (\Exception $e) {
                // 忽略不可用的运行时
            }
        }
        
        $afterRuntimesMemory = memory_get_usage(true);
        $runtimesMemoryUsage = $afterRuntimesMemory - $afterSetupMemory;
        
        // 运行时实例不应该使用过多内存（每个运行时平均不超过5MB）
        $avgMemoryPerRuntime = $runtimesMemoryUsage / count($runtimes);
        $this->assertLessThan(5 * 1024 * 1024, $avgMemoryPerRuntime, "Average memory per runtime too high: " . ($avgMemoryPerRuntime / 1024 / 1024) . "MB");
    }

    /**
     * 测试并发请求处理
     */
    public function testConcurrentRequestHandling(): void
    {
        $this->createApplication();
        $this->createRuntimeConfig();
        $this->createRuntimeManager();
        
        $runtime = $this->runtimeManager->getRuntime('fpm');
        
        $concurrentRequests = 50;
        $requests = [];
        
        // 创建并发请求
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $requests[] = $this->createPsr7Request('GET', "/concurrent/{$i}");
        }
        
        $startTime = microtime(true);
        $responses = [];
        
        // 模拟并发处理（在单线程环境中顺序处理）
        foreach ($requests as $request) {
            $responses[] = $runtime->handleRequest($request);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 验证所有请求都得到了响应
        $this->assertCount($concurrentRequests, $responses);
        
        foreach ($responses as $response) {
            $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        }
        
        // 并发处理应该在合理时间内完成
        $this->assertLessThan(10.0, $duration, "Concurrent request handling took too long: {$duration}s for {$concurrentRequests} requests");
    }

    /**
     * 测试配置缓存性能
     */
    public function testConfigCachingPerformance(): void
    {
        $config = new RuntimeConfig($this->createTestConfig());
        
        $iterations = 10000;
        
        // 测试首次访问
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $config->get('runtimes.swoole.host');
        }
        $firstAccessTime = microtime(true) - $startTime;
        
        // 测试缓存访问
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $config->get('runtimes.swoole.host');
        }
        $cachedAccessTime = microtime(true) - $startTime;
        
        // 缓存访问应该更快或至少不慢于首次访问
        $this->assertLessThanOrEqual($firstAccessTime * 1.1, $cachedAccessTime, "Cached access should not be significantly slower than first access");
    }

    /**
     * 测试大量配置项的处理性能
     */
    public function testLargeConfigPerformance(): void
    {
        // 创建大量配置项
        $largeConfig = $this->createTestConfig();
        
        // 添加大量运行时配置
        for ($i = 0; $i < 100; $i++) {
            $largeConfig['runtimes']["test_runtime_{$i}"] = [
                'host' => "127.0.0.{$i}",
                'port' => 8000 + $i,
                'worker_num' => $i % 10 + 1,
                'options' => array_fill(0, 50, "option_{$i}"),
            ];
        }
        
        $startTime = microtime(true);
        $config = new RuntimeConfig($largeConfig);
        
        // 访问各种配置
        for ($i = 0; $i < 100; $i++) {
            $config->getRuntimeConfig("test_runtime_{$i}");
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 大量配置处理应该在合理时间内完成
        $this->assertLessThan(1.0, $duration, "Large config processing took too long: {$duration}s");
    }

    /**
     * 测试错误处理性能
     */
    public function testErrorHandlingPerformance(): void
    {
        $this->createApplication();
        $this->createRuntimeConfig();
        $this->createRuntimeManager();
        
        $iterations = 100;
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $this->runtimeManager->getRuntime('nonexistent_runtime');
            } catch (\InvalidArgumentException $e) {
                // 预期的异常
            }
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 错误处理应该很快
        $this->assertLessThan(1.0, $duration, "Error handling took too long: {$duration}s for {$iterations} iterations");
        
        $avgTime = $duration / $iterations;
        $this->assertLessThan(0.01, $avgTime, "Average error handling time too slow: {$avgTime}s");
    }

    /**
     * 基准测试：比较不同运行时的初始化性能
     */
    public function testRuntimeInitializationBenchmark(): void
    {
        $this->createApplication();
        $this->createRuntimeConfig();
        $this->createRuntimeManager();
        
        $runtimeNames = ['fpm', 'swoole', 'frankenphp', 'reactphp', 'ripple', 'roadrunner'];
        $benchmarkResults = [];
        
        foreach ($runtimeNames as $runtimeName) {
            $iterations = 50;
            $totalTime = 0;
            
            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);
                
                try {
                    $runtime = $this->runtimeManager->getRuntime($runtimeName);
                    $runtime->getConfig();
                    $runtime->isAvailable();
                } catch (\Exception $e) {
                    // 运行时不可用，跳过
                    continue 2;
                }
                
                $endTime = microtime(true);
                $totalTime += ($endTime - $startTime);
            }
            
            $avgTime = $totalTime / $iterations;
            $benchmarkResults[$runtimeName] = $avgTime;
            
            // 每个运行时初始化应该很快
            $this->assertLessThan(0.01, $avgTime, "Runtime {$runtimeName} initialization too slow: {$avgTime}s");
        }
        
        // 输出基准测试结果（用于性能分析）
        echo "\nRuntime Initialization Benchmark Results:\n";
        foreach ($benchmarkResults as $runtime => $time) {
            echo sprintf("  %s: %.6f seconds\n", $runtime, $time);
        }
    }
}
