<?php

declare(strict_types=1);

/**
 * 手动注册ThinkPHP Runtime服务和命令
 * 当自动发现不工作时使用
 */

echo "手动注册ThinkPHP Runtime服务\n";
echo "============================\n\n";

// 检查环境
if (!file_exists('think') || !file_exists('app')) {
    echo "❌ 请在ThinkPHP项目根目录下运行此脚本\n";
    exit(1);
}

require_once 'vendor/autoload.php';

try {
    // 1. 创建service.php配置文件
    echo "1. 创建服务配置文件...\n";
    
    $serviceConfig = "<?php\n\n";
    $serviceConfig .= "// ThinkPHP服务提供者配置\n";
    $serviceConfig .= "return [\n";
    $serviceConfig .= "    \\yangweijie\\thinkRuntime\\service\\RuntimeService::class,\n";
    $serviceConfig .= "];\n";
    
    if (!file_exists('config/service.php')) {
        file_put_contents('config/service.php', $serviceConfig);
        echo "   ✅ 创建 config/service.php\n";
    } else {
        // 检查是否已经包含我们的服务
        $existing = file_get_contents('config/service.php');
        if (strpos($existing, 'RuntimeService') === false) {
            // 追加到现有配置
            $existing = str_replace('];', "    \\yangweijie\\thinkRuntime\\service\\RuntimeService::class,\n];", $existing);
            file_put_contents('config/service.php', $existing);
            echo "   ✅ 更新 config/service.php\n";
        } else {
            echo "   ✅ config/service.php 已包含RuntimeService\n";
        }
    }
    
    // 2. 创建console.php配置文件
    echo "\n2. 创建命令配置文件...\n";
    
    $consoleConfig = "<?php\n\n";
    $consoleConfig .= "// ThinkPHP命令行配置\n";
    $consoleConfig .= "return [\n";
    $consoleConfig .= "    'commands' => [\n";
    $consoleConfig .= "        \\yangweijie\\thinkRuntime\\command\\RuntimeStartCommand::class,\n";
    $consoleConfig .= "        \\yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand::class,\n";
    $consoleConfig .= "    ],\n";
    $consoleConfig .= "];\n";
    
    if (!file_exists('config/console.php')) {
        file_put_contents('config/console.php', $consoleConfig);
        echo "   ✅ 创建 config/console.php\n";
    } else {
        // 检查是否已经包含我们的命令
        $existing = file_get_contents('config/console.php');
        if (strpos($existing, 'RuntimeStartCommand') === false) {
            // 追加到现有配置
            if (strpos($existing, "'commands'") !== false) {
                $existing = str_replace(
                    "],\n];",
                    "        \\yangweijie\\thinkRuntime\\command\\RuntimeStartCommand::class,\n" .
                    "        \\yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand::class,\n" .
                    "    ],\n];",
                    $existing
                );
            } else {
                $existing = str_replace(
                    "];",
                    "    'commands' => [\n" .
                    "        \\yangweijie\\thinkRuntime\\command\\RuntimeStartCommand::class,\n" .
                    "        \\yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand::class,\n" .
                    "    ],\n];",
                    $existing
                );
            }
            file_put_contents('config/console.php', $existing);
            echo "   ✅ 更新 config/console.php\n";
        } else {
            echo "   ✅ config/console.php 已包含Runtime命令\n";
        }
    }
    
    // 3. 创建runtime.php配置文件
    echo "\n3. 创建运行时配置文件...\n";
    
    if (!file_exists('config/runtime.php')) {
        $source = 'vendor/yangweijie/think-runtime/config/runtime.php';
        if (file_exists($source)) {
            copy($source, 'config/runtime.php');
            echo "   ✅ 复制 config/runtime.php\n";
        } else {
            echo "   ❌ 源配置文件不存在: {$source}\n";
        }
    } else {
        echo "   ✅ config/runtime.php 已存在\n";
    }
    
    // 4. 清除缓存
    echo "\n4. 清除缓存...\n";
    
    if (file_exists('runtime/cache')) {
        $files = glob('runtime/cache/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "   ✅ 清除运行时缓存\n";
    }
    
    // 5. 测试注册
    echo "\n5. 测试服务注册...\n";
    
    $app = new \think\App();
    $app->initialize();
    
    // 手动注册服务
    $service = new \yangweijie\thinkRuntime\service\RuntimeService($app);
    $service->register();
    $service->boot();
    
    if ($app->has('runtime.config')) {
        echo "   ✅ runtime.config 服务注册成功\n";
    } else {
        echo "   ❌ runtime.config 服务注册失败\n";
    }
    
    if ($app->has('runtime.manager')) {
        echo "   ✅ runtime.manager 服务注册成功\n";
    } else {
        echo "   ❌ runtime.manager 服务注册失败\n";
    }
    
    echo "\n============================\n";
    echo "✅ 手动注册完成！\n\n";
    
    echo "现在请运行以下命令测试:\n";
    echo "php think list | grep runtime\n";
    echo "php think runtime:info\n\n";
    
    echo "如果仍然看不到命令，请尝试:\n";
    echo "php think service:discover\n";
    echo "php think clear\n";
    
} catch (\Exception $e) {
    echo "❌ 注册过程中出错: {$e->getMessage()}\n";
    echo "\n请检查:\n";
    echo "1. 是否有写入config目录的权限\n";
    echo "2. ThinkPHP版本是否兼容 (需要8.0+)\n";
    echo "3. think-runtime包是否正确安装\n";
}
