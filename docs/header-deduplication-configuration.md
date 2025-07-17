# HTTP Header Deduplication Configuration Guide

## Overview

The ThinkPHP Runtime extension includes a comprehensive HTTP header deduplication system that prevents duplicate headers in HTTP responses, ensuring compliance with HTTP/1.1 specifications. This guide covers all configuration options and their usage.

## Configuration Location

Header deduplication settings are configured in `config/runtime.php` under the `header_deduplication` key:

```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => false,
    // ... other options
],
```

## Configuration Options

### Basic Settings

#### `enabled` (boolean, default: `true`)
Enables or disables the header deduplication functionality entirely.

```php
'enabled' => true,  // Enable header deduplication
'enabled' => false, // Disable header deduplication (use original behavior)
```

**When to disable**: Only disable if you encounter compatibility issues or need to debug header-related problems.

#### `debug_logging` (boolean, default: `false`)
Enables detailed debug logging for header processing operations.

```php
'debug_logging' => true,  // Enable debug logs
'debug_logging' => false, // Disable debug logs (recommended for production)
```

**Debug output includes**:
- Header conflict detection
- Header merging operations
- Resolution decisions
- Performance metrics

#### `strict_mode` (boolean, default: `false`)
When enabled, throws exceptions on critical header conflicts instead of resolving them automatically.

```php
'strict_mode' => true,  // Throw exceptions on conflicts
'strict_mode' => false, // Resolve conflicts automatically
```

**Use strict mode when**:
- Developing and testing applications
- You want to catch header conflicts early
- Debugging header-related issues

**Avoid strict mode when**:
- In production environments
- Using third-party middleware that may cause conflicts

### Logging Configuration

#### `log_critical_conflicts` (boolean, default: `true`)
Controls whether critical header conflicts are logged as warnings.

```php
'log_critical_conflicts' => true,  // Log critical conflicts
'log_critical_conflicts' => false, // Silent conflict resolution
```

**Critical headers include**: Content-Length, Content-Type, Authorization, Host, Location, Set-Cookie

#### `log_level` (string, default: `'info'`)
Sets the logging level for header deduplication operations.

```php
'log_level' => 'debug',   // Most verbose
'log_level' => 'info',    // Standard information
'log_level' => 'warning', // Only warnings and errors
'log_level' => 'error',   // Only errors
```

#### `log_file` (string, default: `'runtime/logs/header_deduplication.log'`)
Specifies a dedicated log file for header deduplication messages.

```php
'log_file' => 'runtime/logs/header_deduplication.log',
'log_file' => null, // Use default application logger
```

### Error Handling

#### `throw_on_merge_failure` (boolean, default: `false`)
Controls whether exceptions are thrown when header merging fails.

```php
'throw_on_merge_failure' => true,  // Throw exceptions on merge failures
'throw_on_merge_failure' => false, // Use fallback behavior
```

**Fallback behavior**: When merge fails, the system uses the first encountered header value.

### Header Processing Options

#### `preserve_original_case` (boolean, default: `false`)
Preserves the original case of header names instead of normalizing them.

```php
'preserve_original_case' => true,  // Keep original case (content-length)
'preserve_original_case' => false, // Normalize case (Content-Length)
```

**Note**: HTTP headers are case-insensitive, but some clients may expect specific casing.

#### `max_header_value_length` (integer, default: `8192`)
Maximum allowed length for header values in bytes.

```php
'max_header_value_length' => 8192,  // 8KB limit
'max_header_value_length' => 16384, // 16KB limit
```

**Security consideration**: Prevents extremely large header values that could cause memory issues.

### Performance Options

#### `enable_performance_logging` (boolean, default: `false`)
Enables performance metrics logging for header operations.

```php
'enable_performance_logging' => true,  // Log performance metrics
'enable_performance_logging' => false, // No performance logging
```

**Performance metrics include**:
- Processing time per operation
- Headers processed per second
- Memory usage statistics

#### `enable_header_name_cache` (boolean, default: `true`)
Enables caching of normalized header names for better performance.

```php
'enable_header_name_cache' => true,  // Cache normalized names
'enable_header_name_cache' => false, // No caching
```

#### `max_cache_size` (integer, default: `1000`)
Maximum number of header names to cache.

```php
'max_cache_size' => 1000, // Cache up to 1000 header names
'max_cache_size' => 500,  // Smaller cache for memory-constrained environments
```

#### `enable_batch_processing` (boolean, default: `true`)
Enables optimized batch processing for multiple header sets.

```php
'enable_batch_processing' => true,  // Use batch optimization
'enable_batch_processing' => false, // Process individually
```

## Runtime-Specific Configuration

You can override header deduplication settings for specific runtimes:

```php
'runtimes' => [
    'swoole' => [
        // ... other swoole settings
        'header_deduplication' => [
            'debug_logging' => true,
            'strict_mode' => false,
        ],
    ],
    'workerman' => [
        // ... other workerman settings
        'header_deduplication' => [
            'enable_performance_logging' => true,
            'max_header_value_length' => 4096,
        ],
    ],
],
```

## Environment-Specific Configurations

### Development Environment
```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => true,
    'strict_mode' => true,
    'log_critical_conflicts' => true,
    'throw_on_merge_failure' => true,
    'enable_performance_logging' => true,
    'log_level' => 'debug',
],
```

### Production Environment
```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => false,
    'log_critical_conflicts' => true,
    'throw_on_merge_failure' => false,
    'enable_performance_logging' => false,
    'log_level' => 'warning',
],
```

### High-Performance Environment
```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => false,
    'log_critical_conflicts' => false,
    'enable_performance_logging' => false,
    'enable_header_name_cache' => true,
    'max_cache_size' => 2000,
    'enable_batch_processing' => true,
],
```

## Configuration Validation

The system validates configuration options at startup. Invalid configurations will result in warnings or fallback to default values.

### Common Validation Errors

1. **Invalid log level**: Falls back to 'info'
2. **Negative cache size**: Falls back to default (1000)
3. **Invalid header value length**: Falls back to default (8192)

## Dynamic Configuration

You can modify configuration at runtime using the HeaderDeduplicationService:

```php
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

$service = new HeaderDeduplicationService();
$service->setConfig([
    'debug_logging' => true,
    'strict_mode' => false,
]);
```

## Configuration Best Practices

1. **Enable debug logging during development** to understand header conflicts
2. **Use strict mode for testing** to catch issues early
3. **Disable performance logging in production** unless debugging performance issues
4. **Set appropriate header value limits** based on your application needs
5. **Use runtime-specific configurations** for fine-tuned control
6. **Monitor logs regularly** to identify recurring header conflicts

## Next Steps

- Review the [Header Conflict Resolution Rules](header-conflict-resolution.md)
- Check the [Troubleshooting Guide](header-deduplication-troubleshooting.md)
- See [Performance Optimization](header-performance-optimization.md) for advanced tuning