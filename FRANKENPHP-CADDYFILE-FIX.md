# FrankenPHP Caddyfile 配置修复

## 🐛 遇到的问题

用户在启动 FrankenPHP 时遇到 Caddyfile 配置错误：

```
Error: adapting config using caddyfile: parsing caddyfile tokens for 'php_server': unknown 'php or php_server' subdirective: 'worker_num' (allowed directives are: root, split, env, resolve_root_symlink, worker)

[RuntimeException]                     
FrankenPHP process exited with code 1  
```

## 🔍 问题分析

### 根本原因
1. **错误的指令名**: 使用了 `worker_num` 而不是 `worker`
2. **不支持的指令**: 使用了 `max_requests` 等 FrankenPHP 不支持的指令
3. **配置语法错误**: Caddyfile 生成的语法不符合 FrankenPHP 规范

### 错误的配置
```caddyfile
localhost:8080 {
    root * public
    php_server {
        index index.php
        worker_num 4        # ❌ 错误: 应该是 'worker'
        max_requests 1000   # ❌ 错误: FrankenPHP 不支持此指令
    }
    tls off
}
```

## ✅ 修复方案

### 1. 修正指令名称
```php
// 修复前
if ($config['worker_num'] > 0) {
    $caddyfile .= "        worker_num {$config['worker_num']}\n";  // ❌ 错误
}

// 修复后
if ($config['worker_num'] > 0) {
    $caddyfile .= "        worker {$config['worker_num']}\n";     // ✅ 正确
}
```

### 2. 移除不支持的指令
```php
// 移除了这些不支持的配置
// if ($config['max_requests'] > 0) {
//     $caddyfile .= "        max_requests {$config['max_requests']}\n";
// }
```

### 3. 简化配置结构
```php
// 根据是否启用Worker模式选择不同的配置
if ($config['worker_num'] > 0) {
    // Worker模式配置
    $caddyfile .= "    php_server {\n";
    $caddyfile .= "        worker {$config['worker_num']}\n";
    $caddyfile .= "    }\n";
} else {
    // 标准模式配置
    $caddyfile .= "    php_server\n";
}
```

## 🎯 修复后的正确配置

### 基本模式
```caddyfile
localhost:8080 {
    root * public
    php_server
    tls off
}
```

### Worker 模式
```caddyfile
localhost:8080 {
    root * public
    php_server {
        worker 4
    }
    tls off
    log {
        level DEBUG
    }
}
```

### 生产环境
```caddyfile
example.com {
    root * public
    php_server {
        worker 8
    }
    tls internal
}
```

## 📋 FrankenPHP 支持的指令

根据错误信息，FrankenPHP 的 `php_server` 指令支持以下子指令：

- `root`: 设置 PHP 脚本根目录
- `split`: 设置 PATH_INFO 分割
- `env`: 设置环境变量
- `resolve_root_symlink`: 解析根目录符号链接
- `worker`: 启用 Worker 模式并设置 Worker 数量

## 🧪 测试验证

创建了 `test-frankenphp-caddyfile.php` 测试脚本，验证：

- ✅ 基本模式配置生成
- ✅ Worker 模式配置生成
- ✅ 生产环境配置生成
- ✅ Caddyfile 语法检查
- ✅ FrankenPHP 二进制文件查找

测试结果：
```
✅ Caddyfile语法检查通过
✅ 找到FrankenPHP二进制文件: /usr/local/bin/frankenphp
版本信息: FrankenPHP 1.7.0 PHP 8.4.8 Caddy v2.10.0
```

## 🚀 现在可以正常使用

### 启动命令
```bash
# 基本启动
php think runtime:start frankenphp

# 指定参数启动
php think runtime:start frankenphp --host=localhost --port=8080

# 启用调试模式
php think runtime:start frankenphp --debug
```

### 生成的文件
启动时会在项目根目录生成 `Caddyfile.runtime` 文件，包含正确的配置。

### 预期输出
```
FrankenPHP Server starting...
Listening on: localhost:8080
Document root: public
Workers: 4
Execution time: Unlimited
Memory limit: 512M
Mode: External FrankenPHP Process
Press Ctrl+C to stop the server

Created Caddyfile: /path/to/project/Caddyfile.runtime
Starting FrankenPHP process...

2025/06/13 06:22:22.947 INFO    using config from file
# 服务器正常启动，没有配置错误
```

## 📚 相关文件

### 修改的文件
- `src/adapter/FrankenphpAdapter.php` - 修复 Caddyfile 生成逻辑

### 新增的文件
- `test-frankenphp-caddyfile.php` - Caddyfile 生成测试脚本
- `FRANKENPHP-CADDYFILE-FIX.md` - 修复总结文档

## 🎉 修复效果

- ✅ 解决了 `worker_num` 指令错误
- ✅ 移除了不支持的指令
- ✅ 生成正确的 Caddyfile 语法
- ✅ 支持基本模式和 Worker 模式
- ✅ FrankenPHP 可以正常启动
- ✅ 提供了完整的测试验证

现在 FrankenPHP Runtime 可以正确生成 Caddyfile 配置并成功启动服务器！
