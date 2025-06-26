# Getting Started with ThinkPHP Runtime

## Introduction

ThinkPHP Runtime Component enables decoupling ThinkPHP applications from global state to make sure the application can run with runtimes like Workerman, ReactPHP, Swoole, etc. without any changes.

## Installation

Install the package via Composer:

```bash
composer require think/runtime
```

## Basic Usage

### 1. Traditional Web Application

Create a `public/index.php` file:

```php
<?php

use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

### 2. Console Application

Create a `bin/console` file:

```php
#!/usr/bin/env php
<?php

use think\Console;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): Console {
    $console = new Console();
    // Add your commands here
    return $console;
};
```

## How It Works

The Runtime component works in 6 steps:

1. **Entry Point**: Your entry point (e.g., `public/index.php`) returns a callable
2. **Resolution**: The Runtime resolves the callable's arguments
3. **Invocation**: The callable is invoked with resolved arguments to get the application
4. **Runner Selection**: The Runtime selects an appropriate runner for the application
5. **Execution**: The runner executes the application
6. **Exit**: The process exits with the returned status code

## Supported Arguments

Your application callable can accept various arguments that will be automatically resolved:

### Array Arguments

- `array $context` - Combination of `$_SERVER` and `$_ENV`
- `array $argv` - Command line arguments
- `array $request` - HTTP request data (query, body, files, session)

### Object Arguments

- `think\Request` - ThinkPHP Request object
- `think\Console\Input` - Console input
- `think\Console\Output` - Console output

## Supported Applications

The Runtime can handle various application types:

- `think\App` - ThinkPHP application
- `think\Console` - Console application
- `think\Response` - Response object
- `callable` - Any callable
- `Psr\Http\Message\ResponseInterface` - PSR-7 response

## Configuration

You can configure the runtime behavior in your `composer.json`:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\Runtime\\ThinkPHPRuntime",
            "env": "prod",
            "debug": false
        }
    }
}
```

## Environment Variables

The Runtime supports several environment variables:

- `APP_ENV` - Application environment (default: "dev")
- `APP_DEBUG` - Debug mode (default: true)
- `APP_RUNTIME_OPTIONS` - Runtime options as array

## Next Steps

- [Runtime Configuration](configuration.md)
- [Workerman Integration](workerman.md)
- [ReactPHP Integration](reactphp.md)
- [Creating Custom Runtimes](custom-runtimes.md)
