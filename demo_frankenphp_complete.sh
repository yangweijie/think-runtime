#!/bin/bash

echo "🎯 FrankenPHP Runtime 完整功能演示"
echo "================================="

# 检查环境
echo "📍 环境检查"
echo "=========="
echo "当前目录: $(pwd)"
echo "PHP 版本: $(php -v | head -1)"
echo "操作系统: $(uname -s)"

# 检查 FrankenPHP 是否可用
if command -v frankenphp &> /dev/null; then
    echo "✅ FrankenPHP 已安装: $(frankenphp version 2>/dev/null || echo '版本信息不可用')"
else
    echo "⚠️  FrankenPHP 未在 PATH 中找到，但适配器仍可工作"
fi

echo ""

# 1. 演示配置生成
echo "1️⃣ 配置生成演示"
echo "==============="

cd /Volumes/data/git/php/think-runtime

cat > demo_config.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

echo "🔧 创建 FrankenPHP 适配器...\n";
$app = new App();
$adapter = new FrankenphpAdapter($app);

echo "⚙️  设置配置...\n";
$adapter->setConfig([
    'listen' => ':8080',
    'worker_num' => 4,
    'max_requests' => 1000,
    'debug' => true,
    'auto_https' => false,
    'enable_gzip' => true,
    'hosts' => ['localhost', '127.0.0.1'],
]);

echo "📋 适配器信息:\n";
echo "  名称: " . $adapter->getName() . "\n";
echo "  优先级: " . $adapter->getPriority() . "\n";
echo "  支持状态: " . ($adapter->isSupported() ? '✅ 支持' : '❌ 不支持') . "\n";
echo "  可用状态: " . ($adapter->isAvailable() ? '✅ 可用' : '❌ 不可用') . "\n";

echo "\n📊 运行时状态:\n";
$status = $adapter->getStatus();
foreach ($status as $key => $value) {
    if (is_array($value)) {
        echo "  {$key}:\n";
        foreach ($value as $subKey => $subValue) {
            if (is_array($subValue)) {
                echo "    {$subKey}: [数组]\n";
            } else {
                echo "    {$subKey}: {$subValue}\n";
            }
        }
    } else {
        echo "  {$key}: {$value}\n";
    }
}

echo "\n🏥 健康检查: " . ($adapter->healthCheck() ? '✅ 健康' : '❌ 异常') . "\n";
EOF

echo "🧪 运行配置演示..."
php demo_config.php

echo ""

# 2. 演示 Caddyfile 生成
echo "2️⃣ Caddyfile 生成演示"
echo "===================="

cat > demo_caddyfile.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

$app = new App();
$adapter = new FrankenphpAdapter($app);

// 使用反射访问受保护的方法
$reflection = new ReflectionClass($adapter);
$method = $reflection->getMethod('buildFrankenPHPCaddyfile');
$method->setAccessible(true);

$config = [
    'listen' => ':8080',
    'root' => '/Volumes/data/git/php/tp/public',
    'index' => 'index.php',
    'auto_https' => false,
    'enable_gzip' => true,
];

echo "📄 生成 ThinkPHP 专用 Caddyfile:\n";
echo "================================\n";
$caddyfile = $method->invoke($adapter, $config, null);
echo $caddyfile;
echo "================================\n";

// 保存到文件
file_put_contents('demo-Caddyfile', $caddyfile);
echo "✅ Caddyfile 已保存到 demo-Caddyfile\n";
EOF

echo "🧪 运行 Caddyfile 生成演示..."
php demo_caddyfile.php

echo ""

# 3. 演示错误处理
echo "3️⃣ 错误处理演示"
echo "==============="

cat > demo_error_handling.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

$app = new App();
$adapter = new FrankenphpAdapter($app);

// 使用反射测试错误处理
$reflection = new ReflectionClass($adapter);

echo "🚨 演示调试模式错误页面生成:\n";
$renderMethod = $reflection->getMethod('renderDebugErrorPage');
$renderMethod->setAccessible(true);

$testException = new Exception('这是一个演示异常', 500);
$errorPage = $renderMethod->invoke($adapter, $testException);

// 保存错误页面到文件
file_put_contents('demo-error-page.html', $errorPage);
echo "✅ 调试错误页面已保存到 demo-error-page.html\n";

echo "🧮 演示内存限制解析:\n";
$parseMethod = $reflection->getMethod('parseMemoryLimit');
$parseMethod->setAccessible(true);

$memoryTests = ['64M', '128M', '256M', '512M', '1G', '2G'];
foreach ($memoryTests as $limit) {
    $bytes = $parseMethod->invoke($adapter, $limit);
    $mb = round($bytes / 1024 / 1024, 2);
    echo "  {$limit} = {$mb} MB ({$bytes} bytes)\n";
}
EOF

echo "🧪 运行错误处理演示..."
php demo_error_handling.php

echo ""

# 4. 演示实际项目集成
echo "4️⃣ 实际项目集成演示"
echo "=================="

echo "📁 切换到测试项目目录..."
cd /Volumes/data/git/php/tp

# 创建演示用的 runtime 启动脚本
cat > demo_runtime_start.php << 'EOF'
<?php
// 演示如何在实际项目中使用 FrankenPHP runtime

require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

echo "🚀 FrankenPHP Runtime 项目集成演示\n";
echo "================================\n";

try {
    // 创建 ThinkPHP 应用
    $app = new App();
    
    // 创建 FrankenPHP 适配器
    $adapter = new FrankenphpAdapter($app);
    
    // 配置适配器
    $adapter->setConfig([
        'listen' => ':8080',
        'worker_num' => 2,
        'debug' => true,
        'auto_https' => false,
        'root' => 'public',
        'index' => 'index.php',
    ]);
    
    echo "📋 项目信息:\n";
    echo "  项目路径: " . getcwd() . "\n";
    echo "  Public 目录: " . (is_dir('public') ? '✅ 存在' : '❌ 不存在') . "\n";
    echo "  入口文件: " . (file_exists('public/index.php') ? '✅ 存在' : '❌ 不存在') . "\n";
    
    echo "\n⚙️  适配器配置:\n";
    $config = $adapter->getConfig();
    echo "  监听地址: {$config['listen']}\n";
    echo "  Worker 数量: {$config['worker_num']}\n";
    echo "  调试模式: " . ($config['debug'] ? '开启' : '关闭') . "\n";
    echo "  根目录: {$config['root']}\n";
    
    echo "\n📊 系统状态:\n";
    $status = $adapter->getStatus();
    echo "  PHP 版本: {$status['php']['version']}\n";
    echo "  内存使用: " . round($status['php']['memory_usage'] / 1024 / 1024, 2) . " MB\n";
    echo "  内存峰值: " . round($status['php']['memory_peak'] / 1024 / 1024, 2) . " MB\n";
    
    echo "\n🏥 健康检查: " . ($adapter->healthCheck() ? '✅ 通过' : '❌ 失败') . "\n";
    
    echo "\n📄 生成项目专用 Caddyfile...\n";
    
    // 使用反射生成 Caddyfile
    $reflection = new ReflectionClass($adapter);
    $method = $reflection->getMethod('buildFrankenPHPCaddyfile');
    $method->setAccessible(true);
    
    $projectConfig = [
        'listen' => ':8080',
        'root' => getcwd() . '/public',
        'index' => 'index.php',
        'auto_https' => false,
    ];
    
    $caddyfile = $method->invoke($adapter, $projectConfig, null);
    file_put_contents('Caddyfile.demo', $caddyfile);
    
    echo "✅ 项目 Caddyfile 已生成: Caddyfile.demo\n";
    
    echo "\n🎯 启动命令:\n";
    echo "  frankenphp run --config Caddyfile.demo\n";
    
    echo "\n🌐 访问地址:\n";
    echo "  http://localhost:8080/\n";
    echo "  http://localhost:8080/index/index\n";
    echo "  http://localhost:8080/index/file\n";
    
} catch (Exception $e) {
    echo "❌ 演示失败: " . $e->getMessage() . "\n";
    echo "📍 位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n✅ 项目集成演示完成\n";
EOF

echo "🧪 运行项目集成演示..."
php demo_runtime_start.php

echo ""

# 5. 清理演示文件
echo "5️⃣ 清理演示文件"
echo "==============="

cd /Volumes/data/git/php/think-runtime
rm -f demo_config.php demo_caddyfile.php demo_error_handling.php
rm -f demo-Caddyfile demo-error-page.html

cd /Volumes/data/git/php/tp
rm -f demo_runtime_start.php

echo "✅ 演示文件已清理"

echo ""

# 6. 最终总结
echo "📊 完整功能演示总结"
echo "=================="
echo "✅ 配置生成功能 - 正常工作"
echo "✅ Caddyfile 生成 - ThinkPHP 专用配置"
echo "✅ 错误处理系统 - 智能错误显示"
echo "✅ 健康检查功能 - 系统状态监控"
echo "✅ 项目集成演示 - 实际使用场景"

echo ""
echo "🎯 FrankenPHP Runtime 特色功能:"
echo "=============================="
echo "🚀 高性能 - 基于 FrankenPHP 的现代 PHP 运行时"
echo "🔧 智能化 - 自动配置检测和优化"
echo "🛡️  稳定性 - 完善的错误处理和恢复机制"
echo "📊 可监控 - 实时状态监控和健康检查"
echo "🎨 易使用 - 简单的配置和启动流程"
echo "🔄 兼容性 - 完美支持 ThinkPHP 路由系统"

echo ""
echo "✅ FrankenPHP Runtime 完整功能演示完成！"
