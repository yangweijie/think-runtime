#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * FrankenPHP Runtime 测试脚本
 * 
 * 测试 FrankenPHP adapter 的各项功能：
 * 1. 配置自动检测
 * 2. Caddyfile 生成
 * 3. ThinkPHP URL 重写规则
 * 4. 日志配置
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

echo "🧪 FrankenPHP Runtime 测试开始\n";
echo str_repeat("=", 50) . "\n";

try {
    // 创建模拟的 ThinkPHP 应用
    $app = new App();
    
    // 创建 FrankenPHP adapter
    $adapter = new FrankenphpAdapter($app);
    
    echo "✅ FrankenPHP adapter 创建成功\n";
    
    // 测试可用性检查
    echo "\n📋 测试运行时可用性...\n";
    $isAvailable = $adapter->isAvailable();
    echo "FrankenPHP 可用性: " . ($isAvailable ? "✅ 可用" : "❌ 不可用") . "\n";
    
    if (!$isAvailable) {
        echo "⚠️  FrankenPHP 不可用，请先安装 FrankenPHP\n";
        echo "安装方法: https://frankenphp.dev/docs/install/\n";
    }
    
    // 测试配置
    echo "\n⚙️  测试配置...\n";
    
    // 测试默认配置
    $defaultConfig = $adapter->getConfig();
    echo "默认监听地址: {$defaultConfig['listen']}\n";
    echo "默认文档根目录: {$defaultConfig['root']}\n";
    echo "默认Worker数量: {$defaultConfig['worker_num']}\n";
    echo "URL重写: " . ($defaultConfig['enable_rewrite'] ? '启用' : '禁用') . "\n";
    echo "隐藏入口: " . ($defaultConfig['hide_index'] ? '启用' : '禁用') . "\n";
    
    // 测试自定义配置
    echo "\n🔧 测试自定义配置...\n";
    $customConfig = [
        'listen' => ':9000',
        'worker_num' => 8,
        'debug' => true,
        'auto_https' => false,
        'hide_index' => false,
    ];
    
    $adapter->setConfig($customConfig);
    $mergedConfig = $adapter->getConfig();
    
    echo "自定义监听地址: {$mergedConfig['listen']}\n";
    echo "自定义Worker数量: {$mergedConfig['worker_num']}\n";
    echo "调试模式: " . ($mergedConfig['debug'] ? '启用' : '禁用') . "\n";
    echo "自动HTTPS: " . ($mergedConfig['auto_https'] ? '启用' : '禁用') . "\n";
    
    // 测试 Caddyfile 生成
    echo "\n📄 测试 Caddyfile 生成...\n";
    
    // 使用反射来访问 protected 方法
    $reflection = new ReflectionClass($adapter);
    $createCaddyfileMethod = $reflection->getMethod('createCaddyfile');
    $createCaddyfileMethod->setAccessible(true);
    
    $autoDetectConfigMethod = $reflection->getMethod('autoDetectConfig');
    $autoDetectConfigMethod->setAccessible(true);
    
    // 模拟自动检测配置
    try {
        $autoDetectConfigMethod->invoke($adapter);
        echo "✅ 配置自动检测完成\n";
    } catch (Exception $e) {
        echo "⚠️  配置自动检测失败: " . $e->getMessage() . "\n";
    }
    
    // 生成 Caddyfile
    $caddyfile = $createCaddyfileMethod->invoke($adapter, $mergedConfig);
    
    echo "生成的 Caddyfile 内容:\n";
    echo str_repeat("-", 40) . "\n";
    echo $caddyfile;
    echo str_repeat("-", 40) . "\n";
    
    // 保存测试用的 Caddyfile
    $testCaddyfilePath = __DIR__ . '/Caddyfile.test';
    file_put_contents($testCaddyfilePath, $caddyfile);
    echo "✅ 测试 Caddyfile 已保存到: {$testCaddyfilePath}\n";
    
    // 测试 ThinkPHP 重写规则生成
    echo "\n🔗 测试 ThinkPHP 重写规则...\n";
    
    $generateRewriteRulesMethod = $reflection->getMethod('generateThinkPHPRewriteRules');
    $generateRewriteRulesMethod->setAccessible(true);
    
    $rewriteRules = $generateRewriteRulesMethod->invoke($adapter, $mergedConfig);
    echo "生成的重写规则:\n";
    echo str_repeat("-", 30) . "\n";
    echo $rewriteRules;
    echo str_repeat("-", 30) . "\n";
    
    // 测试 PHP 配置生成
    echo "\n🐘 测试 PHP 配置生成...\n";
    
    $generatePHPConfigMethod = $reflection->getMethod('generatePHPConfig');
    $generatePHPConfigMethod->setAccessible(true);
    
    $phpConfig = $generatePHPConfigMethod->invoke($adapter, $mergedConfig);
    echo "生成的 PHP 配置:\n";
    echo str_repeat("-", 30) . "\n";
    echo $phpConfig;
    echo str_repeat("-", 30) . "\n";
    
    // 测试不同配置场景
    echo "\n🎭 测试不同配置场景...\n";
    
    // 场景1: 生产环境配置
    echo "\n场景1: 生产环境配置\n";
    $prodConfig = [
        'debug' => false,
        'auto_https' => true,
        'worker_num' => 16,
        'hide_index' => true,
        'enable_rewrite' => true,
    ];
    $adapter->setConfig($prodConfig);
    $prodCaddyfile = $createCaddyfileMethod->invoke($adapter, array_merge($mergedConfig, $prodConfig));
    echo "✅ 生产环境 Caddyfile 生成成功\n";
    
    // 场景2: 开发环境配置
    echo "\n场景2: 开发环境配置\n";
    $devConfig = [
        'debug' => true,
        'auto_https' => false,
        'worker_num' => 2,
        'hide_index' => false,
        'enable_rewrite' => true,
    ];
    $adapter->setConfig($devConfig);
    $devCaddyfile = $createCaddyfileMethod->invoke($adapter, array_merge($mergedConfig, $devConfig));
    echo "✅ 开发环境 Caddyfile 生成成功\n";
    
    echo "\n🎉 所有测试完成！\n";
    echo str_repeat("=", 50) . "\n";
    
    // 清理测试文件
    if (file_exists($testCaddyfilePath)) {
        unlink($testCaddyfilePath);
        echo "🧹 清理测试文件完成\n";
    }
    
} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✨ FrankenPHP Runtime 测试完成！\n";
