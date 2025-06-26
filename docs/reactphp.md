# ReactPHP Integration

## Overview

The ReactPHP Runtime allows you to run ThinkPHP applications with ReactPHP's event-driven, non-blocking I/O server.

## Installation

First, install the required dependencies:

```bash
composer require think/runtime react/http react/socket
```

## Configuration

Configure ReactPHP runtime in your `composer.json`:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\ReactPHPRuntime",
            "host": "127.0.0.1",
            "port": 8080,
            "max_request_size": 1048576,
            "max_concurrent_requests": 100
        }
    }
}
```

## Server Script

Create a server script (e.g., `server.php`):

```php
<?php

use think\App;

require_once __DIR__.'/vendor/autoload_runtime.php';

return function (array $context): App {
    $app = new App();
    
    // Configure for ReactPHP
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for async
        ],
        'database' => [
            'connections' => 1, // Limit connections for async
        ],
    ]);
    
    return $app;
};
```

## Running the Server

Start the ReactPHP server:

```bash
php server.php
```

The server will start and display:
```
ThinkPHP ReactPHP server started on http://127.0.0.1:8080
Press Ctrl+C to stop the server
```

## Configuration Options

### Basic Options

- `host` - Server host (default: "127.0.0.1")
- `port` - Server port (default: 8080)
- `max_request_size` - Maximum request size in bytes (default: 1MB)
- `max_concurrent_requests` - Maximum concurrent requests (default: 100)
- `request_timeout` - Request timeout in seconds (default: 30)

### Advanced Configuration

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\ReactPHPRuntime",
            "host": "0.0.0.0",
            "port": 8080,
            "max_request_size": 2097152,
            "max_concurrent_requests": 200,
            "request_timeout": 60,
            "debug": true
        }
    }
}
```

## Async Programming Considerations

### Database Operations

Use async database libraries when possible:

```php
// Traditional blocking approach (not recommended)
$user = Db::table('users')->find(1);

// Better approach for ReactPHP
$app->config->set([
    'database' => [
        'type' => 'mysql',
        'connections' => 1, // Single connection for async
        'break_reconnect' => true,
    ],
]);
```

### File Operations

Avoid blocking file operations:

```php
// Blocking (not recommended)
$content = file_get_contents('/large/file.txt');

// Non-blocking alternative
use React\Filesystem\Filesystem;

$filesystem = Filesystem::create($loop);
$filesystem->file('/large/file.txt')->getContents()->then(function ($content) {
    // Handle content
});
```

### HTTP Requests

Use ReactPHP's HTTP client for external requests:

```php
use React\Http\Browser;

$browser = new Browser($loop);
$browser->get('https://api.example.com/data')->then(function ($response) {
    $data = json_decode($response->getBody(), true);
    // Handle response
});
```

## Memory Management

### Avoid Memory Leaks

```php
// Clear large variables
unset($largeArray);

// Monitor memory usage
$memory = memory_get_usage(true);
if ($memory > 50 * 1024 * 1024) { // 50MB
    // Log warning or take action
}
```

### Garbage Collection

```php
// Force garbage collection periodically
if (gc_enabled()) {
    gc_collect_cycles();
}
```

## Error Handling

### Global Error Handler

```php
// In your application bootstrap
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    // Log error
    error_log("Error: $message in $file:$line");
    
    return true;
});
```

### Promise Error Handling

```php
$promise->then(
    function ($result) {
        // Success handler
        return $result;
    },
    function ($error) {
        // Error handler
        error_log("Promise error: " . $error->getMessage());
        throw $error;
    }
);
```

## Performance Optimization

### Connection Pooling

```php
// Configure database connection pooling
$app->config->set([
    'database' => [
        'type' => 'mysql',
        'connections' => 1,
        'pool_size' => 10,
        'max_idle_time' => 60,
    ],
]);
```

### Caching

Use in-memory caching for better performance:

```php
// Simple in-memory cache
class MemoryCache
{
    private static $cache = [];
    
    public static function get($key)
    {
        return self::$cache[$key] ?? null;
    }
    
    public static function set($key, $value, $ttl = 3600)
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
    }
}
```

## Monitoring and Debugging

### Request Logging

```php
// Log all requests
$app->middleware(function ($request, $next) {
    $start = microtime(true);
    
    $response = $next($request);
    
    $duration = microtime(true) - $start;
    error_log(sprintf(
        '%s %s - %d - %.3fs',
        $request->method(),
        $request->url(),
        $response->getStatusCode(),
        $duration
    ));
    
    return $response;
});
```

### Memory Monitoring

```php
use React\EventLoop\Loop;

$loop = Loop::get();

// Monitor memory every 30 seconds
$loop->addPeriodicTimer(30, function () {
    $memory = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    
    echo sprintf(
        "Memory: %s MB (Peak: %s MB)\n",
        round($memory / 1024 / 1024, 2),
        round($peak / 1024 / 1024, 2)
    );
});
```

## Best Practices

1. **Avoid blocking operations** - Use async alternatives
2. **Limit concurrent requests** - Prevent resource exhaustion
3. **Monitor memory usage** - Detect leaks early
4. **Use connection pooling** - Optimize database access
5. **Handle errors gracefully** - Prevent server crashes
6. **Log important events** - Aid in debugging
7. **Test under load** - Ensure stability

## Troubleshooting

### Common Issues

1. **High memory usage**
   - Check for memory leaks
   - Monitor object references
   - Use `unset()` for large variables

2. **Slow responses**
   - Profile database queries
   - Check for blocking operations
   - Monitor event loop lag

3. **Connection errors**
   - Verify database connection limits
   - Check network connectivity
   - Monitor connection pool usage

### Debug Mode

Enable debug mode for development:

```json
{
    "extra": {
        "runtime": {
            "debug": true
        }
    }
}
```

This will provide detailed error messages and stack traces.
