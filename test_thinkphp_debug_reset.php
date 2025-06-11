<?php
/**
 * 测试 ThinkPHP 调试工具条状态重置
 * 专门针对 ThinkPHP 调试工具条运行时间累加问题的测试
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== ThinkPHP 调试工具条状态重置测试 ===" . PHP_EOL;
echo "时间: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "PHP 版本: " . PHP_VERSION . PHP_EOL;
echo "进程 ID: " . getmypid() . PHP_EOL;
echo PHP_EOL;

// 模拟 ThinkPHP 调试工具条的时间统计机制
class MockDebugToolbar 
{
    private static $startTime = null;
    private static $totalTime = 0;
    private static $requestCount = 0;
    
    public static function start()
    {
        if (self::$startTime === null) {
            // 模拟第一次启动时设置的全局开始时间
            self::$startTime = microtime(true);
        }
        self::$requestCount++;
        echo "调试工具条启动 - 请求 #" . self::$requestCount . PHP_EOL;
        echo "全局开始时间: " . number_format(self::$startTime, 6) . PHP_EOL;
    }
    
    public static function end()
    {
        $currentTime = microtime(true);
        $runtime = ($currentTime - self::$startTime) * 1000; // 转换为毫秒
        self::$totalTime += $runtime;
        
        echo "当前时间: " . number_format($currentTime, 6) . PHP_EOL;
        echo "运行时间: " . number_format($runtime, 2) . " ms" . PHP_EOL;
        echo "累计时间: " . number_format(self::$totalTime, 2) . " ms" . PHP_EOL;
        echo PHP_EOL;
        
        return $runtime;
    }
    
    public static function reset()
    {
        echo "执行调试工具条重置..." . PHP_EOL;
        self::$startTime = microtime(true); // 重新设置开始时间
        echo "新的开始时间: " . number_format(self::$startTime, 6) . PHP_EOL;
        echo "重置完成" . PHP_EOL;
        echo PHP_EOL;
    }
    
    public static function getStats()
    {
        return [
            'start_time' => self::$startTime,
            'total_time' => self::$totalTime,
            'request_count' => self::$requestCount
        ];
    }
}

// 模拟多次请求，展示问题和解决方案
echo "=== 问题演示：不重置状态的情况 ===" . PHP_EOL;

for ($i = 1; $i <= 3; $i++) {
    echo "--- 请求 {$i} ---" . PHP_EOL;
    MockDebugToolbar::start();
    
    // 模拟请求处理时间
    usleep(100000); // 100ms
    
    $runtime = MockDebugToolbar::end();
}

$stats = MockDebugToolbar::getStats();
echo "问题总结:" . PHP_EOL;
echo "  请求数量: " . $stats['request_count'] . PHP_EOL;
echo "  累计时间: " . number_format($stats['total_time'], 2) . " ms" . PHP_EOL;
echo "  平均时间: " . number_format($stats['total_time'] / $stats['request_count'], 2) . " ms" . PHP_EOL;
echo "  ❌ 可以看到运行时间在不断累加！" . PHP_EOL;
echo PHP_EOL;

// 重置并演示解决方案
echo "=== 解决方案演示：每次请求前重置状态 ===" . PHP_EOL;

// 重置统计
MockDebugToolbar::reset();

for ($i = 1; $i <= 3; $i++) {
    echo "--- 请求 {$i} ---" . PHP_EOL;
    
    // 在每次请求前重置（这是关键）
    if ($i > 1) {
        MockDebugToolbar::reset();
    }
    
    MockDebugToolbar::start();
    
    // 模拟请求处理时间
    usleep(100000); // 100ms
    
    $runtime = MockDebugToolbar::end();
    echo "✅ 本次请求独立运行时间: " . number_format($runtime, 2) . " ms" . PHP_EOL;
    echo PHP_EOL;
}

echo "=== 实际解决方案说明 ===" . PHP_EOL;
echo "在 AbstractRuntime.php 中已添加以下重置逻辑:" . PHP_EOL;
echo "1. resetGlobalState() - 重置全局变量" . PHP_EOL;
echo "2. resetThinkPHPState() - 重置 ThinkPHP 应用状态" . PHP_EOL;
echo "3. resetStaticVariables() - 重置静态变量" . PHP_EOL;
echo "4. 重置 REQUEST_TIME 相关常量" . PHP_EOL;
echo PHP_EOL;

echo "=== 使用建议 ===" . PHP_EOL;
echo "1. 重启 ReactPHP 服务器使修改生效" . PHP_EOL;
echo "2. 如果问题仍然存在，可能需要:" . PHP_EOL;
echo "   - 检查 ThinkPHP 版本特定的调试实现" . PHP_EOL;
echo "   - 添加更多特定的状态重置逻辑" . PHP_EOL;
echo "   - 考虑禁用调试模式或使用其他调试方案" . PHP_EOL;
echo PHP_EOL;

echo "=== 调试建议 ===" . PHP_EOL;
echo "如果修改后仍有问题，请检查:" . PHP_EOL;
echo "1. ThinkPHP 配置中的 app_debug 设置" . PHP_EOL;
echo "2. 调试工具条的具体实现类" . PHP_EOL;
echo "3. 是否有自定义的调试中间件" . PHP_EOL;
echo "4. 查看 ThinkPHP 日志中的相关信息" . PHP_EOL;
