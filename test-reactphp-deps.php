<?php

declare(strict_types=1);

/**
 * ReactPHP 依赖检查脚本
 */

echo "ReactPHP 依赖检查\n";
echo "================\n\n";

require_once 'vendor/autoload.php';

// 检查必需的类
$requiredClasses = [
    'React\\EventLoop\\Loop' => 'ReactPHP 事件循环',
    'React\\Http\\HttpServer' => 'ReactPHP HTTP 服务器',
    'React\\Socket\\SocketServer' => 'ReactPHP Socket 服务器',
    'React\\Http\\Message\\Response' => 'ReactPHP HTTP 响应',
    'React\\Promise\\Promise' => 'ReactPHP Promise',
    'RingCentral\\Psr7\\Request' => 'RingCentral PSR-7 请求',
    'RingCentral\\Psr7\\Response' => 'RingCentral PSR-7 响应',
];

$missing = [];
$available = [];

foreach ($requiredClasses as $class => $desc) {
    if (class_exists($class)) {
        echo "✅ {$desc}: {$class}\n";
        $available[] = $class;
    } else {
        echo "❌ {$desc}: {$class}\n";
        $missing[] = $class;
    }
}

echo "\n";

if (empty($missing)) {
    echo "🎉 所有必需依赖都已安装！\n\n";
    
    // 测试基本功能
    echo "测试基本功能...\n";
    echo "================\n";
    
    try {
        // 测试事件循环
        $loop = \React\EventLoop\Loop::get();
        echo "✅ 事件循环创建成功\n";
        
        // 测试 HTTP 服务器创建
        $server = new \React\Http\HttpServer($loop, function ($request) {
            return new \React\Http\Message\Response(200, [], 'Hello World');
        });
        echo "✅ HTTP 服务器创建成功\n";
        
        // 测试 Socket 服务器创建
        $socket = new \React\Socket\SocketServer('127.0.0.1:0', [], $loop);
        echo "✅ Socket 服务器创建成功\n";
        
        // 测试 Promise
        $promise = \React\Promise\resolve('test');
        echo "✅ Promise 创建成功\n";
        
        // 测试 PSR-7
        $request = new \RingCentral\Psr7\Request('GET', '/');
        $response = new \RingCentral\Psr7\Response(200, [], 'test');
        echo "✅ PSR-7 消息创建成功\n";
        
        // 关闭测试服务器
        $socket->close();
        
        echo "\n✅ 所有功能测试通过！\n\n";
        
        // 测试 ReactPHP 适配器（如果可用）
        if (class_exists('yangweijie\\thinkRuntime\\adapter\\ReactphpAdapter')) {
            echo "测试 ReactPHP 适配器...\n";
            
            $mockApp = new class {
                public function initialize() {}
            };
            
            $adapter = new \yangweijie\thinkRuntime\adapter\ReactphpAdapter($mockApp);
            
            if ($adapter->isSupported()) {
                echo "✅ ReactPHP 适配器支持当前环境\n";
                echo "✅ 适配器名称: " . $adapter->getName() . "\n";
                echo "✅ 适配器优先级: " . $adapter->getPriority() . "\n";
            } else {
                echo "❌ ReactPHP 适配器不支持当前环境\n";
            }
        } else {
            echo "⚠️  think-runtime 包未安装，跳过适配器测试\n";
        }
        
        echo "\n现在可以使用 ReactPHP Runtime:\n";
        echo "php think runtime:start reactphp\n";
        
    } catch (\Exception $e) {
        echo "❌ 功能测试失败: " . $e->getMessage() . "\n";
        echo "请检查依赖是否正确安装\n";
    }
    
} else {
    echo "❌ 缺少以下依赖:\n";
    foreach ($missing as $class) {
        echo "- {$class}\n";
    }
    
    echo "\n解决方案:\n";
    echo "1. 运行自动安装脚本:\n";
    echo "   php vendor/yangweijie/think-runtime/install-reactphp.php\n\n";
    
    echo "2. 手动安装缺失的包:\n";
    
    $packages = [];
    foreach ($missing as $class) {
        if (strpos($class, 'React\\') === 0) {
            if (strpos($class, 'EventLoop') !== false) {
                $packages['react/event-loop'] = true;
            } elseif (strpos($class, 'Http') !== false) {
                $packages['react/http'] = true;
            } elseif (strpos($class, 'Socket') !== false) {
                $packages['react/socket'] = true;
            } elseif (strpos($class, 'Promise') !== false) {
                $packages['react/promise'] = true;
            }
        } elseif (strpos($class, 'RingCentral\\') === 0) {
            $packages['ringcentral/psr7'] = true;
        }
    }
    
    foreach (array_keys($packages) as $package) {
        echo "   composer require {$package}\n";
    }
    
    echo "\n3. 一键安装所有依赖:\n";
    echo "   composer require react/http react/socket react/promise ringcentral/psr7\n";
}

echo "\n================\n";
echo "检查完成！\n\n";

if (!empty($missing)) {
    echo "注意: ReactPHP 是事件驱动的异步 HTTP 服务器\n";
    echo "特点: 高并发、低内存、支持 WebSocket\n";
    echo "适用场景: API 服务、实时应用、微服务\n\n";
    
    echo "更多信息:\n";
    echo "- ReactPHP 官网: https://reactphp.org/\n";
    echo "- 安装指南: vendor/yangweijie/think-runtime/REACTPHP-INSTALL.md\n";
}
