<?php

declare(strict_types=1);

/**
 * 验证 Workerman 事件循环实现
 * 
 * 深入检查 Workerman 是否真的使用了 Event 扩展
 */

require_once '/Volumes/data/git/php/tp/vendor/autoload.php';

echo "=== Workerman 事件循环深度验证 ===\n";

use Workerman\Worker;
use Workerman\Lib\Timer;

// 检查扩展
echo "PHP 扩展状态:\n";
echo "- Event: " . (extension_loaded('event') ? '✅ 已安装' : '❌ 未安装') . "\n";
echo "- Ev: " . (extension_loaded('ev') ? '✅ 已安装' : '❌ 未安装') . "\n";
echo "- Libevent: " . (extension_loaded('libevent') ? '✅ 已安装' : '❌ 未安装') . "\n";
echo "- Swoole: " . (extension_loaded('swoole') ? '✅ 已安装' : '❌ 未安装') . "\n\n";

// 创建一个 Worker 实例来检查事件循环
$worker = new Worker('http://127.0.0.1:8082');
$worker->count = 1;

// 使用反射检查 Workerman 内部实现
echo "=== Workerman 内部检查 ===\n";

// 检查 Worker 类的静态属性
$workerReflection = new ReflectionClass(Worker::class);

// 查找事件循环相关的属性
$properties = $workerReflection->getStaticProperties();
echo "Worker 静态属性:\n";
foreach ($properties as $name => $value) {
    if (strpos(strtolower($name), 'event') !== false || 
        strpos(strtolower($name), 'loop') !== false) {
        echo "- {$name}: " . var_export($value, true) . "\n";
    }
}

// 检查 Workerman 的事件循环选择逻辑
echo "\n=== 事件循环选择逻辑 ===\n";

// 查看 Workerman 源码中的事件循环选择
$eventLoopClasses = [
    'Workerman\\Events\\Event',
    'Workerman\\Events\\Ev', 
    'Workerman\\Events\\Libevent',
    'Workerman\\Events\\Select'
];

foreach ($eventLoopClasses as $class) {
    if (class_exists($class)) {
        echo "✅ {$class} 类存在\n";
        
        // 检查是否可用
        $reflection = new ReflectionClass($class);
        if ($reflection->hasMethod('available')) {
            $available = $class::available();
            echo "   可用性: " . ($available ? '✅ 可用' : '❌ 不可用') . "\n";
        }
    } else {
        echo "❌ {$class} 类不存在\n";
    }
}

// 手动测试事件循环选择
echo "\n=== 手动测试事件循环选择 ===\n";

// 模拟 Workerman 的事件循环选择逻辑
function selectEventLoop() {
    // 按优先级检查
    if (extension_loaded('event') && class_exists('Workerman\\Events\\Event')) {
        return 'Workerman\\Events\\Event';
    }
    
    if (extension_loaded('ev') && class_exists('Workerman\\Events\\Ev')) {
        return 'Workerman\\Events\\Ev';
    }
    
    if (extension_loaded('libevent') && class_exists('Workerman\\Events\\Libevent')) {
        return 'Workerman\\Events\\Libevent';
    }
    
    return 'Workerman\\Events\\Select';
}

$selectedLoop = selectEventLoop();
echo "预期选择的事件循环: {$selectedLoop}\n";

// 实际启动一个 Worker 来验证
echo "\n=== 实际验证 ===\n";

$worker->onWorkerStart = function($worker) {
    echo "Worker 启动，进程 ID: " . posix_getpid() . "\n";
    
    // 检查当前使用的事件循环
    $reflection = new ReflectionClass(Worker::class);
    
    // 尝试获取事件循环实例
    if ($reflection->hasProperty('_eventLoop')) {
        $eventLoopProperty = $reflection->getProperty('_eventLoop');
        $eventLoopProperty->setAccessible(true);
        $eventLoop = $eventLoopProperty->getValue();
        
        if ($eventLoop) {
            echo "实际使用的事件循环: " . get_class($eventLoop) . "\n";
        }
    }
    
    // 检查全局事件循环
    if (isset($GLOBALS['_eventLoop'])) {
        echo "全局事件循环: " . get_class($GLOBALS['_eventLoop']) . "\n";
    }
    
    // 停止 Worker
    Timer::add(1, function() {
        Worker::stopAll();
    }, [], false);
};

$worker->onMessage = function($connection, $request) {
    $connection->send("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
};

echo "启动 Worker 进行验证...\n";

// 设置为库模式
Worker::$daemonize = false;
Worker::$stdoutFile = '/dev/null';

// 启动
Worker::runAll();
