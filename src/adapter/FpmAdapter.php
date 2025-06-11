<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use think\App;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * FPM适配器
 * 提供传统PHP-FPM运行时支持，主要用于测试和开发环境
 */
class FpmAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'host' => '127.0.0.1',
        'port' => 9000,
        'timeout' => 30,
        'debug' => false,
        'access_log' => true,
        'error_log' => true,
        'max_children' => 50,
        'start_servers' => 5,
        'min_spare_servers' => 5,
        'max_spare_servers' => 35,
        'max_requests' => 500,
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new \RuntimeException('FPM is not available');
        }

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
        $this->boot();

        $config = array_merge($this->defaultConfig, $this->config);

        echo "FPM Runtime starting...\n";
        echo "Host: {$config['host']}\n";
        echo "Port: {$config['port']}\n";
        echo "Max children: {$config['max_children']}\n";
        echo "Note: FPM runtime is mainly for testing and development\n";

        // FPM运行时主要用于测试，不需要实际启动服务器
        // 在实际环境中，FPM由外部Web服务器（如Nginx）管理
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
        // FPM的停止通常由外部Web服务器管理
        echo "FPM runtime stopped\n";
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
        return 'fpm';
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
        // FPM适配器总是可用的，因为它是传统的PHP运行方式
        return true;
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        // FPM优先级最低，作为兜底选择
        return 10;
    }
}
