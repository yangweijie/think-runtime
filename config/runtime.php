<?php
if(!extension_loaded('swoole')){
    if(!defined('SWOOLE_PROCESS'))
        define('SWOOLE_PROCESS', 2);
    if(!defined('SWOOLE_SOCK_TCP'))
        define('SWOOLE_SOCK_TCP', 1);
}

return [
    // 默认运行时
    'default' => 'auto',

    // 自动检测顺序
    'auto_detect_order' => [
        'bref',
        'vercel',
        'swoole',
        'frankenphp',
        'reactphp',
        'ripple',
        'roadrunner',
        'workerman',
    ],

    // 运行时配置
    'runtimes' => [
        'swoole' => [
            'host' => '0.0.0.0',
            'port' => 9501,
            'mode' => SWOOLE_PROCESS??2,
            'sock_type' => SWOOLE_SOCK_TCP??1,
            'settings' => [
                'worker_num' => 4,
                'task_worker_num' => 2,
                'max_request' => 10000,
                'dispatch_mode' => 2,
                'debug_mode' => 0,
                'enable_static_handler' => false,
                'document_root' => '',
            ],
        ],
        'frankenphp' => [
            'listen' => ':8080',
            'worker_num' => 4,
            'max_requests' => 1000,
            'auto_https' => true,
            'http2' => true,
            'http3' => false,
            'debug' => false,
            'access_log' => true,
            'error_log' => true,
            'log_level' => 'INFO',
            'root' => 'public',
            'index' => 'index.php',
            'env' => [],
        ],
        'reactphp' => [
            'host' => '0.0.0.0',
            'port' => 8080,
            'max_connections' => 1000,
            'timeout' => 30,
            'enable_keepalive' => true,
            'keepalive_timeout' => 5,
            'max_request_size' => '8M',
            'enable_compression' => true,
            'debug' => false,
            'access_log' => true,
            'error_log' => true,
            'websocket' => false,
            'ssl' => [
                'enabled' => false,
                'cert' => '',
                'key' => '',
            ],
        ],
        'ripple' => [
            'host' => '0.0.0.0',
            'port' => 8000,
            'workers' => 4,
            'debug' => false,
            'daemonize' => false,
            'compression_level' => 6,
            'access_log' => true,
            'error_log' => true,
            'enable_fiber' => true,
            'fiber_stack_size' => 8192,
            'ssl' => [
                'enabled' => false,
                'cert_file' => '',
                'key_file' => '',
                'verify_peer' => false,
            ],
            'max_request' => 10000,
            'max_package_size' => 10 * 1024 * 1024,
            'enable_static_handler' => true,
            'document_root' => 'public',
            'enable_coroutine' => true,
            'max_coroutine' => 100000,
            'log_file' => 'runtime/ripple.log',
            'pid_file' => 'runtime/ripple.pid',
            'database' => [
                'pool_size' => 10,
                'max_idle_time' => 3600,
            ],
        ],
        'roadrunner' => [
            'debug' => false,
            'max_jobs' => 0,
            'memory_limit' => '128M',
        ],
        'workerman' => [
            'host' => '0.0.0.0',
            'port' => 8080,
            'count' => 4,
            'name' => 'ThinkPHP-Workerman',
            'user' => '',
            'group' => '',
            'reloadable' => true,
            'reusePort' => false,
            'transport' => 'tcp',
            'context' => [],
            'protocol' => 'http',
            'static_file' => [
                'enable' => true,
                'document_root' => 'public',
                'cache_time' => 3600,
                'allowed_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'html', 'htm', 'txt', 'json', 'xml'],
            ],
            'monitor' => [
                'enable' => true,
                'slow_request_threshold' => 1000,
                'memory_limit' => '256M',
            ],
            'middleware' => [
                'cors' => [
                    'enable' => true,
                    'allow_origin' => '*',
                    'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                    'allow_headers' => 'Content-Type, Authorization, X-Requested-With',
                ],
                'security' => [
                    'enable' => true,
                ],
            ],
            'log' => [
                'enable' => true,
                'file' => 'runtime/logs/workerman.log',
                'level' => 'info',
            ],
            'timer' => [
                'enable' => false,
                'interval' => 60,
            ],
        ],
        'bref' => [
            // Lambda运行时配置
            'lambda' => [
                'timeout' => 30,
                'memory' => 512,
                'environment' => 'production',
            ],
            // HTTP处理配置
            'http' => [
                'enable_cors' => true,
                'cors_origin' => '*',
                'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
            ],
            // 错误处理配置
            'error' => [
                'display_errors' => false,
                'log_errors' => true,
            ],
            // 性能监控配置
            'monitor' => [
                'enable' => true,
                'slow_request_threshold' => 1000, // 毫秒
            ],
        ],
        'vercel' => [
            // Vercel函数配置
            'vercel' => [
                'timeout' => 10, // Vercel默认超时10秒
                'memory' => 1024, // 默认内存1GB
                'region' => 'auto', // 自动选择区域
                'runtime' => 'php-8.1',
            ],
            // HTTP处理配置
            'http' => [
                'enable_cors' => true,
                'cors_origin' => '*',
                'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
                'max_body_size' => '5mb', // Vercel请求体限制
            ],
            // 错误处理配置
            'error' => [
                'display_errors' => false,
                'log_errors' => true,
                'error_reporting' => E_ALL & ~E_NOTICE,
            ],
            // 性能监控配置
            'monitor' => [
                'enable' => true,
                'slow_request_threshold' => 1000, // 毫秒
                'memory_threshold' => 80, // 内存使用阈值百分比
            ],
            // 静态文件配置
            'static' => [
                'enable' => false, // Vercel通常由CDN处理静态文件
                'extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'],
            ],
        ],
    ],

    // 全局配置
    'global' => [
        'error_reporting' => E_ALL,
        'display_errors' => false,
        'log_errors' => true,
        'memory_limit' => '256M',
        'max_execution_time' => 30,
    ],
];
