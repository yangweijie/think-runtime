<?php

/**
 * Vercel Serverless Function Example
 * 
 * This example shows how to run ThinkPHP with Vercel serverless functions.
 * 
 * Deploy to Vercel:
 * vercel --prod
 */

use think\App;

require_once dirname(__DIR__, 2) . '/vendor/autoload_runtime.php';

return function (array $context): App {
    // Create ThinkPHP application instance
    $app = new App();
    
    // Configure application for Vercel serverless
    $app->setAppPath($context['APP_PATH'] ?? dirname(__DIR__) . '/app/');
    $app->setRuntimePath($context['RUNTIME_PATH'] ?? '/tmp/');
    $app->setConfigPath($context['CONFIG_PATH'] ?? dirname(__DIR__) . '/config/');
    
    // Configure for Vercel serverless environment
    $app->config->set([
        'app' => [
            'debug' => $context['vercel_context']['environment'] !== 'production',
        ],
        'session' => [
            'auto_start' => false, // Important for serverless
            'type' => 'cache', // Use cache instead of files
        ],
        'database' => [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'type' => 'mysql',
                    'hostname' => $_ENV['DATABASE_HOST'] ?? 'localhost',
                    'database' => $_ENV['DATABASE_NAME'] ?? 'test',
                    'username' => $_ENV['DATABASE_USER'] ?? 'root',
                    'password' => $_ENV['DATABASE_PASSWORD'] ?? '',
                    'charset' => 'utf8mb4',
                    'prefix' => $_ENV['DATABASE_PREFIX'] ?? '',
                    'deploy' => [
                        'type' => 'mysql',
                        'read_master' => true,
                    ],
                ],
            ],
        ],
        'cache' => [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'type' => 'redis',
                    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                    'port' => $_ENV['REDIS_PORT'] ?? 6379,
                    'password' => $_ENV['REDIS_PASSWORD'] ?? '',
                ],
                'file' => [
                    'type' => 'file',
                    'path' => '/tmp/cache/',
                ],
            ],
        ],
        'log' => [
            'default' => 'single',
            'channels' => [
                'single' => [
                    'type' => 'single',
                    'path' => '/tmp/thinkphp.log',
                    'level' => 'error',
                ],
            ],
        ],
    ]);
    
    return $app;
};
