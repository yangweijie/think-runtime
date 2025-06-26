# Vercel Integration

## Overview

The Vercel Runtime allows you to deploy ThinkPHP applications as serverless functions on Vercel, providing automatic scaling, global edge deployment, and zero-configuration infrastructure.

## What is Vercel?

Vercel is a cloud platform for static sites and serverless functions that provides:

- **Serverless Functions**: Automatic scaling with zero configuration
- **Global Edge Network**: Deploy to 40+ regions worldwide
- **Automatic HTTPS**: Built-in SSL certificates
- **Git Integration**: Deploy on every push
- **Zero Downtime**: Atomic deployments
- **Analytics**: Built-in performance monitoring

## Installation

First, install the Vercel CLI and configure your project:

```bash
# Install Vercel CLI
npm i -g vercel

# Install the runtime package
composer require think/runtime
```

## Project Structure

```
your-project/
├── api/
│   └── index.php          # Main serverless function
├── public/                # Static assets (optional)
├── composer.json          # Dependencies and runtime config
├── vercel.json           # Vercel configuration
└── .vercelignore         # Files to ignore during deployment
```

## Configuration

### vercel.json

Create a `vercel.json` file in your project root:

```json
{
  "version": 2,
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.6.0",
      "maxDuration": 10
    }
  },
  "routes": [
    {
      "src": "/api/(.*)",
      "dest": "/api/index.php"
    },
    {
      "src": "/(.*)",
      "dest": "/api/index.php"
    }
  ],
  "env": {
    "APP_ENV": "production",
    "APP_DEBUG": "false"
  }
}
```

### Runtime Configuration

Configure Vercel runtime in your `composer.json`:

```json
{
  "extra": {
    "runtime": {
      "class": "Think\\Runtime\\Runtime\\VercelRuntime",
      "vercel_env": "production",
      "enable_cors": true,
      "cors_origins": "*",
      "enable_static_cache": true,
      "cache_control_max_age": 3600,
      "max_execution_time": 10
    }
  }
}
```

## Serverless Function

Create your main function at `api/index.php`:

```php
<?php

use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    $app = new App();
    
    // Configure for serverless environment
    $app->config->set([
        'session' => [
            'auto_start' => false, // Important for serverless
            'type' => 'cache',
        ],
        'database' => [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'hostname' => $_ENV['DATABASE_HOST'],
                    'database' => $_ENV['DATABASE_NAME'],
                    'username' => $_ENV['DATABASE_USER'],
                    'password' => $_ENV['DATABASE_PASSWORD'],
                ],
            ],
        ],
        'cache' => [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'host' => $_ENV['REDIS_HOST'],
                    'password' => $_ENV['REDIS_PASSWORD'],
                ],
            ],
        ],
    ]);
    
    return $app;
};
```

## Environment Variables

Set environment variables in your Vercel dashboard or using the CLI:

```bash
# Set environment variables
vercel env add DATABASE_HOST
vercel env add DATABASE_NAME
vercel env add DATABASE_USER
vercel env add DATABASE_PASSWORD
vercel env add REDIS_HOST
vercel env add REDIS_PASSWORD
```

### Available Environment Variables

#### Vercel-specific
- `VERCEL_ENV` - Environment (development/preview/production)
- `VERCEL_URL` - Deployment URL
- `VERCEL_REGION` - Deployment region
- `VERCEL_ENABLE_CORS` - Enable CORS (1/0)
- `VERCEL_CORS_ORIGINS` - Allowed origins
- `VERCEL_MAX_EXECUTION_TIME` - Function timeout

#### AWS Lambda (used by Vercel)
- `AWS_LAMBDA_FUNCTION_NAME` - Function name
- `AWS_LAMBDA_FUNCTION_VERSION` - Function version
- `AWS_LAMBDA_FUNCTION_MEMORY_SIZE` - Memory limit

## Configuration Options

### Basic Options

- `vercel_env` - Environment (development/preview/production)
- `vercel_url` - Deployment URL
- `vercel_region` - Deployment region
- `function_name` - Function name
- `max_execution_time` - Maximum execution time in seconds

### CORS Options

- `enable_cors` - Enable CORS support (default: true)
- `cors_origins` - Allowed origins (default: '*')
- `cors_methods` - Allowed methods (default: 'GET,POST,PUT,DELETE,OPTIONS')
- `cors_headers` - Allowed headers (default: 'Content-Type,Authorization')

### Caching Options

- `enable_static_cache` - Enable static caching (default: true)
- `cache_control_max_age` - Cache max age in seconds (default: 3600)

### Logging Options

- `enable_logging` - Enable logging (default: true)
- `log_level` - Log level (default: 'info')

## Deployment

### Local Development

```bash
# Start local development server
vercel dev

# Test your function locally
curl http://localhost:3000/api/users
```

### Deploy to Vercel

```bash
# Deploy to preview environment
vercel

# Deploy to production
vercel --prod

# Deploy with environment variables
vercel --prod --env DATABASE_HOST=your-host
```

### Automatic Deployments

Connect your Git repository to Vercel for automatic deployments:

1. Import your project in Vercel dashboard
2. Connect to GitHub/GitLab/Bitbucket
3. Configure build settings
4. Set environment variables
5. Deploy automatically on every push

## Best Practices

### 1. Database Configuration

Use connection pooling and external databases:

```php
$app->config->set([
    'database' => [
        'connections' => [
            'mysql' => [
                'type' => 'mysql',
                'hostname' => $_ENV['DATABASE_HOST'],
                'database' => $_ENV['DATABASE_NAME'],
                'username' => $_ENV['DATABASE_USER'],
                'password' => $_ENV['DATABASE_PASSWORD'],
                'charset' => 'utf8mb4',
                'deploy' => [
                    'type' => 'mysql',
                    'read_master' => true,
                ],
            ],
        ],
    ],
]);
```

### 2. Session Management

Use external session storage:

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

### 3. File Storage

Use external file storage services:

```php
$app->config->set([
    'filesystem' => [
        'default' => 's3',
        'disks' => [
            's3' => [
                'type' => 's3',
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                'region' => $_ENV['AWS_DEFAULT_REGION'],
                'bucket' => $_ENV['AWS_BUCKET'],
            ],
        ],
    ],
]);
```

### 4. Caching Strategy

Implement multi-level caching:

```php
$app->config->set([
    'cache' => [
        'default' => 'redis',
        'stores' => [
            'redis' => [
                'type' => 'redis',
                'host' => $_ENV['REDIS_HOST'],
                'password' => $_ENV['REDIS_PASSWORD'],
                'persistent' => true,
            ],
            'memory' => [
                'type' => 'array',
            ],
        ],
    ],
]);
```

### 5. Error Handling

Implement proper error handling:

```php
// In your application bootstrap
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("Serverless Error: $message in $file:$line");
    return true;
});
```

## Performance Optimization

### Cold Start Optimization

1. **Minimize dependencies:**
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

2. **Use lightweight libraries:**
   ```json
   {
     "require": {
       "topthink/framework": "^8.0"
     }
   }
   ```

3. **Optimize autoloading:**
   ```json
   {
     "config": {
       "optimize-autoloader": true,
       "classmap-authoritative": true
     }
   }
   ```

### Memory Management

```json
{
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.6.0",
      "memory": 1024,
      "maxDuration": 10
    }
  }
}
```

### Response Optimization

```php
// Enable compression
if (!headers_sent()) {
    header('Content-Encoding: gzip');
    ob_start('ob_gzhandler');
}

// Set appropriate cache headers
header('Cache-Control: public, max-age=3600');
header('ETag: ' . md5($content));
```

## Monitoring and Debugging

### Logging

```php
// Use structured logging
error_log(json_encode([
    'level' => 'info',
    'message' => 'Request processed',
    'context' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
    ],
]));
```

### Performance Monitoring

```php
// Track performance metrics
$startTime = microtime(true);

// Your application logic here

$executionTime = microtime(true) - $startTime;
$memoryUsage = memory_get_peak_usage(true);

error_log("Performance: {$executionTime}s, Memory: " . round($memoryUsage / 1024 / 1024, 2) . "MB");
```

### Error Tracking

Integrate with error tracking services:

```php
// Example with Sentry
if (class_exists('Sentry\init')) {
    \Sentry\init(['dsn' => $_ENV['SENTRY_DSN']]);
    
    set_exception_handler(function ($exception) {
        \Sentry\captureException($exception);
        throw $exception;
    });
}
```

## Limitations

### Vercel Limits

- **Execution Time**: 10 seconds (Hobby), 60 seconds (Pro)
- **Memory**: 1024MB (Hobby), 3008MB (Pro)
- **Payload Size**: 4.5MB request, 4.5MB response
- **Concurrent Executions**: 1000 (Hobby), 1000+ (Pro)

### Serverless Considerations

1. **No persistent storage**: Use external storage services
2. **Cold starts**: Optimize for fast initialization
3. **Stateless**: Don't rely on server-side state
4. **Timeouts**: Handle long-running operations carefully

## Troubleshooting

### Common Issues

1. **Function timeout:**
   ```json
   {
     "functions": {
       "api/index.php": {
         "maxDuration": 30
       }
     }
   }
   ```

2. **Memory limit exceeded:**
   ```json
   {
     "functions": {
       "api/index.php": {
         "memory": 2048
       }
     }
   }
   ```

3. **Environment variables not available:**
   ```bash
   vercel env ls
   vercel env add VARIABLE_NAME
   ```

### Debug Mode

Enable debug mode for development:

```json
{
  "env": {
    "APP_DEBUG": "true",
    "VERCEL_LOG_LEVEL": "debug"
  }
}
```

Vercel provides an excellent platform for deploying ThinkPHP applications with automatic scaling and global distribution.
