<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\service;

use yangweijie\thinkRuntime\contract\HeaderDeduplicationInterface;
use yangweijie\thinkRuntime\exception\HeaderMergingException;
use yangweijie\thinkRuntime\exception\HeaderConflictException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * HTTP头部去重服务
 * 实现HTTP/1.1兼容的头部处理和去重功能
 */
class HeaderDeduplicationService implements HeaderDeduplicationInterface
{
    /**
     * 调试模式
     *
     * @var bool
     */
    protected bool $debugMode = false;

    /**
     * 严格模式 - 在冲突时抛出异常
     *
     * @var bool
     */
    protected bool $strictMode = false;

    /**
     * 日志记录器
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * 配置选项
     *
     * @var array
     */
    protected array $config = [
        'debug_logging' => false,
        'strict_mode' => false,
        'log_critical_conflicts' => true,
        'throw_on_merge_failure' => false,
        'preserve_original_case' => false,
        'max_header_value_length' => 8192,
        'enable_performance_logging' => false,
        'enable_header_name_cache' => true,
        'max_cache_size' => 1000,
        'enable_batch_processing' => true,
        'log_level' => 'info',
        'log_file' => null,
        'custom_rules' => [],
    ];

    /**
     * 自定义头部规则
     *
     * @var array
     */
    protected array $customRules = [];

    /**
     * 头部名称标准化缓存
     *
     * @var array
     */
    protected array $headerNameCache = [];

    /**
     * 缓存命中统计
     *
     * @var array
     */
    protected array $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'evictions' => 0,
    ];

    /**
     * 严重冲突的头部列表
     *
     * @var array
     */
    protected const CRITICAL_HEADERS = [
        'Content-Length',
        'Content-Type',
        'Authorization',
        'Host',
        'Location',
        'Set-Cookie',
    ];

    /**
     * 构造函数
     *
     * @param LoggerInterface|null $logger 日志记录器
     * @param array $config 配置选项
     */
    public function __construct(LoggerInterface $logger = null, array $config = [])
    {
        $this->logger = $logger ?? new NullLogger();
        $this->config = array_merge($this->config, $config);
        $this->debugMode = $this->config['debug_logging'] ?? false;
        $this->strictMode = $this->config['strict_mode'] ?? false;
        
        // 验证和处理配置
        $this->validateConfig();
        $this->processCustomRules();
    }

    /**
     * 头部名称标准化映射
     *
     * @var array
     */
    protected const HEADER_NORMALIZATION = [
        'content-length' => 'Content-Length',
        'content-type' => 'Content-Type',
        'content-encoding' => 'Content-Encoding',
        'cache-control' => 'Cache-Control',
        'set-cookie' => 'Set-Cookie',
        'accept' => 'Accept',
        'accept-charset' => 'Accept-Charset',
        'accept-encoding' => 'Accept-Encoding',
        'accept-language' => 'Accept-Language',
        'authorization' => 'Authorization',
        'connection' => 'Connection',
        'cookie' => 'Cookie',
        'host' => 'Host',
        'user-agent' => 'User-Agent',
        'referer' => 'Referer',
        'server' => 'Server',
        'date' => 'Date',
        'expires' => 'Expires',
        'last-modified' => 'Last-Modified',
        'etag' => 'ETag',
        'location' => 'Location',
        'pragma' => 'Pragma',
        'upgrade' => 'Upgrade',
        'via' => 'Via',
        'warning' => 'Warning',
        'www-authenticate' => 'WWW-Authenticate',
        'x-powered-by' => 'X-Powered-By',
        'x-frame-options' => 'X-Frame-Options',
        'x-content-type-options' => 'X-Content-Type-Options',
        'x-xss-protection' => 'X-XSS-Protection',
        'access-control-allow-origin' => 'Access-Control-Allow-Origin',
        'access-control-allow-methods' => 'Access-Control-Allow-Methods',
        'access-control-allow-headers' => 'Access-Control-Allow-Headers',
        'access-control-allow-credentials' => 'Access-Control-Allow-Credentials',
        'access-control-max-age' => 'Access-Control-Max-Age',
        'vary' => 'Vary',
    ];

    /**
     * 可以合并值的头部列表（而不是覆盖）
     *
     * @var array
     */
    protected const COMBINABLE_HEADERS = [
        'Accept',
        'Accept-Charset',
        'Accept-Encoding',
        'Accept-Language',
        'Connection',
        'Cookie',
        'Pragma',
        'Upgrade',
        'Via',
        'Warning',
        'Vary',
        'Access-Control-Allow-Methods',
        'Access-Control-Allow-Headers',
    ];

    /**
     * 不应该重复的头部列表
     *
     * @var array
     */
    protected const UNIQUE_HEADERS = [
        'Content-Length',
        'Content-Type',
        'Content-Encoding',
        'Host',
        'Authorization',
        'Date',
        'Expires',
        'Last-Modified',
        'ETag',
        'Location',
        'Server',
        'User-Agent',
        'Referer',
        'WWW-Authenticate',
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Credentials',
        'Access-Control-Max-Age',
    ];

    /**
     * 去重HTTP头部数组
     *
     * @param array $headers 原始头部数组
     * @return array 去重后的头部数组
     * @throws HeaderMergingException 当头部合并失败时
     * @throws HeaderConflictException 当检测到严重冲突且启用严格模式时
     */
    public function deduplicateHeaders(array $headers): array
    {
        $startTime = $this->config['enable_performance_logging'] ? microtime(true) : 0;
        $normalized = [];
        $conflicts = [];
        $criticalConflicts = [];

        try {
            $this->logDebug("Starting header deduplication for " . count($headers) . " headers");

            foreach ($headers as $name => $value) {
                // 验证头部名称和值
                $this->validateHeader($name, $value);
                
                $normalizedName = $this->normalizeHeaderName($name);
                
                if (isset($normalized[$normalizedName])) {
                    // 检测到重复头部
                    $conflicts[] = $normalizedName;
                    
                    // 检查是否为严重冲突
                    if ($this->isCriticalHeaderWithCustomRules($normalizedName)) {
                        $criticalConflicts[$normalizedName] = [
                            'existing' => $normalized[$normalizedName],
                            'new' => $value,
                            'original_names' => [$name] // 跟踪原始名称
                        ];
                        
                        $this->logCriticalConflict($normalizedName, $normalized[$normalizedName], $value);
                    }
                    
                    if ($this->shouldCombineHeaderWithCustomRules($normalizedName)) {
                        // 合并头部值
                        try {
                            $existingValues = is_array($normalized[$normalizedName]) 
                                ? $normalized[$normalizedName] 
                                : [$normalized[$normalizedName]];
                            $newValues = is_array($value) ? $value : [$value];
                            $allValues = array_merge($existingValues, $newValues);
                            $normalized[$normalizedName] = $this->combineHeaderValues($normalizedName, $allValues);
                            
                            $this->logDebug("Successfully combined header '{$normalizedName}' values");
                        } catch (\Exception $e) {
                            $this->handleMergeFailure($normalizedName, [$normalized[$normalizedName], $value], $e->getMessage());
                        }
                    } else {
                        // 对于唯一头部，保留第一个值（或根据优先级规则）
                        $this->logHeaderConflict($normalizedName, $normalized[$normalizedName], $value, 'keep_existing');
                        // 保持现有值，不覆盖
                    }
                } else {
                    $normalized[$normalizedName] = $value;
                    $this->logDebug("Added header '{$normalizedName}' with value: " . $this->truncateValue($value));
                }
            }

            // 处理严重冲突
            if (!empty($criticalConflicts)) {
                $this->handleCriticalConflicts($criticalConflicts);
            }

            if ($this->debugMode && !empty($conflicts)) {
                $uniqueConflicts = array_unique($conflicts);
                $this->logDebug("Header conflicts detected and resolved: " . implode(', ', $uniqueConflicts));
                $this->logger->info("Header deduplication completed", [
                    'total_headers' => count($headers),
                    'conflicts_resolved' => count($uniqueConflicts),
                    'critical_conflicts' => count($criticalConflicts)
                ]);
            }

            if ($this->config['enable_performance_logging']) {
                $duration = microtime(true) - $startTime;
                $this->logPerformance('deduplicateHeaders', $duration, count($headers));
            }

            return $normalized;

        } catch (\Exception $e) {
            $this->logger->error("Header deduplication failed", [
                'error' => $e->getMessage(),
                'headers_count' => count($headers),
                'conflicts' => $conflicts
            ]);
            
            if ($this->config['throw_on_merge_failure']) {
                throw $e;
            }
            
            // 返回原始头部作为后备
            return $headers;
        }
    }

    /**
     * 合并多个头部数组
     *
     * @param array $primary 主要头部数组（优先级高）
     * @param array $secondary 次要头部数组（优先级低）
     * @return array 合并后的头部数组
     * @throws HeaderMergingException 当头部合并失败时
     * @throws HeaderConflictException 当检测到严重冲突且启用严格模式时
     */
    public function mergeHeaders(array $primary, array $secondary): array
    {
        $startTime = $this->config['enable_performance_logging'] ? microtime(true) : 0;
        $conflicts = [];
        $criticalConflicts = [];

        try {
            $this->logDebug(sprintf(
                "Starting header merge: primary=%d headers, secondary=%d headers",
                count($primary),
                count($secondary)
            ));

            // 检测冲突
            $conflicts = $this->detectHeaderConflicts($primary, $secondary);
            
            if ($this->debugMode && !empty($conflicts)) {
                $this->logDebug("Merging headers with conflicts: " . implode(', ', $conflicts));
            }

            // 先处理次要头部
            $merged = [];
            foreach ($secondary as $name => $value) {
                $this->validateHeader($name, $value);
                $normalizedName = $this->normalizeHeaderName($name);
                $merged[$normalizedName] = $value;
                $this->logDebug("Added secondary header '{$normalizedName}': " . $this->truncateValue($value));
            }

            // 然后处理主要头部（会覆盖冲突的头部）
            foreach ($primary as $name => $value) {
                $this->validateHeader($name, $value);
                $normalizedName = $this->normalizeHeaderName($name);
                
                if (isset($merged[$normalizedName])) {
                    // 检查是否为严重冲突
                    if ($this->isCriticalHeaderWithCustomRules($normalizedName)) {
                        $criticalConflicts[$normalizedName] = [
                            'primary' => $value,
                            'secondary' => $merged[$normalizedName],
                            'resolution' => $this->shouldCombineHeaderWithCustomRules($normalizedName) ? 'combine' : 'primary_override'
                        ];
                        
                        $this->logCriticalConflict($normalizedName, $merged[$normalizedName], $value);
                    }

                    if ($this->shouldCombineHeaderWithCustomRules($normalizedName)) {
                        // 合并值
                        try {
                            $existingValues = is_array($merged[$normalizedName]) 
                                ? $merged[$normalizedName] 
                                : [$merged[$normalizedName]];
                            $newValues = is_array($value) ? $value : [$value];
                            $allValues = array_merge($existingValues, $newValues);
                            $merged[$normalizedName] = $this->combineHeaderValues($normalizedName, $allValues);
                            
                            $this->logDebug("Successfully merged header '{$normalizedName}' values");
                        } catch (\Exception $e) {
                            $this->handleMergeFailure($normalizedName, [$merged[$normalizedName], $value], $e->getMessage());
                            // 在失败时使用主要头部的值
                            $merged[$normalizedName] = $value;
                        }
                    } else {
                        // 主要头部优先
                        $this->logHeaderConflict($normalizedName, $merged[$normalizedName], $value, 'primary_override');
                        $merged[$normalizedName] = $value;
                    }
                } else {
                    $merged[$normalizedName] = $value;
                    $this->logDebug("Added primary header '{$normalizedName}': " . $this->truncateValue($value));
                }
            }

            // 处理严重冲突
            if (!empty($criticalConflicts)) {
                $this->handleCriticalConflicts($criticalConflicts);
            }

            if ($this->debugMode) {
                $this->logger->info("Header merge completed", [
                    'primary_headers' => count($primary),
                    'secondary_headers' => count($secondary),
                    'merged_headers' => count($merged),
                    'conflicts_resolved' => count($conflicts),
                    'critical_conflicts' => count($criticalConflicts)
                ]);
            }

            if ($this->config['enable_performance_logging']) {
                $duration = microtime(true) - $startTime;
                $this->logPerformance('mergeHeaders', $duration, count($primary) + count($secondary));
            }

            return $merged;

        } catch (\Exception $e) {
            $this->logger->error("Header merge failed", [
                'error' => $e->getMessage(),
                'primary_count' => count($primary),
                'secondary_count' => count($secondary),
                'conflicts' => $conflicts
            ]);
            
            if ($this->config['throw_on_merge_failure']) {
                throw $e;
            }
            
            // 返回主要头部作为后备
            return $primary;
        }
    }

    /**
     * 标准化头部名称（带缓存优化）
     *
     * @param string $name 原始头部名称
     * @return string 标准化后的头部名称
     */
    public function normalizeHeaderName(string $name): string
    {
        // 如果启用缓存，先检查缓存
        if ($this->config['enable_header_name_cache']) {
            if (isset($this->headerNameCache[$name])) {
                $this->cacheStats['hits']++;
                return $this->headerNameCache[$name];
            }
            $this->cacheStats['misses']++;
        }
        
        $lowerName = strtolower(trim($name));
        $normalizedName = self::HEADER_NORMALIZATION[$lowerName] ?? $this->toPascalCase($name);
        
        // 缓存结果
        if ($this->config['enable_header_name_cache']) {
            $this->cacheHeaderName($name, $normalizedName);
        }
        
        return $normalizedName;
    }

    /**
     * 判断头部是否应该合并值
     *
     * @param string $name 头部名称
     * @return bool 是否应该合并
     */
    public function shouldCombineHeader(string $name): bool
    {
        return in_array($name, self::COMBINABLE_HEADERS, true);
    }

    /**
     * 合并头部值
     *
     * @param string $name 头部名称
     * @param array $values 头部值数组
     * @return string 合并后的头部值
     */
    public function combineHeaderValues(string $name, array $values): string
    {
        // 去重并过滤空值
        $uniqueValues = array_unique(array_filter($values, function($value) {
            return $value !== null && $value !== '';
        }));

        // 根据头部类型选择合并方式
        switch ($name) {
            case 'Cache-Control':
            case 'Pragma':
                // 使用逗号和空格分隔
                return implode(', ', $uniqueValues);
                
            case 'Cookie':
                // Cookie使用分号分隔
                return implode('; ', $uniqueValues);
                
            case 'Set-Cookie':
                // Set-Cookie不应该合并，应该作为多个头部发送
                // 但在这里我们返回第一个值
                return $uniqueValues[0] ?? '';
                
            default:
                // 默认使用逗号分隔
                return implode(', ', $uniqueValues);
        }
    }

    /**
     * 检查头部冲突
     *
     * @param array $headers1 第一组头部
     * @param array $headers2 第二组头部
     * @return array 冲突的头部列表
     */
    public function detectHeaderConflicts(array $headers1, array $headers2): array
    {
        $conflicts = [];
        $normalized1 = [];
        $normalized2 = [];

        // 标准化第一组头部名称
        foreach ($headers1 as $name => $value) {
            $normalized1[$this->normalizeHeaderName($name)] = $value;
        }

        // 标准化第二组头部名称并检查冲突
        foreach ($headers2 as $name => $value) {
            $normalizedName = $this->normalizeHeaderName($name);
            $normalized2[$normalizedName] = $value;
            
            if (isset($normalized1[$normalizedName])) {
                $conflicts[] = $normalizedName;
            }
        }

        return array_unique($conflicts);
    }

    /**
     * 启用或禁用调试模式
     *
     * @param bool $enabled 是否启用调试
     * @return void
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
    }

    /**
     * 转换为Pascal命名法
     *
     * @param string $name 原始名称
     * @return string Pascal命名法的名称
     */
    protected function toPascalCase(string $name): string
    {
        return str_replace(' ', '-', ucwords(str_replace(['-', '_'], ' ', strtolower(trim($name)))));
    }

    /**
     * 记录头部冲突
     *
     * @param string $name 头部名称
     * @param mixed $existingValue 现有值
     * @param mixed $newValue 新值
     * @param string $resolution 解决方式
     * @return void
     */
    protected function logHeaderConflict(string $name, $existingValue, $newValue, string $resolution = 'keep_existing'): void
    {
        if ($this->debugMode) {
            $message = sprintf(
                "Header conflict detected for '%s': existing='%s', new='%s', resolution='%s'",
                $name,
                is_array($existingValue) ? implode(', ', $existingValue) : $existingValue,
                is_array($newValue) ? implode(', ', $newValue) : $newValue,
                $resolution
            );
            $this->logDebug($message);
        }
    }

    /**
     * 记录调试信息
     *
     * @param string $message 调试消息
     * @return void
     */
    protected function logDebug(string $message): void
    {
        if ($this->debugMode) {
            error_log("[HeaderDeduplication] " . $message);
        }
    }

    /**
     * 检查头部是否为唯一头部
     *
     * @param string $name 头部名称
     * @return bool 是否为唯一头部
     */
    public function isUniqueHeader(string $name): bool
    {
        return in_array($name, self::UNIQUE_HEADERS, true);
    }

    /**
     * 获取头部优先级规则
     * 返回应该优先保留的头部值
     *
     * @param string $name 头部名称
     * @param mixed $psrValue PSR-7响应中的值
     * @param mixed $runtimeValue 运行时设置的值
     * @return mixed 应该保留的值
     */
    public function resolveHeaderPriority(string $name, $psrValue, $runtimeValue)
    {
        switch ($name) {
            case 'Content-Length':
                // PSR-7响应的Content-Length优先
                return $psrValue !== null && $psrValue !== '' ? $psrValue : $runtimeValue;
                
            case 'Content-Type':
                // 应用设置的Content-Type优先
                return $psrValue !== null && $psrValue !== '' ? $psrValue : $runtimeValue;
                
            case 'Content-Encoding':
                // 运行时压缩设置优先
                return $runtimeValue !== null && $runtimeValue !== '' ? $runtimeValue : $psrValue;
                
            case 'Server':
                // 运行时Server头优先
                return $runtimeValue !== null && $runtimeValue !== '' ? $runtimeValue : $psrValue;
                
            case 'Cache-Control':
                // 应用设置的Cache-Control优先（PSR-7响应）
                return $psrValue !== null && $psrValue !== '' ? $psrValue : $runtimeValue;
                
            default:
                // 默认PSR-7响应优先
                return $psrValue !== null && $psrValue !== '' ? $psrValue : $runtimeValue;
        }
    }

    /**
     * 验证头部名称和值
     *
     * @param string|int $name 头部名称
     * @param mixed $value 头部值
     * @throws HeaderMergingException 当头部无效时
     */
    protected function validateHeader($name, $value): void
    {
        // 转换为字符串进行验证
        $nameStr = (string)$name;
        
        // 验证头部名称
        if (empty(trim($nameStr))) {
            throw HeaderMergingException::invalidValue('', $value, 'Header name cannot be empty');
        }

        // 验证头部名称格式
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $nameStr)) {
            throw HeaderMergingException::invalidValue($nameStr, $value, 'Header name contains invalid characters');
        }

        // 验证头部值长度
        $valueStr = is_array($value) ? implode(', ', $value) : (string)$value;
        if (strlen($valueStr) > $this->config['max_header_value_length']) {
            throw HeaderMergingException::invalidValue($nameStr, $value, 
                sprintf('Header value exceeds maximum length of %d bytes', $this->config['max_header_value_length']));
        }

        // 验证头部值格式（基本检查）
        if (is_string($value) && strpos($value, "\n") !== false) {
            throw HeaderMergingException::invalidValue($nameStr, $value, 'Header value cannot contain newline characters');
        }

        $this->logDebug("Header validation passed for '{$nameStr}'");
    }

    /**
     * 检查是否为严重冲突的头部
     *
     * @param string $name 头部名称
     * @return bool 是否为严重头部
     */
    protected function isCriticalHeader(string $name): bool
    {
        return in_array($name, self::CRITICAL_HEADERS, true);
    }

    /**
     * 记录严重头部冲突
     *
     * @param string $name 头部名称
     * @param mixed $existingValue 现有值
     * @param mixed $newValue 新值
     */
    protected function logCriticalConflict(string $name, $existingValue, $newValue): void
    {
        $message = sprintf(
            "CRITICAL header conflict detected for '%s': existing='%s', new='%s'",
            $name,
            $this->truncateValue($existingValue),
            $this->truncateValue($newValue)
        );

        if ($this->config['log_critical_conflicts']) {
            $this->logger->warning($message, [
                'header_name' => $name,
                'existing_value' => $existingValue,
                'new_value' => $newValue,
                'conflict_type' => 'critical'
            ]);
        }

        $this->logDebug($message);
    }

    /**
     * 处理头部合并失败
     *
     * @param string $name 头部名称
     * @param array $values 冲突的值
     * @param string $reason 失败原因
     * @throws HeaderMergingException 当配置要求抛出异常时
     */
    protected function handleMergeFailure(string $name, array $values, string $reason): void
    {
        $message = sprintf(
            "Failed to merge header '%s' values: %s. Reason: %s",
            $name,
            implode(' vs ', array_map([$this, 'truncateValue'], $values)),
            $reason
        );

        $this->logger->error($message, [
            'header_name' => $name,
            'conflicting_values' => $values,
            'failure_reason' => $reason
        ]);

        if ($this->config['throw_on_merge_failure']) {
            throw HeaderMergingException::mergeFailed($name, $values, $reason);
        }
    }

    /**
     * 处理严重冲突
     *
     * @param array $conflicts 冲突信息
     * @throws HeaderConflictException 当启用严格模式时
     */
    protected function handleCriticalConflicts(array $conflicts): void
    {
        $conflictNames = array_keys($conflicts);
        $message = sprintf(
            "Critical header conflicts detected: %s",
            implode(', ', $conflictNames)
        );

        $this->logger->warning($message, [
            'critical_conflicts' => $conflicts,
            'conflict_count' => count($conflicts),
            'strict_mode' => $this->strictMode
        ]);

        if ($this->strictMode) {
            throw HeaderConflictException::criticalConflict($conflicts, 'strict_mode_abort');
        }
    }

    /**
     * 截断值用于日志记录
     *
     * @param mixed $value 要截断的值
     * @param int $maxLength 最大长度
     * @return string 截断后的值
     */
    protected function truncateValue($value, int $maxLength = 100): string
    {
        $str = is_array($value) ? implode(', ', $value) : (string)$value;
        return strlen($str) > $maxLength ? substr($str, 0, $maxLength) . '...' : $str;
    }

    /**
     * 记录性能信息
     *
     * @param string $operation 操作名称
     * @param float $duration 持续时间（秒）
     * @param int $itemCount 处理项目数量
     */
    protected function logPerformance(string $operation, float $duration, int $itemCount): void
    {
        $message = sprintf(
            "Performance: %s completed in %.4f seconds for %d items (%.4f ms per item)",
            $operation,
            $duration,
            $itemCount,
            $itemCount > 0 ? ($duration * 1000) / $itemCount : 0
        );

        $this->logger->debug($message, [
            'operation' => $operation,
            'duration_seconds' => $duration,
            'item_count' => $itemCount,
            'ms_per_item' => $itemCount > 0 ? ($duration * 1000) / $itemCount : 0
        ]);
    }

    /**
     * 设置配置选项
     *
     * @param array $config 配置数组
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->debugMode = $this->config['debug_logging'] ?? $this->debugMode;
        $this->strictMode = $this->config['strict_mode'] ?? $this->strictMode;
        
        $this->logDebug("Configuration updated: " . json_encode($config));
    }

    /**
     * 获取当前配置
     *
     * @return array 当前配置
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 启用或禁用严格模式
     *
     * @param bool $enabled 是否启用严格模式
     */
    public function setStrictMode(bool $enabled): void
    {
        $this->strictMode = $enabled;
        $this->config['strict_mode'] = $enabled;
        
        $this->logDebug("Strict mode " . ($enabled ? 'enabled' : 'disabled'));
    }

    /**
     * 设置日志记录器
     *
     * @param LoggerInterface $logger 日志记录器
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->logDebug("Logger updated");
    }

    /**
     * 获取头部统计信息
     *
     * @param array $headers 头部数组
     * @return array 统计信息
     */
    public function getHeaderStats(array $headers): array
    {
        $stats = [
            'total_headers' => count($headers),
            'unique_headers' => 0,
            'combinable_headers' => 0,
            'critical_headers' => 0,
            'normalized_names' => [],
            'potential_conflicts' => []
        ];

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalizedName = $this->normalizeHeaderName($name);
            
            if (!isset($normalized[$normalizedName])) {
                $stats['unique_headers']++;
                $normalized[$normalizedName] = true;
            } else {
                $stats['potential_conflicts'][] = $normalizedName;
            }

            if ($this->shouldCombineHeader($normalizedName)) {
                $stats['combinable_headers']++;
            }

            if ($this->isCriticalHeader($normalizedName)) {
                $stats['critical_headers']++;
            }

            $stats['normalized_names'][] = $normalizedName;
        }

        $stats['potential_conflicts'] = array_unique($stats['potential_conflicts']);
        $stats['normalized_names'] = array_unique($stats['normalized_names']);

        return $stats;
    }

    /**
     * 批量处理头部去重
     *
     * @param array $headerSets 头部集合数组
     * @return array 去重后的头部集合数组
     */
    public function batchDeduplicateHeaders(array $headerSets): array
    {
        if (!$this->config['enable_batch_processing']) {
            // 如果未启用批量处理，逐个处理
            $results = [];
            foreach ($headerSets as $headers) {
                $results[] = $this->deduplicateHeaders($headers);
            }
            return $results;
        }

        $startTime = $this->config['enable_performance_logging'] ? microtime(true) : 0;
        $results = [];
        $totalHeaders = 0;

        try {
            foreach ($headerSets as $headers) {
                $totalHeaders += count($headers);
                $results[] = $this->deduplicateHeaders($headers);
            }

            if ($this->config['enable_performance_logging']) {
                $duration = microtime(true) - $startTime;
                $this->logPerformance('batchDeduplicateHeaders', $duration, $totalHeaders);
            }

            return $results;

        } catch (\Exception $e) {
            $this->logger->error("Batch header deduplication failed", [
                'error' => $e->getMessage(),
                'batch_size' => count($headerSets),
                'total_headers' => $totalHeaders
            ]);
            throw $e;
        }
    }

    /**
     * 缓存头部名称
     *
     * @param string $originalName 原始名称
     * @param string $normalizedName 标准化名称
     */
    protected function cacheHeaderName(string $originalName, string $normalizedName): void
    {
        // 检查缓存大小限制
        if (count($this->headerNameCache) >= $this->config['max_cache_size']) {
            // 简单的LRU策略：移除最早的条目
            $firstKey = array_key_first($this->headerNameCache);
            unset($this->headerNameCache[$firstKey]);
            $this->cacheStats['evictions']++;
        }

        $this->headerNameCache[$originalName] = $normalizedName;
    }

    /**
     * 获取缓存统计信息
     *
     * @return array 缓存统计
     */
    public function getCacheStats(): array
    {
        $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        $hitRate = $total > 0 ? ($this->cacheStats['hits'] / $total) * 100 : 0;

        return array_merge($this->cacheStats, [
            'cache_size' => count($this->headerNameCache),
            'hit_rate_percent' => round($hitRate, 2),
            'total_requests' => $total,
        ]);
    }

    /**
     * 清空缓存
     */
    public function clearCache(): void
    {
        $this->headerNameCache = [];
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'evictions' => 0,
        ];
        
        $this->logDebug("Header name cache cleared");
    }

    /**
     * 预热缓存（使用常见头部名称）
     */
    public function warmupCache(): void
    {
        $commonHeaders = [
            'content-type', 'Content-Type', 'CONTENT-TYPE',
            'content-length', 'Content-Length', 'CONTENT-LENGTH',
            'accept', 'Accept', 'ACCEPT',
            'user-agent', 'User-Agent', 'USER-AGENT',
            'authorization', 'Authorization', 'AUTHORIZATION',
            'cache-control', 'Cache-Control', 'CACHE-CONTROL',
            'set-cookie', 'Set-Cookie', 'SET-COOKIE',
            'x-powered-by', 'X-Powered-By', 'X-POWERED-BY',
        ];

        foreach ($commonHeaders as $header) {
            $this->normalizeHeaderName($header);
        }

        $this->logDebug("Header name cache warmed up with " . count($commonHeaders) . " common headers");
    }

    /**
     * 获取性能指标
     *
     * @return array 性能指标
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache_stats' => $this->getCacheStats(),
            'config' => [
                'cache_enabled' => $this->config['enable_header_name_cache'],
                'batch_processing_enabled' => $this->config['enable_batch_processing'],
                'performance_logging_enabled' => $this->config['enable_performance_logging'],
                'max_cache_size' => $this->config['max_cache_size'],
            ],
        ];
    }

    /**
     * 优化配置建议
     *
     * @return array 优化建议
     */
    public function getOptimizationSuggestions(): array
    {
        $suggestions = [];
        $cacheStats = $this->getCacheStats();

        // 缓存命中率建议
        if ($cacheStats['total_requests'] > 100) {
            if ($cacheStats['hit_rate_percent'] < 50) {
                $suggestions[] = [
                    'type' => 'cache',
                    'priority' => 'high',
                    'message' => 'Low cache hit rate (' . $cacheStats['hit_rate_percent'] . '%). Consider increasing max_cache_size.',
                    'recommendation' => 'Increase max_cache_size to ' . ($this->config['max_cache_size'] * 2)
                ];
            }
        }

        // 缓存驱逐建议
        if ($cacheStats['evictions'] > $cacheStats['hits'] * 0.1) {
            $suggestions[] = [
                'type' => 'cache',
                'priority' => 'medium',
                'message' => 'High cache eviction rate. Cache size may be too small.',
                'recommendation' => 'Increase max_cache_size or implement better cache eviction strategy'
            ];
        }

        // 配置建议
        if (!$this->config['enable_header_name_cache']) {
            $suggestions[] = [
                'type' => 'config',
                'priority' => 'medium',
                'message' => 'Header name caching is disabled.',
                'recommendation' => 'Enable header name caching for better performance'
            ];
        }

        if ($this->config['debug_logging'] && !$this->debugMode) {
            $suggestions[] = [
                'type' => 'config',
                'priority' => 'low',
                'message' => 'Debug logging is enabled but debug mode is off.',
                'recommendation' => 'Disable debug logging in production for better performance'
            ];
        }

        return $suggestions;
    }

    /**
     * 验证配置选项
     *
     * @throws \InvalidArgumentException 当配置无效时
     */
    protected function validateConfig(): void
    {
        // 验证日志级别
        $validLogLevels = ['debug', 'info', 'warning', 'error'];
        if (!in_array($this->config['log_level'], $validLogLevels)) {
            $this->logger->warning("Invalid log level '{$this->config['log_level']}', falling back to 'info'");
            $this->config['log_level'] = 'info';
        }

        // 验证缓存大小
        if ($this->config['max_cache_size'] < 0) {
            $this->logger->warning("Invalid cache size '{$this->config['max_cache_size']}', falling back to 1000");
            $this->config['max_cache_size'] = 1000;
        }

        // 验证头部值最大长度
        if ($this->config['max_header_value_length'] < 1) {
            $this->logger->warning("Invalid header value length '{$this->config['max_header_value_length']}', falling back to 8192");
            $this->config['max_header_value_length'] = 8192;
        }

        // 验证布尔值配置
        $booleanConfigs = [
            'debug_logging', 'strict_mode', 'log_critical_conflicts', 
            'throw_on_merge_failure', 'preserve_original_case', 
            'enable_performance_logging', 'enable_header_name_cache', 
            'enable_batch_processing'
        ];

        foreach ($booleanConfigs as $key) {
            if (isset($this->config[$key]) && !is_bool($this->config[$key])) {
                $this->config[$key] = (bool)$this->config[$key];
                $this->logger->debug("Converted config '{$key}' to boolean: " . ($this->config[$key] ? 'true' : 'false'));
            }
        }

        // 验证自定义规则
        if (!empty($this->config['custom_rules']) && !is_array($this->config['custom_rules'])) {
            $this->logger->warning("Invalid custom_rules configuration, must be array. Ignoring custom rules.");
            $this->config['custom_rules'] = [];
        }

        $this->logDebug("Configuration validation completed");
    }

    /**
     * 处理自定义头部规则
     */
    protected function processCustomRules(): void
    {
        if (empty($this->config['custom_rules'])) {
            return;
        }

        foreach ($this->config['custom_rules'] as $headerName => $rule) {
            if (!is_array($rule)) {
                $this->logger->warning("Invalid custom rule for header '{$headerName}', must be array");
                continue;
            }

            // 验证规则结构
            $validatedRule = $this->validateCustomRule($headerName, $rule);
            if ($validatedRule) {
                $this->customRules[$headerName] = $validatedRule;
                $this->logDebug("Added custom rule for header '{$headerName}': " . json_encode($validatedRule));
            }
        }

        $this->logDebug("Processed " . count($this->customRules) . " custom header rules");
    }

    /**
     * 验证自定义规则
     *
     * @param string $headerName 头部名称
     * @param array $rule 规则配置
     * @return array|null 验证后的规则，无效时返回null
     */
    protected function validateCustomRule(string $headerName, array $rule): ?array
    {
        $defaultRule = [
            'priority' => 'psr7_first',
            'combinable' => false,
            'separator' => ', ',
            'critical' => false,
        ];

        $validatedRule = array_merge($defaultRule, $rule);

        // 验证优先级
        $validPriorities = ['psr7_first', 'runtime_first', 'combine'];
        if (!in_array($validatedRule['priority'], $validPriorities)) {
            $this->logger->warning("Invalid priority '{$validatedRule['priority']}' for header '{$headerName}', using 'psr7_first'");
            $validatedRule['priority'] = 'psr7_first';
        }

        // 验证布尔值
        $validatedRule['combinable'] = (bool)$validatedRule['combinable'];
        $validatedRule['critical'] = (bool)$validatedRule['critical'];

        // 验证分隔符
        if (!is_string($validatedRule['separator'])) {
            $this->logger->warning("Invalid separator for header '{$headerName}', using ', '");
            $validatedRule['separator'] = ', ';
        }

        return $validatedRule;
    }

    /**
     * 应用自定义规则判断头部是否应该合并
     *
     * @param string $name 头部名称
     * @return bool 是否应该合并
     */
    protected function shouldCombineHeaderWithCustomRules(string $name): bool
    {
        // 检查自定义规则
        if (isset($this->customRules[$name])) {
            return $this->customRules[$name]['combinable'];
        }

        // 回退到默认规则
        return $this->shouldCombineHeader($name);
    }

    /**
     * 应用自定义规则合并头部值
     *
     * @param string $name 头部名称
     * @param array $values 头部值数组
     * @return string 合并后的头部值
     */
    protected function combineHeaderValuesWithCustomRules(string $name, array $values): string
    {
        // 检查自定义规则
        if (isset($this->customRules[$name])) {
            $rule = $this->customRules[$name];
            if ($rule['combinable']) {
                $uniqueValues = array_unique(array_filter($values, function($value) {
                    return $value !== null && $value !== '';
                }));
                return implode($rule['separator'], $uniqueValues);
            }
        }

        // 回退到默认规则
        return $this->combineHeaderValues($name, $values);
    }

    /**
     * 应用自定义规则检查是否为严重头部
     *
     * @param string $name 头部名称
     * @return bool 是否为严重头部
     */
    protected function isCriticalHeaderWithCustomRules(string $name): bool
    {
        // 检查自定义规则
        if (isset($this->customRules[$name])) {
            return $this->customRules[$name]['critical'];
        }

        // 回退到默认规则
        return $this->isCriticalHeader($name);
    }

    /**
     * 应用自定义优先级规则解决头部冲突
     *
     * @param string $name 头部名称
     * @param mixed $psrValue PSR-7响应中的值
     * @param mixed $runtimeValue 运行时设置的值
     * @return mixed 应该保留的值
     */
    public function resolveHeaderPriorityWithCustomRules(string $name, $psrValue, $runtimeValue)
    {
        // 检查自定义规则
        if (isset($this->customRules[$name])) {
            $rule = $this->customRules[$name];
            
            switch ($rule['priority']) {
                case 'runtime_first':
                    return $runtimeValue !== null && $runtimeValue !== '' ? $runtimeValue : $psrValue;
                    
                case 'combine':
                    if ($rule['combinable']) {
                        $values = array_filter([$psrValue, $runtimeValue], function($value) {
                            return $value !== null && $value !== '';
                        });
                        return $this->combineHeaderValuesWithCustomRules($name, $values);
                    }
                    // 如果不可合并，回退到psr7_first
                    return $psrValue !== null && $psrValue !== '' ? $psrValue : $runtimeValue;
                    
                case 'psr7_first':
                default:
                    return $psrValue !== null && $psrValue !== '' ? $psrValue : $runtimeValue;
            }
        }

        // 回退到默认规则
        return $this->resolveHeaderPriority($name, $psrValue, $runtimeValue);
    }

    /**
     * 获取自定义规则
     *
     * @return array 自定义规则数组
     */
    public function getCustomRules(): array
    {
        return $this->customRules;
    }

    /**
     * 添加自定义规则
     *
     * @param string $headerName 头部名称
     * @param array $rule 规则配置
     * @return bool 是否添加成功
     */
    public function addCustomRule(string $headerName, array $rule): bool
    {
        $validatedRule = $this->validateCustomRule($headerName, $rule);
        if ($validatedRule) {
            $this->customRules[$headerName] = $validatedRule;
            $this->logDebug("Added custom rule for header '{$headerName}': " . json_encode($validatedRule));
            return true;
        }
        
        return false;
    }

    /**
     * 移除自定义规则
     *
     * @param string $headerName 头部名称
     * @return bool 是否移除成功
     */
    public function removeCustomRule(string $headerName): bool
    {
        if (isset($this->customRules[$headerName])) {
            unset($this->customRules[$headerName]);
            $this->logDebug("Removed custom rule for header '{$headerName}'");
            return true;
        }
        
        return false;
    }

    /**
     * 获取配置架构信息
     *
     * @return array 配置架构
     */
    public static function getConfigSchema(): array
    {
        return [
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Enable or disable header deduplication functionality'
            ],
            'debug_logging' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Enable detailed debug logging for header processing'
            ],
            'strict_mode' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Throw exceptions on critical header conflicts'
            ],
            'log_critical_conflicts' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Log critical header conflicts as warnings'
            ],
            'throw_on_merge_failure' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Throw exceptions when header merging fails'
            ],
            'preserve_original_case' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Preserve original header name case instead of normalizing'
            ],
            'max_header_value_length' => [
                'type' => 'integer',
                'default' => 8192,
                'min' => 1,
                'description' => 'Maximum allowed length for header values in bytes'
            ],
            'enable_performance_logging' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Enable performance metrics logging'
            ],
            'enable_header_name_cache' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Enable caching of normalized header names'
            ],
            'max_cache_size' => [
                'type' => 'integer',
                'default' => 1000,
                'min' => 0,
                'description' => 'Maximum number of header names to cache'
            ],
            'enable_batch_processing' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Enable optimized batch processing for multiple header sets'
            ],
            'log_level' => [
                'type' => 'string',
                'default' => 'info',
                'enum' => ['debug', 'info', 'warning', 'error'],
                'description' => 'Logging level for header deduplication operations'
            ],
            'log_file' => [
                'type' => 'string|null',
                'default' => null,
                'description' => 'Dedicated log file for header deduplication messages'
            ],
            'custom_rules' => [
                'type' => 'array',
                'default' => [],
                'description' => 'Custom header resolution rules',
                'schema' => [
                    'header_name' => [
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['psr7_first', 'runtime_first', 'combine'],
                            'default' => 'psr7_first',
                            'description' => 'Priority rule for header conflict resolution'
                        ],
                        'combinable' => [
                            'type' => 'boolean',
                            'default' => false,
                            'description' => 'Whether header values can be combined'
                        ],
                        'separator' => [
                            'type' => 'string',
                            'default' => ', ',
                            'description' => 'Separator for combinable header values'
                        ],
                        'critical' => [
                            'type' => 'boolean',
                            'default' => false,
                            'description' => 'Whether conflicts should be logged as critical'
                        ]
                    ]
                ]
            ]
        ];
    }

}