# Workerman Integration

## Overview

The Workerman Runtime allows you to run ThinkPHP applications with the Workerman server, providing high-performance HTTP server capabilities.

## Installation

First, install the required dependencies:

```bash
composer require think/runtime workerman/workerman
```

## Configuration

Configure Workerman runtime in your `composer.json`:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\WorkermanRuntime",
            "host": "0.0.0.0",
            "port": 8080,
            "worker_count": 4,
            "protocol": "http"
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
    
    // Configure for Workerman
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for Workerman
        ],
    ]);
    
    return $app;
};
```

## Running the Server

Start the Workerman server:

```bash
# Start server
php server.php start

# Start in daemon mode
php server.php start -d

# Stop server
php server.php stop

# Restart server
php server.php restart

# Reload server
php server.php reload

# Check status
php server.php status
```

## Configuration Options

### Basic Options

- `host` - Server host (default: "0.0.0.0")
- `port` - Server port (default: 8080)
- `worker_count` - Number of worker processes (default: 4)
- `protocol` - Protocol to use (default: "http")

### SSL Configuration

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\WorkermanRuntime",
            "host": "0.0.0.0",
            "port": 443,
            "protocol": "http",
            "ssl_context": {
                "ssl": {
                    "local_cert": "/path/to/cert.pem",
                    "local_pk": "/path/to/private.key",
                    "verify_peer": false
                }
            }
        }
    }
}
```

### Socket Context Options

```json
{
    "extra": {
        "runtime": {
            "socket_context": {
                "socket": {
                    "so_reuseport": 1,
                    "so_keepalive": 1
                }
            }
        }
    }
}
```

## Performance Considerations

### Memory Management

- Avoid memory leaks in your application code
- Use `unset()` to free large variables
- Monitor memory usage with `memory_get_usage()`

### Database Connections

Configure connection pooling:

```php
$app->config->set([
    'database' => [
        'connections' => 10, // Limit connections per worker
        'break_reconnect' => true,
    ],
]);
```

### Session Handling

Workerman requires special session handling:

```php
// In your application
$app->config->set([
    'session' => [
        'auto_start' => false,
        'type' => 'file', // or 'redis'
        'path' => '/tmp/sessions',
    ],
]);
```

## Monitoring

### Process Monitoring

```bash
# Check worker processes
ps aux | grep server.php

# Monitor memory usage
top -p $(pgrep -f server.php)
```

### Application Monitoring

Add monitoring to your application:

```php
// In your ThinkPHP application
use Workerman\Timer;

// Monitor memory usage
Timer::add(60, function() {
    $memory = memory_get_usage(true);
    if ($memory > 128 * 1024 * 1024) { // 128MB
        echo "High memory usage: " . ($memory / 1024 / 1024) . "MB\n";
    }
});
```

## Troubleshooting

### Common Issues

1. **Port already in use**
   ```bash
   # Check what's using the port
   lsof -i :8080
   
   # Kill the process
   kill -9 <PID>
   ```

2. **Permission denied**
   ```bash
   # For ports < 1024, run as root or use higher port
   sudo php server.php start
   ```

3. **Memory leaks**
   - Check for circular references
   - Use weak references where appropriate
   - Monitor memory usage

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

## Best Practices

1. **Use process monitoring** (like Supervisor)
2. **Implement graceful shutdown**
3. **Monitor resource usage**
4. **Use connection pooling**
5. **Avoid global state**
6. **Handle errors gracefully**

## Example Supervisor Configuration

```ini
[program:thinkphp-workerman]
command=php /path/to/your/server.php start
directory=/path/to/your/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/thinkphp-workerman.log
```
