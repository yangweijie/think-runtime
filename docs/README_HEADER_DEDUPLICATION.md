# HTTP Header Deduplication System

## Overview

The ThinkPHP Runtime extension includes a comprehensive HTTP header deduplication system that prevents duplicate headers in HTTP responses, ensuring compliance with HTTP/1.1 specifications and resolving common issues like duplicate Content-Length headers.

## Quick Start

### Basic Configuration

Add to your `config/runtime.php`:

```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => false,
],
```

### Enable Debug Mode (Development)

```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => true,
    'strict_mode' => true,
    'log_level' => 'debug',
],
```

## Features

- ✅ **HTTP/1.1 Compliant**: Follows RFC specifications for header handling
- ✅ **Case-Insensitive**: Handles headers regardless of case (Content-Length vs content-length)
- ✅ **Smart Merging**: Combines headers when appropriate, replaces when necessary
- ✅ **Performance Optimized**: Minimal overhead with caching and batch processing
- ✅ **Configurable**: Extensive configuration options for different environments
- ✅ **Runtime Agnostic**: Works with all supported runtimes (Swoole, Workerman, etc.)
- ✅ **Custom Rules**: Define custom resolution rules for specific headers
- ✅ **Debug Support**: Comprehensive logging and error reporting

## Common Issues Resolved

### Duplicate Content-Length Headers
```
Before: Content-Length: 1024
        content-length: 1025
After:  Content-Length: 1024
```

### CORS Header Conflicts
```
Before: Access-Control-Allow-Origin: *
        Access-Control-Allow-Origin: https://example.com
After:  Access-Control-Allow-Origin: https://example.com
```

### Cache-Control Merging
```
Before: Cache-Control: max-age=3600
        cache-control: no-cache
After:  Cache-Control: max-age=3600, no-cache
```

## Configuration Options

### Basic Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | boolean | `true` | Enable/disable header deduplication |
| `debug_logging` | boolean | `false` | Enable detailed debug logging |
| `strict_mode` | boolean | `false` | Throw exceptions on conflicts |
| `log_critical_conflicts` | boolean | `true` | Log critical header conflicts |

### Performance Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enable_header_name_cache` | boolean | `true` | Cache normalized header names |
| `max_cache_size` | integer | `1000` | Maximum cache entries |
| `enable_batch_processing` | boolean | `true` | Optimize batch operations |
| `enable_performance_logging` | boolean | `false` | Log performance metrics |

### Advanced Settings

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `preserve_original_case` | boolean | `false` | Keep original header case |
| `max_header_value_length` | integer | `8192` | Max header value length (bytes) |
| `throw_on_merge_failure` | boolean | `false` | Throw on merge failures |
| `log_level` | string | `'info'` | Logging level |
| `log_file` | string\|null | `null` | Dedicated log file |

## Environment Configurations

### Development
```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => true,
    'strict_mode' => true,
    'log_level' => 'debug',
    'enable_performance_logging' => true,
],
```

### Production
```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => false,
    'log_level' => 'warning',
    'max_cache_size' => 2000,
],
```

### High Performance
```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'log_critical_conflicts' => false,
    'max_cache_size' => 5000,
    'max_header_value_length' => 4096,
],
```

## Custom Rules

Define custom resolution rules for specific headers:

```php
'header_deduplication' => [
    'custom_rules' => [
        'X-API-Version' => [
            'priority' => 'psr7_first',    // Application controls
            'combinable' => false,          // Single value only
            'critical' => true,            // Log conflicts
        ],
        'X-Cache-Tags' => [
            'priority' => 'combine',       // Combine all sources
            'combinable' => true,          // Allow multiple values
            'separator' => ' ',            // Space-separated
            'critical' => false,           // Not critical
        ],
    ],
],
```

### Custom Rule Options

- **priority**: `'psr7_first'` | `'runtime_first'` | `'combine'`
- **combinable**: `true` | `false` - Whether values can be merged
- **separator**: String used to combine values (default: `', '`)
- **critical**: `true` | `false` - Whether conflicts are logged as critical

## Runtime-Specific Configuration

Override settings for specific runtimes:

```php
'runtimes' => [
    'swoole' => [
        'header_deduplication' => [
            'custom_rules' => [
                'Server' => [
                    'priority' => 'runtime_first',
                    'combinable' => false,
                ],
            ],
        ],
    ],
    'workerman' => [
        'header_deduplication' => [
            'debug_logging' => true,
        ],
    ],
],
```

## Debugging

### Enable Debug Logging

```php
'header_deduplication' => [
    'debug_logging' => true,
    'log_level' => 'debug',
    'log_file' => 'runtime/logs/header_debug.log',
],
```

### Debug Output Example

```
[HeaderDeduplication] Starting header deduplication for 5 headers
[HeaderDeduplication] Header conflict detected for 'Content-Length': existing='1024', new='1025', resolution='keep_existing'
[HeaderDeduplication] Successfully combined header 'Cache-Control' values
[HeaderDeduplication] CRITICAL header conflict detected for 'Content-Length'
```

### Validation Script

Validate your configuration:

```bash
php scripts/validate_header_config.php --environment=production
php scripts/validate_header_config.php --fix --environment=development
```

## Troubleshooting

### Common Issues

1. **Still seeing duplicate headers**
   - Check if `enabled => true`
   - Verify runtime is using the service
   - Enable debug logging to see processing

2. **Performance issues**
   - Disable debug logging in production
   - Increase cache size
   - Enable batch processing

3. **CORS not working**
   - Use middleware for CORS, not application code
   - Check middleware priority order

4. **Exceptions in production**
   - Disable strict mode
   - Set `throw_on_merge_failure => false`

### Debug Script

Create a test script:

```php
<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

$service = new HeaderDeduplicationService(null, [
    'debug_logging' => true,
    'strict_mode' => true,
]);

$headers = [
    'Content-Length' => '1024',
    'content-length' => '1025',
];

$result = $service->deduplicateHeaders($headers);
print_r($result);
```

## Performance Impact

- **Overhead**: < 1ms per request for typical header sets
- **Memory**: ~1KB per 1000 cached header names
- **CPU**: Minimal impact with caching enabled

### Benchmarks

| Headers | Time (ms) | Memory (KB) |
|---------|-----------|-------------|
| 10      | 0.1       | 0.5         |
| 50      | 0.3       | 1.2         |
| 100     | 0.5       | 2.1         |

## API Reference

### HeaderDeduplicationService

```php
// Create service
$service = new HeaderDeduplicationService($logger, $config);

// Deduplicate headers
$clean = $service->deduplicateHeaders($headers);

// Merge header arrays
$merged = $service->mergeHeaders($primary, $secondary);

// Add custom rule
$service->addCustomRule('X-Custom', [
    'priority' => 'psr7_first',
    'combinable' => false,
]);

// Get statistics
$stats = $service->getHeaderStats($headers);
$cacheStats = $service->getCacheStats();
```

## Integration Examples

### Manual Usage

```php
use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

$service = new HeaderDeduplicationService();
$cleanHeaders = $service->deduplicateHeaders($responseHeaders);
```

### Middleware Integration

```php
class HeaderDeduplicationMiddleware
{
    public function handle($request, $next)
    {
        $response = $next($request);
        
        $service = new HeaderDeduplicationService();
        $headers = $service->deduplicateHeaders($response->getHeaders());
        
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        
        return $response;
    }
}
```

## Documentation Links

- [Configuration Guide](header-deduplication-configuration.md) - Detailed configuration options
- [Conflict Resolution Rules](header-conflict-resolution.md) - How conflicts are resolved
- [Troubleshooting Guide](header-deduplication-troubleshooting.md) - Common issues and solutions
- [Configuration Examples](../config/header_deduplication_examples.php) - Example configurations

## Contributing

When contributing to the header deduplication system:

1. Add tests for new functionality
2. Update documentation
3. Follow existing code style
4. Test with multiple runtimes
5. Consider performance impact

## License

This feature is part of the ThinkPHP Runtime extension and follows the same license terms.