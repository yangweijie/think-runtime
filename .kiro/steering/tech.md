# 技术栈

## 核心框架
- **PHP**: 8.1+ (需要 Fiber 支持和现代特性)
- **ThinkPHP**: 8.0+ 框架集成
- **PSR 标准**: PSR-7 (HTTP 消息), PSR-15 (HTTP 处理器/中间件), PSR-4 (自动加载)

## 构建系统和依赖
- **Composer**: 包管理和自动加载
- **Pest**: 测试框架 (新测试首选，优于 PHPUnit)
- **PHPUnit**: 遗留测试支持 (9.6+ 或 10.0+)

## 运行时依赖
- **nyholm/psr7**: PSR-7 HTTP 消息实现
- **nyholm/psr7-server**: PSR-7 服务器请求工厂
- **mattvb91/caddy-php**: FrankenPHP 的 Caddy 集成

## 可选运行时扩展
- **ext-swoole**: Swoole 适配器必需 (高性能异步)
- **ext-sockets**: Socket 操作支持
- **workerman/workerman**: 多进程 socket 服务器
- **react/http**: 事件驱动 HTTP 服务器
- **spiral/roadrunner**: 基于 Go 的应用服务器
- **bref/bref**: AWS Lambda serverless 运行时
- **dunglas/frankenphp**: 现代 PHP 应用服务器

## 常用命令

### 开发
```bash
# 安装依赖
composer install

# 运行测试
composer test
# 或
./vendor/bin/pest

# 带覆盖率测试
composer test-coverage
```

### 运行时操作
```bash
# 检查运行时可用性
php think runtime:info

# 自动检测启动
php think runtime:start

# 启动指定运行时
php think runtime:start swoole --port=8080 --workers=4
php think runtime:start frankenphp --port=8080
php think runtime:start workerman --host=0.0.0.0 --port=8080
```

### 服务管理
```bash
# 发现 ThinkPHP 服务 (如果命令不可用)
php think service:discover
php think clear

# 检查安装
composer check-install
```

## 性能测试
```bash
# 快速性能测试
./quick_performance_test.sh

# 内存基准测试
php memory_benchmark.php

# wrk 负载测试
./wrk_keepalive_test.sh
```

## 配置管理
- 主配置: `config/runtime.php`
- 运行时特定配置在 `config/runtime.php` 的 `runtimes` 键下
- 通过 `auto_detect_order` 配置进行环境检测