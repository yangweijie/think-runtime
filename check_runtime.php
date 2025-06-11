<?php

echo "=== 运行时环境检查 ===\n\n";

// 1. RoadRunner 检查
echo "1. RoadRunner 支持检查:\n";
echo "   - Worker 类: " . (class_exists('Spiral\\RoadRunner\\Worker') ? '✅ 存在' : '❌ 不存在') . "\n";
echo "   - PSR7Worker 类: " . (class_exists('Spiral\\RoadRunner\\Http\\PSR7Worker') ? '✅ 存在' : '❌ 不存在') . "\n";

$rrMode = getenv('RR_MODE') ?: (isset($_SERVER['RR_MODE']) ? $_SERVER['RR_MODE'] : null);
echo "   - RR_MODE 环境变量: " . ($rrMode ? "✅ {$rrMode}" : '❌ 未设置') . "\n";

$roadrunnerSupported = class_exists('Spiral\\RoadRunner\\Worker') && 
                      class_exists('Spiral\\RoadRunner\\Http\\PSR7Worker') && 
                      $rrMode !== null;
echo "   - RoadRunner 总体支持: " . ($roadrunnerSupported ? '✅ 支持' : '❌ 不支持') . "\n\n";

// 2. 其他运行时检查
echo "2. 其他运行时支持:\n";
echo "   - Swoole: " . (extension_loaded('swoole') ? '✅ 可用' : '❌ 不可用') . "\n";
echo "   - ReactPHP: " . (class_exists('React\\EventLoop\\Loop') ? '✅ 可用' : '❌ 不可用') . "\n";
echo "   - FrankenPHP: " . (function_exists('frankenphp_handle_request') ? '✅ 可用' : '❌ 不可用') . "\n";
echo "   - Ripple: " . (version_compare(PHP_VERSION, '8.1.0', '>=') ? '✅ PHP版本支持' : '❌ 需要PHP 8.1+') . "\n\n";

// 3. 当前环境信息
echo "3. 当前环境信息:\n";
echo "   - PHP 版本: " . PHP_VERSION . "\n";
echo "   - SAPI: " . php_sapi_name() . "\n";
echo "   - 操作系统: " . PHP_OS . "\n\n";

// 4. RoadRunner 安装建议
if (!$roadrunnerSupported) {
    echo "4. RoadRunner 安装建议:\n";
    
    if (!class_exists('Spiral\\RoadRunner\\Worker')) {
        echo "   ❌ 缺少 RoadRunner PHP 包\n";
        echo "      安装命令: composer require spiral/roadrunner-http\n";
    }
    
    if ($rrMode === null) {
        echo "   ❌ 缺少 RoadRunner 环境\n";
        echo "      需要下载并配置 RoadRunner 二进制文件\n";
        echo "      1. 下载: https://github.com/roadrunner-server/roadrunner/releases\n";
        echo "      2. 配置: 创建 .rr.yaml 配置文件\n";
        echo "      3. 启动: ./rr serve\n";
    }
    
    echo "\n   推荐使用已可用的运行时:\n";
    if (class_exists('React\\EventLoop\\Loop')) {
        echo "   ✅ ReactPHP: php think runtime:start reactphp\n";
    }
    if (extension_loaded('swoole')) {
        echo "   ✅ Swoole: php think runtime:start swoole\n";
    }
    echo "   ✅ PHP 内置服务器: php think run\n";
}

echo "\n=== 检查完成 ===\n";
