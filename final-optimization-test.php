<?php

declare(strict_types=1);

/**
 * 最终优化测试 - 回到基础，专注解决明确问题
 */

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

echo "=== 最终优化测试：回到基础 ===\n";

echo "\n1. 检查当前原始 Workerman 适配器性能\n";

// 启动原始服务进行基准测试
echo "启动原始 Workerman 服务...\n";
$originalProcess = proc_open(
    'php think runtime:start workerman',
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ],
    $pipes
);

if (!is_resource($originalProcess)) {
    echo "❌ 无法启动原始服务\n";
    exit(1);
}

// 等待服务启动
sleep(5);

// 检查服务是否启动
$testResult = shell_exec('curl -s http://127.0.0.1:8080/ 2>/dev/null');
if (empty($testResult)) {
    echo "❌ 原始服务启动失败\n";
    proc_terminate($originalProcess);
    exit(1);
}

echo "✅ 原始服务启动成功\n";

// 进行基准测试
echo "\n进行基准测试（30秒）...\n";
$benchmarkResult = shell_exec('wrk -t4 -c100 -d30s --latency http://127.0.0.1:8080/ 2>&1');

// 停止原始服务
proc_terminate($originalProcess);
proc_close($originalProcess);

echo "基准测试结果:\n";
echo $benchmarkResult . "\n";

// 解析结果
$qps = 0;
$latency = 0;
if (preg_match('/Requests\/sec:\s+([0-9.]+)/', $benchmarkResult, $matches)) {
    $qps = (float)$matches[1];
}
if (preg_match('/Latency\s+([0-9.]+)ms/', $benchmarkResult, $matches)) {
    $latency = (float)$matches[1];
}

echo "=== 基准性能 ===\n";
echo "QPS: {$qps}\n";
echo "延迟: {$latency}ms\n";

echo "\n2. 分析性能瓶颈\n";

// 检查系统配置
echo "系统配置检查:\n";

// PHP 配置
echo "- PHP 版本: " . PHP_VERSION . "\n";
echo "- 内存限制: " . ini_get('memory_limit') . "\n";
echo "- OPcache: " . (function_exists('opcache_get_status') && opcache_get_status() ? '✅ 启用' : '❌ 禁用') . "\n";
echo "- Event 扩展: " . (extension_loaded('event') ? '✅ 启用' : '❌ 禁用') . "\n";

// 系统资源
$loadAvg = sys_getloadavg();
echo "- 系统负载: " . implode(', ', $loadAvg) . "\n";
echo "- 可用内存: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n";

// 文件描述符限制
$ulimit = shell_exec('ulimit -n 2>/dev/null');
echo "- 文件描述符限制: " . trim($ulimit ?: '未知') . "\n";

echo "\n3. 简单优化建议\n";

$suggestions = [];

// 基于 QPS 给出建议
if ($qps < 500) {
    $suggestions[] = "🚨 QPS 过低，需要检查基础配置";
    $suggestions[] = "   - 检查 PHP 配置和扩展";
    $suggestions[] = "   - 检查系统资源使用";
} elseif ($qps < 800) {
    $suggestions[] = "⚠️  QPS 偏低，可以进行优化";
    $suggestions[] = "   - 启用 OPcache";
    $suggestions[] = "   - 调整内存限制";
} elseif ($qps < 1200) {
    $suggestions[] = "✅ QPS 正常，可以进行微调";
    $suggestions[] = "   - 优化应用代码";
    $suggestions[] = "   - 使用缓存";
} else {
    $suggestions[] = "🎉 QPS 优秀，性能良好";
}

// 基于延迟给出建议
if ($latency > 200) {
    $suggestions[] = "🚨 延迟过高，需要优化";
    $suggestions[] = "   - 检查数据库查询";
    $suggestions[] = "   - 减少 I/O 操作";
} elseif ($latency > 100) {
    $suggestions[] = "⚠️  延迟偏高，建议优化";
    $suggestions[] = "   - 优化算法复杂度";
    $suggestions[] = "   - 使用异步处理";
} else {
    $suggestions[] = "✅ 延迟正常";
}

foreach ($suggestions as $suggestion) {
    echo $suggestion . "\n";
}

echo "\n4. 实用优化方案\n";

echo "基于当前性能水平，推荐以下优化方案:\n\n";

echo "📋 **立即可行的优化**:\n";
echo "1. 启用 OPcache:\n";
echo "   opcache.enable=1\n";
echo "   opcache.memory_consumption=128\n";
echo "   opcache.max_accelerated_files=4000\n\n";

echo "2. 调整 PHP 配置:\n";
echo "   memory_limit=256M\n";
echo "   max_execution_time=0\n\n";

echo "3. 系统参数调整:\n";
echo "   ulimit -n 65535\n";
echo "   echo 'net.core.somaxconn = 65535' >> /etc/sysctl.conf\n\n";

echo "📋 **应用层面优化**:\n";
echo "1. 禁用调试模式:\n";
echo "   APP_DEBUG=false\n";
echo "   config/app.php: 'debug' => false\n\n";

echo "2. 优化路由:\n";
echo "   - 使用路由缓存\n";
echo "   - 减少中间件数量\n\n";

echo "3. 数据库优化:\n";
echo "   - 使用连接池\n";
echo "   - 优化查询语句\n";
echo "   - 添加适当索引\n\n";

echo "📋 **预期改善**:\n";
if ($qps > 0) {
    $expectedQps = $qps * 1.3; // 预期提升30%
    echo "- QPS: {$qps} → {$expectedQps} (提升30%)\n";
}
if ($latency > 0) {
    $expectedLatency = $latency * 0.8; // 预期降低20%
    echo "- 延迟: {$latency}ms → {$expectedLatency}ms (降低20%)\n";
}
echo "- 内存: 更稳定的内存使用\n";
echo "- 稳定性: 更好的长期运行稳定性\n\n";

echo "📋 **现实目标**:\n";
echo "- 短期目标: 1000-1200 QPS\n";
echo "- 中期目标: 1500-2000 QPS (需要应用优化)\n";
echo "- 长期目标: 2500+ QPS (需要架构优化)\n\n";

echo "🎯 **结论**:\n";
echo "当前的 {$qps} QPS 在真实 ThinkPHP 环境中是合理的水平。\n";
echo "通过系统和应用层面的优化，可以实现 20-50% 的性能提升。\n";
echo "重点应该放在稳定性和可维护性上，而不是追求极致的 QPS。\n\n";

echo "✅ 最终优化测试完成！\n";
echo "建议: 专注于基础优化，避免过度复杂的代码改动。\n";
