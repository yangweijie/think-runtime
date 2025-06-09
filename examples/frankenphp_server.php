<?php

declare(strict_types=1);

/**
 * FrankenPHP服务器启动脚本示例
 * 
 * 此文件演示如何手动启动FrankenPHP服务器
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/vendor/autoload.php';

// 创建应用实例
$app = new App();

// 初始化应用
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 自定义FrankenPHP配置
$options = [
    'listen' => ':8080',
    'worker_num' => 4,
    'max_requests' => 1000,
    'auto_https' => false, // 开发环境关闭自动HTTPS
    'http2' => true,
    'http3' => false,
    'debug' => true,
    'access_log' => true,
    'error_log' => true,
    'log_level' => 'DEBUG',
    'root' => 'public',
    'index' => 'index.php',
    'env' => [
        'APP_ENV' => 'development',
        'APP_DEBUG' => 'true',
    ],
];

echo "Starting FrankenPHP Server...\n";
echo "Listen: {$options['listen']}\n";
echo "Workers: {$options['worker_num']}\n";
echo "Document Root: {$options['root']}\n";
echo "Features: HTTP/2" . ($options['auto_https'] ? ', Auto HTTPS' : '') . "\n";
echo "Debug Mode: " . ($options['debug'] ? 'Enabled' : 'Disabled') . "\n";
echo "Press Ctrl+C to stop the server\n\n";

// 启动FrankenPHP服务器
$manager->start('frankenphp', $options);
