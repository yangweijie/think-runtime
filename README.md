# ThinkPHP Runtime 扩展包

高性能环境下运行的ThinkPHP Runtime扩展包，支持Swoole、RoadRunner、FrankenPHP等多种运行时环境。

## 特性

- 🚀 **高性能**: 支持Swoole、RoadRunner等高性能运行时
- 🔄 **自动检测**: 自动检测并选择最佳运行时环境
- 🛠 **易于配置**: 简单的配置文件管理
- 🧪 **完整测试**: 使用Pest测试框架，确保代码质量
- 📦 **PSR标准**: 遵循PSR-7、PSR-15等标准
- 🎯 **ThinkPHP规范**: 严格遵循ThinkPHP8.0开发规范

## 支持的运行时

| 运行时 | 描述 | 优先级 | 要求 |
|--------|------|--------|------|
| Swoole | 基于Swoole的高性能HTTP服务器 | 100 | ext-swoole |
| FrankenPHP | 现代PHP应用服务器，支持HTTP/2、HTTP/3 | 95 | dunglas/frankenphp |
| ReactPHP | 事件驱动的异步HTTP服务器 | 92 | react/http, react/socket |
| Ripple | 基于PHP Fiber的高性能协程HTTP服务器 | 91 | cloudtay/ripple, PHP 8.1+ |
| RoadRunner | 基于Go的高性能应用服务器 | 90 | spiral/roadrunner |

## 安装

```bash
composer require yangweijie/think-runtime
```

## 快速开始

### 1. 配置

在ThinkPHP应用的`config`目录下创建`runtime.php`配置文件：

```php
<?php
return [
    // 默认运行时 (auto, swoole, roadrunner)
    'default' => 'auto',

    // 自动检测顺序
    'auto_detect_order' => [
        'swoole',
        'frankenphp',
        'reactphp',
        'ripple',
        'roadrunner',
    ],

    // 运行时配置
    'runtimes' => [
        'swoole' => [
            'host' => '0.0.0.0',
            'port' => 9501,
            'settings' => [
                'worker_num' => 4,
                'task_worker_num' => 2,
                'max_request' => 10000,
            ],
        ],
        'roadrunner' => [
            'debug' => false,
            'max_jobs' => 0,
        ],
    ],
];
```

### 2. 启动服务器

```bash
# 自动检测并启动最佳运行时
php think runtime:start

# 指定运行时启动
php think runtime:start swoole
php think runtime:start frankenphp
php think runtime:start reactphp
php think runtime:start ripple

# 自定义参数启动
php think runtime:start swoole --host=127.0.0.1 --port=8080 --workers=8
php think runtime:start frankenphp --port=8080 --workers=4
php think runtime:start reactphp --host=0.0.0.0 --port=8080
php think runtime:start ripple --host=0.0.0.0 --port=8080 --workers=4
```

### 3. 查看运行时信息

```bash
php think runtime:info
```

## 使用示例

### 基本使用

```php
<?php
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 获取运行时管理器
$manager = app('runtime.manager');

// 自动检测并启动
$manager->start();

// 指定运行时启动
$manager->start('swoole', [
    'host' => '0.0.0.0',
    'port' => 9501,
]);

// 获取运行时信息
$info = $manager->getRuntimeInfo();
```

### 自定义适配器

```php
<?php
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

class CustomAdapter extends AbstractRuntime implements AdapterInterface
{
    public function getName(): string
    {
        return 'custom';
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return 50;
    }

    // 实现其他必需方法...
}

// 注册自定义适配器
$manager = app('runtime.manager');
$manager->registerAdapter('custom', CustomAdapter::class);
```

## 配置说明

### Swoole配置

```php
'swoole' => [
    'host' => '0.0.0.0',           // 监听地址
    'port' => 9501,                // 监听端口
    'mode' => SWOOLE_PROCESS,      // 运行模式
    'sock_type' => SWOOLE_SOCK_TCP, // Socket类型
    'settings' => [
        'worker_num' => 4,          // Worker进程数
        'task_worker_num' => 2,     // Task进程数
        'max_request' => 10000,     // 最大请求数
        'dispatch_mode' => 2,       // 数据包分发策略
        'daemonize' => 0,          // 守护进程化
    ],
],
```

### FrankenPHP配置

```php
'frankenphp' => [
    'listen' => ':8080',           // 监听地址和端口
    'worker_num' => 4,             // Worker进程数
    'max_requests' => 1000,        // 每个Worker最大请求数
    'auto_https' => true,          // 自动HTTPS
    'http2' => true,               // 启用HTTP/2
    'http3' => false,              // 启用HTTP/3
    'debug' => false,              // 调试模式
    'access_log' => true,          // 访问日志
    'error_log' => true,           // 错误日志
    'log_level' => 'INFO',         // 日志级别
    'root' => 'public',            // 文档根目录
    'index' => 'index.php',        // 入口文件
    'env' => [                     // 环境变量
        'APP_ENV' => 'production',
    ],
],
```

### ReactPHP配置

```php
'reactphp' => [
    'host' => '0.0.0.0',           // 监听主机
    'port' => 8080,                // 监听端口
    'max_connections' => 1000,     // 最大连接数
    'timeout' => 30,               // 连接超时时间（秒）
    'enable_keepalive' => true,    // 启用Keep-Alive
    'keepalive_timeout' => 5,      // Keep-Alive超时时间
    'max_request_size' => '8M',    // 最大请求大小
    'enable_compression' => true,  // 启用压缩
    'debug' => false,              // 调试模式
    'access_log' => true,          // 访问日志
    'error_log' => true,           // 错误日志
    'websocket' => false,          // WebSocket支持
    'ssl' => [                     // SSL配置
        'enabled' => false,
        'cert' => '',              // SSL证书路径
        'key' => '',               // SSL私钥路径
    ],
],
```

### Ripple配置

```php
'ripple' => [
    'host' => '0.0.0.0',           // 监听主机
    'port' => 8080,                // 监听端口
    'worker_num' => 4,             // Worker进程数
    'max_connections' => 10000,    // 最大连接数
    'max_coroutines' => 100000,    // 最大协程数
    'coroutine_pool_size' => 1000, // 协程池大小
    'timeout' => 30,               // 连接超时时间（秒）
    'enable_keepalive' => true,    // 启用Keep-Alive
    'keepalive_timeout' => 60,     // Keep-Alive超时时间
    'max_request_size' => '8M',    // 最大请求大小
    'enable_compression' => true,  // 启用压缩
    'compression_level' => 6,      // 压缩级别
    'debug' => false,              // 调试模式
    'access_log' => true,          // 访问日志
    'error_log' => true,           // 错误日志
    'enable_fiber' => true,        // 启用Fiber
    'fiber_stack_size' => 8192,    // Fiber栈大小
    'ssl' => [                     // SSL配置
        'enabled' => false,
        'cert_file' => '',         // SSL证书文件
        'key_file' => '',          // SSL私钥文件
        'verify_peer' => false,    // 验证对等方
    ],
    'database' => [                // 数据库连接池
        'pool_size' => 10,         // 连接池大小
        'max_idle_time' => 3600,   // 最大空闲时间
    ],
],
```

### RoadRunner配置

```php
'roadrunner' => [
    'debug' => false,      // 调试模式
    'max_jobs' => 0,       // 最大任务数 (0为无限制)
    'memory_limit' => '128M', // 内存限制
],
```

## RoadRunner 运行指南

### 1. 安装依赖

```bash
# 安装 RoadRunner PHP 包
composer require spiral/roadrunner spiral/roadrunner-http

# 安装 RoadRunner CLI 工具
composer require spiral/roadrunner-cli --dev

# 下载 RoadRunner 二进制文件
./vendor/bin/rr get-binary

# Windows 用户也可以从官方网站下载二进制文件
# https://github.com/roadrunner-server/roadrunner/releases
```

### 2. 创建 RoadRunner 配置文件

在项目根目录创建 `.rr.yaml` 配置文件：

```yaml
# .rr.yaml
version: "3"

rpc:
  listen: tcp://127.0.0.1:6001

server:
  command: "php worker.php"
  user: ""
  group: ""
  env:
    - APP_ENV: production
  relay: "pipes"
  relay_timeout: "20s"

http:
  address: 0.0.0.0:8080
  middleware: ["static", "gzip"]
  uploads:
    forbid: [".php", ".exe", ".bat"]
  static:
    dir: "public"
    forbid: [".htaccess", ".php"]

logs:
  mode: development
  level: error
  file_logger_options:
    log_output: "./runtime/logs/roadrunner.log"
    max_size: 10
    max_age: 30
    max_backups: 3
    compress: true

reload:
  interval: "1s"
  patterns: [".php"]
  services:
    http:
      recursive: true
      ignore: ["vendor"]
      patterns: [".php"]
      dirs: ["./"]
```

### 3. 创建 Worker 文件

在项目根目录创建 `worker.php` 文件：

```php
<?php

declare(strict_types=1);

/**
 * RoadRunner Worker 入口文件
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/vendor/autoload.php';

// 创建应用实例
$app = new App();

// 初始化应用
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 启动RoadRunner运行时
$manager->start('roadrunner');
```

### 4. 启动 RoadRunner 服务

```bash
# 使用 RoadRunner 二进制文件启动服务
./roadrunner serve -c .rr.yaml

# 或者使用 vendor 中的二进制文件
./vendor/bin/rr serve -c .rr.yaml

# Windows 用户可以使用
rr.exe serve -c .rr.yaml
```

### 5. 管理 RoadRunner 服务

```bash
# 重载配置
./vendor/bin/rr reset

# 查看状态
./vendor/bin/rr status

# 查看工作进程
./vendor/bin/rr workers
```

### 6. 性能优化

可以通过调整 `.rr.yaml` 中的以下配置来优化性能：

```yaml
http:
  pool:
    num_workers: 4      # 工作进程数
    max_jobs: 1000      # 每个进程最大任务数
    allocate_timeout: 60s
    destroy_timeout: 60s
```

## 命令行工具

### runtime:start

启动运行时服务器

```bash
php think runtime:start [runtime] [options]
```

参数：
- `runtime`: 运行时名称 (swoole, roadrunner, auto)

选项：
- `--host, -H`: 服务器地址 (默认: 0.0.0.0)
- `--port, -p`: 服务器端口 (默认: 9501)
- `--daemon, -d`: 守护进程模式
- `--workers, -w`: Worker进程数 (默认: 4)

### runtime:info

显示运行时环境信息

```bash
php think runtime:info
```

## 测试

使用Pest测试框架：

```bash
# 运行所有测试
composer test

# 运行测试并生成覆盖率报告
composer test-coverage
```

## 开发规范

本项目严格遵循ThinkPHP8.0开发规范：

- 遵循PSR-2命名规范和PSR-4自动加载规范
- 目录使用小写+下划线
- 类名采用驼峰法（首字母大写）
- 方法名使用驼峰法（首字母小写）
- 属性名使用驼峰法（首字母小写）
- 常量使用大写字母和下划线

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request！

## 更新日志

### v1.0.0
- 初始版本发布
- 支持Swoole、RoadRunner运行时
- 提供命令行工具
- 完整的测试覆盖
