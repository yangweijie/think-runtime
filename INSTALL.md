# ThinkPHP Runtime 安装指南

## 快速安装

### 1. 系统要求

- PHP >= 8.0
- ThinkPHP >= 8.0
- Composer

### 2. 安装步骤

```bash
# 在你的ThinkPHP项目根目录下运行
composer require yangweijie/think-runtime
```

### 3. 验证安装

```bash
# 检查运行时信息
php think runtime:info

# 如果看到运行时信息，说明安装成功
```

## 常见问题解决

### 问题1: 安装后没有runtime命令

**原因**: ThinkPHP服务发现机制没有正确工作

**解决方案**:
```bash
# 方案1: 重新发现服务
php think service:discover
php think clear

# 方案2: 手动注册服务和命令
php vendor/yangweijie/think-runtime/test-thinkphp-commands.php

# 方案3: 检查配置文件
php think list | grep runtime
```

### 问题2: 命令不存在

**现象**: 运行 `php think runtime:info` 时提示命令不存在

**解决方案**:
```bash
# 1. 确保服务已注册
php think service:discover

# 2. 清除缓存
php think clear

# 3. 检查配置
php think config:cache
```

### 问题3: 配置文件未找到

**解决方案**:
```bash
# 复制配置文件到项目
cp vendor/yangweijie/think-runtime/config/runtime.php config/

# 或者手动创建 config/runtime.php
```

### 问题4: 运行时不可用

**现象**: 运行 `php think runtime:start` 时提示运行时不可用

**解决方案**:

1. **Swoole运行时**:
```bash
# 安装Swoole扩展
pecl install swoole

# 或者使用包管理器
# Ubuntu/Debian
sudo apt-get install php-swoole

# CentOS/RHEL
sudo yum install php-swoole
```

2. **FrankenPHP运行时**:
```bash
# 安装FrankenPHP
composer require dunglas/frankenphp
```

3. **ReactPHP运行时**:
```bash
# 安装ReactPHP
composer require react/http react/socket
```

4. **Ripple运行时**:
```bash
# 安装Ripple (需要PHP 8.1+)
composer require cloudtay/ripple
```

5. **RoadRunner运行时**:
```bash
# 安装RoadRunner
composer require spiral/roadrunner spiral/roadrunner-http
```

## 手动验证安装

如果自动安装有问题，可以使用我们提供的检查脚本：

```bash
# 在think-runtime包目录下运行
php install-check.php
```

这个脚本会检查：
- PHP版本和扩展
- 核心类加载情况
- 配置文件有效性
- 运行时适配器可用性

## 配置文件

安装后，你需要在ThinkPHP项目的 `config` 目录下创建 `runtime.php` 配置文件：

```php
<?php
return [
    // 默认运行时
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
        // 其他运行时配置...
    ],
];
```

## 测试安装

```bash
# 1. 查看运行时信息
php think runtime:info

# 2. 启动默认运行时
php think runtime:start

# 3. 启动指定运行时
php think runtime:start swoole --host=127.0.0.1 --port=8080
```

## 获取帮助

如果遇到其他问题：

1. 查看 [README.md](README.md) 获取详细文档
2. 检查 [GitHub Issues](https://github.com/yangweijie/think-runtime/issues)
3. 提交新的Issue描述你的问题

## 卸载

如果需要卸载：

```bash
composer remove yangweijie/think-runtime
```

记得删除配置文件：
```bash
rm config/runtime.php
```
