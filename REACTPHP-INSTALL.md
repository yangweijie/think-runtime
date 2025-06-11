# ReactPHP Runtime 安装指南

## 🐛 遇到的问题

```
[Error]                                     
Class "RingCentral\Psr7\Request" not found  
```

## 🔍 问题原因

ReactPHP HTTP 组件内部使用 `RingCentral\Psr7` 作为 PSR-7 实现，但是这个依赖没有被正确安装。

## ✅ 解决方案

### 方案1: 完整安装 ReactPHP 依赖

在您的 ThinkPHP 项目中运行：

```bash
# 安装 ReactPHP 核心组件
composer require react/http react/socket react/promise

# 安装 PSR-7 实现（ReactPHP 内部使用）
composer require ringcentral/psr7

# 可选：安装其他 ReactPHP 组件
composer require react/stream react/dns
```

### 方案2: 一键安装脚本

创建 `install-reactphp.php` 脚本：

```php
<?php
echo "安装 ReactPHP Runtime 依赖...\n";

$packages = [
    'react/http',
    'react/socket', 
    'react/promise',
    'ringcentral/psr7'
];

foreach ($packages as $package) {
    echo "安装 {$package}...\n";
    $result = shell_exec("composer require {$package} 2>&1");
    if (strpos($result, 'Installation failed') !== false) {
        echo "❌ {$package} 安装失败\n";
        echo $result . "\n";
    } else {
        echo "✅ {$package} 安装成功\n";
    }
}

echo "\n检查安装结果...\n";
$classes = [
    'React\\Http\\HttpServer',
    'React\\Socket\\SocketServer',
    'React\\Promise\\Promise',
    'RingCentral\\Psr7\\Request'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "✅ {$class} 可用\n";
    } else {
        echo "❌ {$class} 不可用\n";
    }
}
```

### 方案3: 手动检查和修复

```bash
# 1. 检查当前安装的包
composer show | grep react
composer show | grep psr7

# 2. 如果缺少包，逐个安装
composer require react/http
composer require react/socket
composer require react/promise
composer require ringcentral/psr7

# 3. 更新自动加载
composer dump-autoload

# 4. 测试 ReactPHP
php -r "
if (class_exists('React\\Http\\HttpServer')) {
    echo 'ReactPHP HTTP Server: ✅ 可用\n';
} else {
    echo 'ReactPHP HTTP Server: ❌ 不可用\n';
}

if (class_exists('RingCentral\\Psr7\\Request')) {
    echo 'RingCentral PSR-7: ✅ 可用\n';
} else {
    echo 'RingCentral PSR-7: ❌ 不可用\n';
}
"
```

## 📋 完整的依赖列表

ReactPHP Runtime 需要以下包：

### 必需依赖
- `react/http` - HTTP 服务器组件
- `react/socket` - Socket 服务器组件  
- `react/promise` - Promise 实现
- `ringcentral/psr7` - PSR-7 HTTP 消息实现

### 可选依赖
- `react/stream` - 流处理组件
- `react/dns` - DNS 解析组件
- `react/filesystem` - 文件系统组件

## 🧪 验证安装

创建测试脚本 `test-reactphp.php`：

```php
<?php
require_once 'vendor/autoload.php';

echo "ReactPHP Runtime 依赖检查\n";
echo "========================\n\n";

$required = [
    'React\\EventLoop\\Loop' => 'ReactPHP 事件循环',
    'React\\Http\\HttpServer' => 'ReactPHP HTTP 服务器',
    'React\\Socket\\SocketServer' => 'ReactPHP Socket 服务器',
    'React\\Http\\Message\\Response' => 'ReactPHP HTTP 响应',
    'React\\Promise\\Promise' => 'ReactPHP Promise',
    'RingCentral\\Psr7\\Request' => 'RingCentral PSR-7 请求',
    'RingCentral\\Psr7\\Response' => 'RingCentral PSR-7 响应',
];

$allOk = true;
foreach ($required as $class => $desc) {
    if (class_exists($class)) {
        echo "✅ {$desc}: {$class}\n";
    } else {
        echo "❌ {$desc}: {$class}\n";
        $allOk = false;
    }
}

echo "\n";
if ($allOk) {
    echo "✅ 所有依赖都已正确安装！\n";
    echo "现在可以使用 ReactPHP Runtime:\n";
    echo "php think runtime:start reactphp\n";
} else {
    echo "❌ 部分依赖缺失，请按照上述方案安装\n";
}
```

## 🚀 使用 ReactPHP Runtime

安装完成后，可以这样使用：

```bash
# 启动 ReactPHP 服务器
php think runtime:start reactphp

# 指定参数启动
php think runtime:start reactphp --host=127.0.0.1 --port=8080

# 查看运行时信息
php think runtime:info
```

## ⚠️ 常见问题

### 1. 版本冲突
如果遇到版本冲突，尝试：
```bash
composer update --with-dependencies
```

### 2. 内存不足
ReactPHP 是事件驱动的，内存使用较低，但如果遇到内存问题：
```bash
php -d memory_limit=512M think runtime:start reactphp
```

### 3. 端口占用
确保指定的端口没有被占用：
```bash
# 检查端口
lsof -i :8080

# 或使用其他端口
php think runtime:start reactphp --port=8081
```

## 📚 更多信息

- [ReactPHP 官方文档](https://reactphp.org/)
- [ReactPHP HTTP 组件](https://github.com/reactphp/http)
- [RingCentral PSR-7](https://github.com/ringcentral/psr7)

## 🎯 总结

ReactPHP Runtime 的核心问题是缺少 `ringcentral/psr7` 包。按照上述方案安装完整的依赖后，就可以正常使用 ReactPHP 事件驱动的异步 HTTP 服务器了。
