<?php

/**
 * Bref Adapter 简单测试脚本
 * 用于快速验证 BrefAdapter 的基本功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\adapter\BrefAdapter;

echo "=== Bref Adapter 测试 ===\n\n";

// 创建模拟的 ThinkPHP 应用
class MockApp extends App
{
    public function initialize()
    {
        // 简化的初始化，避免依赖问题
        return $this;
    }
}

$mockApp = new MockApp();

try {
    $mockApp->initialize();
    echo "✅ 模拟 ThinkPHP 应用创建成功\n";
} catch (\Exception $e) {
    echo "❌ 模拟应用创建失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 创建 Bref 适配器
try {
    $adapter = new BrefAdapter($mockApp, [
        'lambda' => [
            'timeout' => 30,
            'memory' => 512,
        ],
        'http' => [
            'enable_cors' => true,
        ],
        'error' => [
            'display_errors' => true,
        ],
    ]);
    echo "✅ Bref 适配器创建成功\n";
} catch (\Exception $e) {
    echo "❌ Bref 适配器创建失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 测试适配器方法
echo "\n测试适配器方法:\n";

// 测试 getName
echo "- 适配器名称: " . $adapter->getName() . "\n";

// 测试 isSupported
if ($adapter->isSupported()) {
    echo "✅ 适配器支持当前环境\n";
} else {
    echo "❌ 适配器不支持当前环境\n";
}

// 测试 isAvailable
if ($adapter->isAvailable()) {
    echo "✅ 适配器在当前环境可用\n";
} else {
    echo "❌ 适配器在当前环境不可用\n";
}

// 测试 getPriority
echo "- 适配器优先级: " . $adapter->getPriority() . "\n";

// 测试配置
echo "\n测试配置:\n";
$config = $adapter->getConfig();
echo "- Lambda 超时: " . ($config['lambda']['timeout'] ?? 'N/A') . " 秒\n";
echo "- Lambda 内存: " . ($config['lambda']['memory'] ?? 'N/A') . " MB\n";
echo "- CORS 启用: " . ($config['http']['enable_cors'] ? '是' : '否') . "\n";
echo "- 错误显示: " . ($config['error']['display_errors'] ? '是' : '否') . "\n";

// 测试环境检测
echo "\n测试环境检测:\n";

// 保存原始环境变量
$originalEnv = [
    'AWS_LAMBDA_FUNCTION_NAME' => $_ENV['AWS_LAMBDA_FUNCTION_NAME'] ?? null,
    'LAMBDA_TASK_ROOT' => $_ENV['LAMBDA_TASK_ROOT'] ?? null,
    'AWS_EXECUTION_ENV' => $_ENV['AWS_EXECUTION_ENV'] ?? null,
];

// 测试非 Lambda 环境
unset($_ENV['AWS_LAMBDA_FUNCTION_NAME']);
unset($_ENV['LAMBDA_TASK_ROOT']);
unset($_ENV['AWS_EXECUTION_ENV']);

$adapter1 = new BrefAdapter($mockApp, []);
echo "- 非 Lambda 环境优先级: " . $adapter1->getPriority() . "\n";
echo "- 非 Lambda 环境支持: " . ($adapter1->isSupported() ? '是' : '否') . "\n";

// 测试 Lambda 环境
$_ENV['AWS_LAMBDA_FUNCTION_NAME'] = 'test-function';
$_ENV['AWS_LAMBDA_FUNCTION_VERSION'] = '$LATEST';
$_ENV['AWS_LAMBDA_FUNCTION_MEMORY_SIZE'] = '512';
$_ENV['AWS_EXECUTION_ENV'] = 'AWS_Lambda_php81';

$adapter2 = new BrefAdapter($mockApp, []);
echo "- Lambda 环境优先级: " . $adapter2->getPriority() . "\n";
echo "- Lambda 环境支持: " . ($adapter2->isSupported() ? '是' : '否') . "\n";

// 测试事件格式检测
echo "\n测试事件格式检测:\n";

$reflection = new ReflectionClass($adapter2);
$method = $reflection->getMethod('isHttpLambdaEvent');
$method->setAccessible(true);

// API Gateway v1.0 事件
$apiGatewayV1Event = [
    'httpMethod' => 'GET',
    'path' => '/api/test',
    'headers' => ['Content-Type' => 'application/json'],
    'queryStringParameters' => ['param1' => 'value1'],
    'body' => '',
];

echo "- API Gateway v1.0 事件检测: " . ($method->invoke($adapter2, $apiGatewayV1Event) ? '✅ HTTP事件' : '❌ 非HTTP事件') . "\n";

// API Gateway v2.0 事件
$apiGatewayV2Event = [
    'version' => '2.0',
    'requestContext' => [
        'http' => [
            'method' => 'POST',
            'path' => '/api/test',
        ],
    ],
    'headers' => ['content-type' => 'application/json'],
    'body' => '{"test": "data"}',
];

echo "- API Gateway v2.0 事件检测: " . ($method->invoke($adapter2, $apiGatewayV2Event) ? '✅ HTTP事件' : '❌ 非HTTP事件') . "\n";

// ALB 事件
$albEvent = [
    'requestContext' => [
        'elb' => [
            'targetGroupArn' => 'arn:aws:elasticloadbalancing:us-east-1:123456789012:targetgroup/my-target-group/1234567890123456',
        ],
    ],
    'httpMethod' => 'GET',
    'path' => '/health',
    'headers' => ['host' => 'example.com'],
];

echo "- ALB 事件检测: " . ($method->invoke($adapter2, $albEvent) ? '✅ HTTP事件' : '❌ 非HTTP事件') . "\n";

// SQS 事件（非HTTP）
$sqsEvent = [
    'Records' => [
        [
            'eventSource' => 'aws:sqs',
            'body' => 'test message',
            'messageId' => '12345',
        ],
    ],
];

echo "- SQS 事件检测: " . ($method->invoke($adapter2, $sqsEvent) ? '❌ 错误检测为HTTP事件' : '✅ 正确识别为非HTTP事件') . "\n";

// 测试启动（在支持的环境中）
echo "\n测试启动:\n";
try {
    ob_start();
    $adapter2->boot();
    $output = ob_get_clean();
    echo "✅ 适配器启动成功\n";
} catch (\Exception $e) {
    echo "❌ 适配器启动失败: " . $e->getMessage() . "\n";
}

// 恢复原始环境变量
foreach ($originalEnv as $key => $value) {
    if ($value !== null) {
        $_ENV[$key] = $value;
    } else {
        unset($_ENV[$key]);
    }
}

echo "\n=== 测试完成 ===\n";
echo "\n使用说明:\n";
echo "1. 这个适配器专为 AWS Lambda 环境设计\n";
echo "2. 在 Lambda 环境中会自动获得最高优先级 (200)\n";
echo "3. 支持 API Gateway v1.0、v2.0 和 ALB 事件格式\n";
echo "4. 可以处理 HTTP 请求和自定义事件\n";
echo "5. 内置 CORS 支持和错误处理\n";
echo "6. 支持性能监控和慢请求记录\n\n";

echo "要在 AWS Lambda 中使用，请:\n";
echo "1. 安装 bref: composer require runtime/bref bref/bref\n";
echo "2. 配置 serverless.yml\n";
echo "3. 部署: serverless deploy\n\n";
