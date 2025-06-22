# FrankenPHP Runtime 增强版

## 概述

FrankenPHP Runtime 增强版为 ThinkPHP 应用提供了完整的 FrankenPHP 支持，包括：

- 🚀 自动生成优化的 Caddyfile 配置
- 🔗 完整的 ThinkPHP URL 重写规则支持
- 📝 与 ThinkPHP 日志系统集成
- 🐛 基于 `app_debug` 环境变量的智能调试模式
- ⚡ 高性能 Worker 模式支持
- 🔒 灵活的入口文件隐藏/显示配置

## 功能特性

### 1. 智能配置检测

- **自动调试模式**: 根据 `app_debug` 环境变量自动启用/禁用调试模式
- **日志目录集成**: 自动使用 ThinkPHP 的日志目录
- **路径自动检测**: 智能检测项目根目录和文档根目录

### 2. ThinkPHP URL 重写支持

#### 隐藏入口文件模式 (默认)
```
http://localhost:8080/                    -> /index.php
http://localhost:8080/index/hello         -> /index.php (路由: index/hello)
http://localhost:8080/api/user/list       -> /index.php (路由: api/user/list)
```

#### 显示入口文件模式
```
http://localhost:8080/index.php           -> /index.php
http://localhost:8080/index.php/index/hello -> /index.php (路由: index/hello)
```

### 3. 日志集成

- **访问日志**: `{thinkphp_log_dir}/frankenphp_access.log`
- **错误日志**: `{thinkphp_log_dir}/frankenphp_error.log`
- **PHP错误日志**: `{thinkphp_log_dir}/frankenphp_php_error.log`
- **日志轮转**: 自动轮转，保留10个文件，每个最大100MB

### 4. 性能优化

- **Worker模式**: 支持多Worker进程，避免重复初始化
- **内存管理**: 自动垃圾回收和内存监控
- **请求隔离**: 每个请求间的状态完全隔离
- **静态文件优化**: 直接提供静态文件，不经过PHP处理

## 使用方法

### 1. 基本使用

```php
use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

$app = new App();
$adapter = new FrankenphpAdapter($app);

// 启动服务器
$adapter->start();
```

### 2. 自定义配置

```php
$config = [
    'listen' => ':9000',           // 监听端口
    'worker_num' => 8,             // Worker进程数
    'debug' => true,               // 调试模式 (会覆盖app_debug检测)
    'auto_https' => false,         // 禁用自动HTTPS
    'hide_index' => true,          // 隐藏入口文件
    'enable_rewrite' => true,      // 启用URL重写
    'max_requests' => 2000,        // 每个Worker最大请求数
    'env' => [                     // 自定义环境变量
        'CUSTOM_VAR' => 'value'
    ]
];

$adapter->setConfig($config);
$adapter->start();
```

### 3. 命令行使用

```bash
# 使用runtime命令
php think runtime:start frankenphp

# 使用测试脚本
php test/start_frankenphp.php --port=9000 --workers=8 --debug
```

## 配置选项

| 选项 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `listen` | string | `:8080` | 监听地址和端口 |
| `worker_num` | int | `4` | Worker进程数量 |
| `max_requests` | int | `1000` | 每个Worker最大请求数 |
| `debug` | bool | `null` | 调试模式 (null时自动检测app_debug) |
| `auto_https` | bool | `false` | 自动HTTPS |
| `hide_index` | bool | `true` | 隐藏入口文件 |
| `enable_rewrite` | bool | `true` | 启用URL重写 |
| `root` | string | `public` | 文档根目录 |
| `index` | string | `index.php` | 入口文件名 |
| `log_dir` | string | `null` | 日志目录 (null时自动检测) |

## 生成的 Caddyfile 示例

### 开发环境配置

```caddyfile
:8080 {
    root * public
    auto_https off
    
    log {
        level DEBUG
        output file runtime/log/frankenphp_access.log {
            roll_size 100mb
            roll_keep 10
        }
        format console
    }
    
    handle_errors {
        @error_log {
            expression {http.error.status_code} >= 400
        }
        log @error_log {
            output file runtime/log/frankenphp_error.log
        }
    }
    
    encode gzip zstd
    
    # 静态文件处理
    @static {
        file {
            try_files {path} {path}/
        }
    }
    handle @static {
        file_server
    }
    
    # ThinkPHP URL重写
    @thinkphp {
        not file
        not path *.css *.js *.png *.jpg *.jpeg *.gif *.ico *.svg *.woff *.woff2 *.ttf *.eot
    }
    handle @thinkphp {
        rewrite * /index.php
    }
    
    # PHP处理器
    php {
        worker frankenphp-worker.php
        worker_count 4
        restart_after 1000
        env PHP_INI_SCAN_DIR /dev/null
        env FRANKENPHP_NO_DEPRECATION_WARNINGS 1
    }
    
    file_server
}
```

## 故障排除

### 1. FrankenPHP 不可用

确保已安装 FrankenPHP:
```bash
# macOS
brew install frankenphp

# Linux
curl -fsSL https://get.frankenphp.dev | sh

# 或下载二进制文件
wget https://github.com/dunglas/frankenphp/releases/latest/download/frankenphp-linux-x86_64
```

### 2. 路由不工作

检查以下配置：
- `enable_rewrite` 是否为 `true`
- `hide_index` 配置是否正确
- ThinkPHP 路由配置是否正确

### 3. 日志问题

确保日志目录有写权限：
```bash
chmod 755 runtime/log
```

### 4. 性能问题

调整以下参数：
- 增加 `worker_num`
- 调整 `max_requests`
- 启用 OPcache

## 与其他 Runtime 的对比

| 特性 | FrankenPHP | Swoole | ReactPHP | Workerman |
|------|------------|--------|----------|-----------|
| HTTP/2 支持 | ✅ | ✅ | ❌ | ❌ |
| HTTP/3 支持 | ✅ | ❌ | ❌ | ❌ |
| 自动HTTPS | ✅ | ❌ | ❌ | ❌ |
| Worker模式 | ✅ | ✅ | ✅ | ✅ |
| 静态文件服务 | ✅ | ✅ | ✅ | ✅ |
| 配置复杂度 | 低 | 中 | 中 | 中 |
| 性能 | 高 | 很高 | 中 | 高 |

## 最佳实践

1. **开发环境**: 使用调试模式，较少的Worker数量
2. **生产环境**: 禁用调试，增加Worker数量，启用HTTPS
3. **静态文件**: 让FrankenPHP直接处理，不要通过PHP
4. **日志监控**: 定期检查错误日志
5. **内存监控**: 监控Worker内存使用情况
