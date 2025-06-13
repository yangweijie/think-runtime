<?php

declare(strict_types=1);

/**
 * 测试 --workers=0 参数处理
 */

echo "测试 --workers=0 参数处理\n";
echo "========================\n\n";

// 模拟命令行参数
$testCases = [
    'workers=0' => ['workers' => '0'],
    'workers=4' => ['workers' => '4'],
    'workers=null' => ['workers' => null],
    'workers=empty' => ['workers' => ''],
];

foreach ($testCases as $testName => $input) {
    echo "测试用例: {$testName}\n";
    echo "输入: " . json_encode($input) . "\n";
    
    $workers = $input['workers'];
    $options = [];
    
    // 原来的逻辑（错误）
    if ($workers) {
        $options['worker_num_old'] = $workers;
    }
    
    // 修复后的逻辑（正确）
    if ($workers !== null) {
        $options['worker_num_new'] = $workers;
    }
    
    echo "原逻辑结果: " . (isset($options['worker_num_old']) ? $options['worker_num_old'] : '未设置') . "\n";
    echo "新逻辑结果: " . (isset($options['worker_num_new']) ? $options['worker_num_new'] : '未设置') . "\n";
    echo "修复效果: " . (isset($options['worker_num_new']) && $options['worker_num_new'] === '0' ? '✅ 正确' : '❌ 错误') . "\n";
    echo "\n";
}

echo "========================\n";
echo "修复说明\n";
echo "========================\n\n";

echo "问题:\n";
echo "- 原来的 if (\$workers) 会将 '0' 当作 falsy 值\n";
echo "- 导致 --workers=0 参数被忽略\n";
echo "- 结果仍然使用默认的 worker_num=4\n\n";

echo "修复:\n";
echo "- 改为 if (\$workers !== null)\n";
echo "- 只要参数存在就设置，不管值是什么\n";
echo "- 现在 --workers=0 可以正确设置为 0\n\n";

echo "测试命令:\n";
echo "php think runtime:start frankenphp --host=localhost --port=8080 --workers=0\n\n";

echo "预期效果:\n";
echo "- Workers: 0 (而不是 4)\n";
echo "- 使用标准模式而不是 Worker 模式\n";
echo "- 避免弃用警告\n";
