<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use think\App;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * FrankenPHP适配器
 * 提供FrankenPHP应用服务器支持
 */
class FrankenphpAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
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
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new \RuntimeException('FrankenPHP is not available');
        }

        // 初始化应用
        $this->app->initialize();

        // 设置环境变量
        $this->setupEnvironment();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        $this->boot();

        $config = array_merge($this->defaultConfig, $this->config);

        echo "FrankenPHP Server starting...\n";
        echo "Listening on: {$config['listen']}\n";
        echo "Document root: {$config['root']}\n";
        echo "Workers: {$config['worker_num']}\n";

        // FrankenPHP通过环境变量和配置文件运行
        // 这里主要是设置环境和启动Worker模式
        $this->startWorkerMode();
    }

    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        $this->setConfig($options);
        $this->run();
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        // FrankenPHP的停止通常由外部信号处理
        if (function_exists('frankenphp_stop')) {
            frankenphp_stop();
        }
    }

    /**
     * 停止适配器
     *
     * @return void
     */
    public function terminate(): void
    {
        $this->stop();
    }

    /**
     * 获取适配器名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'frankenphp';
    }

    /**
     * 检查运行时是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isSupported();
    }

    /**
     * 检查适配器是否支持当前环境
     *
     * @return bool
     */
    public function isSupported(): bool
    {
        // 检查是否在FrankenPHP环境中运行
        return isset($_SERVER['FRANKENPHP_VERSION']) ||
               function_exists('frankenphp_handle_request') ||
               getenv('FRANKENPHP_CONFIG') !== false;
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 95; // 高优先级，仅次于Swoole
    }

    /**
     * 设置环境变量
     *
     * @return void
     */
    protected function setupEnvironment(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 设置FrankenPHP相关环境变量
        if (!empty($config['env'])) {
            foreach ($config['env'] as $key => $value) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        // 设置基本配置
        putenv("FRANKENPHP_CONFIG=" . $this->buildFrankenphpConfig());

        // 设置Worker数量
        if (isset($config['worker_num'])) {
            putenv("FRANKENPHP_WORKER_NUM={$config['worker_num']}");
        }
    }

    /**
     * 构建FrankenPHP配置
     *
     * @return string
     */
    protected function buildFrankenphpConfig(): string
    {
        $config = array_merge($this->defaultConfig, $this->config);

        $frankenphpConfig = [
            'listen' => $config['listen'],
            'root' => $config['root'],
            'index' => $config['index'],
            'worker_num' => $config['worker_num'],
            'max_requests' => $config['max_requests'],
        ];

        if ($config['auto_https']) {
            $frankenphpConfig['auto_https'] = 'on';
        }

        if ($config['http2']) {
            $frankenphpConfig['http2'] = 'on';
        }

        if ($config['http3']) {
            $frankenphpConfig['http3'] = 'on';
        }

        return json_encode($frankenphpConfig);
    }

    /**
     * 启动Worker模式
     *
     * @return void
     */
    protected function startWorkerMode(): void
    {
        // 检查是否支持Worker模式
        if (!function_exists('frankenphp_handle_request')) {
            // 如果不在FrankenPHP环境中，使用传统方式处理请求
            $this->handleTraditionalRequest();
            return;
        }

        // FrankenPHP Worker模式
        for ($nbHandledRequests = 0, $running = true; $running; ++$nbHandledRequests) {
            $running = frankenphp_handle_request(function (): void {
                try {
                    // 创建PSR-7请求
                    $psr7Request = $this->createPsr7RequestFromGlobals();

                    // 处理请求
                    $psr7Response = $this->handleRequest($psr7Request);

                    // 发送响应
                    $this->sendResponse($psr7Response);

                } catch (\Throwable $e) {
                    $this->handleFrankenphpError($e);
                }
            });

            // 检查是否达到最大请求数
            $config = array_merge($this->defaultConfig, $this->config);
            if ($config['max_requests'] > 0 && $nbHandledRequests >= $config['max_requests']) {
                break;
            }

            // 垃圾回收
            if ($nbHandledRequests % 100 === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * 处理传统请求（非Worker模式）
     *
     * @return void
     */
    protected function handleTraditionalRequest(): void
    {
        try {
            // 创建PSR-7请求
            $psr7Request = $this->createPsr7RequestFromGlobals();

            // 处理请求
            $psr7Response = $this->handleRequest($psr7Request);

            // 发送响应
            $this->sendResponse($psr7Response);

        } catch (\Throwable $e) {
            $this->handleFrankenphpError($e);
        }
    }

    /**
     * 从全局变量创建PSR-7请求
     *
     * @return \Psr\Http\Message\ServerRequestInterface
     */
    protected function createPsr7RequestFromGlobals(): \Psr\Http\Message\ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $creator->fromGlobals();
    }

    /**
     * 发送响应
     *
     * @param \Psr\Http\Message\ResponseInterface $response PSR-7响应
     * @return void
     */
    protected function sendResponse(\Psr\Http\Message\ResponseInterface $response): void
    {
        // 发送状态码
        http_response_code($response->getStatusCode());

        // 发送响应头
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', (string) $name, $value), false);
            }
        }

        // 发送响应体
        echo (string) $response->getBody();
    }

    /**
     * 处理FrankenPHP错误
     *
     * @param \Throwable $e 异常
     * @return void
     */
    protected function handleFrankenphpError(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
