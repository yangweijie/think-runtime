<?php

declare(strict_types=1);

/**
 * 测试ThinkPHP原生命令是否正常工作
 */

echo "ThinkPHP Runtime 命令测试\n";
echo "========================\n\n";

// 检查是否在ThinkPHP项目中
if (!file_exists('think') || !file_exists('app')) {
    echo "❌ 请在ThinkPHP项目根目录下运行此脚本\n";
    exit(1);
}

require_once 'vendor/autoload.php';

echo "1. 检查ThinkPHP命令类...\n";

// 检查ThinkPHP命令基类
if (class_exists('think\\console\\Command')) {
    echo "   ✅ think\\console\\Command 已加载\n";
} else {
    echo "   ❌ think\\console\\Command 未加载\n";
    exit(1);
}

// 检查我们的命令类
$commandClasses = [
    'yangweijie\\thinkRuntime\\command\\RuntimeStartCommand',
    'yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand',
];

foreach ($commandClasses as $class) {
    if (class_exists($class)) {
        echo "   ✅ {$class} 已加载\n";
        
        // 检查是否继承自正确的基类
        if (is_subclass_of($class, 'think\\console\\Command')) {
            echo "      ✅ 正确继承自 think\\console\\Command\n";
        } else {
            echo "      ❌ 未正确继承自 think\\console\\Command\n";
        }
    } else {
        echo "   ❌ {$class} 未加载\n";
    }
}

echo "\n2. 测试命令实例化...\n";

try {
    // 创建应用实例
    $app = new \think\App();
    $app->initialize();
    
    // 测试RuntimeInfoCommand
    echo "   测试 RuntimeInfoCommand...\n";
    $infoCommand = new \yangweijie\thinkRuntime\command\RuntimeInfoCommand();
    $infoCommand->setApp($app);
    echo "      ✅ RuntimeInfoCommand 实例化成功\n";
    
    // 测试RuntimeStartCommand
    echo "   测试 RuntimeStartCommand...\n";
    $startCommand = new \yangweijie\thinkRuntime\command\RuntimeStartCommand();
    $startCommand->setApp($app);
    echo "      ✅ RuntimeStartCommand 实例化成功\n";
    
} catch (\Exception $e) {
    echo "   ❌ 命令实例化失败: {$e->getMessage()}\n";
}

echo "\n3. 测试服务注册...\n";

try {
    $app = new \think\App();
    $app->initialize();
    
    // 手动注册服务
    $service = new \yangweijie\thinkRuntime\service\RuntimeService($app);
    $service->register();
    $service->boot();
    
    if ($app->has('runtime.config')) {
        echo "   ✅ runtime.config 服务已注册\n";
    } else {
        echo "   ❌ runtime.config 服务未注册\n";
    }
    
    if ($app->has('runtime.manager')) {
        echo "   ✅ runtime.manager 服务已注册\n";
    } else {
        echo "   ❌ runtime.manager 服务未注册\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ 服务注册失败: {$e->getMessage()}\n";
}

echo "\n4. 测试命令配置...\n";

try {
    $app = new \think\App();
    $app->initialize();
    
    // 注册服务
    $service = new \yangweijie\thinkRuntime\service\RuntimeService($app);
    $service->register();
    $service->boot();
    
    // 测试命令配置
    $infoCommand = new \yangweijie\thinkRuntime\command\RuntimeInfoCommand();
    $infoCommand->setApp($app);
    
    // 获取命令名称和描述
    $reflection = new \ReflectionClass($infoCommand);
    $configureMethod = $reflection->getMethod('configure');
    $configureMethod->setAccessible(true);
    $configureMethod->invoke($infoCommand);
    
    echo "   ✅ 命令配置测试成功\n";
    
} catch (\Exception $e) {
    echo "   ❌ 命令配置测试失败: {$e->getMessage()}\n";
}

echo "\n5. 生成配置文件...\n";

// 创建必要的配置文件
$configs = [
    'config/service.php' => "<?php\n\nreturn [\n    \\yangweijie\\thinkRuntime\\service\\RuntimeService::class,\n];\n",
    'config/console.php' => "<?php\n\nreturn [\n    'commands' => [\n        \\yangweijie\\thinkRuntime\\command\\RuntimeStartCommand::class,\n        \\yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand::class,\n    ],\n];\n",
];

foreach ($configs as $file => $content) {
    if (!file_exists($file)) {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, $content);
        echo "   ✅ 创建 {$file}\n";
    } else {
        echo "   ✅ {$file} 已存在\n";
    }
}

// 复制runtime配置文件
if (!file_exists('config/runtime.php')) {
    $source = 'vendor/yangweijie/think-runtime/config/runtime.php';
    if (file_exists($source)) {
        copy($source, 'config/runtime.php');
        echo "   ✅ 复制 config/runtime.php\n";
    } else {
        echo "   ⚠️  源配置文件不存在: {$source}\n";
    }
} else {
    echo "   ✅ config/runtime.php 已存在\n";
}

echo "\n========================\n";
echo "✅ 测试完成！\n\n";

echo "现在请运行以下命令测试:\n\n";
echo "1. 查看所有命令:\n";
echo "   php think list\n\n";
echo "2. 查看runtime命令:\n";
echo "   php think list | grep runtime\n\n";
echo "3. 运行runtime命令:\n";
echo "   php think runtime:info\n";
echo "   php think runtime:start\n\n";

echo "如果命令仍然不可用，请尝试:\n";
echo "1. 清除缓存: php think clear\n";
echo "2. 重新发现服务: php think service:discover\n";
echo "3. 检查配置: php think config:cache\n\n";

echo "注意: 现在使用的是ThinkPHP原生命令系统，不再依赖Symfony Console\n";
