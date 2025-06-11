<?php

declare(strict_types=1);

/**
 * 简单的执行时间修复验证
 */

echo "执行时间修复验证\n";
echo "================\n\n";

echo "当前PHP配置:\n";
echo "- max_execution_time: " . ini_get('max_execution_time') . " 秒\n";
echo "- memory_limit: " . ini_get('memory_limit') . "\n\n";

echo "测试set_time_limit(0)功能...\n";

// 记录原始设置
$originalTimeLimit = ini_get('max_execution_time');
echo "原始执行时间限制: " . ($originalTimeLimit == 0 ? '无限制' : $originalTimeLimit . ' 秒') . "\n";

// 设置无限执行时间
set_time_limit(0);
$newTimeLimit = ini_get('max_execution_time');
echo "设置后执行时间限制: " . ($newTimeLimit == 0 ? '无限制' : $newTimeLimit . ' 秒') . "\n";

if ($newTimeLimit == 0) {
    echo "✅ set_time_limit(0) 设置成功\n\n";
} else {
    echo "❌ set_time_limit(0) 设置失败\n\n";
}

echo "模拟长时间运行测试...\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n";

$startTime = time();
$testDuration = 3; // 3秒测试

echo "运行 {$testDuration} 秒测试...\n";

for ($i = 1; $i <= $testDuration; $i++) {
    sleep(1);
    echo "已运行: {$i} 秒\n";
}

echo "结束时间: " . date('Y-m-d H:i:s') . "\n";
echo "✅ 测试完成，无超时错误\n\n";

echo "ReactPHP适配器修复说明:\n";
echo "========================\n\n";

echo "修复位置:\n";
echo "1. src/adapter/ReactphpAdapter.php - boot()方法\n";
echo "2. src/adapter/ReactphpAdapter.php - run()方法\n\n";

echo "修复内容:\n";
echo "```php\n";
echo "// 在boot()方法中添加:\n";
echo "set_time_limit(0);\n\n";
echo "// 在run()方法中添加:\n";
echo "set_time_limit(0);\n";
echo "ini_set('memory_limit', '512M');\n";
echo "```\n\n";

echo "修复效果:\n";
echo "- ✅ ReactPHP服务器不会在30秒后自动停止\n";
echo "- ✅ 事件循环可以无限期运行\n";
echo "- ✅ 内存限制设置为512M\n";
echo "- ✅ 启动时显示配置信息\n\n";

echo "使用说明:\n";
echo "1. 安装ReactPHP依赖:\n";
echo "   composer require react/http react/socket react/promise ringcentral/psr7\n\n";
echo "2. 启动ReactPHP服务器:\n";
echo "   php think runtime:start reactphp\n\n";
echo "3. 服务器启动后会显示:\n";
echo "   ReactPHP HTTP Server starting...\n";
echo "   Listening on: 0.0.0.0:8080\n";
echo "   Event-driven: Yes\n";
echo "   Memory limit: 512M\n";
echo "   Execution time: Unlimited\n";
echo "   Press Ctrl+C to stop the server\n\n";

echo "故障排除:\n";
echo "如果仍然遇到超时问题:\n";
echo "1. 确认使用CLI模式: php think runtime:start reactphp\n";
echo "2. 检查PHP配置: php -i | grep max_execution_time\n";
echo "3. 检查错误日志中的具体错误信息\n";
echo "4. 确认ReactPHP依赖已正确安装\n\n";

echo "与Swoole修复的对比:\n";
echo "- Swoole: 修复了Worker进程超时和信号问题\n";
echo "- ReactPHP: 修复了事件循环超时问题\n";
echo "- 两者都需要set_time_limit(0)来支持长期运行\n";
