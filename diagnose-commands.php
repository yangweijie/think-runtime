<?php

declare(strict_types=1);

/**
 * ThinkPHP Runtime 命令诊断脚本
 * 用于诊断为什么runtime命令没有出现
 */

echo "ThinkPHP Runtime 命令诊断\n";
echo "========================\n\n";

// 检查是否在ThinkPHP项目中
if (!file_exists('think') || !file_exists('app')) {
    echo "❌ 请在ThinkPHP项目根目录下运行此脚本\n";
    exit(1);
}

// 加载ThinkPHP
require_once 'vendor/autoload.php';

echo "1. 检查ThinkPHP环境...\n";

// 检查ThinkPHP版本
if (class_exists('think\\App')) {
    echo "   ✅ ThinkPHP App类已加载\n";
    
    // 尝试获取版本信息
    try {
        $app = new \think\App();
        echo "   ✅ ThinkPHP应用实例创建成功\n";
    } catch (\Exception $e) {
        echo "   ❌ ThinkPHP应用实例创建失败: {$e->getMessage()}\n";
    }
} else {
    echo "   ❌ ThinkPHP App类未找到\n";
    exit(1);
}

echo "\n2. 检查think-runtime包...\n";

// 检查包是否安装
if (!file_exists('vendor/yangweijie/think-runtime')) {
    echo "   ❌ think-runtime包未安装\n";
    exit(1);
}
echo "   ✅ think-runtime包已安装\n";

// 检查服务提供者
if (class_exists('yangweijie\\thinkRuntime\\service\\RuntimeService')) {
    echo "   ✅ RuntimeService类已加载\n";
} else {
    echo "   ❌ RuntimeService类未加载\n";
    exit(1);
}

// 检查命令类
$commandClasses = [
    'yangweijie\\thinkRuntime\\command\\RuntimeStartCommand',
    'yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand',
];

foreach ($commandClasses as $class) {
    if (class_exists($class)) {
        echo "   ✅ {$class} 已加载\n";
    } else {
        echo "   ❌ {$class} 未加载\n";
    }
}

echo "\n3. 检查服务注册...\n";

// 检查composer.json配置
$composerFile = 'vendor/yangweijie/think-runtime/composer.json';
if (file_exists($composerFile)) {
    $composer = json_decode(file_get_contents($composerFile), true);
    if (isset($composer['extra']['think']['services'])) {
        echo "   ✅ composer.json中已配置服务提供者\n";
        foreach ($composer['extra']['think']['services'] as $service) {
            echo "      - {$service}\n";
        }
    } else {
        echo "   ❌ composer.json中未配置服务提供者\n";
    }
} else {
    echo "   ❌ 未找到composer.json文件\n";
}

echo "\n4. 检查ThinkPHP命令系统...\n";

try {
    // 尝试创建Console实例
    if (class_exists('think\\Console')) {
        echo "   ✅ ThinkPHP Console类已加载\n";
        
        // 检查是否可以获取命令列表
        $app = new \think\App();
        $app->initialize();
        
        // 检查服务是否已注册
        if ($app->has('runtime.config')) {
            echo "   ✅ runtime.config服务已注册\n";
        } else {
            echo "   ❌ runtime.config服务未注册\n";
        }
        
        if ($app->has('runtime.manager')) {
            echo "   ✅ runtime.manager服务已注册\n";
        } else {
            echo "   ❌ runtime.manager服务未注册\n";
        }
        
    } else {
        echo "   ❌ ThinkPHP Console类未加载\n";
    }
} catch (\Exception $e) {
    echo "   ❌ 检查命令系统时出错: {$e->getMessage()}\n";
}

echo "\n5. 尝试手动注册服务...\n";

try {
    $app = new \think\App();
    $app->initialize();
    
    // 手动注册服务
    $service = new \yangweijie\thinkRuntime\service\RuntimeService($app);
    $service->register();
    $service->boot();
    
    echo "   ✅ 服务手动注册成功\n";
    
    // 检查服务是否可用
    if ($app->has('runtime.config')) {
        echo "   ✅ runtime.config服务现在可用\n";
    }
    
    if ($app->has('runtime.manager')) {
        echo "   ✅ runtime.manager服务现在可用\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ 手动注册服务失败: {$e->getMessage()}\n";
}

echo "\n========================\n";
echo "诊断完成！\n\n";

echo "解决方案:\n\n";

echo "方案1: 手动发现服务\n";
echo "php think service:discover\n";
echo "php think clear\n\n";

echo "方案2: 手动注册服务提供者\n";
echo "在 config/service.php 中添加:\n";
echo "return [\n";
echo "    \\yangweijie\\thinkRuntime\\service\\RuntimeService::class,\n";
echo "];\n\n";

echo "方案3: 直接在应用中注册命令\n";
echo "在 config/console.php 中添加:\n";
echo "return [\n";
echo "    'commands' => [\n";
echo "        \\yangweijie\\thinkRuntime\\command\\RuntimeStartCommand::class,\n";
echo "        \\yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand::class,\n";
echo "    ],\n";
echo "];\n\n";

echo "方案4: 检查ThinkPHP版本兼容性\n";
echo "确保使用ThinkPHP 8.0+版本\n";
echo "composer show topthink/framework\n\n";

echo "如果问题仍然存在，请运行:\n";
echo "php think list\n";
echo "查看是否有runtime相关命令\n";
