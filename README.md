# ThinkPHP Runtime 扩展包

高性能环境下运行的ThinkPHP Runtime扩展包，支持Swoole、RoadRunner、FrankenPHP等多种运行时环境。

## 特性

- 🚀 **高性能**: 支持Swoole、RoadRunner、ReactPHP、FrankenPHP、Workerman、Bref、Vercel等多种运行时
- 🔄 **自动检测**: 自动检测并选择最佳运行时环境
- 🛠 **易于配置**: 简单的配置文件管理
- 🧪 **完整测试**: 使用Pest测试框架，确保代码质量
- 📦 **PSR标准**: 遵循PSR-7、PSR-15等标准
- 🎯 **ThinkPHP规范**: 严格遵循ThinkPHP8.0开发规范
- 🛡️ **安全增强**: 内置安全防护、CORS支持、静态文件安全检查
- 📊 **性能监控**: 请求时间统计、慢请求记录、内存使用监控
- 🔌 **中间件系统**: 灵活的中间件支持，可扩展功能
- 🌐 **WebSocket支持**: 完整的WebSocket服务器功能（Swoole）
- 📁 **静态文件服务**: 高效的静态资源处理能力

## 支持的运行时

| 运行时 | 描述 | 优先级 | 要求 |
|--------|------|--------|------|
| Swoole | 基于Swoole的高性能HTTP服务器 | 100 | ext-swoole |
| FrankenPHP | 现代PHP应用服务器，支持HTTP/2、HTTP/3 | 95 | frankenphp 二进制文件 |
| Workerman | 高性能PHP socket服务器框架 | 93 | workerman/workerman |
| ReactPHP | 事件驱动的异步HTTP服务器 | 92 | react/http, react/socket |
| Ripple | 基于PHP Fiber的高性能协程HTTP服务器 | 91 | cloudtay/ripple, PHP 8.1+ |
| RoadRunner | 基于Go的高性能应用服务器 | 90 | spiral/roadrunner |
| Bref | AWS Lambda serverless运行时 | 85 | bref/bref |
| Vercel | Vercel serverless functions运行时 | 80 | vercel/php |

## 安装

### 要求

- PHP >= 8.0
- ThinkPHP >= 8.0

### 安装步骤

```bash
# 安装扩展包
composer require yangweijie/think-runtime
```

### 故障排除

#### 1. 命令不可用
如果安装后没有看到runtime命令，请尝试以下解决方案：

```bash
# 方案1: 重新发现服务
php think service:discover
php think clear

# 方案2: 手动注册（运行项目根目录下的脚本）
php vendor/yangweijie/think-runtime/test-thinkphp-commands.php
```

#### 2. ReactPHP 依赖问题
如果使用 ReactPHP 运行时遇到 `Class "RingCentral\Psr7\Request" not found` 错误：

```bash
# 自动安装 ReactPHP 依赖
php vendor/yangweijie/think-runtime/install-reactphp.php

# 或手动安装
composer require react/http react/socket react/promise ringcentral/psr7
```

#### 3. Swoole 进程问题
如果 Swoole 运行时出现 Worker 进程退出：

```bash
# 检查 Swoole 版本
php --ri swoole

# 确保版本 >= 4.8.0
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
        'workerman',
        'reactphp',
        'ripple',
        'roadrunner',
        'bref',
        'vercel',
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
php think runtime:start workerman
php think runtime:start reactphp
php think runtime:start ripple
php think runtime:start bref
php think runtime:start vercel

# 自定义参数启动
php think runtime:start swoole --host=127.0.0.1 --port=8080 --workers=8
php think runtime:start frankenphp --port=8080 --workers=4
php think runtime:start workerman --host=0.0.0.0 --port=8080 --workers=4
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
    'mode' => 3,                   // 运行模式 (SWOOLE_PROCESS)
    'sock_type' => 1,              // Socket类型 (SWOOLE_SOCK_TCP)
    'settings' => [
        'worker_num' => 4,          // Worker进程数
        'task_worker_num' => 2,     // Task进程数
        'max_request' => 10000,     // 最大请求数
        'dispatch_mode' => 2,       // 数据包分发策略
        'daemonize' => 0,          // 守护进程化
        'enable_coroutine' => 1,    // 启用协程
        'max_coroutine' => 100000,  // 最大协程数
        'hook_flags' => 268435455,  // 协程Hook标志 (SWOOLE_HOOK_ALL)
        'enable_preemptive_scheduler' => true, // 启用抢占式调度
    ],
    // 静态文件配置
    'static_file' => [
        'enable' => true,           // 启用静态文件服务
        'document_root' => 'public', // 文档根目录
        'cache_time' => 3600,       // 缓存时间（秒）
        'allowed_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'], // 允许的文件扩展名
    ],
    // WebSocket 配置
    'websocket' => [
        'enable' => false,          // 启用WebSocket支持
    ],
    // 性能监控配置
    'monitor' => [
        'enable' => true,           // 启用性能监控
        'slow_request_threshold' => 1000, // 慢请求阈值（毫秒）
    ],
    // 中间件配置
    'middleware' => [
        'cors' => [
            'enable' => true,       // 启用CORS中间件
            'allow_origin' => '*',  // 允许的源
            'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS', // 允许的方法
            'allow_headers' => 'Content-Type, Authorization, X-Requested-With', // 允许的头
        ],
        'security' => [
            'enable' => true,       // 启用安全中间件
        ],
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

### Workerman配置

```php
'workerman' => [
    'host' => '0.0.0.0',           // 监听地址
    'port' => 8080,                // 监听端口
    'count' => 4,                  // 进程数
    'name' => 'ThinkPHP-Workerman', // 进程名称
    'user' => '',                  // 运行用户
    'group' => '',                 // 运行用户组
    'reloadable' => true,          // 是否可重载
    'reusePort' => false,          // 端口复用
    'transport' => 'tcp',          // 传输协议
    'context' => [],               // Socket上下文选项
    'protocol' => 'http',          // 应用层协议
    // 静态文件配置
    'static_file' => [
        'enable' => true,           // 启用静态文件服务
        'document_root' => 'public', // 文档根目录
        'cache_time' => 3600,       // 缓存时间（秒）
        'allowed_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'], // 允许的文件扩展名
    ],
    // 性能监控配置
    'monitor' => [
        'enable' => true,           // 启用性能监控
        'slow_request_threshold' => 1000, // 慢请求阈值（毫秒）
        'memory_limit' => '256M',   // 内存限制
    ],
    // 中间件配置
    'middleware' => [
        'cors' => [
            'enable' => true,       // 启用CORS中间件
            'allow_origin' => '*',  // 允许的源
            'allow_methods' => 'GET, POST, PUT, DELETE, OPTIONS', // 允许的方法
            'allow_headers' => 'Content-Type, Authorization, X-Requested-With', // 允许的头
        ],
        'security' => [
            'enable' => true,       // 启用安全中间件
        ],
    ],
    // 日志配置
    'log' => [
        'enable' => true,           // 启用日志
        'file' => 'runtime/logs/workerman.log', // 日志文件
        'level' => 'info',          // 日志级别
    ],
    // 定时器配置
    'timer' => [
        'enable' => false,          // 启用定时器
        'interval' => 60,           // 定时器间隔（秒）
    ],
],
```

### Bref配置

```php
'bref' => [
    // Lambda运行时配置
    'lambda' => [
        'timeout' => 30,               // Lambda函数超时时间（秒）
        'memory' => 512,               // Lambda函数内存大小（MB）
        'environment' => 'production', // 运行环境
    ],
    // HTTP处理配置
    'http' => [
        'enable_cors' => true,         // 启用CORS
        'cors_origin' => '*',          // 允许的源
        'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS', // 允许的方法
        'cors_headers' => 'Content-Type, Authorization, X-Requested-With', // 允许的头
    ],
    // 错误处理配置
    'error' => [
        'display_errors' => false,     // 显示错误
        'log_errors' => true,          // 记录错误日志
    ],
    // 性能监控配置
    'monitor' => [
        'enable' => true,              // 启用性能监控
        'slow_request_threshold' => 1000, // 慢请求阈值（毫秒）
    ],
],
```

### Vercel配置

```php
'vercel' => [
    // Vercel函数配置
    'vercel' => [
        'timeout' => 10,               // Vercel函数超时时间（秒）
        'memory' => 1024,              // 函数内存大小（MB）
        'region' => 'auto',            // 部署区域
        'runtime' => 'php-8.1',        // PHP运行时版本
    ],
    // HTTP处理配置
    'http' => [
        'enable_cors' => true,         // 启用CORS
        'cors_origin' => '*',          // 允许的源
        'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS', // 允许的方法
        'cors_headers' => 'Content-Type, Authorization, X-Requested-With', // 允许的头
        'max_body_size' => '5mb',      // 最大请求体大小
    ],
    // 错误处理配置
    'error' => [
        'display_errors' => false,     // 显示错误
        'log_errors' => true,          // 记录错误日志
        'error_reporting' => E_ALL & ~E_NOTICE, // 错误报告级别
    ],
    // 性能监控配置
    'monitor' => [
        'enable' => true,              // 启用性能监控
        'slow_request_threshold' => 1000, // 慢请求阈值（毫秒）
        'memory_threshold' => 80,      // 内存使用阈值百分比
    ],
    // 静态文件配置
    'static' => [
        'enable' => false,             // 启用静态文件服务（Vercel通常由CDN处理）
        'extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'], // 允许的文件扩展名
    ],
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

## 运行时可用性要求

要让每个运行时在 `php think runtime:info` 中显示 "Available: Yes"，需要满足以下条件：

### Swoole Runtime

**要求**：
- 安装 Swoole PHP 扩展
- Swoole\Server 类可用

**安装步骤**：
```bash
# 通过 PECL 安装
pecl install swoole

# 或通过包管理器安装（Ubuntu/Debian）
sudo apt-get install php-swoole

# 或通过包管理器安装（CentOS/RHEL）
sudo yum install php-swoole

# 验证安装
php -m | grep swoole
```

**配置**：
在 php.ini 中添加：
```ini
extension=swoole
```

### FrankenPHP Runtime

**要求**：
- 在 FrankenPHP 环境中运行，或
- 系统中安装了 FrankenPHP 二进制文件

**安装步骤**：
```bash
# 方法1：下载预编译二进制文件
curl -fsSL https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64 -o frankenphp
chmod +x frankenphp
sudo mv frankenphp /usr/local/bin/

# 方法2：通过 Docker
docker pull dunglas/frankenphp

# 方法3：通过 Composer（开发环境）
composer require dunglas/frankenphp-dev

# 验证安装
frankenphp version
```

**环境变量**（可选）：
```bash
export FRANKENPHP_VERSION=1.0.0
export FRANKENPHP_CONFIG=/path/to/config
```

### Workerman Runtime

**要求**：
- Workerman\Worker 类可用

**安装步骤**：
```bash
# 通过 Composer 安装
composer require workerman/workerman

# 验证安装
php -r "echo class_exists('Workerman\\Worker') ? 'OK' : 'Failed';"
```

### ReactPHP Runtime

**要求**：
- React\EventLoop\Loop 类可用
- React\Http\HttpServer 类可用
- React\Socket\SocketServer 类可用
- React\Http\Message\Response 类可用
- React\Promise\Promise 类可用

**安装步骤**：
```bash
# 通过 Composer 安装
composer require react/http react/socket

# 验证安装
php -r "
echo class_exists('React\\EventLoop\\Loop') ? 'EventLoop: OK' : 'EventLoop: Failed';
echo PHP_EOL;
echo class_exists('React\\Http\\HttpServer') ? 'HttpServer: OK' : 'HttpServer: Failed';
"
```

### Ripple Runtime

**要求**：
- PHP 8.1+ （支持 Fiber）
- Ripple\Http\Server 或 Ripple\Server\Server 类可用，或
- ripple_server_create 函数可用

**安装步骤**：
```bash
# 检查 PHP 版本
php -v  # 确保 >= 8.1

# 通过 Composer 安装
composer require cloudtay/ripple

# 验证安装
php -r "
echo version_compare(PHP_VERSION, '8.1.0', '>=') ? 'PHP Version: OK' : 'PHP Version: Failed';
echo PHP_EOL;
echo class_exists('Ripple\\Http\\Server') ? 'Ripple: OK' : 'Ripple: Failed';
"
```

### RoadRunner Runtime

**要求**：
- Spiral\RoadRunner\Worker 类可用
- Spiral\RoadRunner\Http\PSR7Worker 类可用
- RR_MODE 环境变量设置

**安装步骤**：
```bash
# 安装 RoadRunner 二进制文件
curl -fsSL https://github.com/roadrunner-server/roadrunner/releases/latest/download/roadrunner-linux-amd64.tar.gz | tar -xz
sudo mv rr /usr/local/bin/

# 通过 Composer 安装 PHP 包
composer require spiral/roadrunner-http spiral/roadrunner-worker

# 创建 .rr.yaml 配置文件
cat > .rr.yaml << EOF
version: "3"
server:
  command: "php worker.php"
http:
  address: 0.0.0.0:8080
EOF

# 设置环境变量
export RR_MODE=http

# 验证安装
rr version
php -r "echo class_exists('Spiral\\RoadRunner\\Worker') ? 'OK' : 'Failed';"
```

### Bref Runtime

**要求**：
- 在 AWS Lambda 环境中运行，或
- Bref\Context\Context 类可用，或
- Runtime\Bref\Runtime 类可用

**安装步骤**：
```bash
# 通过 Composer 安装
composer require bref/bref

# 验证安装
php -r "echo class_exists('Bref\\Context\\Context') ? 'OK' : 'Failed';"
```

**AWS Lambda 环境变量**（自动设置）：
```bash
AWS_LAMBDA_FUNCTION_NAME=your-function
AWS_LAMBDA_RUNTIME_API=127.0.0.1:9001
_LAMBDA_SERVER_PORT=8080
```

### Vercel Runtime

**要求**：
- 在 Vercel 环境中运行，或
- vercel_request 函数可用，或
- VERCEL 或 VERCEL_ENV 环境变量设置

**安装步骤**：
```bash
# 安装 Vercel CLI
npm i -g vercel

# 在项目中创建 vercel.json
cat > vercel.json << EOF
{
  "functions": {
    "api/*.php": {
      "runtime": "vercel-php@0.6.0"
    }
  }
}
EOF

# 设置环境变量（开发测试）
export VERCEL=1
export VERCEL_ENV=development

# 验证安装
vercel --version
```

**Vercel 环境变量**（自动设置）：
```bash
VERCEL=1
VERCEL_ENV=production|preview|development
VERCEL_URL=your-app.vercel.app
```

### 故障排除

**常见问题**：

1. **Swoole 显示 "Not Available"**：
   ```bash
   # 检查扩展是否加载
   php -m | grep swoole

   # 检查 php.ini 配置
   php --ini
   ```

2. **FrankenPHP 显示 "Not Available"**：
   ```bash
   # 检查二进制文件
   which frankenphp

   # 检查环境变量
   echo $FRANKENPHP_VERSION
   ```

3. **Composer 包未找到**：
   ```bash
   # 重新安装依赖
   composer install --no-dev --optimize-autoloader

   # 检查 autoload
   composer dump-autoload
   ```

4. **权限问题**：
   ```bash
   # 确保二进制文件可执行
   chmod +x /usr/local/bin/frankenphp
   chmod +x /usr/local/bin/rr
   ```

## 命令行工具

### runtime:start

启动运行时服务器

```bash
php think runtime:start [runtime] [options]
```

参数：
- `runtime`: 运行时名称 (swoole, reactphp, frankenphp, ripple, roadrunner, workerman, bref, vercel, auto)

选项：
- `--host, -H`: 服务器地址 (默认: 0.0.0.0)
- `--port, -p`: 服务器端口 (默认: 9501)
- `--daemon, -d`: 守护进程模式
- `--workers, -w`: Worker进程数 (默认: 4)
- `--debug`: 启用调试模式

示例：
```bash
# 自动检测最佳运行时
php think runtime:start

# 启动Swoole服务器
php think runtime:start swoole --host=127.0.0.1 --port=8080 --workers=8 --debug

# 启动ReactPHP服务器
php think runtime:start reactphp --port=8080 --debug

# 启动FrankenPHP服务器
php think runtime:start frankenphp --port=8080 --workers=4 --debug

# 启动Workerman服务器
php think runtime:start workerman --port=8080 --workers=4 --daemon

# 启动Ripple服务器
php think runtime:start ripple --host=0.0.0.0 --port=8080 --workers=4

# 启动RoadRunner服务器
php think runtime:start roadrunner --debug

# 启动Bref服务器（AWS Lambda环境）
php think runtime:start bref --debug

# 启动Vercel服务器（Vercel Serverless环境）
php think runtime:start vercel --debug
```

### runtime:info

显示运行时环境信息

```bash
php think runtime:info
```

显示内容包括：
- 当前可用的运行时
- 各运行时的支持状态
- 推荐的运行时配置
- 性能优化建议

### 命令行工具改进

#### v1.3.0 重大优化

1. **代码结构优化**：
   - 将RuntimeStartCommand中的if-else链重构为switch语句
   - 提高代码可读性和维护性
   - 更清晰的runtime处理逻辑

2. **新runtime支持**：
   - 完整支持Bref、Vercel、Workerman runtime
   - 智能参数处理，根据runtime类型自动调整选项
   - 专门的serverless环境适配

3. **参数处理优化**：
   - Workerman: `--workers` 自动转换为 `count` 参数
   - Bref/Vercel: 自动移除不适用的选项（host, port, workers, daemon）
   - FrankenPHP: `--host` 和 `--port` 自动合并为 `listen` 参数
   - ReactPHP: 自动移除不支持的 `workers` 选项
   - RoadRunner: 自动移除不适用的网络选项

4. **启动信息显示**：
   - 每个runtime都有专门的信息显示格式
   - Serverless runtime显示环境信息
   - 传统runtime显示网络和进程信息

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

## 性能优化建议

### Swoole 性能优化

1. **进程配置**：
   ```php
   'settings' => [
       'worker_num' => 4,              // 设置为CPU核心数
       'max_request' => 10000,         // 防止内存泄漏
       'enable_coroutine' => 1,        // 启用协程
       'max_coroutine' => 100000,      // 根据内存调整
   ]
   ```

2. **静态文件优化**：
   ```php
   'static_file' => [
       'enable' => true,
       'cache_time' => 86400,          // 24小时缓存
       'allowed_extensions' => ['css', 'js', 'png', 'jpg'], // 限制文件类型
   ]
   ```

3. **监控配置**：
   ```php
   'monitor' => [
       'slow_request_threshold' => 500, // 500ms慢请求阈值
   ]
   ```

### ReactPHP 性能优化

1. **连接管理**：
   ```php
   'max_connections' => 1000,          // 根据服务器配置调整
   'timeout' => 30,                    // 合理的超时时间
   'enable_keepalive' => true,         // 启用长连接
   ```

2. **内存优化**：
   ```php
   'max_request_size' => '8M',         // 限制请求大小
   'enable_compression' => true,       // 启用压缩
   ```

### Workerman 性能优化

1. **进程配置**：
   ```php
   'count' => 4,                       // 设置为CPU核心数
   'reloadable' => true,               // 启用平滑重启
   'reusePort' => true,                // 启用端口复用（Linux 3.9+）
   ```

2. **静态文件优化**：
   ```php
   'static_file' => [
       'enable' => true,
       'cache_time' => 86400,          // 24小时缓存
       'allowed_extensions' => ['css', 'js', 'png', 'jpg'], // 限制文件类型
   ]
   ```

3. **监控配置**：
   ```php
   'monitor' => [
       'slow_request_threshold' => 500, // 500ms慢请求阈值
       'memory_limit' => '256M',        // 内存限制
   ]
   ```

### Bref/Vercel 性能优化

1. **Lambda/Serverless配置**：
   ```php
   // Bref
   'lambda' => [
       'timeout' => 30,                // 合理的超时时间
       'memory' => 1024,               // 根据需求调整内存
   ]

   // Vercel
   'vercel' => [
       'timeout' => 10,                // Vercel限制
       'memory' => 1024,               // 最大内存
   ]
   ```

2. **冷启动优化**：
   ```php
   'monitor' => [
       'slow_request_threshold' => 1000, // 考虑冷启动时间
   ]
   ```

### 通用优化建议

1. **PHP配置**：
   - 启用OPcache
   - 设置合适的内存限制
   - 优化垃圾回收设置

2. **系统配置**：
   - 调整系统文件描述符限制
   - 优化TCP内核参数
   - 使用SSD存储

3. **应用优化**：
   - 使用数据库连接池
   - 实现缓存策略
   - 优化数据库查询

## 故障排除

### 常见问题

1. **Swoole扩展未安装**：
   ```bash
   # Ubuntu/Debian
   sudo apt-get install php-swoole

   # CentOS/RHEL
   sudo yum install php-swoole

   # 或使用PECL安装
   pecl install swoole
   ```

2. **ReactPHP依赖缺失**：
   ```bash
   composer require react/http react/socket
   ```

3. **调试工具条时间累加**：
   - 已在v1.1.0版本修复
   - 自动重置全局状态和调试信息

4. **端口被占用**：
   ```bash
   # 查看端口占用
   netstat -tlnp | grep 9501

   # 或使用其他端口
   php think runtime:start swoole --port=8080
   ```

5. **权限问题**：
   ```bash
   # 确保目录权限正确
   chmod -R 755 runtime/
   chmod -R 755 public/
   ```

## 贡献

欢迎提交Issue和Pull Request！

### 贡献指南

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 打开 Pull Request

### 开发环境

```bash
# 克隆仓库
git clone https://github.com/yangweijie/think-runtime.git

# 安装依赖
composer install

# 运行测试
composer test

# 代码格式检查
composer cs-fix
```

## 更新日志

### v1.3.0 (最新)
- 🆕 **新增 Bref 和 Vercel 适配器**：
  - Bref: AWS Lambda serverless运行时支持
  - Vercel: Vercel serverless functions运行时支持
  - 自动环境检测和配置优化
  - 完整的serverless环境适配
- 🛠 **RuntimeStartCommand 重大优化**：
  - 将if-else链重构为switch语句，提高代码可读性
  - 添加对所有新runtime的完整支持
  - 优化命令行参数处理逻辑
  - 改进启动信息显示，支持所有runtime类型
- 🐛 **测试修复**：
  - 修复FrankenphpAdapterTest环境检测测试
  - 提高测试在不同系统环境中的稳定性
  - 完善测试覆盖率，确保代码质量

### v1.2.0
- 🆕 **新增 Workerman 适配器**：
  - 多进程架构，充分利用多核CPU
  - 事件驱动的高效I/O处理
  - 内置静态文件服务器
  - 完整的中间件系统支持
  - 性能监控和慢请求记录
  - 定时器支持，后台任务处理
  - 平滑重启，零停机部署
  - 内存监控，防止内存泄漏

### v1.1.0
- 🚀 **Swoole适配器重大改进**：
  - 新增协程上下文管理，提升并发安全性
  - 实现PSR-7工厂复用，减少内存使用20-30%
  - 添加中间件系统支持（CORS、安全头等）
  - 集成静态文件服务，响应速度提升50-80%
  - 新增WebSocket支持，实现实时通信功能
  - 添加性能监控和慢请求记录
  - 增强安全防护，防止目录遍历攻击
- 🛠 **ReactPHP适配器优化**：
  - 修复setTimeout方法调用错误
  - 添加依赖包自动安装
  - 优化错误处理机制
- 🐛 **调试工具条修复**：
  - 解决think-trace运行时间累加问题
  - 添加全局状态重置机制
  - 修复Swoole常驻内存环境中调试信息累积导致的内存和耗时异常上涨
  - 实现定期深度调试状态重置，确保长期运行稳定性
  - 优化常驻内存运行时的状态管理
- 📚 **文档完善**：
  - 新增RoadRunner详细配置指南
  - 添加性能优化建议
  - 完善故障排除文档

### v1.0.0
- 初始版本发布
- 支持Swoole、RoadRunner、ReactPHP、FrankenPHP、Ripple运行时
- 提供命令行工具
- 完整的测试覆盖
- 自动检测最佳运行时环境
