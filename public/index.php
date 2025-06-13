<?php

declare(strict_types=1);

/**
 * ThinkPHP Runtime 扩展包 - FrankenPHP 入口文件
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\config\RuntimeConfig;

// 设置错误报告级别（抑制弃用警告）
error_reporting(E_ERROR | E_WARNING | E_PARSE);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ThinkPHP Runtime 扩展包 - FrankenPHP</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007acc;
        }
        .status {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007acc;
        }
        .info-card h3 {
            margin-top: 0;
            color: #007acc;
        }
        .adapters {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Monaco', 'Consolas', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 ThinkPHP Runtime 扩展包</h1>
            <p>FrankenPHP 服务器运行中</p>
        </div>

        <div class="status">
            <h2>✅ 服务器状态</h2>
            <p><strong>FrankenPHP 服务器已成功启动！</strong></p>
            <p>当前时间: <?= date('Y-m-d H:i:s') ?></p>
            <p>服务器地址: <code>http://localhost:8080</code></p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>🐘 PHP 环境</h3>
                <p><strong>版本:</strong> <?= PHP_VERSION ?></p>
                <p><strong>SAPI:</strong> <?= php_sapi_name() ?></p>
                <p><strong>内存限制:</strong> <?= ini_get('memory_limit') ?></p>
            </div>

            <div class="info-card">
                <h3>🔧 FrankenPHP 信息</h3>
                <p><strong>版本:</strong> <?= $_SERVER['FRANKENPHP_VERSION'] ?? '未知' ?></p>
                <p><strong>Worker 模式:</strong> <?= isset($_SERVER['FRANKENPHP_WORKER']) ? '是' : '否' ?></p>
                <p><strong>HTTP/2:</strong> <?= isset($_SERVER['HTTP2']) ? '支持' : '不支持' ?></p>
            </div>
        </div>

        <div class="adapters">
            <h3>📦 可用的运行时适配器</h3>
            <ul>
                <?php
                $adapters = [
                    'swoole' => extension_loaded('swoole'),
                    'frankenphp' => isset($_SERVER['FRANKENPHP_VERSION']),
                    'reactphp' => class_exists('React\\EventLoop\\Loop'),
                    'ripple' => class_exists('Ripple\\Http\\Server'),
                    'roadrunner' => isset($_SERVER['RR_MODE']),
                ];

                foreach ($adapters as $name => $available) {
                    $status = $available ? '✅ 可用' : '❌ 不可用';
                    echo "<li><strong>{$name}:</strong> {$status}</li>";
                }
                ?>
            </ul>
        </div>

        <div class="info-card">
            <h3>🛠️ 使用说明</h3>
            <p>这是一个演示页面，展示 FrankenPHP 服务器正在正常运行。</p>
            <p>要在您的 ThinkPHP 项目中使用此扩展包：</p>
            <ol>
                <li>安装扩展包: <code>composer require yangweijie/think-runtime</code></li>
                <li>配置运行时: 编辑 <code>config/runtime.php</code></li>
                <li>启动服务器: <code>php think runtime:start frankenphp</code></li>
            </ol>
        </div>

        <div class="footer">
            <p>ThinkPHP Runtime 扩展包 - 让您的应用运行得更快！</p>
            <p>项目地址: <a href="https://github.com/yangweijie/think-runtime" target="_blank">GitHub</a></p>
        </div>
    </div>
</body>
</html>
