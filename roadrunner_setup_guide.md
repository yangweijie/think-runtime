# ThinkPHP Runtime - RoadRunner 环境配置指南

## 概述

RoadRunner 是一个用 Go 语言编写的高性能 PHP 应用服务器，支持 HTTP/2、gRPC、队列等功能。本指南将帮助您在 think-runtime 中配置和运行 RoadRunner 环境。

## 1. 安装依赖

### 安装 RoadRunner PHP 包
```bash
composer require spiral/roadrunner spiral/roadrunner-http
```

### 下载 RoadRunner 二进制文件
```bash
# 方法1：使用 Composer
composer require spiral/roadrunner-cli --dev
./vendor/bin/rr get-binary

# 方法2：直接下载（Linux/macOS）
curl -sSL https://github.com/roadrunner-server/roadrunner/releases/latest/download/roadrunner-linux-amd64.tar.gz | tar -xz
chmod +x roadrunner
sudo mv roadrunner /usr/local/bin/

# 方法3：直接下载（Windows）
# 从 https://github.com/roadrunner-server/roadrunner/releases 下载对应版本
```

## 2. 配置文件

### 创建 RoadRunner 配置文件 `.rr.yaml`
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
    - RR_MODE: http
  relay: "pipes"
  relay_timeout: "20s"

http:
  address: 0.0.0.0:8080
  middleware: ["headers", "gzip"]
  uploads:
    forbid: [".php", ".exe", ".bat"]
  trusted_subnets:
    - "10.0.0.0/8"
    - "127.0.0.0/8"
    - "172.16.0.0/12"
    - "192.168.0.0/16"
    - "::1/128"
    - "fc00::/7"
    - "fe80::/10"

headers:
  cors:
    allowed_origin: "*"
    allowed_headers: "*"
    allowed_methods: "GET,POST,PUT,DELETE,OPTIONS"
    allow_credentials: true

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

### 创建 Worker 文件 `worker.php`
```php
<?php
// worker.php

use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\Http\PSR7Worker;
use Nyholm\Psr7\Factory\Psr17Factory;

require_once __DIR__ . '/vendor/autoload.php';

// 设置 RoadRunner 环境变量
$_SERVER['RR_MODE'] = 'http';

// 创建应用实例
$app = new think\App();
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 启动 RoadRunner 运行时
$manager->start('roadrunner');
```

## 3. ThinkPHP 配置

### 确认 runtime.php 配置
```php
// config/runtime.php
return [
    'default' => 'auto',
    
    'auto_detect_order' => [
        'swoole',
        'frankenphp',
        'reactphp',
        'ripple',
        'roadrunner',  // 确保包含 roadrunner
        'fpm',
    ],
    
    'runtimes' => [
        'roadrunner' => [
            'debug' => false,
            'max_jobs' => 0,           // 0 表示无限制
            'memory_limit' => '128M',  // 内存限制
        ],
        // ... 其他运行时配置
    ],
];
```

## 4. 启动 RoadRunner

### 方法1：使用 RoadRunner 命令
```bash
# 启动 RoadRunner 服务器
./roadrunner serve -c .rr.yaml

# 或者使用 vendor 中的二进制文件
./vendor/bin/rr serve -c .rr.yaml
```

### 方法2：后台运行
```bash
# 后台启动
nohup ./roadrunner serve -c .rr.yaml > /dev/null 2>&1 &

# 查看进程
ps aux | grep roadrunner

# 停止服务
pkill roadrunner
```

## 5. 验证运行

### 检查服务状态
```bash
# 检查端口是否监听
netstat -tlnp | grep 8080

# 测试 HTTP 请求
curl http://localhost:8080
```

### 查看日志
```bash
# 查看 RoadRunner 日志
tail -f ./runtime/logs/roadrunner.log

# 查看 ThinkPHP 日志
tail -f ./runtime/log/$(date +%Y%m%d).log
```

## 6. 性能优化

### RoadRunner 配置优化
```yaml
# .rr.yaml 性能优化配置
server:
  command: "php worker.php"
  relay: "pipes"
  relay_timeout: "20s"

http:
  address: 0.0.0.0:8080
  max_request_size: 10MB
  middleware: ["headers", "gzip"]
  pool:
    num_workers: 4      # 工作进程数
    max_jobs: 1000      # 每个进程最大任务数
    allocate_timeout: 60s
    destroy_timeout: 60s
```

## 7. 常见问题

### 问题1：RoadRunner 二进制文件未找到
```bash
# 解决方案：确保 RoadRunner 在 PATH 中
which roadrunner
# 或使用完整路径
/path/to/roadrunner serve -c .rr.yaml
```

### 问题2：端口被占用
```bash
# 检查端口占用
lsof -i :8080
# 修改 .rr.yaml 中的端口
```

### 问题3：权限问题
```bash
# 确保文件权限正确
chmod +x roadrunner
chmod +x worker.php
```

## 8. 监控和管理

### 使用 RoadRunner 管理命令
```bash
# 重载配置
./roadrunner reset

# 查看状态
./roadrunner status

# 查看工作进程
./roadrunner workers
```

现在您可以使用 RoadRunner 运行 ThinkPHP 应用了！
