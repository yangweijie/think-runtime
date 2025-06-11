<?php

declare(strict_types=1);

/**
 * ReactPHP服务器启动脚本示例
 * 
 * 此文件演示如何手动启动ReactPHP服务器
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/../vendor/autoload.php';

// 创建应用实例
$app = new App();

// 初始化应用
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 自定义ReactPHP配置
$options = [
    'host' => '0.0.0.0',
    'port' => 8080,
    'max_connections' => 1000,
    'timeout' => 30,
    'enable_keepalive' => true,
    'keepalive_timeout' => 5,
    'max_request_size' => '8M',
    'enable_compression' => true,
    'debug' => true,
    'access_log' => true,
    'error_log' => true,
    'websocket' => false,
    'ssl' => [
        'enabled' => false,
        'cert' => '',
        'key' => '',
    ],
];

echo "Starting ReactPHP Server...\n";
echo "Host: {$options['host']}\n";
echo "Port: {$options['port']}\n";
echo "Max Connections: {$options['max_connections']}\n";
echo "Event-driven: Yes\n";
echo "Async I/O: Yes\n";
echo "WebSocket: " . ($options['websocket'] ? 'Enabled' : 'Disabled') . "\n";
echo "SSL/TLS: " . ($options['ssl']['enabled'] ? 'Enabled' : 'Disabled') . "\n";
echo "Debug Mode: " . ($options['debug'] ? 'Enabled' : 'Disabled') . "\n";
echo "Press Ctrl+C to stop the server\n\n";

// 启动ReactPHP服务器
$manager->start('reactphp', $options);
