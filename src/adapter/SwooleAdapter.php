<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use think\App;
use Swoole\Http\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * Swoole适配器
 * 提供Swoole HTTP服务器支持
 */
class SwooleAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * Swoole HTTP服务器实例
     *
     * @var Server|null
     */
    protected ?Server $server = null;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'host' => '0.0.0.0',
        'port' => 9501,
        'mode' => SWOOLE_PROCESS,
        'sock_type' => SWOOLE_SOCK_TCP,
        'worker_num' => 4,
        'task_worker_num' => 0,  // 默认不启用Task进程
        'max_request' => 10000,
        'dispatch_mode' => 2,
        'debug_mode' => 0,
        'enable_static_handler' => false,
        'document_root' => '/tmp',  // 默认文档根目录，启动时会重新设置
        'daemonize' => false,
        'enable_coroutine' => true,
        'max_coroutine' => 100000,
        'socket_buffer_size' => 2097152,
        'settings' => [
            'worker_num' => 4,
            'task_worker_num' => 0,  // 默认不启用Task进程
            'max_request' => 10000,
            'dispatch_mode' => 2,
            'debug_mode' => 0,
            'enable_static_handler' => false,
            'document_root' => '/tmp',  // 默认文档根目录，启动时会重新设置
            'daemonize' => 0,
            'enable_coroutine' => 1,
            'max_coroutine' => 100000,
            'socket_buffer_size' => 2097152,
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
            throw new \RuntimeException('Swoole extension is not installed');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 动态设置文档根目录
        if (!isset($config['settings']['document_root']) || $config['settings']['document_root'] === '/tmp') {
            $config['settings']['document_root'] = getcwd() . '/public';
            // 如果public目录不存在，使用当前目录
            if (!is_dir($config['settings']['document_root'])) {
                $config['settings']['document_root'] = getcwd();
            }
        }

        $this->server = new Server(
            $config['host'],
            $config['port'],
            $config['mode'],
            $config['sock_type']
        );

        // 设置服务器配置
        $this->server->set($config['settings']);

        // 绑定事件
        $this->bindEvents();
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

        echo "Swoole HTTP Server starting...\n";
        echo "Listening on: {$config['host']}:{$config['port']}\n";

        $this->server->start();
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
        if ($this->server !== null) {
            $this->server->shutdown();
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
        return 'swoole';
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
        return extension_loaded('swoole') && class_exists(Server::class);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 如果有嵌套的settings配置，将其合并到顶层
        if (isset($config['settings']) && is_array($config['settings'])) {
            $config = array_merge($config, $config['settings']);
        }

        // 如果用户配置中有直接的配置项，它们应该覆盖settings中的配置
        if (!empty($this->config)) {
            $config = array_merge($config, $this->config);
        }

        return $config;
    }

    /**
     * 获取Swoole服务器实例
     *
     * @return Server|null
     */
    public function getSwooleServer(): ?Server
    {
        return $this->server;
    }

    /**
     * 绑定Swoole事件
     *
     * @return void
     */
    protected function bindEvents(): void
    {
        // 工作进程启动事件
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);

        // HTTP请求事件
        $this->server->on('Request', [$this, 'onRequest']);

        // 服务器启动事件
        $this->server->on('Start', [$this, 'onStart']);

        // 服务器关闭事件
        $this->server->on('Shutdown', [$this, 'onShutdown']);

        // 如果启用了Task进程，绑定Task事件
        $config = array_merge($this->defaultConfig, $this->config);
        if (isset($config['settings']['task_worker_num']) && $config['settings']['task_worker_num'] > 0) {
            $this->server->on('Task', [$this, 'onTask']);
            $this->server->on('Finish', [$this, 'onFinish']);
        }
    }

    /**
     * 工作进程启动事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $workerId 工作进程ID
     * @return void
     */
    public function onWorkerStart(Server $server, int $workerId): void
    {
        // 在工作进程中初始化应用
        $this->app->initialize();
    }

    /**
     * HTTP请求事件处理
     *
     * @param SwooleRequest $request Swoole请求对象
     * @param SwooleResponse $response Swoole响应对象
     * @return void
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response): void
    {
        try {
            // 转换为PSR-7请求
            $psr7Request = $this->convertSwooleRequestToPsr7($request);

            // 处理请求
            $psr7Response = $this->handleRequest($psr7Request);

            // 发送响应
            $this->sendSwooleResponse($response, $psr7Response);

        } catch (\Throwable $e) {
            $this->handleSwooleError($response, $e);
        }
    }

    /**
     * 服务器启动事件处理
     *
     * @param Server $server Swoole服务器实例
     * @return void
     */
    public function onStart(Server $server): void
    {
        echo "Swoole HTTP Server started successfully\n";
        echo "Master PID: {$server->master_pid}\n";
        echo "Manager PID: {$server->manager_pid}\n";
    }

    /**
     * 服务器关闭事件处理
     *
     * @param Server $server Swoole服务器实例
     * @return void
     */
    public function onShutdown(Server $server): void
    {
        echo "Swoole HTTP Server shutdown\n";
    }

    /**
     * Task事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $taskId Task ID
     * @param int $reactorId Reactor ID
     * @param mixed $data Task数据
     * @return mixed
     */
    public function onTask(Server $server, int $taskId, int $reactorId, $data)
    {
        // 默认的Task处理逻辑
        return "Task {$taskId} completed";
    }

    /**
     * Finish事件处理
     *
     * @param Server $server Swoole服务器实例
     * @param int $taskId Task ID
     * @param mixed $data Task返回数据
     * @return void
     */
    public function onFinish(Server $server, int $taskId, $data): void
    {
        // 默认的Finish处理逻辑
        echo "Task {$taskId} finished with result: {$data}\n";
    }

    /**
     * 将Swoole请求转换为PSR-7请求
     *
     * @param SwooleRequest $request Swoole请求
     * @return \Psr\Http\Message\ServerRequestInterface PSR-7请求
     */
    protected function convertSwooleRequestToPsr7(SwooleRequest $request): \Psr\Http\Message\ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        // 构建服务器变量
        $server = array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->server['request_method'] ?? 'GET',
            'REQUEST_URI' => $request->server['request_uri'] ?? '/',
            'PATH_INFO' => $request->server['path_info'] ?? '/',
            'QUERY_STRING' => $request->server['query_string'] ?? '',
            'HTTP_HOST' => $request->header['host'] ?? 'localhost',
        ]);

        return $creator->fromArrays(
            $server,
            $request->header ?? [],
            $request->cookie ?? [],
            $request->get ?? [],
            $request->post ?? [],
            $request->files ?? [],
            $request->rawContent() ?: null
        );
    }

    /**
     * 发送Swoole响应
     *
     * @param SwooleResponse $swooleResponse Swoole响应对象
     * @param \Psr\Http\Message\ResponseInterface $psr7Response PSR-7响应对象
     * @return void
     */
    protected function sendSwooleResponse(SwooleResponse $swooleResponse, \Psr\Http\Message\ResponseInterface $psr7Response): void
    {
        // 设置状态码
        $swooleResponse->status($psr7Response->getStatusCode());

        // 设置响应头
        foreach ($psr7Response->getHeaders() as $name => $values) {
            $swooleResponse->header((string) $name, implode(', ', $values));
        }

        // 发送响应体
        $swooleResponse->end((string) $psr7Response->getBody());
    }

    /**
     * 处理Swoole错误
     *
     * @param SwooleResponse $response Swoole响应对象
     * @param \Throwable $e 异常
     * @return void
     */
    protected function handleSwooleError(SwooleResponse $response, \Throwable $e): void
    {
        $response->status(500);
        $response->header('Content-Type', 'application/json');
        $response->end(json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
        ], JSON_UNESCAPED_UNICODE));
    }
}
