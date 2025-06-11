<?php

declare(strict_types=1);

/**
 * ReactPHP Runtime 依赖自动安装脚本
 */

echo "ReactPHP Runtime 依赖安装\n";
echo "========================\n\n";

// 检查是否在正确的目录
if (!file_exists('composer.json')) {
    echo "❌ 未找到 composer.json 文件\n";
    echo "请在项目根目录下运行此脚本\n";
    exit(1);
}

echo "✅ 检测到 Composer 项目\n\n";

// 需要安装的包
$packages = [
    'react/http' => 'ReactPHP HTTP 服务器组件',
    'react/socket' => 'ReactPHP Socket 服务器组件',
    'react/promise' => 'ReactPHP Promise 实现',
    'ringcentral/psr7' => 'RingCentral PSR-7 HTTP 消息实现',
];

// 可选包
$optionalPackages = [
    'react/stream' => 'ReactPHP 流处理组件',
    'react/dns' => 'ReactPHP DNS 解析组件',
];

echo "开始安装必需依赖...\n";
echo "==================\n\n";

$failed = [];
$success = [];

foreach ($packages as $package => $description) {
    echo "安装 {$package} ({$description})...\n";
    
    // 检查是否已经安装
    $checkCmd = "composer show {$package} 2>/dev/null";
    $checkResult = shell_exec($checkCmd);
    
    if ($checkResult && strpos($checkResult, $package) !== false) {
        echo "   ✅ {$package} 已安装\n";
        $success[] = $package;
        continue;
    }
    
    // 安装包
    $installCmd = "composer require {$package} 2>&1";
    $result = shell_exec($installCmd);
    
    if ($result && (strpos($result, 'Installation failed') !== false || strpos($result, 'Could not find') !== false)) {
        echo "   ❌ {$package} 安装失败\n";
        echo "   错误信息: " . trim($result) . "\n";
        $failed[] = $package;
    } else {
        echo "   ✅ {$package} 安装成功\n";
        $success[] = $package;
    }
    
    echo "\n";
}

// 询问是否安装可选包
if (empty($failed)) {
    echo "是否安装可选依赖？(y/n): ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($input) === 'y' || strtolower($input) === 'yes') {
        echo "\n安装可选依赖...\n";
        echo "================\n\n";
        
        foreach ($optionalPackages as $package => $description) {
            echo "安装 {$package} ({$description})...\n";
            
            $installCmd = "composer require {$package} 2>&1";
            $result = shell_exec($installCmd);
            
            if ($result && (strpos($result, 'Installation failed') !== false || strpos($result, 'Could not find') !== false)) {
                echo "   ⚠️  {$package} 安装失败 (可选)\n";
            } else {
                echo "   ✅ {$package} 安装成功\n";
                $success[] = $package;
            }
            
            echo "\n";
        }
    }
}

// 更新自动加载
echo "更新 Composer 自动加载...\n";
$dumpResult = shell_exec("composer dump-autoload 2>&1");
if ($dumpResult) {
    echo "✅ 自动加载更新完成\n\n";
} else {
    echo "⚠️  自动加载更新可能有问题\n\n";
}

// 验证安装
echo "验证安装结果...\n";
echo "================\n\n";

$requiredClasses = [
    'React\\EventLoop\\Loop' => 'ReactPHP 事件循环',
    'React\\Http\\HttpServer' => 'ReactPHP HTTP 服务器',
    'React\\Socket\\SocketServer' => 'ReactPHP Socket 服务器',
    'React\\Http\\Message\\Response' => 'ReactPHP HTTP 响应',
    'React\\Promise\\Promise' => 'ReactPHP Promise',
    'RingCentral\\Psr7\\Request' => 'RingCentral PSR-7 请求',
    'RingCentral\\Psr7\\Response' => 'RingCentral PSR-7 响应',
];

$allOk = true;
foreach ($requiredClasses as $class => $desc) {
    if (class_exists($class)) {
        echo "✅ {$desc}: {$class}\n";
    } else {
        echo "❌ {$desc}: {$class}\n";
        $allOk = false;
    }
}

echo "\n";

// 测试 ReactPHP 适配器
if ($allOk) {
    echo "测试 ReactPHP 适配器...\n";
    
    try {
        // 检查 think-runtime 是否可用
        if (class_exists('yangweijie\\thinkRuntime\\adapter\\ReactphpAdapter')) {
            // 创建模拟应用
            $mockApp = new class {
                public function initialize() {}
            };
            
            $adapter = new \yangweijie\thinkRuntime\adapter\ReactphpAdapter($mockApp);
            
            if ($adapter->isSupported()) {
                echo "✅ ReactPHP 适配器支持当前环境\n";
            } else {
                echo "❌ ReactPHP 适配器不支持当前环境\n";
                $allOk = false;
            }
        } else {
            echo "⚠️  think-runtime 包未安装，无法测试适配器\n";
        }
    } catch (\Exception $e) {
        echo "❌ ReactPHP 适配器测试失败: " . $e->getMessage() . "\n";
        $allOk = false;
    }
}

echo "\n========================\n";

if ($allOk && empty($failed)) {
    echo "🎉 ReactPHP Runtime 安装完成！\n\n";
    
    echo "现在可以使用以下命令:\n";
    echo "1. 查看运行时信息: php think runtime:info\n";
    echo "2. 启动 ReactPHP 服务器: php think runtime:start reactphp\n";
    echo "3. 指定参数启动: php think runtime:start reactphp --host=127.0.0.1 --port=8080\n\n";
    
    echo "ReactPHP 特性:\n";
    echo "- 事件驱动异步处理\n";
    echo "- 高并发支持\n";
    echo "- 低内存占用\n";
    echo "- 支持 WebSocket (如果启用)\n";
    
} else {
    echo "❌ 安装过程中遇到问题\n\n";
    
    if (!empty($failed)) {
        echo "失败的包:\n";
        foreach ($failed as $package) {
            echo "- {$package}\n";
        }
        echo "\n";
    }
    
    echo "建议:\n";
    echo "1. 检查网络连接\n";
    echo "2. 更新 Composer: composer self-update\n";
    echo "3. 清除缓存: composer clear-cache\n";
    echo "4. 手动安装失败的包\n";
    echo "5. 查看详细错误信息\n\n";
    
    echo "手动安装命令:\n";
    foreach ($failed as $package) {
        echo "composer require {$package}\n";
    }
}

echo "\n如需帮助，请查看 REACTPHP-INSTALL.md 文档\n";
