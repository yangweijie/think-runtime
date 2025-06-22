<?php

declare(strict_types=1);

/**
 * 分析 think-worker 实现和验证 Event 扩展使用
 */

echo "=== think-worker 分析和 Event 扩展验证 ===\n";

// 分析 think-worker 源码路径
$thinkWorkerPath = '/Volumes/data/git/php/hello-tp/vendor/topthink/think-worker';

if (!is_dir($thinkWorkerPath)) {
    echo "❌ think-worker 路径不存在: {$thinkWorkerPath}\n";
    exit(1);
}

echo "✅ think-worker 路径存在\n";

// 分析 think-worker 的主要文件
$files = [
    'src/Server.php',
    'src/Worker.php', 
    'src/command/Server.php',
    'src/command/Worker.php',
];

echo "\n=== think-worker 文件分析 ===\n";

foreach ($files as $file) {
    $fullPath = $thinkWorkerPath . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ {$file} 存在\n";
        
        $content = file_get_contents($fullPath);
        
        // 分析内存优化相关代码
        if (strpos($content, 'memory') !== false) {
            echo "   📝 包含内存相关代码\n";
        }
        
        // 分析性能优化相关代码
        if (strpos($content, 'gc_collect_cycles') !== false) {
            echo "   🔄 包含垃圾回收代码\n";
        }
        
        // 分析应用实例管理
        if (strpos($content, 'clone') !== false || strpos($content, 'new ') !== false) {
            echo "   🏗️  包含实例创建代码\n";
        }
        
    } else {
        echo "❌ {$file} 不存在\n";
    }
}

// 验证当前项目的 Workerman 事件循环
echo "\n=== 验证 Workerman 事件循环 ===\n";

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');
require_once 'vendor/autoload.php';

use Workerman\Worker;

// 检查扩展
echo "扩展状态:\n";
echo "- Event: " . (extension_loaded('event') ? '✅' : '❌') . "\n";
echo "- Ev: " . (extension_loaded('ev') ? '✅' : '❌') . "\n";
echo "- Libevent: " . (extension_loaded('libevent') ? '✅' : '❌') . "\n";

// 创建临时 Worker 检查事件循环
echo "\n检查 Workerman 事件循环选择:\n";

// 检查 Workerman 版本
if (class_exists('Workerman\Worker')) {
    $reflection = new ReflectionClass('Workerman\Worker');
    $constants = $reflection->getConstants();
    
    if (isset($constants['VERSION'])) {
        echo "Workerman 版本: " . $constants['VERSION'] . "\n";
    }
}

// 检查事件循环类
$eventClasses = [
    'Workerman\\Events\\Event',
    'Workerman\\Events\\Ev',
    'Workerman\\Events\\Libevent', 
    'Workerman\\Events\\Select'
];

foreach ($eventClasses as $class) {
    if (class_exists($class)) {
        echo "✅ {$class}\n";
        
        // 检查可用性
        try {
            $reflection = new ReflectionClass($class);
            if ($reflection->hasMethod('available')) {
                $available = $class::available();
                echo "   可用: " . ($available ? '✅' : '❌') . "\n";
                
                if ($available && strpos($class, 'Event') !== false) {
                    echo "   🎯 这是最优选择！\n";
                }
            }
        } catch (Exception $e) {
            echo "   检查失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ {$class}\n";
    }
}

// 实际测试事件循环
echo "\n=== 实际测试事件循环 ===\n";

// 创建一个简单的测试
$testWorker = new Worker('http://127.0.0.1:8083');
$testWorker->count = 1;

$eventLoopDetected = false;

$testWorker->onWorkerStart = function($worker) use (&$eventLoopDetected) {
    echo "测试 Worker 启动\n";
    
    // 尝试多种方法检测事件循环
    $reflection = new ReflectionClass($worker);
    
    // 方法1: 检查静态属性
    $staticProps = $reflection->getStaticProperties();
    foreach ($staticProps as $name => $value) {
        if (strpos(strtolower($name), 'event') !== false) {
            echo "静态属性 {$name}: " . var_export($value, true) . "\n";
        }
    }
    
    // 方法2: 检查实例属性
    $props = $reflection->getProperties();
    foreach ($props as $prop) {
        if (strpos(strtolower($prop->getName()), 'event') !== false) {
            $prop->setAccessible(true);
            $value = $prop->getValue($worker);
            echo "实例属性 {$prop->getName()}: " . var_export($value, true) . "\n";
        }
    }
    
    // 方法3: 检查全局变量
    if (isset($GLOBALS['_eventLoop'])) {
        echo "全局事件循环: " . get_class($GLOBALS['_eventLoop']) . "\n";
        $eventLoopDetected = true;
    }
    
    // 停止测试
    \Workerman\Lib\Timer::add(0.1, function() {
        Worker::stopAll();
    }, [], false);
};

$testWorker->onMessage = function($connection, $request) {
    $connection->send("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
};

echo "启动测试 Worker...\n";

// 捕获输出
ob_start();
Worker::runAll();
$output = ob_get_clean();

echo "Worker 输出:\n{$output}\n";

// 分析 think-worker 的关键优化点
echo "\n=== think-worker 关键优化分析 ===\n";

echo "基于 think-worker 的常见优化策略:\n";
echo "1. 🔄 应用实例复用 - 避免每次请求创建新实例\n";
echo "2. 🧹 定期垃圾回收 - gc_collect_cycles()\n";
echo "3. 💾 内存监控 - memory_get_usage() 监控\n";
echo "4. 🚀 进程池管理 - 合理的进程数配置\n";
echo "5. ⚡ 事件循环优化 - 使用最佳事件循环\n";

// 提供优化建议
echo "\n=== 优化建议 ===\n";

if (extension_loaded('event')) {
    echo "✅ Event 扩展已安装，应该能获得最佳性能\n";
    echo "🎯 如果 QPS 仍然不高，问题可能在于:\n";
    echo "   1. ThinkPHP 应用层面的开销\n";
    echo "   2. 数据库连接和查询\n";
    echo "   3. 调试工具 (think-trace) 的影响\n";
    echo "   4. 内存管理不当\n";
} else {
    echo "⚠️  建议安装 Event 扩展以获得最佳性能\n";
    echo "   pecl install event\n";
}

echo "\n建议的优化步骤:\n";
echo "1. 禁用 debug 模式和 think-trace\n";
echo "2. 启用 OPcache\n";
echo "3. 优化数据库连接池\n";
echo "4. 调整 Workerman 进程数\n";
echo "5. 实现更激进的内存管理\n";

echo "\n分析完成！\n";
