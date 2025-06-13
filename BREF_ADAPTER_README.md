# Bref Runtime Adapter for ThinkPHP

这是为 think-runtime 项目添加的 Bref 运行时适配器，让 ThinkPHP 应用能够在 AWS Lambda 上通过 bref 运行时运行。

## 功能特性

- ✅ **AWS Lambda 支持**: 专为 AWS Lambda 环境设计
- ✅ **多事件格式**: 支持 API Gateway v1.0、v2.0 和 ALB 事件格式
- ✅ **HTTP 请求处理**: 完整的 HTTP 请求/响应处理
- ✅ **自定义事件**: 支持 SQS、S3 等自定义事件处理
- ✅ **CORS 支持**: 内置跨域资源共享支持
- ✅ **错误处理**: 完善的错误处理和日志记录
- ✅ **性能监控**: 请求性能监控和慢请求记录
- ✅ **环境检测**: 自动检测 Lambda 环境并调整优先级

## 安装依赖

```bash
# 安装 bref 相关依赖
composer require runtime/bref bref/bref
```

## 配置

### 1. 运行时配置

在 `config/runtime.php` 中添加 bref 配置：

```php
'runtimes' => [
    'bref' => [
        // Lambda运行时配置
        'lambda' => [
            'timeout' => 30,
            'memory' => 512,
            'environment' => 'production',
        ],
        // HTTP处理配置
        'http' => [
            'enable_cors' => true,
            'cors_origin' => '*',
            'cors_methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'cors_headers' => 'Content-Type, Authorization, X-Requested-With',
        ],
        // 错误处理配置
        'error' => [
            'display_errors' => false,
            'log_errors' => true,
        ],
        // 性能监控配置
        'monitor' => [
            'enable' => true,
            'slow_request_threshold' => 1000, // 毫秒
        ],
    ],
],
```

### 2. Serverless 配置

创建 `serverless.yml` 文件：

```yaml
service: my-thinkphp-app

plugins:
  - ./vendor/runtime/bref-layer

provider:
  name: aws
  region: us-east-1
  runtime: provided.al2
  memorySize: 512
  timeout: 30
  environment:
    APP_ENV: prod
    APP_RUNTIME: yangweijie\thinkRuntime\adapter\BrefAdapter

functions:
  web:
    handler: public/index.php
    layers:
      - ${runtime-bref:php-81}
    events:
      - httpApi: '*'

  # 自定义事件处理函数示例
  worker:
    handler: app/lambda/handler.php
    layers:
      - ${runtime-bref:php-81}
    events:
      - sqs:
          arn: arn:aws:sqs:us-east-1:123456789012:my-queue
```

## 使用方法

### 1. 基本使用

```php
use yangweijie\thinkRuntime\adapter\BrefAdapter;
use think\App;

$app = new App();
$adapter = new BrefAdapter($app, [
    'lambda' => [
        'timeout' => 30,
        'memory' => 512,
    ],
    'http' => [
        'enable_cors' => true,
    ],
]);

// 在 Lambda 环境中会自动处理请求
$adapter->run();
```

### 2. 通过运行时管理器使用

```php
use yangweijie\thinkRuntime\runtime\RuntimeManager;
use yangweijie\thinkRuntime\config\RuntimeConfig;

$config = new RuntimeConfig([
    'default' => 'bref',
    'runtimes' => [
        'bref' => [
            'lambda' => ['timeout' => 30],
        ],
    ],
]);

$manager = new RuntimeManager($app, $config);
$manager->start('bref');
```

## 支持的事件格式

### 1. API Gateway v1.0

```json
{
  "httpMethod": "GET",
  "path": "/api/users",
  "headers": {
    "Content-Type": "application/json"
  },
  "queryStringParameters": {
    "page": "1"
  },
  "body": ""
}
```

### 2. API Gateway v2.0

```json
{
  "version": "2.0",
  "requestContext": {
    "http": {
      "method": "POST",
      "path": "/api/users"
    }
  },
  "headers": {
    "content-type": "application/json"
  },
  "body": "{\"name\":\"John\"}"
}
```

### 3. Application Load Balancer

```json
{
  "requestContext": {
    "elb": {
      "targetGroupArn": "arn:aws:elasticloadbalancing:..."
    }
  },
  "httpMethod": "GET",
  "path": "/health",
  "headers": {
    "host": "example.com"
  }
}
```

## 部署

### 1. 安装 Serverless Framework

```bash
npm install -g serverless
```

### 2. 部署应用

```bash
serverless deploy
```

### 3. 查看日志

```bash
serverless logs -f web -t
```

## 测试

### 1. 运行单元测试

```bash
./vendor/bin/pest tests/Unit/BrefAdapterUnitTest.php
```

### 2. 运行简单测试

```bash
php test_bref_adapter.php
```

### 3. 运行示例

```bash
php examples/bref_example.php
```

## 环境变量

适配器会自动检测以下 Lambda 环境变量：

- `AWS_LAMBDA_FUNCTION_NAME`: Lambda 函数名称
- `AWS_LAMBDA_FUNCTION_VERSION`: 函数版本
- `AWS_LAMBDA_FUNCTION_MEMORY_SIZE`: 内存大小
- `AWS_EXECUTION_ENV`: 执行环境
- `LAMBDA_TASK_ROOT`: 任务根目录

## 性能优化

1. **内存配置**: 根据应用需求调整 Lambda 内存大小
2. **超时设置**: 合理设置函数超时时间
3. **冷启动优化**: 使用预留并发减少冷启动
4. **依赖优化**: 减少不必要的依赖包大小

## 故障排除

### 1. 常见问题

**问题**: 适配器不支持当前环境
**解决**: 确保在 Lambda 环境中运行或安装了 bref 相关包

**问题**: 请求超时
**解决**: 增加 Lambda 函数的超时设置

**问题**: 内存不足
**解决**: 增加 Lambda 函数的内存配置

### 2. 调试

启用调试模式：

```php
$adapter = new BrefAdapter($app, [
    'error' => [
        'display_errors' => true,
        'log_errors' => true,
    ],
]);
```

## 贡献

欢迎提交 Issue 和 Pull Request 来改进这个适配器。

## 许可证

MIT License
