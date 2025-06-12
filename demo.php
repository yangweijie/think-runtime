<?php

declare(strict_types=1);

/**
 * ThinkPHP Runtime 扩展包演示脚本
 *
 * 此脚本演示如何使用ThinkPHP Runtime扩展包
 */

require_once __DIR__ . '/vendor/autoload.php';

use yangweijie\thinkRuntime\config\RuntimeConfig;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

echo "=== ThinkPHP Runtime 扩展包演示 ===\n\n";

// 1. 创建运行时配置
echo "1. 创建运行时配置\n";
$config = new RuntimeConfig([
    'default' => 'auto',
    'runtimes' => [
        'swoole' => [
            'host' => '127.0.0.1',
            'port' => 8080,
        ],
    ],
]);

echo "   默认运行时: " . $config->getDefaultRuntime() . "\n";
echo "   自动检测顺序: " . implode(', ', $config->getAutoDetectOrder()) . "\n";
echo "   Swoole配置: " . json_encode($config->getRuntimeConfig('swoole'), JSON_UNESCAPED_UNICODE) . "\n\n";

// 2. 创建模拟应用
echo "2. 创建模拟应用\n";
$app = new class {
    private array $instances = [];

    public function instance(string $name, $instance): void
    {
        $this->instances[$name] = $instance;
    }

    public function make(string $name)
    {
        return $this->instances[$name] ?? null;
    }

    public function initialize(): void
    {
        echo "   应用已初始化\n";
    }

    public function __get($name)
    {
        return $this->instances[$name] ?? null;
    }
};

$app->initialize();

// 3. 创建运行时管理器（注释掉，因为需要真实的ThinkPHP App实例）
echo "\n3. 运行时管理器功能演示\n";
echo "   注意: 完整的运行时管理器需要真实的ThinkPHP App实例\n";
echo "   在实际项目中，您可以这样使用:\n";
echo "   \$manager = new RuntimeManager(\$app, \$config);\n";
echo "   \$runtime = \$manager->getRuntime('swoole');\n";
echo "   \$manager->start('swoole');\n\n";

// 4. 显示可用的适配器
echo "4. 可用的运行时适配器\n";
$adapters = [
    'swoole' => 'yangweijie\\thinkRuntime\\adapter\\SwooleAdapter',
    'frankenphp' => 'yangweijie\\thinkRuntime\\adapter\\FrankenphpAdapter',
    'reactphp' => 'yangweijie\\thinkRuntime\\adapter\\ReactphpAdapter',
    'ripple' => 'yangweijie\\thinkRuntime\\adapter\\RippleAdapter',
    'roadrunner' => 'yangweijie\\thinkRuntime\\adapter\\RoadrunnerAdapter',
    'bref' => 'yangweijie\\thinkRuntime\\adapter\\BrefAdapter',
];

foreach ($adapters as $name => $class) {
    echo "   - {$name}: {$class}\n";
}

echo "\n5. 环境检测\n";
echo "   PHP版本: " . PHP_VERSION . "\n";
echo "   PHP SAPI: " . php_sapi_name() . "\n";
echo "   Swoole扩展: " . (extension_loaded('swoole') ? '已安装' : '未安装') . "\n";
echo "   FrankenPHP环境: " . (isset($_SERVER['FRANKENPHP_VERSION']) ? '是' : '否') . "\n";
echo "   ReactPHP支持: " . (class_exists('React\\EventLoop\\Loop') ? '是' : '否') . "\n";
echo "   Ripple支持: " . (class_exists('Ripple\\Http\\Server') ? '是' : '否') . "\n";
echo "   Fiber支持: " . (class_exists('Fiber') ? '是' : '否') . " (PHP " . PHP_VERSION . ")\n";
echo "   RoadRunner环境: " . (isset($_SERVER['RR_MODE']) ? '是' : '否') . "\n";

echo "\n=== 演示完成 ===\n";
echo "\n使用说明:\n";
echo "1. 在ThinkPHP项目中安装此扩展包\n";
echo "2. 配置 config/runtime.php 文件\n";
echo "3. 使用命令启动服务器: php think runtime:start\n";
echo "4. 查看运行时信息: php think runtime:info\n";
echo "\n更多信息请查看 README.md 文件\n";
