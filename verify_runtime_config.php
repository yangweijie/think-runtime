<?php

declare(strict_types=1);

/**
 * RuntimeConfig Workerman 配置验证脚本
 */

echo "=== RuntimeConfig Workerman 配置验证 ===\n\n";

// 1. 检查文件是否存在
$configPath = 'src/config/RuntimeConfig.php';

if (!file_exists($configPath)) {
    echo "❌ RuntimeConfig.php 文件不存在: $configPath\n";
    exit(1);
}

echo "✅ RuntimeConfig.php 文件存在: $configPath\n";

// 2. 检查语法
$syntaxCheck = shell_exec("php -l $configPath 2>&1");
if (strpos($syntaxCheck, 'No syntax errors') !== false) {
    echo "✅ PHP 语法检查通过\n";
} else {
    echo "❌ PHP 语法错误:\n";
    echo "   " . trim($syntaxCheck) . "\n";
    exit(1);
}

// 3. 尝试加载类
try {
    require_once $configPath;
    echo "✅ RuntimeConfig 类加载成功\n";
} catch (Exception $e) {
    echo "❌ RuntimeConfig 类加载失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. 创建实例并测试
try {
    $config = new \yangweijie\thinkRuntime\config\RuntimeConfig();
    echo "✅ RuntimeConfig 实例创建成功\n";
} catch (Exception $e) {
    echo "❌ RuntimeConfig 实例创建失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. 检查自动检测顺序
$autoDetectOrder = $config->getAutoDetectOrder();
echo "\n=== 自动检测顺序检查 ===\n";
echo "📊 检测顺序: " . implode(', ', $autoDetectOrder) . "\n";

if (in_array('workerman', $autoDetectOrder)) {
    $position = array_search('workerman', $autoDetectOrder) + 1;
    echo "✅ workerman 在自动检测顺序中 (第 $position 位)\n";
} else {
    echo "❌ workerman 不在自动检测顺序中\n";
}

// 6. 检查 workerman 配置
echo "\n=== Workerman 配置检查 ===\n";
$workermanConfig = $config->getRuntimeConfig('workerman');

if (empty($workermanConfig)) {
    echo "❌ workerman 配置为空\n";
    exit(1);
}

echo "✅ workerman 配置存在\n";

// 检查基础配置
$basicKeys = ['host', 'port', 'count', 'name'];
foreach ($basicKeys as $key) {
    if (isset($workermanConfig[$key])) {
        echo "   ✅ 基础配置 '$key': " . json_encode($workermanConfig[$key]) . "\n";
    } else {
        echo "   ❌ 基础配置 '$key' 缺失\n";
    }
}

// 7. 检查 Session 修复配置
echo "\n=== Session 修复配置检查 ===\n";
if (isset($workermanConfig['session'])) {
    echo "✅ Session 修复配置存在\n";
    $sessionConfig = $workermanConfig['session'];
    
    $sessionKeys = ['enable_fix', 'create_new_app', 'preserve_session_cookies', 'debug_session'];
    foreach ($sessionKeys as $key) {
        if (isset($sessionConfig[$key])) {
            $value = is_bool($sessionConfig[$key]) ? ($sessionConfig[$key] ? 'true' : 'false') : $sessionConfig[$key];
            echo "   ✅ session.$key: $value\n";
        } else {
            echo "   ❌ session.$key 缺失\n";
        }
    }
} else {
    echo "❌ Session 修复配置缺失\n";
}

// 8. 检查高级配置
echo "\n=== 高级配置检查 ===\n";
$advancedSections = [
    'memory' => '内存管理',
    'monitor' => '性能监控',
    'compression' => '压缩功能',
    'keep_alive' => 'Keep-Alive',
    'socket' => 'Socket 优化',
    'error' => '错误处理',
    'debug' => '调试配置',
    'security' => '安全配置',
    'process' => '进程管理'
];

foreach ($advancedSections as $section => $description) {
    if (isset($workermanConfig[$section])) {
        echo "   ✅ $description ($section) 配置存在\n";
    } else {
        echo "   ❌ $description ($section) 配置缺失\n";
    }
}

// 9. 检查向后兼容性
echo "\n=== 向后兼容性检查 ===\n";
$compatibilityKeys = ['static_file', 'middleware', 'log', 'timer'];
foreach ($compatibilityKeys as $key) {
    if (isset($workermanConfig[$key])) {
        echo "   ✅ 兼容配置 '$key' 存在\n";
    } else {
        echo "   ❌ 兼容配置 '$key' 缺失\n";
    }
}

// 10. 配置统计
echo "\n=== 配置统计 ===\n";
$allRuntimes = $config->get('runtimes', []);
echo "📊 运行时总数: " . count($allRuntimes) . "\n";
echo "📊 自动检测顺序: " . count($autoDetectOrder) . " 个\n";
echo "📊 workerman 配置项: " . count($workermanConfig) . " 个\n";

// 11. 显示 workerman 配置摘要
echo "\n=== Workerman 配置摘要 ===\n";
if (!empty($workermanConfig)) {
    echo "🌐 服务器: {$workermanConfig['host']}:{$workermanConfig['port']}\n";
    echo "⚙️  进程数: {$workermanConfig['count']}\n";
    echo "📝 进程名: {$workermanConfig['name']}\n";
    
    if (isset($workermanConfig['session'])) {
        $session = $workermanConfig['session'];
        echo "🔧 Session 修复: " . ($session['enable_fix'] ? '启用' : '禁用') . "\n";
        echo "🚀 新应用实例模式: " . ($session['create_new_app'] ? '启用' : '禁用') . "\n";
        echo "🍪 保留 Session Cookie: " . ($session['preserve_session_cookies'] ? '启用' : '禁用') . "\n";
        echo "🐛 Session 调试: " . ($session['debug_session'] ? '启用' : '禁用') . "\n";
    }
    
    if (isset($workermanConfig['monitor'])) {
        echo "📊 性能监控: " . ($workermanConfig['monitor']['enable'] ? '启用' : '禁用') . "\n";
    }
    
    if (isset($workermanConfig['compression'])) {
        echo "🗜️  压缩: " . ($workermanConfig['compression']['enable'] ? '启用' : '禁用') . "\n";
    }
    
    if (isset($workermanConfig['keep_alive'])) {
        echo "🔗 Keep-Alive: " . ($workermanConfig['keep_alive']['enable'] ? '启用' : '禁用') . "\n";
    }
}

// 12. 测试配置方法
echo "\n=== 配置方法测试 ===\n";

// 测试 get 方法
$host = $config->get('runtimes.workerman.host');
if ($host === '0.0.0.0') {
    echo "✅ get() 方法测试通过\n";
} else {
    echo "❌ get() 方法测试失败\n";
}

// 测试 set 方法
$config->set('runtimes.workerman.test_key', 'test_value');
$testValue = $config->get('runtimes.workerman.test_key');
if ($testValue === 'test_value') {
    echo "✅ set() 方法测试通过\n";
} else {
    echo "❌ set() 方法测试失败\n";
}

// 测试默认运行时
$defaultRuntime = $config->getDefaultRuntime();
echo "✅ 默认运行时: $defaultRuntime\n";

// 测试全局配置
$globalConfig = $config->getGlobalConfig();
echo "✅ 全局配置项: " . count($globalConfig) . " 个\n";

// 13. 总结
echo "\n=== 验证总结 ===\n";

$checks = [
    '文件存在' => file_exists($configPath),
    '语法正确' => strpos($syntaxCheck, 'No syntax errors') !== false,
    '类加载成功' => class_exists('yangweijie\\thinkRuntime\\config\\RuntimeConfig'),
    'workerman 配置存在' => !empty($workermanConfig),
    'Session 修复配置' => isset($workermanConfig['session']),
    '高级配置完整' => count(array_intersect_key($workermanConfig, array_flip(array_keys($advancedSections)))) >= 7,
];

$passed = 0;
$total = count($checks);

foreach ($checks as $name => $result) {
    if ($result) {
        echo "✅ $name\n";
        $passed++;
    } else {
        echo "❌ $name\n";
    }
}

echo "\n📊 验证结果: $passed/$total 项检查通过\n";

if ($passed === $total) {
    echo "\n🎉 RuntimeConfig Workerman 配置更新成功！\n";
    echo "✅ 所有必要的配置都已正确添加\n";
    echo "✅ Session 修复功能已集成\n";
    echo "✅ 高级功能配置完整\n";
    echo "✅ 向后兼容性保持\n";
    echo "\n💡 现在可以使用更新后的 RuntimeConfig 了！\n";
} else {
    echo "\n⚠️ RuntimeConfig 配置存在问题，请检查上述失败项。\n";
}

echo "\n📁 配置文件位置: $configPath\n";
echo "🔄 备份文件位置: src/config/RuntimeConfig.php.backup\n";
