<?php

declare(strict_types=1);

/**
 * ThinkPHP Runtime 配置文件
 *
 * 此文件定义了不同运行时环境的配置选项
 */

return [
    // 默认运行时
    // 可选值: auto, swoole, roadrunner, fpm
    'default' => 'auto',

    // 自动检测顺序
    // 当default为auto时，按此顺序检测可用的运行时
    'auto_detect_order' => [
        'swoole',
        'frankenphp',
        'reactphp',
        'ripple',
        'roadrunner',
        'fpm',
    ],

    // 运行时配置
    'runtimes' => [
        // Swoole配置
        'swoole' => [
            'host' => env('SWOOLE_HOST', '0.0.0.0'),
            'port' => env('SWOOLE_PORT', 9501),
            'mode' => SWOOLE_PROCESS,
            'sock_type' => SWOOLE_SOCK_TCP,
            'settings' => [
                // 工作进程数
                'worker_num' => env('SWOOLE_WORKER_NUM', 4),

                // 任务进程数
                'task_worker_num' => env('SWOOLE_TASK_WORKER_NUM', 2),

                // 最大请求数，超过后重启进程
                'max_request' => env('SWOOLE_MAX_REQUEST', 10000),

                // 数据包分发策略
                'dispatch_mode' => 2,

                // 调试模式
                'debug_mode' => env('APP_DEBUG', false) ? 1 : 0,

                // 守护进程化
                'daemonize' => env('SWOOLE_DAEMONIZE', 0),

                // 静态文件处理
                'enable_static_handler' => env('SWOOLE_STATIC_HANDLER', false),
                'document_root' => env('SWOOLE_DOCUMENT_ROOT', ''),

                // 日志配置
                'log_file' => runtime_path() . 'swoole.log',
                'log_level' => env('SWOOLE_LOG_LEVEL', 0),

                // 进程名称
                'process_name' => 'thinkphp-swoole',

                // 心跳检测
                'heartbeat_check_interval' => 60,
                'heartbeat_idle_time' => 600,

                // 缓冲区设置
                'buffer_output_size' => 2 * 1024 * 1024,
                'socket_buffer_size' => 128 * 1024 * 1024,

                // 协程设置
                'enable_coroutine' => true,
                'max_coroutine' => 100000,
            ],
        ],

        // FrankenPHP配置
        'frankenphp' => [
            'listen' => env('FRANKENPHP_LISTEN', ':8080'),
            'worker_num' => env('FRANKENPHP_WORKER_NUM', 4),
            'max_requests' => env('FRANKENPHP_MAX_REQUESTS', 1000),
            'auto_https' => env('FRANKENPHP_AUTO_HTTPS', true),
            'http2' => env('FRANKENPHP_HTTP2', true),
            'http3' => env('FRANKENPHP_HTTP3', false),
            'debug' => env('FRANKENPHP_DEBUG', false),
            'access_log' => env('FRANKENPHP_ACCESS_LOG', true),
            'error_log' => env('FRANKENPHP_ERROR_LOG', true),
            'log_level' => env('FRANKENPHP_LOG_LEVEL', 'INFO'),
            'root' => env('FRANKENPHP_ROOT', 'public'),
            'index' => env('FRANKENPHP_INDEX', 'index.php'),
            'env' => [
                'APP_ENV' => env('APP_ENV', 'production'),
                'APP_DEBUG' => env('APP_DEBUG', false),
            ],
        ],

        // ReactPHP配置
        'reactphp' => [
            'host' => env('REACTPHP_HOST', '0.0.0.0'),
            'port' => env('REACTPHP_PORT', 8080),
            'max_connections' => env('REACTPHP_MAX_CONNECTIONS', 1000),
            'timeout' => env('REACTPHP_TIMEOUT', 30),
            'enable_keepalive' => env('REACTPHP_KEEPALIVE', true),
            'keepalive_timeout' => env('REACTPHP_KEEPALIVE_TIMEOUT', 5),
            'max_request_size' => env('REACTPHP_MAX_REQUEST_SIZE', '8M'),
            'enable_compression' => env('REACTPHP_COMPRESSION', true),
            'debug' => env('REACTPHP_DEBUG', false),
            'access_log' => env('REACTPHP_ACCESS_LOG', true),
            'error_log' => env('REACTPHP_ERROR_LOG', true),
            'websocket' => env('REACTPHP_WEBSOCKET', false),
            'ssl' => [
                'enabled' => env('REACTPHP_SSL_ENABLED', false),
                'cert' => env('REACTPHP_SSL_CERT', ''),
                'key' => env('REACTPHP_SSL_KEY', ''),
            ],
        ],

        // Ripple配置
        'ripple' => [
            'host' => env('RIPPLE_HOST', '0.0.0.0'),
            'port' => env('RIPPLE_PORT', 8080),
            'worker_num' => env('RIPPLE_WORKER_NUM', 4),
            'max_connections' => env('RIPPLE_MAX_CONNECTIONS', 10000),
            'max_coroutines' => env('RIPPLE_MAX_COROUTINES', 100000),
            'coroutine_pool_size' => env('RIPPLE_COROUTINE_POOL_SIZE', 1000),
            'timeout' => env('RIPPLE_TIMEOUT', 30),
            'enable_keepalive' => env('RIPPLE_KEEPALIVE', true),
            'keepalive_timeout' => env('RIPPLE_KEEPALIVE_TIMEOUT', 60),
            'max_request_size' => env('RIPPLE_MAX_REQUEST_SIZE', '8M'),
            'enable_compression' => env('RIPPLE_COMPRESSION', true),
            'compression_level' => env('RIPPLE_COMPRESSION_LEVEL', 6),
            'debug' => env('RIPPLE_DEBUG', false),
            'access_log' => env('RIPPLE_ACCESS_LOG', true),
            'error_log' => env('RIPPLE_ERROR_LOG', true),
            'enable_fiber' => env('RIPPLE_FIBER', true),
            'fiber_stack_size' => env('RIPPLE_FIBER_STACK_SIZE', 8192),
            'ssl' => [
                'enabled' => env('RIPPLE_SSL_ENABLED', false),
                'cert_file' => env('RIPPLE_SSL_CERT', ''),
                'key_file' => env('RIPPLE_SSL_KEY', ''),
                'verify_peer' => env('RIPPLE_SSL_VERIFY_PEER', false),
            ],
            'database' => [
                'pool_size' => env('RIPPLE_DB_POOL_SIZE', 10),
                'max_idle_time' => env('RIPPLE_DB_MAX_IDLE_TIME', 3600),
            ],
        ],

        // RoadRunner配置
        'roadrunner' => [
            'debug' => env('RR_DEBUG', false),
            'max_jobs' => env('RR_MAX_JOBS', 0),
            'memory_limit' => env('RR_MEMORY_LIMIT', '128M'),
        ],

        // FPM配置
        'fpm' => [
            'auto_start' => true,
            'handle_errors' => true,
        ],
    ],

    // 全局配置
    'global' => [
        'error_reporting' => E_ALL,
        'display_errors' => env('APP_DEBUG', false),
        'log_errors' => true,
        'memory_limit' => env('PHP_MEMORY_LIMIT', '256M'),
        'max_execution_time' => env('PHP_MAX_EXECUTION_TIME', 30),
    ],
];
