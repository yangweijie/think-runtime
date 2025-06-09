<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\contract;

use think\App;

/**
 * 适配器接口
 * 定义不同运行时环境的适配器规范
 */
interface AdapterInterface
{
    /**
     * 初始化适配器
     *
     * @param App $app ThinkPHP应用实例
     * @param array $config 配置数组
     */
    public function __construct(App $app, array $config = []);

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void;

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void;

    /**
     * 停止适配器
     *
     * @return void
     */
    public function terminate(): void;

    /**
     * 获取适配器名称
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 检查适配器是否支持当前环境
     *
     * @return bool
     */
    public function isSupported(): bool;

    /**
     * 获取适配器优先级
     * 数值越大优先级越高
     *
     * @return int
     */
    public function getPriority(): int;
}
