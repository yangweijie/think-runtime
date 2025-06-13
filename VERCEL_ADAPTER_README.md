# Vercel Runtime Adapter for ThinkPHP

这是为 think-runtime 项目添加的 Vercel 运行时适配器，让 ThinkPHP 应用能够在 Vercel 平台上作为 serverless 函数运行。

## 功能特性

- ✅ **Vercel Serverless 支持**: 专为 Vercel serverless 函数设计
- ✅ **HTTP 请求处理**: 完整的 HTTP 请求/响应处理
- ✅ **自动环境检测**: 自动检测 Vercel 环境并调整优先级
- ✅ **CORS 支持**: 内置跨域资源共享支持
- ✅ **错误处理**: 完善的错误处理和日志记录
- ✅ **性能监控**: 请求性能监控和内存使用监控
- ✅ **快速部署**: 与 Vercel 平台无缝集成
- ✅ **CDN 集成**: 静态文件由 Vercel CDN 自动处理

## 安装依赖

```bash
# 安装 vercel 相关依赖
composer require vercel/php
```

## 配置

### 1. 运行时配置

在 `config/runtime.php` 中添加 vercel 配置：

```php
'runtimes' => [
    'vercel' => [
        // Vercel函数配置
        'vercel' => [
            'timeout' => 10, // Vercel默认超时10秒
            'memory' => 1024, // 默认内存1GB
            'region' => 'auto', // 自动选择区域
            'runtime' => 'php-8.1',
        ],
        // HTTP处理配置
        'http' => [
            'enable_cors' => true,
            'cors_origin' => '*',
            'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
            'max_body_size' => '5mb', // Vercel请求体限制
        ],
        // 错误处理配置
        'error' => [
            'display_errors' => false,
            'log_errors' => true,
            'error_reporting' => E_ALL & ~E_NOTICE,
        ],
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
            'memory_threshold' => 80, // 内存使用阈值百分比
        ],
        // 静态文件配置
        'static' => [
            'enable' => false, // Vercel通常由CDN处理静态文件
            'extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg'],
        ],
    ],
],
```

### 2. Vercel 配置

创建 `vercel.json` 文件：

```json
{
  "functions": {
    "api/*.php": {
      "runtime": "vercel-php@0.6.0"
    }
  },
  "routes": [
    {
      "src": "/(.*)",
      "dest": "/api/index.php"
    }
  ],
  "env": {
    "APP_ENV": "production"
  }
}
```

### 3. 入口文件

创建 `api/index.php` 文件：

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\adapter\VercelAdapter;

// 创建 ThinkPHP 应用
$app = new App();

// 创建 Vercel 适配器
$adapter = new VercelAdapter($app, [
    'vercel' => [
        'timeout' => 10,
        'memory' => 1024,
    ],
    'http' => [
        'enable_cors' => true,
    ],
]);

// 运行应用
$adapter->run();
```

## 使用方法

### 1. 基本使用

```php
use yangweijie\thinkRuntime\adapter\VercelAdapter;
use think\App;

$app = new App();
$adapter = new VercelAdapter($app, [
    'vercel' => [
        'timeout' => 10,
        'memory' => 1024,
    ],
    'http' => [
        'enable_cors' => true,
    ],
]);

// 在 Vercel 环境中会自动处理请求
$adapter->run();
```

### 2. 通过运行时管理器使用

```php
use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

$config = new RuntimeConfig([
    'default' => 'vercel',
    'runtimes' => [
        'vercel' => [
            'vercel' => ['timeout' => 10],
        ],
    ],
]);

$manager = new RuntimeManager($app, $config);
$manager->start('vercel');
```

## 项目结构

```
your-thinkphp-app/
├── api/
│   └── index.php          # Vercel 函数入口
├── app/                   # ThinkPHP 应用目录
├── config/                # 配置文件
├── public/                # 静态资源（由 Vercel CDN 处理）
├── vendor/                # Composer 依赖
├── vercel.json           # Vercel 配置文件
└── composer.json         # Composer 配置
```

## 部署

### 1. 安装 Vercel CLI

```bash
npm install -g vercel
```

### 2. 登录 Vercel

```bash
vercel login
```

### 3. 部署应用

```bash
# 开发环境部署
vercel

# 生产环境部署
vercel --prod
```

### 4. 查看日志

```bash
vercel logs
```

## 环境变量

适配器会自动检测以下 Vercel 环境变量：

- `VERCEL`: Vercel 环境标识
- `VERCEL_ENV`: 环境类型（development、preview、production）
- `VERCEL_URL`: 部署URL
- `VERCEL_REGION`: 部署区域
- `HTTP_X_VERCEL_ID`: Vercel 请求ID

## 性能优化

1. **内存配置**: 根据应用需求调整函数内存大小
2. **超时设置**: 合理设置函数超时时间（最大10秒）
3. **冷启动优化**: 减少依赖包大小，优化应用启动时间
4. **缓存策略**: 利用 Vercel Edge Cache 缓存静态内容

## 限制说明

1. **执行时间**: 最大10秒执行时间
2. **内存限制**: 最大1GB内存
3. **请求体大小**: 最大5MB请求体
4. **并发限制**: 根据 Vercel 计划限制
5. **文件系统**: 只读文件系统，无法写入文件

## 故障排除

### 1. 常见问题

**问题**: 适配器不支持当前环境
**解决**: 确保在 Vercel 环境中运行或设置了相关环境变量

**问题**: 函数超时
**解决**: 优化代码性能，减少执行时间

**问题**: 内存不足
**解决**: 增加函数内存配置或优化内存使用

### 2. 调试

启用调试模式：

```php
$adapter = new VercelAdapter($app, [
    'error' => [
        'display_errors' => true,
        'log_errors' => true,
    ],
]);
```

查看 Vercel 日志：

```bash
vercel logs --follow
```

## 测试

### 1. 运行单元测试

```bash
./vendor/bin/pest tests/Unit/VercelAdapterUnitTest.php
```

### 2. 运行简单测试

```bash
php test_vercel_adapter.php
```

### 3. 运行示例

```bash
php examples/vercel_example.php
```

### 4. 本地开发

```bash
# 使用 Vercel CLI 本地开发
vercel dev
```

## 贡献

欢迎提交 Issue 和 Pull Request 来改进这个适配器。

## 许可证

MIT License
