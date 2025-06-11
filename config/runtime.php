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
        'swoole',
        'frankenphp',
        'reactphp',
        'ripple',
        'roadrunner',
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
            'port' => 8080,
            'worker_num' => 4,
            'max_connections' => 10000,
            'max_coroutines' => 100000,
            'coroutine_pool_size' => 1000,
            'timeout' => 30,
            'enable_keepalive' => true,
            'keepalive_timeout' => 60,
            'max_request_size' => '8M',
            'enable_compression' => true,
            'compression_level' => 6,
            'debug' => false,
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
