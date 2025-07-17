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
            'mode' => defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 2,
            'sock_type' => defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 1,
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
            // 基础服务器配置
            'host' => '0.0.0.0',
            'port' => 8080,
            'count' => 4, // 进程数量
            'name' => 'think-workerman',
            'protocol' => 'http',
            'context' => [],
            'reuse_port' => false,
            'transport' => 'tcp',
            
            // Session 修复配置（新增）
            'session' => [
                'enable_fix' => true,           // 启用 session 修复
                'create_new_app' => false,      // 是否每次请求创建新应用实例（类似 ReactPHP）
                'preserve_session_cookies' => true, // 保留 session cookie
                'debug_session' => false,       // 调试 session 处理
            ],
            
            // 内存管理配置
            'memory' => [
                'enable_gc' => true,
                'gc_interval' => 100, // 每100个请求GC一次
                'context_cleanup_interval' => 60, // 60秒清理一次上下文
                'max_context_size' => 1000, // 最大上下文数量
            ],
            
            // 性能监控配置
            'monitor' => [
                'enable' => true,
                'slow_request_threshold' => 1000, // 毫秒
                'memory_limit' => '256M',
            ],
            
            // 定时器配置
            'timer' => [
                'enable' => false,
                'interval' => 60, // 秒
            ],
            
            // 日志配置
            'log' => [
                'enable' => true,
                'file' => 'runtime/logs/workerman.log',
                'level' => 'info',
            ],
            
            // 静态文件配置
            'static_file' => [
                'enable' => true,
                'document_root' => 'public',
                'enable_negotiation' => false,
            ],

            // 压缩配置
            'compression' => [
                'enable' => true,
                'type' => 'gzip', // gzip, deflate
                'level' => 6, // 压缩级别 1-9
                'min_length' => 1024, // 最小压缩长度 (字节)
                'types' => [
                    'text/html',
                    'text/css',
                    'text/javascript',
                    'text/xml',
                    'text/plain',
                    'application/javascript',
                    'application/json',
                    'application/xml',
                    'application/rss+xml',
                    'application/atom+xml',
                    'image/svg+xml',
                ],
            ],

            // Keep-Alive 配置
            'keep_alive' => [
                'enable' => true,
                'timeout' => 60,        // keep-alive 超时时间 (秒)
                'max_requests' => 1000, // 每个连接最大请求数
                'close_on_idle' => 300, // 空闲连接关闭时间 (秒)
            ],

            // Socket 配置
            'socket' => [
                'so_reuseport' => true,  // 启用端口复用
                'tcp_nodelay' => true,   // 禁用 Nagle 算法
                'so_keepalive' => true,  // 启用 TCP keep-alive
                'backlog' => 1024,       // 监听队列长度
            ],

            // 错误处理配置
            'error' => [
                'display_errors' => false,
                'log_errors' => true,
                'error_reporting' => E_ALL & ~E_NOTICE,
            ],

            // 调试配置
            'debug' => [
                'enable' => false,
                'log_level' => 'info', // debug, info, warning, error
                'log_requests' => false,
                'show_errors' => false,
            ],

            // 安全配置
            'security' => [
                'max_request_size' => '10M',
                'max_upload_size' => '10M',
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD'],
                'enable_cors' => false,
                'cors_origin' => '*',
                'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
            ],

            // 进程管理配置
            'process' => [
                'daemonize' => false,
                'pid_file' => 'runtime/workerman.pid',
                'log_file' => 'runtime/workerman.log',
                'stdout_file' => 'runtime/workerman_stdout.log',
                'max_request' => 10000, // 每个进程最大请求数
                'graceful_stop_timeout' => 30, // 优雅停止超时时间
            ],
        ],
    ],
];