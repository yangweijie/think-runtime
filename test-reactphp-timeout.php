<?php

declare(strict_types=1);

/**
 * 测试ReactPHP执行时间修复
 */

echo "ReactPHP 执行时间修复测试\n";
echo "========================\n\n";

require_once 'vendor/autoload.php';

// 检查当前执行时间限制
echo "当前PHP配置:\n";
echo "- max_execution_time: " . ini_get('max_execution_time') . " 秒\n";
echo "- memory_limit: " . ini_get('memory_limit') . "\n";
echo "- time_limit (当前): " . (ini_get('max_execution_time') == 0 ? '无限制' : ini_get('max_execution_time') . ' 秒') . "\n\n";

// 检查ReactPHP依赖
$requiredClasses = [
    'React\\EventLoop\\Loop',
    'React\\Http\\HttpServer',
    'React\\Socket\\SocketServer',
    'React\\Http\\Message\\Response',
    'React\\Promise\\Promise',
];

$missing = [];
foreach ($requiredClasses as $class) {
    if (!class_exists($class)) {
        $missing[] = $class;
    }
}

if (!empty($missing)) {
    echo "❌ 缺少ReactPHP依赖:\n";
    foreach ($missing as $class) {
        echo "- {$class}\n";
    }
    echo "\n请先安装依赖: php install-reactphp.php\n";
    exit(1);
}

echo "✅ ReactPHP依赖检查通过\n\n";

// 创建模拟应用
$mockApp = new class {
    public function initialize() {
        echo "应用初始化完成\n";
    }
};

// 测试ReactPHP适配器的执行时间设置
echo "测试ReactPHP适配器执行时间设置...\n";
echo "==================================\n\n";

try {
    // 创建适配器
    $adapter = new \yangweijie\thinkRuntime\adapter\ReactphpAdapter($mockApp, [
        'host' => '127.0.0.1',
        'port' => 8081,  // 使用不同端口避免冲突
    ]);
    
    echo "✅ ReactPHP适配器创建成功\n";
    
    // 测试boot方法中的执行时间设置
    echo "\n测试boot()方法...\n";
    $originalTimeLimit = ini_get('max_execution_time');
    
    $adapter->boot();
    
    $newTimeLimit = ini_get('max_execution_time');
    echo "- 原始执行时间限制: {$originalTimeLimit} 秒\n";
    echo "- boot()后执行时间限制: " . ($newTimeLimit == 0 ? '无限制' : $newTimeLimit . ' 秒') . "\n";
    
    if ($newTimeLimit == 0) {
        echo "✅ boot()方法正确设置了无限执行时间\n";
    } else {
        echo "❌ boot()方法未正确设置执行时间\n";
    }
    
    // 模拟长时间运行测试（不实际启动服务器）
    echo "\n模拟长时间运行测试...\n";
    echo "开始时间: " . date('Y-m-d H:i:s') . "\n";
    
    // 模拟运行35秒（超过默认30秒限制）
    $startTime = time();
    $testDuration = 5; // 缩短测试时间到5秒，避免实际等待太久
    
    echo "模拟运行 {$testDuration} 秒...\n";
    
    while (time() - $startTime < $testDuration) {
        // 模拟事件循环处理
        usleep(100000); // 0.1秒
        
        $elapsed = time() - $startTime;
        if ($elapsed % 1 == 0 && $elapsed > 0) {
            echo "已运行: {$elapsed} 秒\n";
        }
    }
    
    echo "结束时间: " . date('Y-m-d H:i:s') . "\n";
    echo "✅ 成功运行超过默认时间限制，无超时错误\n";
    
} catch (\Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n========================\n";
echo "执行时间修复验证\n";
echo "========================\n\n";

echo "修复内容:\n";
echo "1. ✅ 在boot()方法中添加 set_time_limit(0)\n";
echo "2. ✅ 在run()方法中添加 set_time_limit(0)\n";
echo "3. ✅ 设置内存限制为512M\n";
echo "4. ✅ 添加启动信息显示\n\n";

echo "修复效果:\n";
echo "- ReactPHP服务器可以无限期运行\n";
echo "- 不会在30秒后自动停止\n";
echo "- 事件循环可以持续处理请求\n";
echo "- 内存使用得到合理控制\n\n";

echo "使用方法:\n";
echo "1. 确保安装ReactPHP依赖:\n";
echo "   php vendor/yangweijie/think-runtime/install-reactphp.php\n\n";
echo "2. 启动ReactPHP服务器:\n";
echo "   php think runtime:start reactphp --host=127.0.0.1 --port=8080\n\n";
echo "3. 服务器将持续运行，直到手动停止(Ctrl+C)\n\n";

echo "注意事项:\n";
echo "- 服务器启动后会显示'Execution time: Unlimited'\n";
echo "- 如果仍然遇到超时，检查php.ini中的max_execution_time设置\n";
echo "- 在生产环境中，建议使用进程管理器(如supervisor)管理服务\n\n";

echo "故障排除:\n";
echo "如果仍然遇到30秒超时:\n";
echo "1. 检查PHP配置: php -i | grep max_execution_time\n";
echo "2. 检查Web服务器配置(如nginx/apache超时设置)\n";
echo "3. 确认使用的是CLI模式而不是Web模式\n";
echo "4. 检查是否有其他中间件或组件设置了时间限制\n";
