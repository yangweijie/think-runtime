<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use think\App;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * Ripple适配器
 * 提供基于PHP Fiber的高性能协程HTTP服务器支持
 */
class RippleAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Ripple服务器实例
     *
     * @var object|null
     */
    protected $server = null;

    /**
     * 协程池
     *
     * @var array
     */
    protected array $coroutinePool = [];

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
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
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new \RuntimeException('Ripple is not available');
        }

        // 初始化应用
        $this->app->initialize();
        
        // 创建Ripple服务器
        $this->createRippleServer();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->server === null) {
            $this->boot();
        }

        $config = array_merge($this->defaultConfig, $this->config);
        
        echo "Ripple HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";
        echo "Workers: {$config['worker_num']}\n";
        echo "Max Coroutines: {$config['max_coroutines']}\n";
        echo "Fiber Support: " . ($config['enable_fiber'] ? 'Yes' : 'No') . "\n";
        echo "Coroutine Pool: {$config['coroutine_pool_size']}\n";
        echo "Press Ctrl+C to stop the server\n\n";
        
        // 启动Ripple服务器
        $this->startRippleServer();
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
        if ($this->server !== null && method_exists($this->server, 'stop')) {
            $this->server->stop();
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
        return 'ripple';
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
        // 检查PHP版本是否支持Fiber
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            return false;
        }
        
        // 检查Ripple相关类是否存在
        return class_exists('Ripple\\Http\\Server') || 
               class_exists('Ripple\\Server\\Server') ||
               function_exists('ripple_server_create');
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 91; // 高优先级，在ReactPHP和RoadRunner之间
    }

    /**
     * 获取运行时配置（合并默认配置）
     *
     * @return array
     */
    public function getConfig(): array
    {
        return array_merge($this->defaultConfig, $this->config);
    }

    /**
     * 创建Ripple服务器
     *
     * @return void
     */
    protected function createRippleServer(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        
        // 根据不同的Ripple版本创建服务器
        if (class_exists('Ripple\\Http\\Server')) {
            $this->server = new \Ripple\Http\Server([
                'host' => $config['host'],
                'port' => $config['port'],
                'worker_num' => $config['worker_num'],
                'max_connections' => $config['max_connections'],
                'max_coroutines' => $config['max_coroutines'],
            ]);
        } elseif (class_exists('Ripple\\Server\\Server')) {
            $this->server = new \Ripple\Server\Server([
                'host' => $config['host'],
                'port' => $config['port'],
                'worker_num' => $config['worker_num'],
            ]);
        } else {
            // 模拟Ripple服务器（用于测试环境）
            $this->server = new class($config) {
                private array $config;
                
                public function __construct(array $config)
                {
                    $this->config = $config;
                }
                
                public function on(string $event, callable $callback): void
                {
                    // 模拟事件绑定
                }
                
                public function start(): void
                {
                    // 模拟启动
                    echo "Mock Ripple server started\n";
                }
                
                public function stop(): void
                {
                    // 模拟停止
                    echo "Mock Ripple server stopped\n";
                }
            };
        }
        
        // 绑定请求处理器
        if (method_exists($this->server, 'on')) {
            $this->server->on('request', [$this, 'handleRippleRequest']);
        }
    }

    /**
     * 启动Ripple服务器
     *
     * @return void
     */
    protected function startRippleServer(): void
    {
        if ($this->server !== null && method_exists($this->server, 'start')) {
            $this->server->start();
        }
    }

    /**
     * 处理Ripple请求
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    public function handleRippleRequest($request, $response): void
    {
        try {
            // 创建协程处理请求
            if (function_exists('go') || class_exists('Fiber')) {
                $this->handleRequestInCoroutine($request, $response);
            } else {
                $this->handleRequestSync($request, $response);
            }
        } catch (\Throwable $e) {
            $this->handleRippleError($e, $response);
        }
    }

    /**
     * 在协程中处理请求
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function handleRequestInCoroutine($request, $response): void
    {
        // 使用Fiber处理请求
        if (class_exists('Fiber')) {
            $fiber = new \Fiber(function () use ($request, $response) {
                $this->processRequest($request, $response);
            });
            $fiber->start();
        } else {
            // 降级到同步处理
            $this->processRequest($request, $response);
        }
    }

    /**
     * 同步处理请求
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function handleRequestSync($request, $response): void
    {
        $this->processRequest($request, $response);
    }

    /**
     * 处理请求核心逻辑
     *
     * @param mixed $request Ripple请求对象
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function processRequest($request, $response): void
    {
        // 转换为PSR-7请求
        $psr7Request = $this->convertRippleRequestToPsr7($request);
        
        // 处理请求
        $psr7Response = $this->handleRequest($psr7Request);
        
        // 发送响应
        $this->sendRippleResponse($psr7Response, $response);
    }

    /**
     * 将Ripple请求转换为PSR-7请求
     *
     * @param mixed $request Ripple请求对象
     * @return ServerRequestInterface PSR-7请求
     */
    protected function convertRippleRequestToPsr7($request): ServerRequestInterface
    {
        // 这里需要根据实际的Ripple请求对象结构进行转换
        // 由于Ripple可能有不同的API，这里提供一个通用的实现
        
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        // 从全局变量创建（作为后备方案）
        return $creator->fromGlobals();
    }

    /**
     * 发送Ripple响应
     *
     * @param \Psr\Http\Message\ResponseInterface $psr7Response PSR-7响应
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function sendRippleResponse(\Psr\Http\Message\ResponseInterface $psr7Response, $response): void
    {
        // 设置状态码
        if (method_exists($response, 'status')) {
            $response->status($psr7Response->getStatusCode());
        }
        
        // 设置响应头
        if (method_exists($response, 'header')) {
            foreach ($psr7Response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $response->header((string) $name, $value);
                }
            }
        }
        
        // 发送响应体
        if (method_exists($response, 'end')) {
            $response->end((string) $psr7Response->getBody());
        } elseif (method_exists($response, 'write')) {
            $response->write((string) $psr7Response->getBody());
        }
    }

    /**
     * 处理Ripple错误
     *
     * @param \Throwable $e 异常
     * @param mixed $response Ripple响应对象
     * @return void
     */
    protected function handleRippleError(\Throwable $e, $response): void
    {
        $content = json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);
        
        if (method_exists($response, 'status')) {
            $response->status(500);
        }
        
        if (method_exists($response, 'header')) {
            $response->header('Content-Type', 'application/json');
        }
        
        if (method_exists($response, 'end')) {
            $response->end($content);
        }
    }

    /**
     * 创建协程
     *
     * @param callable $callback 协程回调函数
     * @return mixed 协程ID或对象
     */
    public function createCoroutine(callable $callback)
    {
        if (class_exists('Fiber')) {
            $fiber = new \Fiber($callback);
            $this->coroutinePool[] = $fiber;
            return $fiber->start();
        } elseif (function_exists('go')) {
            return go($callback);
        } else {
            // 降级到同步执行
            return $callback();
        }
    }

    /**
     * 获取协程池状态
     *
     * @return array
     */
    public function getCoroutinePoolStatus(): array
    {
        return [
            'total' => count($this->coroutinePool),
            'active' => count(array_filter($this->coroutinePool, function ($fiber) {
                return $fiber instanceof \Fiber && !$fiber->isTerminated();
            })),
        ];
    }
}
