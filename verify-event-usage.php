<?php

declare(strict_types=1);

/**
 * 验证 Workerman 是否真正使用了 Event 扩展
 */

require_once '/Volumes/data/git/php/tp/vendor/autoload.php';

echo "=== 验证 Workerman Event 扩展真实使用情况 ===\n";

use Workerman\Worker;
use Workerman\Lib\Timer;

// 检查扩展
echo "扩展检查:\n";
echo "- Event: " . (extension_loaded('event') ? '✅ 已安装' : '❌ 未安装') . "\n";
echo "- Ev: " . (extension_loaded('ev') ? '✅ 已安装' : '❌ 未安装') . "\n";
echo "- Libevent: " . (extension_loaded('libevent') ? '✅ 已安装' : '❌ 未安装') . "\n\n";

// 创建测试 Worker
$worker = new Worker('http://127.0.0.1:8084');
$worker->count = 1;

$eventLoopClass = null;
$actualEventLoop = null;

$worker->onWorkerStart = function($worker) use (&$eventLoopClass, &$actualEventLoop) {
    echo "Worker 启动，开始检测事件循环...\n";
    
    // 方法1: 检查 Workerman 的全局事件循环
    if (class_exists('Workerman\\Events\\EventInterface')) {
        echo "EventInterface 存在\n";
    }
    
    // 方法2: 通过反射检查 Worker 类
    $reflection = new ReflectionClass($worker);
    $properties = $reflection->getStaticProperties();
    
    foreach ($properties as $name => $value) {
        if (stripos($name, 'event') !== false) {
            echo "静态属性 {$name}: " . var_export($value, true) . "\n";
            if ($name === 'eventLoopClass') {
                $eventLoopClass = $value;
            }
        }
    }
    
    // 方法3: 检查全局变量
    foreach ($GLOBALS as $key => $value) {
        if (stripos($key, 'event') !== false && is_object($value)) {
            echo "全局对象 {$key}: " . get_class($value) . "\n";
            $actualEventLoop = $value;
        }
    }
    
    // 方法4: 直接检查 Workerman 内部
    if (class_exists('Workerman\\Worker')) {
        $workerReflection = new ReflectionClass('Workerman\\Worker');
        
        // 查找所有静态属性
        $staticProps = $workerReflection->getStaticProperties();
        echo "Worker 静态属性:\n";
        foreach ($staticProps as $prop => $val) {
            if (is_string($val) && (stripos($prop, 'event') !== false || stripos($val, 'event') !== false)) {
                echo "  {$prop}: {$val}\n";
            }
        }
    }
    
    // 方法5: 检查 Timer 类（Timer 也使用事件循环）
    if (class_exists('Workerman\\Lib\\Timer')) {
        $timerReflection = new ReflectionClass('Workerman\\Lib\\Timer');
        $timerProps = $timerReflection->getStaticProperties();
        
        foreach ($timerProps as $prop => $val) {
            if (stripos($prop, 'event') !== false) {
                echo "Timer 属性 {$prop}: " . var_export($val, true) . "\n";
            }
        }
    }
    
    // 方法6: 尝试创建事件循环实例
    $eventClasses = [
        'Workerman\\Events\\Event',
        'Workerman\\Events\\Ev',
        'Workerman\\Events\\Libevent',
        'Workerman\\Events\\Select'
    ];
    
    echo "\n事件循环类检查:\n";
    foreach ($eventClasses as $class) {
        if (class_exists($class)) {
            echo "✅ {$class}\n";
            
            try {
                $reflection = new ReflectionClass($class);
                if ($reflection->hasMethod('available')) {
                    $available = $class::available();
                    echo "   可用: " . ($available ? '✅' : '❌') . "\n";
                    
                    if ($available) {
                        // 尝试创建实例
                        try {
                            $instance = new $class();
                            echo "   实例创建: ✅\n";
                            echo "   实例类型: " . get_class($instance) . "\n";
                            
                            // 检查实例方法
                            $methods = get_class_methods($instance);
                            $eventMethods = array_filter($methods, function($method) {
                                return in_array($method, ['add', 'del', 'loop', 'destroy']);
                            });
                            echo "   事件方法: " . implode(', ', $eventMethods) . "\n";
                            
                        } catch (Exception $e) {
                            echo "   实例创建失败: " . $e->getMessage() . "\n";
                        }
                    }
                }
            } catch (Exception $e) {
                echo "   检查失败: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ {$class}\n";
        }
    }
    
    // 停止测试
    Timer::add(0.5, function() {
        Worker::stopAll();
    }, [], false);
};

$worker->onMessage = function($connection, $request) {
    $connection->send("HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nOK");
};

echo "启动测试 Worker 进行深度检测...\n";

// 设置为库模式，避免命令行参数问题
Worker::$daemonize = false;
Worker::$stdoutFile = '/dev/null';

// 模拟命令行参数
global $argv;
$argv = ['test', 'start'];

// 捕获所有输出
ob_start();
Worker::runAll();
$output = ob_get_clean();

echo "Worker 运行输出:\n";
echo $output . "\n";

// 分析结果
echo "\n=== 分析结果 ===\n";

if ($eventLoopClass) {
    echo "检测到事件循环类: {$eventLoopClass}\n";
    
    if (strpos($eventLoopClass, 'Event') !== false) {
        echo "🎉 确认使用 Event 扩展！\n";
    } elseif (strpos($eventLoopClass, 'Select') !== false) {
        echo "⚠️  使用 Select 事件循环（性能较低）\n";
    }
} else {
    echo "❌ 未能检测到事件循环类\n";
}

if ($actualEventLoop) {
    echo "实际事件循环对象: " . get_class($actualEventLoop) . "\n";
}

// 提供诊断建议
echo "\n=== 诊断建议 ===\n";

if (extension_loaded('event')) {
    echo "✅ Event 扩展已安装\n";
    
    // 检查 Event 扩展版本和配置
    $eventVersion = phpversion('event');
    echo "Event 扩展版本: {$eventVersion}\n";
    
    // 检查 libevent 版本
    if (function_exists('event_get_version')) {
        try {
            $libeventVersion = event_get_version();
            echo "Libevent 版本: {$libeventVersion}\n";
        } catch (Exception $e) {
            echo "无法获取 Libevent 版本: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n如果 Workerman 仍未使用 Event 扩展，可能的原因:\n";
    echo "1. Event 扩展安装不完整\n";
    echo "2. Workerman 版本过旧\n";
    echo "3. 系统环境问题\n";
    echo "4. PHP 配置问题\n";
    
} else {
    echo "❌ Event 扩展未安装\n";
    echo "安装命令: pecl install event\n";
}

// 性能对比建议
echo "\n=== 性能对比 ===\n";
echo "事件循环性能排序:\n";
echo "1. Event (libevent) - 最高性能，支持数万并发\n";
echo "2. Ev - 高性能，基于 libev\n";
echo "3. Libevent - 中等性能\n";
echo "4. Select - 基础性能，有连接数限制\n";

echo "\n如果您的 QPS 只有 870-930，而期望 3000+:\n";
echo "1. 确保使用 Event 扩展\n";
echo "2. 禁用所有调试工具\n";
echo "3. 启用 OPcache\n";
echo "4. 优化应用代码\n";
echo "5. 调整系统参数\n";

echo "\n检测完成！\n";
