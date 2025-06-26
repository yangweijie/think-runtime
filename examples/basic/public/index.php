<?php

/**
 * Basic ThinkPHP Runtime Example
 * 
 * This example shows how to use ThinkPHP Runtime with a traditional web application.
 */

use think\App;

require_once dirname(__DIR__, 2) . '/vendor/autoload_runtime.php';

return function (array $context): App {
    // Create ThinkPHP application instance
    $app = new App();
    
    // Configure application paths
    $app->setAppPath($context['APP_PATH'] ?? dirname(__DIR__) . '/app/');
    $app->setRuntimePath($context['RUNTIME_PATH'] ?? dirname(__DIR__) . '/runtime/');
    $app->setConfigPath($context['CONFIG_PATH'] ?? dirname(__DIR__) . '/config/');
    
    return $app;
};
