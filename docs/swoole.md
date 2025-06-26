# Swoole Integration

## Overview

The Swoole Runtime allows you to run ThinkPHP applications with Swoole server, providing high-performance HTTP server capabilities with coroutine support and async I/O.

## What is Swoole?

Swoole is a high-performance network framework for PHP that provides:

- **Coroutines**: Lightweight threads for concurrent programming
- **Async I/O**: Non-blocking I/O operations
- **HTTP/WebSocket Server**: Built-in server implementations
- **Process Management**: Multi-process and multi-threading support
- **Memory Resident**: Keep application in memory between requests
- **High Performance**: Significantly faster than traditional PHP-FPM

## Installation

First, install Swoole extension and the required dependencies:

```bash
# Install Swoole extension (Linux/macOS)
pecl install swoole

# Or using package manager (Ubuntu/Debian)
sudo apt-get install php-swoole

# Or using package manager (CentOS/RHEL)
sudo yum install php-swoole

# Install the runtime package
composer require think/runtime
```

## Configuration

Configure Swoole runtime in your `composer.json`:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\SwooleRuntime",
            "host": "0.0.0.0",
            "port": 9501,
            "worker_num": 4,
            "enable_coroutine": true,
            "enable_static_handler": true,
            "document_root": "./public",
            "max_request": 10000,
            "daemonize": false
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
    
    // Configure for Swoole server
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for Swoole
        ],
        'database' => [
            'break_reconnect' => true, // Reconnect on connection loss
        ],
        'cache' => [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'type' => 'redis',
                    'persistent' => true,
                ],
            ],
        ],
    ]);
    
    return $app;
};
```

## Running the Server

### Basic Usage

```bash
# Start Swoole server
php public/index.php

# Or using the startup script
php start.php start
```

### Advanced Management

Create a management script (`start.php`):

```php
<?php

use Think\Runtime\Runtime\SwooleRuntime;

require_once __DIR__ . '/vendor/autoload.php';

$command = $argv[1] ?? 'start';

switch ($command) {
    case 'start':
        // Start server logic
        break;
    case 'stop':
        // Stop server logic
        break;
    case 'restart':
        // Restart server logic
        break;
    case 'reload':
        // Reload workers logic
        break;
}
```

### Process Management

```bash
# Start server
php start.php start

# Stop server
php start.php stop

# Restart server
php start.php restart

# Reload workers (zero downtime)
php start.php reload

# Check status
php start.php status
```

## Configuration Options

### Server Options

- `host` - Server host (default: '0.0.0.0')
- `port` - Server port (default: 9501)
- `mode` - Server mode (default: SWOOLE_PROCESS)
- `sock_type` - Socket type (default: SWOOLE_SOCK_TCP)

### Worker Options

- `worker_num` - Number of worker processes (default: CPU cores)
- `task_worker_num` - Number of task worker processes (default: 0)
- `max_request` - Max requests per worker (default: 10000)
- `max_conn` - Max concurrent connections (default: 10000)

### Performance Options

- `enable_coroutine` - Enable coroutine support (default: true)
- `enable_static_handler` - Enable static file handler (default: true)
- `document_root` - Static files root directory
- `package_max_length` - Max package size (default: 2MB)
- `buffer_output_size` - Output buffer size (default: 2MB)
- `socket_buffer_size` - Socket buffer size (default: 2MB)

### Connection Options

- `heartbeat_check_interval` - Heartbeat check interval (default: 60s)
- `heartbeat_idle_time` - Heartbeat idle time (default: 600s)

### Log Options

- `log_file` - Log file path (default: '/tmp/swoole.log')
- `log_level` - Log level (default: SWOOLE_LOG_INFO)

### Process Options

- `daemonize` - Run as daemon (default: false)
- `pid_file` - PID file path (default: '/tmp/swoole.pid')

## Environment Variables

You can override configuration using environment variables:

- `SWOOLE_HOST` - Server host
- `SWOOLE_PORT` - Server port
- `SWOOLE_WORKER_NUM` - Number of workers
- `SWOOLE_TASK_WORKER_NUM` - Number of task workers
- `SWOOLE_MAX_REQUEST` - Max requests per worker
- `SWOOLE_ENABLE_COROUTINE` - Enable coroutine (1/0)
- `SWOOLE_ENABLE_STATIC_HANDLER` - Enable static handler (1/0)
- `SWOOLE_DOCUMENT_ROOT` - Static files root
- `SWOOLE_DAEMONIZE` - Run as daemon (1/0)
- `SWOOLE_LOG_FILE` - Log file path
- `SWOOLE_LOG_LEVEL` - Log level
- `SWOOLE_PID_FILE` - PID file path

## Coroutine Support

### Enabling Coroutines

```json
{
    "extra": {
        "runtime": {
            "enable_coroutine": true
        }
    }
}
```

### Coroutine Features

- **Concurrent Database Queries**: Multiple database operations in parallel
- **HTTP Client**: Non-blocking HTTP requests
- **File I/O**: Async file operations
- **Sleep/Timer**: Non-blocking delays

### Example Usage

```php
// In your controller
use Swoole\Coroutine;

public function index()
{
    // Concurrent database queries
    $results = [];
    
    Coroutine::create(function () use (&$results) {
        $results['users'] = Db::table('users')->select();
    });
    
    Coroutine::create(function () use (&$results) {
        $results['posts'] = Db::table('posts')->select();
    });
    
    // Wait for all coroutines to complete
    Coroutine::sleep(0.1);
    
    return json($results);
}
```

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
        'connections' => [
            'mysql' => [
                'type' => 'mysql',
                'hostname' => '127.0.0.1',
                'database' => 'test',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'pool_size' => 64, // Connection pool size
            ],
        ],
    ],
]);
```

### 2. Session Handling

```php
$app->config->set([
    'session' => [
        'auto_start' => false,
        'type' => 'redis',
        'store' => 'redis',
        'prefix' => 'think_session:',
    ],
]);
```

### 3. Cache Configuration

```php
$app->config->set([
    'cache' => [
        'default' => 'redis',
        'stores' => [
            'redis' => [
                'type' => 'redis',
                'host' => '127.0.0.1',
                'port' => 6379,
                'persistent' => true,
                'pool_size' => 64,
            ],
        ],
    ],
]);
```

### 4. Memory Management

```php
// Register shutdown function for memory monitoring
register_shutdown_function(function () {
    $memory = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);
    error_log("Memory: " . round($memory / 1024 / 1024, 2) . "MB, Peak: " . round($peak / 1024 / 1024, 2) . "MB");
});
```

### 5. Error Handling

```php
// In your application bootstrap
set_error_handler(function ($severity, $message, $file, $line) {
    // Log errors but don't terminate worker
    error_log("Swoole Worker Error: $message in $file:$line");
    return true;
});
```

## Performance Tuning

### High Traffic Configuration

```json
{
    "extra": {
        "runtime": {
            "worker_num": 16,
            "task_worker_num": 8,
            "max_request": 50000,
            "max_conn": 100000,
            "package_max_length": 4194304,
            "buffer_output_size": 4194304,
            "socket_buffer_size": 4194304
        }
    }
}
```

### Memory Optimization

```bash
# Set PHP memory limit
export PHP_MEMORY_LIMIT=512M

# Optimize OPcache
export PHP_OPCACHE_MEMORY_CONSUMPTION=256
export PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
```

## Monitoring and Debugging

### Server Statistics

```php
// Get server stats
$stats = $server->stats();
echo "Connections: " . $stats['connection_num'] . "\n";
echo "Requests: " . $stats['request_count'] . "\n";
echo "Workers: " . $stats['worker_num'] . "\n";
```

### Process Monitoring

```bash
# Monitor processes
ps aux | grep swoole

# Monitor memory usage
top -p $(cat /tmp/swoole.pid)

# Monitor network connections
netstat -tlnp | grep :9501
```

### Debugging

```php
// Enable debug mode
$app->config->set(['app_debug' => true]);

// Log requests
$server->on('Request', function ($request, $response) {
    error_log("Request: " . $request->server['request_uri']);
});
```

## Deployment

### Docker Example

```dockerfile
FROM php:8.1-cli

# Install Swoole
RUN pecl install swoole && docker-php-ext-enable swoole

COPY . /app
WORKDIR /app

RUN composer install --no-dev --optimize-autoloader

EXPOSE 9501

CMD ["php", "start.php", "start"]
```

### Systemd Service

```ini
[Unit]
Description=Swoole ThinkPHP Server
After=network.target

[Service]
Type=forking
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php start.php start
ExecReload=/usr/bin/php start.php reload
ExecStop=/usr/bin/php start.php stop
PIDFile=/tmp/swoole.pid
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Nginx Proxy

```nginx
upstream swoole {
    server 127.0.0.1:9501;
    keepalive 64;
}

server {
    listen 80;
    server_name example.com;
    
    location / {
        proxy_pass http://swoole;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Swoole provides excellent performance and modern features for ThinkPHP applications with its coroutine support and async I/O capabilities.
