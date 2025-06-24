<?php

declare(strict_types=1);
namespace yangweijie\thinkRuntime\config;
if(!extension_loaded('swoole')){
    if(!defined('SWOOLE_PROCESS'))
        define('SWOOLE_PROCESS', 2);
    if(!defined('SWOOLE_SOCK_TCP'))
        define('SWOOLE_SOCK_TCP', 1);
}
/**
 * 运行时配置类
 * 管理不同运行时环境的配置
 */
class RuntimeConfig
{
    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
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
                // 基础服务器配置
                'host' => '0.0.0.0',
                'port' => 8080,
                'count' => 4,                    // 进程数
                'name' => 'think-workerman',     // 进程名称
                'user' => '',                    // 运行用户
                'group' => '',                   // 运行用户组
                'reloadable' => true,            // 是否可重载
                'reuse_port' => false,           // 端口复用
                'transport' => 'tcp',            // 传输协议
                'context' => [],                 // Socket上下文选项
                'protocol' => 'http',            // 应用层协议
                
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
                    'cache_time' => 3600,
                    'enable_negotiation' => false,
                    'allowed_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'html', 'htm', 'txt', 'json', 'xml'],
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

                // 中间件配置（保持向后兼容）
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
            'max_execution_time' => 0,
        ],
    ];

    /**
     * 用户配置
     *
     * @var array
     */
    protected array $userConfig = [];

    /**
     * 构造函数
     *
     * @param array $config 用户配置
     */
    public function __construct(array $config = [])
    {
        $this->userConfig = $config;
    }

    /**
     * 获取配置
     *
     * @param string|null $key 配置键，为null时返回所有配置
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        $config = $this->mergeConfig($this->defaultConfig, $this->userConfig);

        if ($key === null) {
            return $config;
        }

        return $this->getNestedValue($config, $key, $default);
    }

    /**
     * 设置配置
     *
     * @param string $key 配置键
     * @param mixed $value 配置值
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->setNestedValue($this->userConfig, $key, $value);
    }

    /**
     * 获取运行时配置
     *
     * @param string $runtime 运行时名称
     * @return array
     */
    public function getRuntimeConfig(string $runtime): array
    {
        return $this->get("runtimes.{$runtime}", []);
    }

    /**
     * 获取默认运行时
     *
     * @return string
     */
    public function getDefaultRuntime(): string
    {
        return $this->get('default', 'auto');
    }

    /**
     * 获取自动检测顺序
     *
     * @return array
     */
    public function getAutoDetectOrder(): array
    {
        return $this->get('auto_detect_order', []);
    }

    /**
     * 获取全局配置
     *
     * @return array
     */
    public function getGlobalConfig(): array
    {
        return $this->get('global', []);
    }

    /**
     * 获取嵌套值
     *
     * @param array $array 数组
     * @param string $key 键（支持点号分隔）
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getNestedValue(array $array, string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $array;


        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置嵌套值
     *
     * @param array &$array 数组引用
     * @param string $key 键（支持点号分隔）
     * @param mixed $value 值
     * @return void
     */
    protected function setNestedValue(array &$array, string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * 合并配置数组
     *
     * @param array $default 默认配置
     * @param array $user 用户配置
     * @return array
     */
    protected function mergeConfig(array $default, array $user): array
    {
        $result = $default;

        foreach ($user as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->mergeConfig($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
