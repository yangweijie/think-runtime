<?php

/**
 * ReactPHP Server Example
 * 
 * This example shows how to run ThinkPHP with ReactPHP server.
 * 
 * Usage:
 * php server.php
 */

use think\App;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context): App {
    // Create ThinkPHP application instance
    $app = new App();
    
    // Configure application for ReactPHP
    $app->setAppPath($context['APP_PATH'] ?? dirname(__DIR__) . '/app/');
    $app->setRuntimePath($context['RUNTIME_PATH'] ?? dirname(__DIR__) . '/runtime/');
    $app->setConfigPath($context['CONFIG_PATH'] ?? dirname(__DIR__) . '/config/');
    
    // Configure for async environment
    $app->config->set([
        'session' => [
            'auto_start' => false,
        ],
        'database' => [
            'connections' => 1, // Limit database connections
        ],
    ]);
    
    return $app;
};
