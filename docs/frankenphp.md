# FrankenPHP Integration

## Overview

The FrankenPHP Runtime allows you to run ThinkPHP applications with FrankenPHP server, providing high-performance HTTP server capabilities with worker mode support.

## What is FrankenPHP?

FrankenPHP is a modern application server for PHP built on top of the Caddy web server. It provides:

- **Worker Mode**: Keep your application in memory between requests
- **HTTP/2 and HTTP/3 Support**: Modern protocol support out of the box
- **Automatic HTTPS**: Built-in SSL certificate management
- **High Performance**: Significantly faster than traditional PHP-FPM
- **Real-time Features**: WebSocket and Server-Sent Events support

## Installation

First, install FrankenPHP and the required dependencies:

```bash
# Install FrankenPHP
curl https://frankenphp.dev/install.sh | sh

# Install the runtime package
composer require think/runtime
```

## Configuration

Configure FrankenPHP runtime in your `composer.json`:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\FrankenPHPRuntime",
            "frankenphp_loop_max": 500,
            "frankenphp_worker": true,
            "enable_xdebug": false,
            "ignore_user_abort": true,
            "gc_collect_cycles": true
        }
    }
}
```

## Server Script

Create a server script (e.g., `public/index.php`):

```php
<?php

use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    $app = new App();
    
    // Configure for FrankenPHP worker mode
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for worker mode
        ],
        'database' => [
            'break_reconnect' => true, // Reconnect on connection loss
        ],
    ]);
    
    return $app;
};
```

## Caddyfile Configuration

Create a `Caddyfile` for FrankenPHP:

```caddyfile
{
    frankenphp
    order php_server before file_server
}

localhost:8080 {
    root * public
    
    # Enable FrankenPHP worker mode
    php_server {
        worker ./public/index.php
    }
    
    # Handle static files
    file_server
    
    # Logs
    log {
        output stdout
        format console
    }
}
```

## Running the Server

### Development Mode

```bash
# Start FrankenPHP with Caddyfile
frankenphp run

# Or start with worker mode directly
frankenphp run --worker ./public/index.php
```

### Production Mode

```bash
# Start in production mode
frankenphp run --config Caddyfile

# Or with environment variables
FRANKENPHP_WORKER=1 FRANKENPHP_LOOP_MAX=1000 frankenphp run
```

## Configuration Options

### Basic Options

- `frankenphp_loop_max` - Maximum number of requests per worker (default: 500)
- `frankenphp_worker` - Enable worker mode (default: false)
- `enable_xdebug` - Enable Xdebug support (default: auto-detect)
- `ignore_user_abort` - Ignore user connection abort (default: true)
- `gc_collect_cycles` - Enable garbage collection (default: true)

### Environment Variables

- `FRANKENPHP_LOOP_MAX` - Override loop max setting
- `FRANKENPHP_WORKER` - Enable worker mode
- `APP_RUNTIME_MODE` - Runtime mode information

### Advanced Configuration

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\FrankenPHPRuntime",
            "frankenphp_loop_max": 1000,
            "frankenphp_worker": true,
            "enable_xdebug": false,
            "ignore_user_abort": true,
            "gc_collect_cycles": true,
            "app_path": "./app",
            "config_path": "./config",
            "runtime_path": "./runtime"
        }
    }
}
```

## Worker Mode Benefits

### Performance Improvements

- **Memory Persistence**: Application stays in memory between requests
- **Reduced Bootstrap Time**: No need to initialize framework on each request
- **Connection Pooling**: Database connections can be reused
- **Opcode Caching**: Better utilization of OPcache

### Memory Management

The runtime automatically handles:

- **Garbage Collection**: Runs `gc_collect_cycles()` after each request
- **Memory Limits**: Respects `frankenphp_loop_max` to prevent memory leaks
- **Request Isolation**: Resets global state between requests

## Best Practices

### 1. Database Configuration

```php
$app->config->set([
    'database' => [
        'break_reconnect' => true,
        'deploy' => [
            'type' => 'mysql',
            'read_master' => true,
        ],
    ],
]);
```

### 2. Session Handling

```php
$app->config->set([
    'session' => [
        'auto_start' => false,
        'type' => 'redis', // Use external session storage
        'store' => 'redis',
    ],
]);
```

### 3. Logging Configuration

```php
$app->config->set([
    'log' => [
        'default' => 'file',
        'channels' => [
            'file' => [
                'type' => 'file',
                'path' => './runtime/log/',
                'max_files' => 30,
            ],
        ],
    ],
]);
```

### 4. Error Handling

```php
// In your application bootstrap
set_error_handler(function ($severity, $message, $file, $line) {
    // Log errors but don't terminate worker
    error_log("Worker Error: $message in $file:$line");
    return true;
});
```

## Debugging

### Xdebug Support

Enable Xdebug in worker mode:

```json
{
    "extra": {
        "runtime": {
            "enable_xdebug": true
        }
    }
}
```

### Logging

```bash
# Enable debug logging
CADDY_LOG_LEVEL=debug frankenphp run

# Monitor worker status
tail -f /var/log/frankenphp.log
```

### Memory Monitoring

```php
// Add to your application
register_shutdown_function(function () {
    $memory = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    error_log("Memory: " . round($memory / 1024 / 1024, 2) . "MB, Peak: " . round($peak / 1024 / 1024, 2) . "MB");
});
```

## Troubleshooting

### Common Issues

1. **Memory Leaks**
   - Reduce `frankenphp_loop_max`
   - Enable `gc_collect_cycles`
   - Check for circular references

2. **Database Connection Issues**
   - Enable `break_reconnect`
   - Use connection pooling
   - Monitor connection limits

3. **Session Problems**
   - Disable `auto_start`
   - Use external session storage
   - Clear session data properly

### Performance Tuning

```bash
# Optimize for high traffic
FRANKENPHP_LOOP_MAX=2000 \
FRANKENPHP_WORKER=1 \
frankenphp run --config Caddyfile
```

## Comparison with Other Runtimes

| Feature | FrankenPHP | Workerman | ReactPHP | PHP-FPM |
|---------|------------|-----------|----------|---------|
| Worker Mode | ✅ | ✅ | ✅ | ❌ |
| HTTP/2 | ✅ | ❌ | ❌ | ✅ |
| HTTPS | ✅ | Manual | Manual | Manual |
| Static Files | ✅ | Manual | Manual | ✅ |
| Memory Usage | Low | Low | Low | High |
| Setup Complexity | Low | Medium | Medium | Low |

## Production Deployment

### Docker Example

```dockerfile
FROM dunglas/frankenphp

COPY . /app
WORKDIR /app

RUN composer install --no-dev --optimize-autoloader

EXPOSE 80 443

CMD ["frankenphp", "run", "--config", "Caddyfile"]
```

### Systemd Service

```ini
[Unit]
Description=FrankenPHP ThinkPHP Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/local/bin/frankenphp run --config Caddyfile
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

FrankenPHP provides an excellent balance of performance, ease of use, and modern features for ThinkPHP applications.
