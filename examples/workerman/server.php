<?php

/**
 * Workerman Server Example
 * 
 * This example shows how to run ThinkPHP with Workerman server.
 * 
 * Usage:
 * php server.php start
 * php server.php stop
 * php server.php restart
 */

use think\App;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context): App {
    // Create ThinkPHP application instance
    $app = new App();
    
    // Configure application for Workerman
    $app->setAppPath($context['APP_PATH'] ?? dirname(__DIR__) . '/app/');
    $app->setRuntimePath($context['RUNTIME_PATH'] ?? dirname(__DIR__) . '/runtime/');
    $app->setConfigPath($context['CONFIG_PATH'] ?? dirname(__DIR__) . '/config/');
    
    // Disable session auto-start for Workerman
    $app->config->set([
        'session' => [
            'auto_start' => false,
        ],
    ]);
    
    return $app;
};
