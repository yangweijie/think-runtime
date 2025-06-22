#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * FrankenPHP Runtime 启动脚本
 * 
 * 用于测试和演示 FrankenPHP adapter 的实际运行
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

// 检查命令行参数
$options = getopt('', [
    'port:',
    'workers:',
    'debug',
    'help',
    'hide-index',
    'show-index',
    'https',
    'no-https'
]);

if (isset($options['help'])) {
    echo "FrankenPHP Runtime 启动脚本\n\n";
    echo "用法: php start_frankenphp.php [选项]\n\n";
    echo "选项:\n";
    echo "  --port=PORT        监听端口 (默认: 8080)\n";
    echo "  --workers=NUM      Worker进程数 (默认: 4)\n";
    echo "  --debug            启用调试模式\n";
    echo "  --hide-index       隐藏入口文件 (默认)\n";
    echo "  --show-index       显示入口文件\n";
    echo "  --https            启用HTTPS\n";
    echo "  --no-https         禁用HTTPS (默认)\n";
    echo "  --help             显示此帮助信息\n\n";
    echo "示例:\n";
    echo "  php start_frankenphp.php --port=9000 --workers=8 --debug\n";
    echo "  php start_frankenphp.php --show-index --https\n\n";
    exit(0);
}

try {
    echo "🚀 启动 FrankenPHP Runtime for ThinkPHP\n";
    echo str_repeat("=", 50) . "\n";
    
    // 创建模拟的 ThinkPHP 应用环境
    $app = new App();
    
    // 设置环境变量模拟
    if (isset($options['debug'])) {
        putenv('app_debug=true');
        $_ENV['app_debug'] = true;
    } else {
        putenv('app_debug=false');
        $_ENV['app_debug'] = false;
    }
    
    // 创建 FrankenPHP adapter
    $adapter = new FrankenphpAdapter($app);
    
    // 构建配置
    $config = [];
    
    // 端口配置
    if (isset($options['port'])) {
        $port = (int) $options['port'];
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException("无效的端口号: {$port}");
        }
        $config['listen'] = ":{$port}";
    }
    
    // Worker数量配置
    if (isset($options['workers'])) {
        $workers = (int) $options['workers'];
        if ($workers < 1 || $workers > 100) {
            throw new InvalidArgumentException("无效的Worker数量: {$workers}");
        }
        $config['worker_num'] = $workers;
    }
    
    // 调试模式
    if (isset($options['debug'])) {
        $config['debug'] = true;
    }
    
    // 入口文件显示/隐藏
    if (isset($options['show-index'])) {
        $config['hide_index'] = false;
    } elseif (isset($options['hide-index'])) {
        $config['hide_index'] = true;
    }
    
    // HTTPS配置
    if (isset($options['https'])) {
        $config['auto_https'] = true;
    } elseif (isset($options['no-https'])) {
        $config['auto_https'] = false;
    }
    
    // 应用配置
    $adapter->setConfig($config);
    
    // 检查可用性
    if (!$adapter->isAvailable()) {
        echo "❌ FrankenPHP 不可用\n";
        echo "请确保已安装 FrankenPHP: https://frankenphp.dev/docs/install/\n";
        exit(1);
    }
    
    echo "✅ FrankenPHP 可用\n";
    
    // 显示配置信息
    $finalConfig = $adapter->getConfig();
    echo "\n📋 运行配置:\n";
    echo "   监听地址: {$finalConfig['listen']}\n";
    echo "   文档根目录: {$finalConfig['root']}\n";
    echo "   Worker数量: {$finalConfig['worker_num']}\n";
    echo "   调试模式: " . ($finalConfig['debug'] ? '启用' : '禁用') . "\n";
    echo "   隐藏入口: " . ($finalConfig['hide_index'] ? '启用' : '禁用') . "\n";
    echo "   自动HTTPS: " . ($finalConfig['auto_https'] ? '启用' : '禁用') . "\n";
    echo "   日志目录: {$finalConfig['log_dir']}\n";
    
    // 显示访问URL示例
    $port = str_replace(':', '', $finalConfig['listen']);
    $protocol = $finalConfig['auto_https'] ? 'https' : 'http';
    echo "\n🌐 访问URL示例:\n";
    if ($finalConfig['hide_index']) {
        echo "   {$protocol}://localhost{$port}/\n";
        echo "   {$protocol}://localhost{$port}/index/hello\n";
        echo "   {$protocol}://localhost{$port}/api/user/list\n";
    } else {
        echo "   {$protocol}://localhost{$port}/index.php\n";
        echo "   {$protocol}://localhost{$port}/index.php/index/hello\n";
        echo "   {$protocol}://localhost{$port}/index.php/api/user/list\n";
    }
    
    echo "\n⚠️  注意: 这是一个测试脚本，实际使用请在真实的ThinkPHP项目中运行\n";
    echo "按 Ctrl+C 停止服务器\n\n";
    
    // 启动适配器
    $adapter->start();
    
} catch (Exception $e) {
    echo "❌ 启动失败: " . $e->getMessage() . "\n";
    if (isset($options['debug'])) {
        echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
} catch (Error $e) {
    echo "❌ 系统错误: " . $e->getMessage() . "\n";
    if (isset($options['debug'])) {
        echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
    }
    exit(1);
}
