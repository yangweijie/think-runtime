<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\exception;

use Exception;

/**
 * HTTP头部冲突异常
 * 当检测到严重的头部冲突时抛出
 */
class HeaderConflictException extends Exception
{
    /**
     * 冲突的头部信息
     *
     * @var array
     */
    protected array $conflicts;

    /**
     * 冲突解决策略
     *
     * @var string
     */
    protected string $resolutionStrategy;

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param array $conflicts 冲突信息
     * @param string $resolutionStrategy 解决策略
     * @param int $code 异常代码
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(
        string $message,
        array $conflicts = [],
        string $resolutionStrategy = '',
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->conflicts = $conflicts;
        $this->resolutionStrategy = $resolutionStrategy;
    }

    /**
     * 获取冲突信息
     *
     * @return array
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * 获取解决策略
     *
     * @return string
     */
    public function getResolutionStrategy(): string
    {
        return $this->resolutionStrategy;
    }

    /**
     * 创建严重冲突异常
     *
     * @param array $conflicts 冲突信息
     * @param string $strategy 解决策略
     * @return static
     */
    public static function criticalConflict(array $conflicts, string $strategy = 'abort'): self
    {
        $headerNames = array_keys($conflicts);
        $message = sprintf(
            "Critical header conflicts detected for: %s. Resolution strategy: %s",
            implode(', ', $headerNames),
            $strategy
        );

        return new self($message, $conflicts, $strategy);
    }

    /**
     * 创建不兼容头部异常
     *
     * @param string $headerName 头部名称
     * @param array $values 不兼容的值
     * @return static
     */
    public static function incompatibleValues(string $headerName, array $values): self
    {
        $message = sprintf(
            "Incompatible values detected for header '%s': %s",
            $headerName,
            implode(' vs ', $values)
        );

        $conflicts = [$headerName => $values];
        return new self($message, $conflicts, 'manual_resolution_required');
    }
}