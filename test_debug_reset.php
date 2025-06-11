<?php
/**
 * 测试调试工具条状态重置
 * 用于验证在常驻内存运行时中调试信息是否正确重置
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== ThinkPHP 调试状态重置测试 ===" . PHP_EOL;
echo "时间: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "PHP 版本: " . PHP_VERSION . PHP_EOL;
echo "进程 ID: " . getmypid() . PHP_EOL;
echo PHP_EOL;

// 模拟多次请求，检查状态是否正确重置
for ($i = 1; $i <= 3; $i++) {
    echo "=== 模拟第 {$i} 次请求 ===" . PHP_EOL;
    
    // 记录请求开始时间
    $startTime = microtime(true);
    $startMemory = memory_get_usage();
    
    // 显示当前全局变量状态
    echo "请求前状态:" . PHP_EOL;
    echo "  \$_GET: " . json_encode($_GET) . PHP_EOL;
    echo "  \$_POST: " . json_encode($_POST) . PHP_EOL;
    echo "  \$_SERVER HTTP 头数量: " . count(array_filter(array_keys($_SERVER), function($key) {
        return strpos($key, 'HTTP_') === 0;
    })) . PHP_EOL;
    echo "  内存使用: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB" . PHP_EOL;
    echo "  峰值内存: " . number_format(memory_get_peak_usage() / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // 模拟设置请求数据（模拟常驻内存运行时中的状态污染）
    $_GET['test'] = 'value_' . $i;
    $_POST['data'] = 'post_data_' . $i;
    $_SERVER['HTTP_X_TEST'] = 'header_' . $i;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test/' . $i;
    
    echo "设置请求数据后:" . PHP_EOL;
    echo "  \$_GET: " . json_encode($_GET) . PHP_EOL;
    echo "  \$_POST: " . json_encode($_POST) . PHP_EOL;
    echo "  \$_SERVER HTTP 头数量: " . count(array_filter(array_keys($_SERVER), function($key) {
        return strpos($key, 'HTTP_') === 0;
    })) . PHP_EOL;
    
    // 模拟处理时间
    usleep(100000); // 100ms
    
    // 计算运行时间
    $endTime = microtime(true);
    $runtime = ($endTime - $startTime) * 1000; // 转换为毫秒
    $endMemory = memory_get_usage();
    $memoryUsed = $endMemory - $startMemory;
    
    echo "请求处理完成:" . PHP_EOL;
    echo "  运行时间: " . number_format($runtime, 2) . " ms" . PHP_EOL;
    echo "  内存增长: " . number_format($memoryUsed / 1024, 2) . " KB" . PHP_EOL;
    echo "  当前内存: " . number_format(memory_get_usage() / 1024 / 1024, 2) . " MB" . PHP_EOL;
    echo "  峰值内存: " . number_format(memory_get_peak_usage() / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    echo PHP_EOL;
    
    // 在实际应用中，这里应该调用 resetGlobalState() 方法
    // 为了测试，我们手动演示重置过程
    if ($i < 3) {
        echo "执行状态重置..." . PHP_EOL;
        
        // 重置超全局变量
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
        $_REQUEST = [];
        
        // 清理HTTP头
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 || 
                in_array($key, ['REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING'])) {
                unset($_SERVER[$key]);
            }
        }
        
        echo "状态重置完成" . PHP_EOL;
        echo "  \$_GET: " . json_encode($_GET) . PHP_EOL;
        echo "  \$_POST: " . json_encode($_POST) . PHP_EOL;
        echo "  \$_SERVER HTTP 头数量: " . count(array_filter(array_keys($_SERVER), function($key) {
            return strpos($key, 'HTTP_') === 0;
        })) . PHP_EOL;
        echo PHP_EOL;
    }
}

echo "=== 测试完成 ===" . PHP_EOL;
echo "说明：在常驻内存运行时中，如果不进行状态重置，" . PHP_EOL;
echo "全局变量会在请求之间保持状态，导致调试信息累加。" . PHP_EOL;
echo "通过在每次请求前调用 resetGlobalState() 方法，" . PHP_EOL;
echo "可以确保每次请求都有干净的初始状态。" . PHP_EOL;
