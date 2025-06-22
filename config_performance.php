<?php
require_once 'vendor/autoload.php';

use yangweijie\thinkRuntime\adapter\FrankenphpAdapter;
use think\App;

echo "⚡ 配置生成性能测试\n";
echo "==================\n";

$app = new App();
$adapter = new FrankenphpAdapter($app);

$reflection = new ReflectionClass($adapter);
$method = $reflection->getMethod('buildFrankenPHPCaddyfile');
$method->setAccessible(true);

$configs = [
    'basic' => [
        'listen' => ':8080',
        'root' => '/tmp/test',
        'index' => 'index.php',
        'auto_https' => false,
    ],
    'advanced' => [
        'listen' => ':8080',
        'root' => '/var/www/html',
        'index' => 'index.php',
        'auto_https' => true,
        'worker_num' => 8,
        'max_requests' => 2000,
        'debug' => true,
    ],
];

foreach ($configs as $name => $config) {
    echo "测试配置: {$name}\n";
    
    $times = [];
    $sizes = [];
    
    for ($i = 0; $i < 1000; $i++) {
        $startTime = microtime(true);
        $caddyfile = $method->invoke($adapter, $config, null);
        $endTime = microtime(true);
        
        $times[] = ($endTime - $startTime) * 1000; // 转换为毫秒
        $sizes[] = strlen($caddyfile);
    }
    
    $avgTime = array_sum($times) / count($times);
    $minTime = min($times);
    $maxTime = max($times);
    $avgSize = array_sum($sizes) / count($sizes);
    
    echo "  平均生成时间: " . round($avgTime, 3) . " ms\n";
    echo "  最快生成时间: " . round($minTime, 3) . " ms\n";
    echo "  最慢生成时间: " . round($maxTime, 3) . " ms\n";
    echo "  平均配置大小: " . round($avgSize) . " bytes\n";
    echo "  性能评级: " . ($avgTime < 1 ? '✅ 优秀' : ($avgTime < 5 ? '🟡 良好' : '🔴 需要优化')) . "\n\n";
}
