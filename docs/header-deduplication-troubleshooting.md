# Header Deduplication Troubleshooting Guide

## Overview

This guide helps you diagnose and resolve issues related to HTTP header deduplication in ThinkPHP Runtime. It covers common problems, diagnostic steps, and solutions.

## Quick Diagnostic Checklist

Before diving into specific issues, run through this quick checklist:

1. ✅ **Is header deduplication enabled?** Check `config/runtime.php` → `header_deduplication.enabled`
2. ✅ **Are there error logs?** Check application logs and `runtime/logs/header_deduplication.log`
3. ✅ **Is debug logging enabled?** Set `debug_logging => true` for detailed information
4. ✅ **Which runtime are you using?** Different runtimes may have specific behaviors
5. ✅ **Are you using middleware?** CORS, security, and compression middleware can affect headers

## Common Issues and Solutions

### Issue 1: Duplicate Content-Length Headers

**Symptoms**:
- Browser warnings about duplicate Content-Length headers
- HTTP clients reporting malformed responses
- `curl` showing multiple Content-Length headers

**Diagnostic Steps**:
```bash
# Enable debug logging
# In config/runtime.php:
'header_deduplication' => [
    'debug_logging' => true,
    'log_critical_conflicts' => true,
]

# Check logs for Content-Length conflicts
tail -f runtime/logs/header_deduplication.log | grep "Content-Length"
```

**Common Causes**:
1. PSR-7 response sets Content-Length AND runtime calculates it
2. Compression middleware interferes with Content-Length
3. Custom middleware manually sets Content-Length

**Solutions**:

**Solution 1: Let PSR-7 handle Content-Length**
```php
// In your controller/response handling:
$response = $response->withHeader('Content-Length', strlen($body));
// Don't set Content-Length in runtime configuration
```

**Solution 2: Disable runtime Content-Length calculation**
```php
// In runtime-specific configuration:
'swoole' => [
    'settings' => [
        'auto_content_length' => false, // Disable automatic calculation
    ],
],
```

**Solution 3: Check middleware order**
```php
// Ensure compression middleware runs before content-length calculation
// Move compression middleware earlier in the stack
```

### Issue 2: CORS Headers Not Working

**Symptoms**:
- CORS errors in browser console
- Preflight requests failing
- Access-Control headers missing or incorrect

**Diagnostic Steps**:
```bash
# Check CORS header conflicts
curl -H "Origin: https://example.com" -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type" \
     -X OPTIONS http://your-app.com/api/endpoint

# Enable CORS debug logging
'header_deduplication' => [
    'debug_logging' => true,
]
```

**Common Causes**:
1. Application code overrides CORS middleware headers
2. Multiple CORS middleware instances
3. Incorrect CORS configuration priority

**Solutions**:

**Solution 1: Use middleware for CORS, not application code**
```php
// DON'T do this in controllers:
$response = $response->withHeader('Access-Control-Allow-Origin', '*');

// DO configure CORS in middleware:
'middleware' => [
    'cors' => [
        'enable' => true,
        'allow_origin' => 'https://example.com',
        'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'allow_headers' => 'Content-Type, Authorization',
    ],
],
```

**Solution 2: Check middleware priority**
```php
// Ensure CORS middleware runs early
// Check your middleware stack order
```

### Issue 3: Set-Cookie Headers Missing

**Symptoms**:
- Cookies not being set in browser
- Only one cookie working when multiple should be set
- Session issues

**Diagnostic Steps**:
```bash
# Check Set-Cookie header handling
curl -v http://your-app.com/login | grep -i "set-cookie"

# Enable cookie debug logging
'header_deduplication' => [
    'debug_logging' => true,
]
```

**Common Causes**:
1. Set-Cookie headers being combined instead of kept separate
2. Header deduplication treating Set-Cookie as unique header
3. Session middleware conflicts

**Solutions**:

**Solution 1: Verify Set-Cookie handling**
```php
// Set-Cookie headers should NOT be combined
// Check that HeaderDeduplicationService handles Set-Cookie correctly
// Each cookie should create a separate Set-Cookie header
```

**Solution 2: Check session configuration**
```php
// In runtime configuration:
'workerman' => [
    'session' => [
        'enable_fix' => true,
        'preserve_session_cookies' => true,
    ],
],
```

### Issue 4: Performance Degradation

**Symptoms**:
- Slower response times after enabling header deduplication
- High CPU usage during header processing
- Memory usage increases

**Diagnostic Steps**:
```bash
# Enable performance logging
'header_deduplication' => [
    'enable_performance_logging' => true,
]

# Check performance logs
tail -f runtime/logs/header_deduplication.log | grep "Performance"
```

**Common Causes**:
1. Debug logging enabled in production
2. Large number of headers being processed
3. Cache disabled or too small

**Solutions**:

**Solution 1: Optimize configuration for production**
```php
'header_deduplication' => [
    'debug_logging' => false,              // Disable debug logs
    'enable_performance_logging' => false, // Disable performance logs
    'enable_header_name_cache' => true,    // Enable caching
    'max_cache_size' => 2000,             // Increase cache size
    'enable_batch_processing' => true,     // Enable batch optimization
],
```

**Solution 2: Reduce header processing overhead**
```php
'header_deduplication' => [
    'log_critical_conflicts' => false,     // Only log if needed
    'max_header_value_length' => 4096,     // Reduce if appropriate
],
```

### Issue 5: Headers Not Being Deduplicated

**Symptoms**:
- Still seeing duplicate headers in responses
- Header conflicts not being resolved
- No debug logs appearing

**Diagnostic Steps**:
```bash
# Verify header deduplication is enabled
grep -r "header_deduplication" config/

# Check if service is being used
'header_deduplication' => [
    'debug_logging' => true,
    'enabled' => true,
]
```

**Common Causes**:
1. Header deduplication disabled
2. Service not properly initialized
3. Runtime not using the service

**Solutions**:

**Solution 1: Verify configuration**
```php
// Ensure header deduplication is enabled
'header_deduplication' => [
    'enabled' => true,
]
```

**Solution 2: Check service initialization**
```php
// Verify HeaderDeduplicationService is being created and used
// Check AbstractRuntime implementation
```

**Solution 3: Runtime-specific configuration**
```php
// Some runtimes may need specific configuration
'swoole' => [
    'header_deduplication' => [
        'enabled' => true,
    ],
],
```

### Issue 6: Strict Mode Exceptions

**Symptoms**:
- Application throwing HeaderConflictException
- Requests failing due to header conflicts
- Error logs showing header merge failures

**Diagnostic Steps**:
```bash
# Check if strict mode is enabled
grep -r "strict_mode" config/runtime.php

# Review exception logs
tail -f storage/logs/laravel.log | grep "HeaderConflictException"
```

**Solutions**:

**Solution 1: Disable strict mode for production**
```php
'header_deduplication' => [
    'strict_mode' => false,           // Don't throw exceptions
    'throw_on_merge_failure' => false, // Use fallback behavior
],
```

**Solution 2: Fix the underlying conflicts**
```php
// Use strict mode in development to identify conflicts
'header_deduplication' => [
    'strict_mode' => true,  // Only in development
],

// Then fix the conflicts identified by the exceptions
```

## Advanced Debugging

### Enable Comprehensive Logging

```php
'header_deduplication' => [
    'debug_logging' => true,
    'log_critical_conflicts' => true,
    'enable_performance_logging' => true,
    'log_level' => 'debug',
    'log_file' => 'runtime/logs/header_debug.log',
],
```

### Custom Debug Script

Create a debug script to test header processing:

```php
<?php
// debug_headers.php

require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

$service = new HeaderDeduplicationService(null, [
    'debug_logging' => true,
    'strict_mode' => true,
]);

// Test headers that might conflict
$headers = [
    'Content-Length' => '1024',
    'content-length' => '1025',  // Duplicate with different case
    'Content-Type' => 'application/json',
    'Cache-Control' => 'max-age=3600',
    'cache-control' => 'no-cache', // Another duplicate
];

try {
    $result = $service->deduplicateHeaders($headers);
    echo "Deduplicated headers:\n";
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Runtime-Specific Debugging

#### Swoole Debugging
```php
// Add to Swoole configuration
'swoole' => [
    'settings' => [
        'log_level' => SWOOLE_LOG_DEBUG,
        'log_file' => 'runtime/logs/swoole.log',
    ],
    'header_deduplication' => [
        'debug_logging' => true,
    ],
],
```

#### Workerman Debugging
```php
// Add to Workerman configuration
'workerman' => [
    'debug' => [
        'enable' => true,
        'log_level' => 'debug',
        'log_requests' => true,
    ],
    'header_deduplication' => [
        'debug_logging' => true,
    ],
],
```

## Monitoring and Maintenance

### Regular Health Checks

Create a monitoring script:

```php
<?php
// monitor_headers.php

// Check for common header issues
$checks = [
    'duplicate_content_length' => false,
    'cors_conflicts' => false,
    'performance_issues' => false,
];

// Parse logs and check for issues
$logFile = 'runtime/logs/header_deduplication.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    
    if (strpos($logs, 'Content-Length conflict') !== false) {
        $checks['duplicate_content_length'] = true;
    }
    
    if (strpos($logs, 'CORS header conflict') !== false) {
        $checks['cors_conflicts'] = true;
    }
    
    if (strpos($logs, 'Performance:') !== false) {
        // Check if processing time is too high
        preg_match_all('/Performance: \w+ completed in ([\d.]+) seconds/', $logs, $matches);
        foreach ($matches[1] as $time) {
            if ((float)$time > 0.01) { // 10ms threshold
                $checks['performance_issues'] = true;
                break;
            }
        }
    }
}

// Report issues
foreach ($checks as $check => $hasIssue) {
    if ($hasIssue) {
        echo "WARNING: {$check} detected\n";
    }
}
```

### Log Rotation

Set up log rotation for header deduplication logs:

```bash
# Add to logrotate configuration
/path/to/your/app/runtime/logs/header_deduplication.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
```

## Getting Help

If you're still experiencing issues:

1. **Check the logs** with debug logging enabled
2. **Create a minimal reproduction case**
3. **Document your runtime and middleware configuration**
4. **Include relevant log excerpts**
5. **Test with different runtimes** to isolate the issue

## Performance Optimization

For high-traffic applications:

```php
'header_deduplication' => [
    'enabled' => true,
    'debug_logging' => false,
    'strict_mode' => false,
    'log_critical_conflicts' => false,
    'enable_performance_logging' => false,
    'enable_header_name_cache' => true,
    'max_cache_size' => 5000,
    'enable_batch_processing' => true,
    'max_header_value_length' => 4096,
],
```

## Related Documentation

- [Header Deduplication Configuration Guide](header-deduplication-configuration.md)
- [Header Conflict Resolution Rules](header-conflict-resolution.md)
- [Runtime-Specific Configuration Guides](../README.md)