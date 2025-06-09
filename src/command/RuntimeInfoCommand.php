<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

/**
 * 运行时信息命令
 * 显示运行时环境信息
 */
class RuntimeInfoCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('runtime:info')
            ->setDescription('Show runtime information');
    }

    /**
     * 执行命令
     *
     * @param Input $input 输入对象
     * @param Output $output 输出对象
     * @return int
     */
    protected function execute(Input $input, Output $output): int
    {
        /** @var RuntimeManager $manager */
        $manager = $this->app->make('runtime.manager');

        try {
            $this->displaySystemInfo($output);
            $this->displayRuntimeInfo($output, $manager);
            $this->displayAvailableRuntimes($output, $manager);

            return 0;

        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to get runtime info: {$e->getMessage()}</error>");
            return 1;
        }
    }

    /**
     * 显示系统信息
     *
     * @param Output $output 输出对象
     * @return void
     */
    protected function displaySystemInfo(Output $output): void
    {
        $output->writeln('<comment>System Information:</comment>');
        $output->writeln('  PHP Version: ' . PHP_VERSION);
        $output->writeln('  PHP SAPI: ' . php_sapi_name());
        $output->writeln('  Operating System: ' . PHP_OS);
        $output->writeln('  Architecture: ' . php_uname('m'));
        $output->writeln('  Memory Limit: ' . ini_get('memory_limit'));
        $output->writeln('  Max Execution Time: ' . ini_get('max_execution_time') . 's');
        $output->writeln('');
    }

    /**
     * 显示运行时信息
     *
     * @param Output $output 输出对象
     * @param RuntimeManager $manager 运行时管理器
     * @return void
     */
    protected function displayRuntimeInfo(Output $output, RuntimeManager $manager): void
    {
        $info = $manager->getRuntimeInfo();

        $output->writeln('<comment>Current Runtime:</comment>');
        $output->writeln("  Name: {$info['name']}");
        $output->writeln("  Available: " . ($info['available'] ? 'Yes' : 'No'));
        $output->writeln('');
    }

    /**
     * 显示可用运行时
     *
     * @param Output $output 输出对象
     * @param RuntimeManager $manager 运行时管理器
     * @return void
     */
    protected function displayAvailableRuntimes(Output $output, RuntimeManager $manager): void
    {
        $output->writeln('<comment>Available Runtimes:</comment>');

        $runtimes = [
            'swoole' => 'High-performance HTTP server based on Swoole',
            'frankenphp' => 'Modern PHP app server with HTTP/2, HTTP/3 and real-time features',
            'reactphp' => 'Event-driven, non-blocking I/O HTTP server with async capabilities',
            'ripple' => 'High-performance coroutine HTTP server based on PHP Fiber',
            'roadrunner' => 'High-performance application server written in Go',
            'fpm' => 'Traditional PHP-FPM environment',
        ];

        foreach ($runtimes as $name => $description) {
            $available = $manager->isRuntimeAvailable($name);
            $status = $available ? '<info>Yes</info>' : '<error>No</error>';
            $output->writeln("  {$name}: {$status} - {$description}");
        }
        $output->writeln('');

        $this->displayExtensionInfo($output);
    }

    /**
     * 显示扩展信息
     *
     * @param Output $output 输出对象
     * @return void
     */
    protected function displayExtensionInfo(Output $output): void
    {
        $output->writeln('<comment>PHP Extensions:</comment>');

        $extensions = [
            'swoole' => 'Swoole extension for high-performance networking',
            'curl' => 'cURL extension for HTTP requests',
            'json' => 'JSON extension for data serialization',
            'mbstring' => 'Multibyte string extension',
            'openssl' => 'OpenSSL extension for encryption',
        ];

        foreach ($extensions as $name => $description) {
            $loaded = extension_loaded($name);
            $version = $loaded ? phpversion($name) : 'N/A';
            $status = $loaded ? '<info>Loaded</info>' : '<error>Not Loaded</error>';
            $versionStr = $version ?: 'Unknown';

            $output->writeln("  {$name}: {$status} (v{$versionStr})");
        }
    }
}
