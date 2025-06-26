# ThinkPHP Runtime Component

[![Latest Stable Version](https://poser.pugx.org/think/runtime/v/stable)](https://packagist.org/packages/think/runtime)
[![Total Downloads](https://poser.pugx.org/think/runtime/downloads)](https://packagist.org/packages/think/runtime)
[![License](https://poser.pugx.org/think/runtime/license)](https://packagist.org/packages/think/runtime)

ThinkPHP Runtime Component enables decoupling ThinkPHP applications from global state to make sure the application can run with runtimes like Workerman, ReactPHP, Swoole, etc. without any changes.

## Features

- 🚀 **Decoupled Architecture**: Separate bootstrapping logic from global state
- 🔄 **Multiple Runtimes**: Support for Workerman, ReactPHP, and traditional PHP-FPM
- 🎯 **Framework Agnostic**: Works with ThinkPHP and can be extended for other frameworks
- 📦 **Easy Integration**: Simple composer installation and configuration
- 🛠️ **Extensible**: Easy to create custom runtime adapters

## Installation

```bash
composer require think/runtime
```

## Quick Start

### Traditional Web Application

```php
// public/index.php
use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

### Workerman Server

```php
// server/workerman.php
use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

Set runtime in composer.json:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\WorkermanRuntime"
        }
    }
}
```

### ReactPHP Server

```php
// server/reactphp.php
use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

Set runtime in composer.json:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\ReactPHPRuntime"
        }
    }
}
```

### FrankenPHP Server

```php
// public/index.php
use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

Set runtime in composer.json:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\FrankenPHPRuntime",
            "frankenphp_worker": true
        }
    }
}
```

Run with FrankenPHP:

```bash
frankenphp run --worker ./public/index.php
```

### Swoole Server

```php
// public/index.php
use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

Set runtime in composer.json:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\SwooleRuntime",
            "host": "0.0.0.0",
            "port": 9501,
            "enable_coroutine": true
        }
    }
}
```

Run with Swoole:

```bash
php public/index.php
```

### Vercel Serverless

```php
// api/index.php
use think\App;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): App {
    return new App($context['APP_PATH'] ?? '');
};
```

Set runtime in composer.json:

```json
{
    "extra": {
        "runtime": {
            "class": "Think\\Runtime\\VercelRuntime",
            "enable_cors": true,
            "enable_static_cache": true
        }
    }
}
```

Deploy to Vercel:

```bash
vercel --prod
```

## Documentation

- [Getting Started](docs/getting-started.md)
- [Runtime Configuration](docs/configuration.md)
- [Creating Custom Runtimes](docs/custom-runtimes.md)
- [Workerman Integration](docs/workerman.md)
- [ReactPHP Integration](docs/reactphp.md)
- [FrankenPHP Integration](docs/frankenphp.md)
- [Swoole Integration](docs/swoole.md)
- [Vercel Integration](docs/vercel.md)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for details on our code of conduct and the process for submitting pull requests.

## Acknowledgments

- Inspired by [Symfony Runtime Component](https://github.com/symfony/runtime)
- Built for the ThinkPHP community
