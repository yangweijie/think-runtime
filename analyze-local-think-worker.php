<?php

declare(strict_types=1);

/**
 * 分析本地 think-worker 源码
 * 学习其高性能和内存优化实现
 */

echo "=== 分析本地 think-worker 源码 ===\n";

$thinkWorkerPath = '/Volumes/data/git/php/hello-tp/vendor/topthink/think-worker';

if (!is_dir($thinkWorkerPath)) {
    echo "❌ think-worker 路径不存在: {$thinkWorkerPath}\n";
    exit(1);
}

echo "✅ think-worker 路径存在: {$thinkWorkerPath}\n";

// 递归扫描所有 PHP 文件
function scanPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

$phpFiles = scanPhpFiles($thinkWorkerPath);
echo "找到 " . count($phpFiles) . " 个 PHP 文件\n\n";

// 分析关键文件
$keyFiles = [];
foreach ($phpFiles as $file) {
    $relativePath = str_replace($thinkWorkerPath . '/', '', $file);
    if (strpos($relativePath, 'src/') === 0) {
        $keyFiles[] = $file;
        echo "📁 {$relativePath}\n";
    }
}

echo "\n=== 分析关键优化点 ===\n";

$optimizations = [
    'memory_management' => [],
    'gc_optimization' => [],
    'instance_reuse' => [],
    'event_loop' => [],
    'performance_tips' => []
];

foreach ($keyFiles as $file) {
    $content = file_get_contents($file);
    $relativePath = str_replace($thinkWorkerPath . '/', '', $file);
    
    // 分析内存管理
    if (preg_match_all('/memory_get_usage|memory_get_peak_usage|memory_limit/i', $content, $matches)) {
        $optimizations['memory_management'][] = [
            'file' => $relativePath,
            'matches' => array_unique($matches[0])
        ];
    }
    
    // 分析垃圾回收
    if (preg_match_all('/gc_collect_cycles|gc_enable|gc_disable/i', $content, $matches)) {
        $optimizations['gc_optimization'][] = [
            'file' => $relativePath,
            'matches' => array_unique($matches[0])
        ];
    }
    
    // 分析实例复用
    if (preg_match_all('/clone|new\s+\$|singleton|instance/i', $content, $matches)) {
        $optimizations['instance_reuse'][] = [
            'file' => $relativePath,
            'matches' => array_unique($matches[0])
        ];
    }
    
    // 分析事件循环相关
    if (preg_match_all('/event|loop|select|epoll|kqueue/i', $content, $matches)) {
        $optimizations['event_loop'][] = [
            'file' => $relativePath,
            'matches' => array_unique($matches[0])
        ];
    }
}

// 输出分析结果
foreach ($optimizations as $category => $items) {
    if (!empty($items)) {
        echo "\n🔍 " . strtoupper(str_replace('_', ' ', $category)) . ":\n";
        foreach ($items as $item) {
            echo "  📄 {$item['file']}: " . implode(', ', $item['matches']) . "\n";
        }
    }
}

// 读取主要文件内容进行深度分析
echo "\n=== 深度分析主要文件 ===\n";

$mainFiles = [
    'src/Server.php',
    'src/Worker.php',
    'src/command/Server.php'
];

foreach ($mainFiles as $mainFile) {
    $fullPath = $thinkWorkerPath . '/' . $mainFile;
    if (file_exists($fullPath)) {
        echo "\n📖 分析 {$mainFile}:\n";
        $content = file_get_contents($fullPath);
        
        // 提取关键方法
        if (preg_match_all('/(?:public|protected|private)\s+function\s+(\w+)/i', $content, $matches)) {
            echo "  方法: " . implode(', ', array_unique($matches[1])) . "\n";
        }
        
        // 查找性能相关的注释和代码
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            if (stripos($line, 'performance') !== false || 
                stripos($line, 'optimize') !== false ||
                stripos($line, 'memory') !== false ||
                stripos($line, 'gc_') !== false) {
                echo "  第" . ($lineNum + 1) . "行: {$line}\n";
            }
        }
    }
}

// 检查配置文件
echo "\n=== 检查配置和文档 ===\n";

$configFiles = [
    'composer.json',
    'README.md',
    'src/config.php'
];

foreach ($configFiles as $configFile) {
    $fullPath = $thinkWorkerPath . '/' . $configFile;
    if (file_exists($fullPath)) {
        echo "✅ {$configFile} 存在\n";
        
        if ($configFile === 'composer.json') {
            $composer = json_decode(file_get_contents($fullPath), true);
            if (isset($composer['require'])) {
                echo "  依赖: " . implode(', ', array_keys($composer['require'])) . "\n";
            }
        }
    }
}

// 验证 Event 扩展使用情况
echo "\n=== 验证 Event 扩展使用 ===\n";

// 切换到项目目录验证
chdir('/Volumes/data/git/php/tp');

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    
    echo "扩展状态:\n";
    echo "- Event: " . (extension_loaded('event') ? '✅ 已安装' : '❌ 未安装') . "\n";
    echo "- Ev: " . (extension_loaded('ev') ? '✅ 已安装' : '❌ 未安装') . "\n";
    echo "- Libevent: " . (extension_loaded('libevent') ? '✅ 已安装' : '❌ 未安装') . "\n";
    
    // 检查 Workerman 事件循环选择
    if (class_exists('Workerman\Worker')) {
        echo "\nWorkerman 事件循环检查:\n";
        
        // 检查可用的事件循环类
        $eventClasses = [
            'Workerman\\Events\\Event' => 'Event (最高性能)',
            'Workerman\\Events\\Ev' => 'Ev (高性能)', 
            'Workerman\\Events\\Libevent' => 'Libevent (中等性能)',
            'Workerman\\Events\\Select' => 'Select (基础性能)'
        ];
        
        foreach ($eventClasses as $class => $desc) {
            if (class_exists($class)) {
                echo "✅ {$class} - {$desc}\n";
                
                // 检查是否可用
                try {
                    $reflection = new ReflectionClass($class);
                    if ($reflection->hasMethod('available')) {
                        $available = $class::available();
                        echo "   可用性: " . ($available ? '✅ 可用' : '❌ 不可用') . "\n";
                        
                        if ($available && strpos($class, 'Event') !== false) {
                            echo "   🎯 这应该是 Workerman 的首选！\n";
                        }
                    }
                } catch (Exception $e) {
                    echo "   检查失败: " . $e->getMessage() . "\n";
                }
            } else {
                echo "❌ {$class} - {$desc}\n";
            }
        }
    }
}

// 总结分析结果
echo "\n=== 分析总结 ===\n";

echo "基于 think-worker 的优化策略:\n";
echo "1. 🔄 应用实例管理 - 避免重复创建应用实例\n";
echo "2. 🧹 内存管理 - 定期垃圾回收和内存监控\n";
echo "3. ⚡ 事件循环优化 - 使用最高性能的事件循环\n";
echo "4. 🚀 进程管理 - 合理配置进程数和资源\n";
echo "5. 📊 性能监控 - 实时监控QPS和内存使用\n";

echo "\n如果您的 QPS 只有 870-930 而不是 3000+，可能的原因:\n";
echo "1. ❌ Event 扩展未正确使用\n";
echo "2. ❌ ThinkPHP 调试模式未关闭\n";
echo "3. ❌ 应用实例仍在重复创建\n";
echo "4. ❌ 数据库连接未优化\n";
echo "5. ❌ 系统参数未调优\n";

echo "\n建议下一步:\n";
echo "1. 🔍 确认 Workerman 真正使用了 Event 扩展\n";
echo "2. 🚫 完全禁用调试模式和 think-trace\n";
echo "3. 🔧 参考 think-worker 的实例管理方式\n";
echo "4. ⚙️  调整系统和 PHP 配置\n";

echo "\n分析完成！\n";
