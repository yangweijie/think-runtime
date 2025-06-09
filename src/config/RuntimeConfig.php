<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\config;

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
            'swoole',
            'frankenphp',
            'reactphp',
            'ripple',
            'roadrunner',
            'fpm',
        ],

        // 运行时配置
        'runtimes' => [
            'swoole' => [
                'host' => '0.0.0.0',
                'port' => 9501,
                'mode' => SWOOLE_PROCESS,
                'sock_type' => SWOOLE_SOCK_TCP,
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
            'fpm' => [
                'auto_start' => true,
                'handle_errors' => true,
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
