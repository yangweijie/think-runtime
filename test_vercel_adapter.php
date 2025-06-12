<?php

/**
 * Vercel Adapter 简单测试脚本
 * 用于快速验证 VercelAdapter 的基本功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use think\App;
use yangweijie\thinkRuntime\adapter\VercelAdapter;

echo "=== Vercel Adapter 测试 ===\n\n";

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

// 创建 Vercel 适配器
try {
    $adapter = new VercelAdapter($mockApp, [
        'vercel' => [
            'timeout' => 10,
            'memory' => 1024,
            'region' => 'auto',
        ],
        'http' => [
            'enable_cors' => true,
            'max_body_size' => '5mb',
        ],
        'error' => [
            'display_errors' => true,
        ],
    ]);
    echo "✅ Vercel 适配器创建成功\n";
} catch (\Exception $e) {
    echo "❌ Vercel 适配器创建失败: " . $e->getMessage() . "\n";
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
echo "- Vercel 超时: " . ($config['vercel']['timeout'] ?? 'N/A') . " 秒\n";
echo "- Vercel 内存: " . ($config['vercel']['memory'] ?? 'N/A') . " MB\n";
echo "- Vercel 区域: " . ($config['vercel']['region'] ?? 'N/A') . "\n";
echo "- CORS 启用: " . ($config['http']['enable_cors'] ? '是' : '否') . "\n";
echo "- 错误显示: " . ($config['error']['display_errors'] ? '是' : '否') . "\n";
echo "- 静态文件处理: " . (($config['static']['enable'] ?? false) ? '是' : '否') . "\n";

// 测试环境检测
echo "\n测试环境检测:\n";

// 保存原始环境变量
$originalEnv = [
    'VERCEL' => $_ENV['VERCEL'] ?? null,
    'VERCEL_ENV' => $_ENV['VERCEL_ENV'] ?? null,
    'VERCEL_URL' => $_ENV['VERCEL_URL'] ?? null,
    'VERCEL_REGION' => $_ENV['VERCEL_REGION'] ?? null,
];

$originalServer = [
    'HTTP_X_VERCEL_ID' => $_SERVER['HTTP_X_VERCEL_ID'] ?? null,
];

// 测试非 Vercel 环境
unset($_ENV['VERCEL']);
unset($_ENV['VERCEL_ENV']);
unset($_ENV['VERCEL_URL']);
unset($_SERVER['HTTP_X_VERCEL_ID']);

$adapter1 = new VercelAdapter($mockApp, []);
echo "- 非 Vercel 环境优先级: " . $adapter1->getPriority() . "\n";
echo "- 非 Vercel 环境支持: " . ($adapter1->isSupported() ? '是' : '否') . "\n";

// 测试 Vercel 环境
$_ENV['VERCEL'] = '1';
$_ENV['VERCEL_ENV'] = 'production';
$_ENV['VERCEL_URL'] = 'my-app.vercel.app';
$_ENV['VERCEL_REGION'] = 'iad1';
$_SERVER['HTTP_X_VERCEL_ID'] = 'iad1::12345';

$adapter2 = new VercelAdapter($mockApp, []);
echo "- Vercel 环境优先级: " . $adapter2->getPriority() . "\n";
echo "- Vercel 环境支持: " . ($adapter2->isSupported() ? '是' : '否') . "\n";

// 测试请求头解析
echo "\n测试请求头解析:\n";

// 设置模拟的请求头
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
$_SERVER['HTTP_X_CUSTOM_HEADER'] = 'custom-value';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['CONTENT_LENGTH'] = '100';

$reflection = new ReflectionClass($adapter2);
$method = $reflection->getMethod('getRequestHeaders');
$method->setAccessible(true);

$headers = $method->invoke($adapter2);

echo "- Content-Type: " . ($headers['content-type'] ?? 'N/A') . "\n";
echo "- Authorization: " . ($headers['authorization'] ?? 'N/A') . "\n";
echo "- X-Custom-Header: " . ($headers['x-custom-header'] ?? 'N/A') . "\n";
echo "- Content-Length: " . ($headers['content-length'] ?? 'N/A') . "\n";

// 测试内存限制解析
echo "\n测试内存限制解析:\n";

$parseMethod = $reflection->getMethod('parseMemoryLimit');
$parseMethod->setAccessible(true);

$testCases = ['128M', '1G', '512K', '1048576'];
foreach ($testCases as $testCase) {
    $result = $parseMethod->invoke($adapter2, $testCase);
    echo "- {$testCase} = " . number_format($result) . " bytes\n";
}

// 测试Vercel请求数据获取
echo "\n测试Vercel请求数据获取:\n";

// 模拟请求数据
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/test?param=value';
$_GET['param'] = 'value';

$getDataMethod = $reflection->getMethod('getVercelRequestData');
$getDataMethod->setAccessible(true);

$requestData = $getDataMethod->invoke($adapter2);

echo "- 请求方法: " . ($requestData['method'] ?? 'N/A') . "\n";
echo "- 请求URI: " . ($requestData['uri'] ?? 'N/A') . "\n";
echo "- 查询参数: " . json_encode($requestData['query'] ?? []) . "\n";
echo "- 头信息数量: " . count($requestData['headers'] ?? []) . "\n";

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

foreach ($originalServer as $key => $value) {
    if ($value !== null) {
        $_SERVER[$key] = $value;
    } else {
        unset($_SERVER[$key]);
    }
}

// 清理测试用的服务器变量
unset($_SERVER['HTTP_CONTENT_TYPE']);
unset($_SERVER['HTTP_AUTHORIZATION']);
unset($_SERVER['HTTP_X_CUSTOM_HEADER']);
unset($_SERVER['CONTENT_TYPE']);
unset($_SERVER['CONTENT_LENGTH']);
unset($_SERVER['REQUEST_METHOD']);
unset($_SERVER['REQUEST_URI']);
unset($_GET['param']);

echo "\n=== 测试完成 ===\n";
echo "\n使用说明:\n";
echo "1. 这个适配器专为 Vercel serverless 函数设计\n";
echo "2. 在 Vercel 环境中会自动获得高优先级 (180)\n";
echo "3. 支持标准的 HTTP 请求/响应处理\n";
echo "4. 内置 CORS 支持和错误处理\n";
echo "5. 支持性能监控和内存使用监控\n";
echo "6. 静态文件通常由 Vercel CDN 处理\n\n";

echo "要在 Vercel 中使用，请:\n";
echo "1. 安装依赖: composer require vercel/php\n";
echo "2. 配置 vercel.json\n";
echo "3. 创建 api/index.php 入口文件\n";
echo "4. 部署: vercel --prod\n\n";
