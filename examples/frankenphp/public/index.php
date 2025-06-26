<?php

/**
 * FrankenPHP Server Example
 * 
 * This example shows how to run ThinkPHP with FrankenPHP server in worker mode.
 * 
 * Usage:
 * frankenphp run --worker ./public/index.php
 */

use think\App;

require_once dirname(__DIR__, 2) . '/vendor/autoload_runtime.php';

return function (array $context): App {
    // Create ThinkPHP application instance
    $app = new App();
    
    // Configure application for FrankenPHP
    $app->setAppPath($context['APP_PATH'] ?? dirname(__DIR__) . '/app/');
    $app->setRuntimePath($context['RUNTIME_PATH'] ?? dirname(__DIR__) . '/runtime/');
    $app->setConfigPath($context['CONFIG_PATH'] ?? dirname(__DIR__) . '/config/');
    
    // Configure for FrankenPHP worker mode
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for worker mode
        ],
        'database' => [
            'break_reconnect' => true, // Reconnect on connection loss
        ],
    ]);
    
    return $app;
};
