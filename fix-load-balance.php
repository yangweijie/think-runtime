<?php

declare(strict_types=1);

/**
 * 修复 Workerman 负载均衡问题
 */

echo "=== Workerman 负载均衡修复工具 ===\n";

// 切换到项目目录
chdir('/Volumes/data/git/php/tp');

if (!file_exists('think')) {
    echo "❌ 请在 ThinkPHP 项目根目录运行此脚本\n";
    exit(1);
}

echo "✅ 检测到 ThinkPHP 项目\n";

echo "\n🔍 问题分析:\n";
echo "在压力测试中发现4个worker进程中只有1个在处理请求\n";
echo "这是典型的负载均衡问题，可能的原因:\n";
echo "1. reusePort 配置导致负载不均\n";
echo "2. 连接保持导致请求粘性\n";
echo "3. 系统内核负载均衡算法问题\n";

echo "\n🚀 解决方案:\n";
echo "1. 禁用 reusePort (推荐)\n";
echo "2. 单进程高性能模式\n";
echo "3. 强制关闭连接\n";

echo "\n请选择修复方案:\n";
echo "1) 禁用 reusePort (推荐，风险最低)\n";
echo "2) 单进程模式 (简单有效)\n";
echo "3) 查看当前配置\n";
echo "4) 进行负载均衡测试\n";
echo "5) 退出\n";

echo "\n请输入选择 (1-5): ";
$choice = trim(fgets(STDIN));

switch ($choice) {
    case '1':
        fixReusePort();
        break;
    case '2':
        setSingleProcess();
        break;
    case '3':
        showCurrentConfig();
        break;
    case '4':
        runLoadBalanceTest();
        break;
    case '5':
        echo "退出\n";
        exit(0);
    default:
        echo "无效选择\n";
        exit(1);
}

/**
 * 修复 reusePort 配置
 */
function fixReusePort(): void
{
    echo "\n=== 修复 reusePort 配置 ===\n";
    
    $configFile = 'config/runtime.php';
    
    if (!file_exists($configFile)) {
        echo "创建 runtime 配置文件...\n";
        $config = <<<'PHP'
<?php

return [
    'workerman' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'count' => 4,
        'reusePort' => false,  // 禁用 reusePort 解决负载均衡问题
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 100,
        ],
    ],
];
PHP;
        
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }
        
        file_put_contents($configFile, $config);
        echo "✅ 创建了新的 runtime 配置文件\n";
    } else {
        // 备份原文件
        copy($configFile, $configFile . '.backup.' . date('YmdHis'));
        echo "✅ 已备份原配置文件\n";
        
        // 读取现有配置
        $content = file_get_contents($configFile);
        
        // 修改 reusePort 设置
        if (strpos($content, 'reusePort') !== false) {
            $content = preg_replace(
                "/'reusePort'\s*=>\s*(true|false)/",
                "'reusePort' => false",
                $content
            );
        } else {
            // 添加 reusePort 设置
            $content = str_replace(
                "'count' => ",
                "'count' => 4,\n        'reusePort' => false,  // 禁用 reusePort 解决负载均衡问题\n        'count' => ",
                $content
            );
        }
        
        file_put_contents($configFile, $content);
        echo "✅ 已修改 reusePort 配置\n";
    }
    
    echo "\n配置修改完成！\n";
    echo "下一步:\n";
    echo "1. 重启 Workerman: php think runtime:start workerman\n";
    echo "2. 运行压测: wrk -t4 -c100 -d30s http://127.0.0.1:8080/\n";
    echo "3. 观察是否4个进程都在处理请求\n";
}

/**
 * 设置单进程模式
 */
function setSingleProcess(): void
{
    echo "\n=== 设置单进程高性能模式 ===\n";
    
    $configFile = 'config/runtime.php';
    
    if (!file_exists($configFile)) {
        echo "创建单进程配置文件...\n";
        $config = <<<'PHP'
<?php

return [
    'workerman' => [
        'host' => '127.0.0.1',
        'port' => 8080,
        'count' => 1,  // 单进程模式
        'memory' => [
            'memory_limit' => '512M',  // 增加内存限制
            'enable_gc' => true,
            'gc_interval' => 100,
        ],
    ],
];
PHP;
        
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }
        
        file_put_contents($configFile, $config);
        echo "✅ 创建了单进程配置文件\n";
    } else {
        // 备份原文件
        copy($configFile, $configFile . '.backup.' . date('YmdHis'));
        echo "✅ 已备份原配置文件\n";
        
        // 读取现有配置
        $content = file_get_contents($configFile);
        
        // 修改进程数
        $content = preg_replace(
            "/'count'\s*=>\s*\d+/",
            "'count' => 1",
            $content
        );
        
        file_put_contents($configFile, $content);
        echo "✅ 已设置为单进程模式\n";
    }
    
    echo "\n单进程模式配置完成！\n";
    echo "优点: 避免负载均衡问题，配置简单\n";
    echo "缺点: 无法利用多核优势\n";
    echo "\n下一步:\n";
    echo "1. 重启 Workerman: php think runtime:start workerman\n";
    echo "2. 运行压测对比性能差异\n";
}

/**
 * 显示当前配置
 */
function showCurrentConfig(): void
{
    echo "\n=== 当前配置 ===\n";
    
    $configFile = 'config/runtime.php';
    
    if (file_exists($configFile)) {
        echo "配置文件: {$configFile}\n";
        echo "内容:\n";
        echo file_get_contents($configFile);
    } else {
        echo "❌ 未找到 runtime 配置文件\n";
        echo "建议创建配置文件来解决负载均衡问题\n";
    }
    
    // 检查当前运行的进程
    echo "\n=== 当前运行的 Workerman 进程 ===\n";
    $processes = shell_exec('ps aux | grep workerman | grep -v grep');
    if ($processes) {
        echo $processes;
    } else {
        echo "未发现运行中的 Workerman 进程\n";
    }
}

/**
 * 运行负载均衡测试
 */
function runLoadBalanceTest(): void
{
    echo "\n=== 负载均衡测试 ===\n";
    
    echo "检查 Workerman 服务状态...\n";
    $testResult = shell_exec('curl -s http://127.0.0.1:8080/ 2>/dev/null');
    
    if (empty($testResult)) {
        echo "❌ Workerman 服务未运行\n";
        echo "请先启动服务: php think runtime:start workerman\n";
        return;
    }
    
    echo "✅ Workerman 服务正在运行\n";
    
    echo "\n进行负载均衡测试...\n";
    echo "测试方法: 发送多个请求，观察响应中的进程信息\n";
    
    for ($i = 1; $i <= 10; $i++) {
        $response = shell_exec('curl -s http://127.0.0.1:8080/ 2>/dev/null');
        echo "请求 {$i}: " . substr($response, 0, 100) . "...\n";
        usleep(100000); // 100ms 间隔
    }
    
    echo "\n如果看到相同的进程ID重复出现，说明负载均衡有问题\n";
    echo "建议运行完整压测: wrk -t4 -c100 -d30s http://127.0.0.1:8080/\n";
    echo "然后检查进程统计: ps aux | grep workerman\n";
}

echo "\n✅ 负载均衡修复工具执行完成！\n";
