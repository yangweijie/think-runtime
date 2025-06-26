<?php

/**
 * Swoole Server Example
 * 
 * This example shows how to run ThinkPHP with Swoole server.
 * 
 * Usage:
 * php public/index.php
 */

use think\App;

require_once dirname(__DIR__, 2) . '/vendor/autoload_runtime.php';

return function (array $context): App {
    // Create ThinkPHP application instance
    $app = new App();
    
    // Configure application for Swoole
    $app->setAppPath($context['APP_PATH'] ?? dirname(__DIR__) . '/app/');
    $app->setRuntimePath($context['RUNTIME_PATH'] ?? dirname(__DIR__) . '/runtime/');
    $app->setConfigPath($context['CONFIG_PATH'] ?? dirname(__DIR__) . '/config/');
    
    // Configure for Swoole server
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for Swoole
        ],
        'database' => [
            'break_reconnect' => true, // Reconnect on connection loss
            'deploy' => [
                'type' => 'mysql',
                'read_master' => true,
            ],
        ],
        'cache' => [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'type' => 'redis',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'persistent' => true,
                ],
            ],
        ],
        'log' => [
            'default' => 'file',
            'channels' => [
                'file' => [
                    'type' => 'file',
                    'path' => './runtime/log/',
                    'max_files' => 30,
                ],
            ],
        ],
    ]);
    
    return $app;
};
