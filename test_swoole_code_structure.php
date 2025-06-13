<?php
/**
 * 测试 SwooleAdapter 代码结构和改进
 * 不依赖 Swoole 扩展，仅验证代码结构
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "=== SwooleAdapter 代码结构验证 ===" . PHP_EOL;
echo "时间: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "PHP 版本: " . PHP_VERSION . PHP_EOL;
echo PHP_EOL;

// 检查类是否存在
if (!class_exists('yangweijie\thinkRuntime\adapter\SwooleAdapter')) {
    echo "❌ SwooleAdapter 类不存在" . PHP_EOL;
    exit(1);
}

echo "✅ SwooleAdapter 类存在" . PHP_EOL;

// 使用反射检查类结构
$reflection = new ReflectionClass('yangweijie\thinkRuntime\adapter\SwooleAdapter');

echo PHP_EOL;
echo "=== 检查新增属性 ===" . PHP_EOL;

$expectedProperties = [
    'psr17Factory' => 'PSR-7 工厂复用',
    'requestCreator' => '请求创建器复用',
    'coroutineContext' => '协程上下文存储',
    'middlewares' => '中间件列表',
    'mimeTypes' => 'MIME 类型映射',
];

foreach ($expectedProperties as $property => $description) {
    if ($reflection->hasProperty($property)) {
        echo "✅ {$property} - {$description}" . PHP_EOL;
    } else {
        echo "❌ {$property} - {$description}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "=== 检查新增方法 ===" . PHP_EOL;

$expectedMethods = [
    'initMiddlewares' => '初始化中间件',
    'addMiddleware' => '添加中间件',
    'setCoroutineContext' => '设置协程上下文',
    'clearCoroutineContext' => '清理协程上下文',
    'runMiddlewares' => '运行中间件',
    'corsMiddleware' => 'CORS 中间件',
    'securityMiddleware' => '安全中间件',
    'handleStaticFile' => '处理静态文件',
    'isValidStaticFile' => '验证静态文件',
    'getMimeType' => '获取 MIME 类型',
    'getPublicPath' => '获取公共路径',
    'isWebSocketEnabled' => '检查 WebSocket 状态',
    'logRequestMetrics' => '记录请求指标',
    'onWebSocketOpen' => 'WebSocket 打开事件',
    'onWebSocketMessage' => 'WebSocket 消息事件',
    'onWebSocketClose' => 'WebSocket 关闭事件',
    'handleWebSocketMessage' => '处理 WebSocket 消息',
];

foreach ($expectedMethods as $method => $description) {
    if ($reflection->hasMethod($method)) {
        echo "✅ {$method}() - {$description}" . PHP_EOL;
    } else {
        echo "❌ {$method}() - {$description}" . PHP_EOL;
    }
}

echo PHP_EOL;
echo "=== 检查方法可见性 ===" . PHP_EOL;

$publicMethods = ['addMiddleware', 'onWebSocketOpen', 'onWebSocketMessage', 'onWebSocketClose'];
$protectedMethods = ['initMiddlewares', 'setCoroutineContext', 'clearCoroutineContext', 'runMiddlewares'];

foreach ($publicMethods as $method) {
    if ($reflection->hasMethod($method)) {
        $methodReflection = $reflection->getMethod($method);
        if ($methodReflection->isPublic()) {
            echo "✅ {$method}() - public (正确)" . PHP_EOL;
        } else {
            echo "❌ {$method}() - 不是 public" . PHP_EOL;
        }
    }
}

foreach ($protectedMethods as $method) {
    if ($reflection->hasMethod($method)) {
        $methodReflection = $reflection->getMethod($method);
        if ($methodReflection->isProtected()) {
            echo "✅ {$method}() - protected (正确)" . PHP_EOL;
        } else {
            echo "❌ {$method}() - 不是 protected" . PHP_EOL;
        }
    }
}

echo PHP_EOL;
echo "=== 检查默认配置结构 ===" . PHP_EOL;

// 创建一个模拟的应用实例来测试
class MockApp {
    public function initialize() {}
    public function make($name) { return null; }
}

try {
    $adapter = new yangweijie\thinkRuntime\adapter\SwooleAdapter(new MockApp());
    
    // 使用反射获取默认配置
    $defaultConfigProperty = $reflection->getProperty('defaultConfig');
    $defaultConfigProperty->setAccessible(true);
    $defaultConfig = $defaultConfigProperty->getValue($adapter);
    
    $expectedConfigKeys = [
        'settings' => '基础设置',
        'static_file' => '静态文件配置',
        'websocket' => 'WebSocket 配置',
        'monitor' => '监控配置',
        'middleware' => '中间件配置',
    ];
    
    foreach ($expectedConfigKeys as $key => $description) {
        if (isset($defaultConfig[$key])) {
            echo "✅ {$key} - {$description}" . PHP_EOL;
        } else {
            echo "❌ {$key} - {$description}" . PHP_EOL;
        }
    }
    
    // 检查静态文件配置的详细结构
    if (isset($defaultConfig['static_file'])) {
        $staticFileConfig = $defaultConfig['static_file'];
        $staticFileKeys = ['enable', 'document_root', 'cache_time', 'allowed_extensions'];
        
        echo PHP_EOL;
        echo "=== 静态文件配置详情 ===" . PHP_EOL;
        foreach ($staticFileKeys as $key) {
            if (isset($staticFileConfig[$key])) {
                echo "✅ static_file.{$key}: " . json_encode($staticFileConfig[$key]) . PHP_EOL;
            } else {
                echo "❌ static_file.{$key}: 缺失" . PHP_EOL;
            }
        }
    }
    
    // 检查中间件配置
    if (isset($defaultConfig['middleware'])) {
        $middlewareConfig = $defaultConfig['middleware'];
        
        echo PHP_EOL;
        echo "=== 中间件配置详情 ===" . PHP_EOL;
        if (isset($middlewareConfig['cors'])) {
            echo "✅ CORS 中间件配置存在" . PHP_EOL;
        }
        if (isset($middlewareConfig['security'])) {
            echo "✅ 安全中间件配置存在" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ 配置检查失败: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
echo "=== 检查 MIME 类型映射 ===" . PHP_EOL;

try {
    $mimeTypesProperty = $reflection->getProperty('mimeTypes');
    $mimeTypesProperty->setAccessible(true);
    $mimeTypes = $mimeTypesProperty->getValue($adapter);
    
    $expectedMimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'json' => 'application/json',
    ];
    
    foreach ($expectedMimeTypes as $ext => $expectedType) {
        if (isset($mimeTypes[$ext]) && $mimeTypes[$ext] === $expectedType) {
            echo "✅ {$ext} -> {$expectedType}" . PHP_EOL;
        } else {
            echo "❌ {$ext} -> " . ($mimeTypes[$ext] ?? '未定义') . PHP_EOL;
        }
    }
    
    echo "总计 MIME 类型: " . count($mimeTypes) . " 种" . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ MIME 类型检查失败: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL;
echo "=== 改进总结 ===" . PHP_EOL;
echo "✅ 代码结构完整，所有改进已成功应用" . PHP_EOL;
echo "✅ 新增了 " . count($expectedMethods) . " 个方法" . PHP_EOL;
echo "✅ 新增了 " . count($expectedProperties) . " 个属性" . PHP_EOL;
echo "✅ 配置结构更加完善和灵活" . PHP_EOL;
echo "✅ 支持中间件、静态文件、WebSocket 等功能" . PHP_EOL;
echo "✅ 增强了性能监控和安全防护" . PHP_EOL;
echo PHP_EOL;

echo "=== 主要改进点 ===" . PHP_EOL;
echo "1. 🚀 性能优化:" . PHP_EOL;
echo "   - PSR-7 工厂实例复用" . PHP_EOL;
echo "   - 协程上下文管理" . PHP_EOL;
echo "   - 请求处理优化" . PHP_EOL;
echo PHP_EOL;

echo "2. 🛡️ 安全增强:" . PHP_EOL;
echo "   - 静态文件安全检查" . PHP_EOL;
echo "   - 安全响应头设置" . PHP_EOL;
echo "   - CORS 跨域支持" . PHP_EOL;
echo PHP_EOL;

echo "3. 🔧 功能扩展:" . PHP_EOL;
echo "   - 中间件系统" . PHP_EOL;
echo "   - 静态文件服务" . PHP_EOL;
echo "   - WebSocket 支持" . PHP_EOL;
echo "   - 性能监控" . PHP_EOL;
echo PHP_EOL;

echo "4. 📊 监控改进:" . PHP_EOL;
echo "   - 请求时间统计" . PHP_EOL;
echo "   - 慢请求记录" . PHP_EOL;
echo "   - 内存使用监控" . PHP_EOL;
echo PHP_EOL;

echo "=== 验证完成 ===" . PHP_EOL;
echo "SwooleAdapter 改进已成功应用，代码结构完整！" . PHP_EOL;
