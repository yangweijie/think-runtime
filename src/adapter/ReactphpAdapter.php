<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Promise\PromiseInterface;
use React\Socket\SocketServer;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;
use function React\Promise\resolve;

/**
 * ReactPHP适配器
 * 提供ReactPHP事件驱动异步HTTP服务器支持
 */
class ReactphpAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * ReactPHP事件循环
     *
     * @var LoopInterface|null
     */
    protected ?LoopInterface $loop = null;

    /**
     * ReactPHP HTTP服务器
     *
     * @var HttpServer|null
     */
    protected ?HttpServer $httpServer = null;

    /**
     * ReactPHP Socket服务器
     *
     * @var SocketServer|null
     */
    protected ?SocketServer $socketServer = null;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
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
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('ReactPHP is not available');
        }

        // 设置无限执行时间，ReactPHP服务器需要持续运行
        set_time_limit(0);

        // 初始化应用
        $this->app->initialize();

        // 创建事件循环
        $this->loop = Loop::get();

        // 创建HTTP服务器
        $this->createHttpServer();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->loop === null) {
            $this->boot();
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 设置无限执行时间，因为ReactPHP服务器需要持续运行
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        echo "ReactPHP HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";
        echo "Event-driven: Yes\n";
        echo "Max connections: {$config['max_connections']}\n";
        echo "WebSocket support: " . ($config['websocket'] ? 'Yes' : 'No') . "\n";
        echo "Memory limit: " . ini_get('memory_limit') . "\n";
        echo "Execution time: Unlimited\n";
        echo "Press Ctrl+C to stop the server\n\n";

        // 启动事件循环
        $this->loop->run();
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
        if ($this->socketServer !== null) {
            $this->socketServer->close();
        }

        if ($this->loop !== null) {
            $this->loop->stop();
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
        return 'reactphp';
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
        return class_exists('React\\EventLoop\\Loop') &&
               class_exists('React\\Http\\HttpServer') &&
               class_exists('React\\Socket\\SocketServer') &&
               class_exists('React\\Http\\Message\\Response') &&
               class_exists('React\\Promise\\Promise');
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 92; // 高优先级，在FrankenPHP和RoadRunner之间
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
     * 创建HTTP服务器
     *
     * @return void
     */
    protected function createHttpServer(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 创建HTTP服务器
        $this->httpServer = new HttpServer(
            $this->loop,
            [$this, 'handleReactRequest']
        );

        // 创建Socket服务器
        $listen = $config['host'] . ':' . $config['port'];
        if ($config['ssl']['enabled']) {
            $context = [
                'tls' => [
                    'local_cert' => $config['ssl']['cert'],
                    'local_pk' => $config['ssl']['key'],
                ]
            ];
            $this->socketServer = new SocketServer('tls://' . $listen, $context, $this->loop);
        } else {
            $this->socketServer = new SocketServer($listen, [], $this->loop);
        }

        // 绑定HTTP服务器到Socket
        $this->httpServer->listen($this->socketServer);

        // 设置连接限制
        if ($config['max_connections'] > 0) {
            $this->socketServer->on('connection', function ($connection) use ($config) {
                // ReactPHP Connection 不支持 setTimeout 方法
                // 超时控制应该在 Connector 层面或通过事件循环定时器实现
                // 这里可以添加其他连接相关的配置
            });
        }
    }

    /**
     * 处理HTTP请求（ReactPHP回调）
     *
     * @param ServerRequestInterface $request PSR-7请求
     * @return PromiseInterface
     */
    public function handleReactRequest(ServerRequestInterface $request): PromiseInterface
    {
        try {
            // 处理请求
            $response = $this->handleRequest($request);

            // 返回ReactPHP Response
            return resolve(
                new Response(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    (string) $response->getBody()
                )
            );

        } catch (Throwable $e) {
            return resolve(
                $this->handleReactError($e)
            );
        }
    }

    /**
     * 处理ReactPHP错误
     *
     * @param Throwable $e 异常
     * @return Response ReactPHP响应
     */
    protected function handleReactError(Throwable $e): Response
    {
        $content = json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);

        return new Response(
            500,
            ['Content-Type' => 'application/json'],
            $content
        );
    }

    /**
     * 获取事件循环
     *
     * @return LoopInterface|null
     */
    public function getLoop(): ?LoopInterface
    {
        return $this->loop;
    }

    /**
     * 添加定时器
     *
     * @param float $interval 间隔时间（秒）
     * @param callable $callback 回调函数
     * @return TimerInterface
     */
    public function addTimer(float $interval, callable $callback): TimerInterface
    {
        if ($this->loop === null) {
            throw new RuntimeException('Event loop not initialized');
        }

        return $this->loop->addTimer($interval, $callback);
    }

    /**
     * 添加周期性定时器
     *
     * @param float $interval 间隔时间（秒）
     * @param callable $callback 回调函数
     * @return TimerInterface
     */
    public function addPeriodicTimer(float $interval, callable $callback): TimerInterface
    {
        if ($this->loop === null) {
            throw new RuntimeException('Event loop not initialized');
        }

        return $this->loop->addPeriodicTimer($interval, $callback);
    }

    /**
     * 取消定时器
     *
     * @param TimerInterface $timer 定时器
     * @return void
     */
    public function cancelTimer(TimerInterface $timer): void
    {
        if ($this->loop !== null) {
            $this->loop->cancelTimer($timer);
        }
    }
}
