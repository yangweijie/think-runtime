<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\contract;

/**
 * HTTP头部去重接口
 * 提供HTTP/1.1兼容的头部合并和去重功能
 */
interface HeaderDeduplicationInterface
{
    /**
     * 去重HTTP头部数组
     *
     * @param array $headers 原始头部数组
     * @return array 去重后的头部数组
     */
    public function deduplicateHeaders(array $headers): array;

    /**
     * 合并多个头部数组
     *
     * @param array $primary 主要头部数组（优先级高）
     * @param array $secondary 次要头部数组（优先级低）
     * @return array 合并后的头部数组
     */
    public function mergeHeaders(array $primary, array $secondary): array;

    /**
     * 标准化头部名称（处理大小写不敏感）
     *
     * @param string $name 原始头部名称
     * @return string 标准化后的头部名称
     */
    public function normalizeHeaderName(string $name): string;

    /**
     * 判断头部是否应该合并值而不是覆盖
     *
     * @param string $name 头部名称
     * @return bool 是否应该合并
     */
    public function shouldCombineHeader(string $name): bool;

    /**
     * 合并头部值
     *
     * @param string $name 头部名称
     * @param array $values 头部值数组
     * @return string 合并后的头部值
     */
    public function combineHeaderValues(string $name, array $values): string;

    /**
     * 检查头部冲突
     *
     * @param array $headers1 第一组头部
     * @param array $headers2 第二组头部
     * @return array 冲突的头部列表
     */
    public function detectHeaderConflicts(array $headers1, array $headers2): array;

    /**
     * 启用或禁用调试模式
     *
     * @param bool $enabled 是否启用调试
     * @return void
     */
    public function setDebugMode(bool $enabled): void;
}