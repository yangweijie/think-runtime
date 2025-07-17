<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\exception;

use Exception;

/**
 * HTTP头部合并异常
 * 当头部合并过程中发生错误时抛出
 */
class HeaderMergingException extends Exception
{
    /**
     * 冲突的头部名称
     *
     * @var string
     */
    protected string $headerName;

    /**
     * 冲突的头部值
     *
     * @var array
     */
    protected array $conflictingValues;

    /**
     * 构造函数
     *
     * @param string $message 异常消息
     * @param string $headerName 冲突的头部名称
     * @param array $conflictingValues 冲突的头部值
     * @param int $code 异常代码
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(
        string $message,
        string $headerName = '',
        array $conflictingValues = [],
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->headerName = $headerName;
        $this->conflictingValues = $conflictingValues;
    }

    /**
     * 获取冲突的头部名称
     *
     * @return string
     */
    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    /**
     * 获取冲突的头部值
     *
     * @return array
     */
    public function getConflictingValues(): array
    {
        return $this->conflictingValues;
    }

    /**
     * 创建头部重复异常
     *
     * @param string $headerName 头部名称
     * @param mixed $existingValue 现有值
     * @param mixed $newValue 新值
     * @return static
     */
    public static function duplicateHeader(string $headerName, $existingValue, $newValue): self
    {
        $message = sprintf(
            "Duplicate header '%s' detected. Existing: '%s', New: '%s'",
            $headerName,
            is_array($existingValue) ? implode(', ', $existingValue) : $existingValue,
            is_array($newValue) ? implode(', ', $newValue) : $newValue
        );

        return new self($message, $headerName, [$existingValue, $newValue]);
    }

    /**
     * 创建头部合并失败异常
     *
     * @param string $headerName 头部名称
     * @param array $values 要合并的值
     * @param string $reason 失败原因
     * @return static
     */
    public static function mergeFailed(string $headerName, array $values, string $reason = ''): self
    {
        $message = sprintf(
            "Failed to merge header '%s' values: %s. %s",
            $headerName,
            implode(', ', $values),
            $reason
        );

        return new self($message, $headerName, $values);
    }

    /**
     * 创建无效头部值异常
     *
     * @param string $headerName 头部名称
     * @param mixed $value 无效值
     * @param string $reason 原因
     * @return static
     */
    public static function invalidValue(string $headerName, $value, string $reason = ''): self
    {
        $message = sprintf(
            "Invalid value for header '%s': '%s'. %s",
            $headerName,
            is_array($value) ? implode(', ', $value) : $value,
            $reason
        );

        return new self($message, $headerName, [$value]);
    }
}