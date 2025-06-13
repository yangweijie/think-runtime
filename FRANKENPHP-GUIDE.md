# FrankenPHP Runtime 运行指南

## 🚀 什么是 FrankenPHP

FrankenPHP 是一个现代的 PHP 应用服务器，基于 Go 和 Caddy 构建，提供：

- **高性能**: 比传统 PHP-FPM 快 3-4 倍
- **HTTP/2 & HTTP/3**: 原生支持现代 HTTP 协议
- **自动 HTTPS**: 自动获取和续期 SSL 证书
- **Worker 模式**: 类似 Swoole 的常驻内存模式
- **零配置**: 开箱即用，无需复杂配置

## 📦 安装 FrankenPHP

### 方法1: 使用官方二进制文件

```bash
# 下载 FrankenPHP
curl -fsSL https://frankenphp.dev/install.sh | bash

# 或者手动下载
wget https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64
chmod +x frankenphp-linux-x86_64
sudo mv frankenphp-linux-x86_64 /usr/local/bin/frankenphp
```

### 方法2: 使用 Docker

```bash
# 拉取 FrankenPHP 镜像
docker pull dunglas/frankenphp

# 运行容器
docker run -p 80:80 -p 443:443 -v $PWD:/app dunglas/frankenphp
```

## 🔧 在 ThinkPHP 中使用 FrankenPHP

### 1. 安装 think-runtime

```bash
composer require yangweijie/think-runtime
```

### 2. 使用命令行启动

```bash
# 基本启动
php think runtime:start frankenphp

# 指定参数启动
php think runtime:start frankenphp --listen=:8080 --workers=4

# 启用调试模式
php think runtime:start frankenphp --debug --access-log
```

### 3. 使用示例脚本启动

```bash
# 运行示例脚本
php examples/frankenphp_server.php
```

### 4. 手动配置启动

创建 `frankenphp_start.php`:

```php
<?php
require_once 'vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

$app = new App();
$app->initialize();

$manager = $app->make('runtime.manager');

$options = [
    'listen' => ':8080',
    'worker_num' => 4,
    'max_requests' => 1000,
    'auto_https' => false,  // 开发环境关闭
    'http2' => true,
    'debug' => true,
    'root' => 'public',
    'index' => 'index.php',
];

$manager->start('frankenphp', $options);
```

## ⚙️ 配置选项

### 基本配置

```php
$config = [
    'listen' => ':8080',           // 监听地址和端口
    'worker_num' => 4,             // Worker 进程数
    'max_requests' => 1000,        // 每个 Worker 最大请求数
    'root' => 'public',            // 文档根目录
    'index' => 'index.php',        // 入口文件
];
```

### 高级配置

```php
$config = [
    'auto_https' => true,          // 自动 HTTPS (生产环境)
    'http2' => true,               // 启用 HTTP/2
    'http3' => false,              // 启用 HTTP/3 (实验性)
    'debug' => false,              // 调试模式
    'access_log' => true,          // 访问日志
    'error_log' => true,           // 错误日志
    'log_level' => 'INFO',         // 日志级别
    'env' => [                     // 环境变量
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
    ],
];
```

## 🌐 部署模式

### 1. 开发模式

```bash
# 启动开发服务器
php think runtime:start frankenphp --listen=:8080 --debug

# 或使用配置
$config = [
    'listen' => ':8080',
    'worker_num' => 1,
    'auto_https' => false,
    'debug' => true,
    'access_log' => true,
];
```

### 2. 生产模式

```bash
# 启动生产服务器
php think runtime:start frankenphp --listen=:443 --workers=8 --auto-https

# 或使用配置
$config = [
    'listen' => ':443',
    'worker_num' => 8,
    'max_requests' => 10000,
    'auto_https' => true,
    'http2' => true,
    'debug' => false,
    'log_level' => 'WARN',
];
```

### 3. Docker 部署

创建 `Dockerfile`:

```dockerfile
FROM dunglas/frankenphp

# 复制应用代码
COPY . /app

# 设置工作目录
WORKDIR /app

# 安装依赖
RUN composer install --no-dev --optimize-autoloader

# 暴露端口
EXPOSE 80 443

# 启动命令
CMD ["php", "think", "runtime:start", "frankenphp"]
```

## 🔍 运行状态检查

### 检查 FrankenPHP 是否可用

```bash
# 检查运行时信息
php think runtime:info

# 检查 FrankenPHP 版本
frankenphp version

# 检查进程状态
ps aux | grep frankenphp
```

### 性能监控

```bash
# 查看访问日志
tail -f /var/log/frankenphp/access.log

# 查看错误日志
tail -f /var/log/frankenphp/error.log

# 监控资源使用
top -p $(pgrep frankenphp)
```

## ⚠️ 注意事项

### 1. 环境要求
- PHP >= 8.0
- 支持的操作系统: Linux, macOS, Windows
- 推荐内存: >= 512MB

### 2. Worker 模式注意事项
- 全局变量会在请求间保持
- 需要注意内存泄漏
- 定期重启 Worker 进程

### 3. 生产环境建议
- 使用进程管理器 (systemd, supervisor)
- 配置反向代理 (nginx, cloudflare)
- 启用监控和日志

## 🚨 故障排除

### 1. 启动失败
```bash
# 检查端口占用
lsof -i :8080

# 检查权限
sudo chown -R www-data:www-data /path/to/app

# 检查配置
php think runtime:info
```

### 2. 性能问题
```bash
# 增加 Worker 数量
php think runtime:start frankenphp --workers=8

# 调整最大请求数
php think runtime:start frankenphp --max-requests=5000

# 启用 HTTP/2
php think runtime:start frankenphp --http2
```

### 3. SSL 证书问题
```bash
# 手动获取证书
frankenphp run --domain example.com

# 检查证书状态
openssl s_client -connect example.com:443
```

## 📚 更多资源

- [FrankenPHP 官方文档](https://frankenphp.dev/)
- [GitHub 仓库](https://github.com/dunglas/frankenphp)
- [性能基准测试](https://frankenphp.dev/docs/benchmark/)
- [Docker 镜像](https://hub.docker.com/r/dunglas/frankenphp)

## 🎯 总结

FrankenPHP 是一个强大的现代 PHP 应用服务器，特别适合：

- **高性能 API 服务**
- **现代 Web 应用**
- **微服务架构**
- **需要 HTTP/2 支持的应用**
- **自动 HTTPS 的生产环境**

通过 think-runtime，您可以轻松在 ThinkPHP 项目中使用 FrankenPHP 的强大功能！
