<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\runtime;

use InvalidArgumentException;
use RuntimeException;
use think\App;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\contract\RuntimeInterface;
use yangweijie\thinkRuntime\config\RuntimeConfig;
use yangweijie\thinkRuntime\adapter\SwooleAdapter;
use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use yangweijie\thinkRuntime\adapter\ReactphpAdapter;
use yangweijie\thinkRuntime\adapter\RippleAdapter;
use yangweijie\thinkRuntime\adapter\RoadrunnerAdapter;
use yangweijie\thinkRuntime\adapter\FpmAdapter;
use yangweijie\thinkRuntime\adapter\BrefAdapter;
use yangweijie\thinkRuntime\adapter\VercelAdapter;
use yangweijie\thinkRuntime\adapter\WorkermanAdapter;


/**
 * 运行时管理器
 * 负责管理和选择合适的运行时适配器
 */
class RuntimeManager
{
    /**
     * ThinkPHP应用实例
     *
     * @var App
     */
    protected App $app;

    /**
     * 运行时配置
     *
     * @var RuntimeConfig
     */
    protected RuntimeConfig $config;

    /**
     * 已注册的适配器
     *
     * @var array<string, string>
     */
    protected array $adapters = [
        'fpm' => FpmAdapter::class,
        'swoole' => SwooleAdapter::class,
        'frankenphp' => FrankenphpAdapter::class,
        'reactphp' => ReactphpAdapter::class,
        'ripple' => RippleAdapter::class,
        'roadrunner' => RoadrunnerAdapter::class,
        'bref' => BrefAdapter::class,
        'vercel' => VercelAdapter::class,
        'workerman' => WorkermanAdapter::class,
    ];

    /**
     * 当前运行时实例
     *
     * @var RuntimeInterface|null
     */
    protected ?RuntimeInterface $currentRuntime = null;

    /**
     * 构造函数
     *
     * @param App $app ThinkPHP应用实例
     * @param RuntimeConfig $config 运行时配置
     */
    public function __construct(App $app, RuntimeConfig $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * 获取运行时实例
     *
     * @param string|null $name 运行时名称，为null时自动检测
     * @return RuntimeInterface
     */
    public function getRuntime(?string $name = null): RuntimeInterface
    {
        if ($this->currentRuntime !== null) {
            return $this->currentRuntime;
        }

        $runtimeName = $name ?: $this->detectRuntime();
        $this->currentRuntime = $this->createRuntime($runtimeName);

        return $this->currentRuntime;
    }

    /**
     * 创建运行时实例
     *
     * @param string $name 运行时名称
     * @return RuntimeInterface
     */
    public function createRuntime(string $name): RuntimeInterface
    {
        if (!isset($this->adapters[$name])) {
            throw new InvalidArgumentException("Unknown runtime adapter: {$name}");
        }

        $adapterClass = $this->adapters[$name];
        $runtimeConfig = $this->config->getRuntimeConfig($name);

        if (!class_exists($adapterClass)) {
            throw new RuntimeException("Runtime adapter class not found: {$adapterClass}");
        }

        $adapter = new $adapterClass($this->app, $runtimeConfig);

        if (!$adapter instanceof AdapterInterface) {
            throw new RuntimeException("Runtime adapter must implement AdapterInterface");
        }

        if (!$adapter instanceof RuntimeInterface) {
            throw new RuntimeException("Runtime adapter must implement RuntimeInterface");
        }

        return $adapter;
    }

    /**
     * 自动检测运行时
     *
     * @return string
     */
    public function detectRuntime(): string
    {
        $defaultRuntime = $this->config->getDefaultRuntime();

        // 如果指定了具体的运行时，直接返回
        if ($defaultRuntime !== 'auto') {
            return $defaultRuntime;
        }

        // 按优先级检测可用的运行时
        $detectOrder = $this->config->getAutoDetectOrder();

        foreach ($detectOrder as $runtimeName) {
            if ($this->isRuntimeAvailable($runtimeName)) {
                return $runtimeName;
            }
        }

        // 如果都不可用，返回roadrunner作为兜底
        return 'roadrunner';
    }

    /**
     * 检查运行时是否可用
     *
     * @param string $name 运行时名称
     * @return bool
     */
    public function isRuntimeAvailable(string $name): bool
    {
        try {
            $runtime = $this->createRuntime($name);
            return $runtime->isAvailable();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 注册运行时适配器
     *
     * @param string $name 运行时名称
     * @param string $adapterClass 适配器类名
     * @return void
     */
    public function registerAdapter(string $name, string $adapterClass): void
    {
        $this->adapters[$name] = $adapterClass;
    }

    /**
     * 获取所有已注册的适配器
     *
     * @return array<string, string>
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * 获取可用的运行时列表
     *
     * @return array
     */
    public function getAvailableRuntimes(): array
    {
        $available = [];

        foreach (array_keys($this->adapters) as $name) {
            if ($this->isRuntimeAvailable($name)) {
                $available[] = $name;
            }
        }

        return $available;
    }

    /**
     * 启动运行时
     *
     * @param string|null $name 运行时名称
     * @param array $options 启动选项
     * @return void
     */
    public function start(?string $name = null, array $options = []): void
    {
        $runtime = $this->getRuntime($name);
        $runtime->start($options);
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        if ($this->currentRuntime !== null) {
            $this->currentRuntime->stop();
            $this->currentRuntime = null;
        }
    }

    /**
     * 获取当前运行时信息
     *
     * @return array
     */
    public function getRuntimeInfo(): array
    {
        $runtime = $this->getRuntime();

        return [
            'name' => $runtime->getName(),
            'available' => $runtime->isAvailable(),
            'config' => $runtime->getConfig(),
            'all_available' => $this->getAvailableRuntimes(),
        ];
    }
}
