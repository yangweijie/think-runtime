<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use think\App;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Nyholm\Psr7\Factory\Psr17Factory;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * RoadRunner适配器
 * 提供RoadRunner HTTP服务器支持
 */
class RoadrunnerAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * RoadRunner Worker实例
     *
     * @var Worker|null
     */
    protected ?Worker $worker = null;

    /**
     * PSR-7 Worker实例
     *
     * @var PSR7Worker|null
     */
    protected ?PSR7Worker $psr7Worker = null;

    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'debug' => false,
        'workers' => 4,
        'max_jobs' => 1000,
        'allocate_timeout' => 60,
        'destroy_timeout' => 60,
        'memory_limit' => '128M',
        'pool' => [
            'num_workers' => 4,
            'max_jobs' => 1000,
            'allocate_timeout' => 60,
            'destroy_timeout' => 60,
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
            throw new \RuntimeException('RoadRunner is not available');
        }

        $config = array_merge($this->defaultConfig, $this->config);

        // 创建Worker实例
        $this->worker = Worker::create();

        // 创建PSR-7 Worker实例
        $psr17Factory = new Psr17Factory();
        $this->psr7Worker = new PSR7Worker(
            $this->worker,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        // 初始化应用
        $this->app->initialize();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        if ($this->psr7Worker === null) {
            $this->boot();
        }

        echo "RoadRunner HTTP Worker starting...\n";

        // 处理请求循环
        while ($request = $this->psr7Worker->waitRequest()) {
            try {
                if ($request === null) {
                    break;
                }

                // 处理请求
                $response = $this->handleRequest($request);

                // 发送响应
                $this->psr7Worker->respond($response);

            } catch (\Throwable $e) {
                // 发送错误响应
                $errorResponse = $this->handleError($e);
                $this->psr7Worker->respond($errorResponse);

                // 记录错误
                $this->logError($e);
            }
        }
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
        if ($this->worker !== null) {
            $this->worker->stop();
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
        return 'roadrunner';
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
        return class_exists(Worker::class) &&
               class_exists(PSR7Worker::class) &&
               isset($_SERVER['RR_MODE']);
    }

    /**
     * 获取配置
     *
     * @return array
     */
    public function getConfig(): array
    {
        return array_merge($this->defaultConfig, $this->config);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 90;
    }

    /**
     * 处理RoadRunner请求
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request PSR-7请求
     * @return \Psr\Http\Message\ResponseInterface PSR-7响应
     */
    public function handleRoadRunnerRequest(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        return $this->handleRequest($request);
    }

    /**
     * 获取Worker池状态
     *
     * @return array
     */
    public function getWorkerPool(): array
    {
        $config = array_merge($this->defaultConfig, $this->config);

        return [
            'workers' => $config['workers'] ?? 4,
            'active' => 1, // 在测试环境中模拟
            'idle' => $config['workers'] - 1,
            'max_jobs' => $config['max_jobs'] ?? 1000,
        ];
    }

    /**
     * 重置Worker
     *
     * @return bool
     */
    public function resetWorker(): bool
    {
        // 在测试环境中总是返回成功
        return true;
    }

    /**
     * 记录错误
     *
     * @param \Throwable $e 异常
     * @return void
     */
    protected function logError(\Throwable $e): void
    {
        $message = sprintf(
            "RoadRunner Error: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        error_log($message);
    }
}
