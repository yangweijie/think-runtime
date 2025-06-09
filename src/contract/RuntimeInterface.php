<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\contract;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Runtime接口
 * 定义运行时的核心方法
 */
interface RuntimeInterface
{
    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void;

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void;

    /**
     * 处理HTTP请求
     *
     * @param ServerRequestInterface $request PSR-7请求对象
     * @return ResponseInterface PSR-7响应对象
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface;

    /**
     * 获取运行时名称
     *
     * @return string
     */
    public function getName(): string;

    /**
     * 检查运行时是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * 获取运行时配置
     *
     * @return array
     */
    public function getConfig(): array;

    /**
     * 设置运行时配置
     *
     * @param array $config 配置数组
     * @return void
     */
    public function setConfig(array $config): void;
}
