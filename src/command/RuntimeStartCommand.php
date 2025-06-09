<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

/**
 * 运行时启动命令
 * 用于启动指定的运行时服务器
 */
class RuntimeStartCommand extends Command
{
    /**
     * 配置命令
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('runtime:start')
            ->setDescription('Start runtime server')
            ->addArgument('runtime', Argument::OPTIONAL, 'Runtime name (swoole, frankenphp, reactphp, ripple, roadrunner, fpm)', 'auto')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'Server host', '0.0.0.0')
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'Server port', 9501)
            ->addOption('daemon', 'd', Option::VALUE_NONE, 'Run as daemon')
            ->addOption('workers', 'w', Option::VALUE_OPTIONAL, 'Number of workers', 4);
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
        $runtimeName = $input->getArgument('runtime');

        /** @var RuntimeManager $manager */
        $manager = $this->app->make('runtime.manager');

        try {
            // 检测运行时
            if ($runtimeName === 'auto') {
                $runtimeName = $manager->detectRuntime();
                $output->writeln("<info>Auto-detected runtime: {$runtimeName}</info>");
            }

            // 检查运行时是否可用
            if (!$manager->isRuntimeAvailable($runtimeName)) {
                $output->writeln("<error>Runtime '{$runtimeName}' is not available</error>");
                return 1;
            }

            // 构建启动选项
            $options = $this->buildStartOptions($input, $runtimeName);

            $output->writeln("<info>Starting {$runtimeName} runtime server...</info>");
            $this->displayStartupInfo($output, $runtimeName, $options);

            // 启动运行时
            $manager->start($runtimeName, $options);

            return 0;

        } catch (\Throwable $e) {
            $output->writeln("<error>Failed to start runtime: {$e->getMessage()}</error>");
            return 1;
        }
    }

    /**
     * 构建启动选项
     *
     * @param Input $input 输入对象
     * @param string $runtimeName 运行时名称
     * @return array
     */
    protected function buildStartOptions(Input $input, string $runtimeName): array
    {
        $options = [];

        switch ($runtimeName) {
            case 'swoole':
                $options = [
                    'host' => $input->getOption('host'),
                    'port' => (int) $input->getOption('port'),
                    'settings' => [
                        'worker_num' => (int) $input->getOption('workers'),
                        'daemonize' => $input->getOption('daemon') ? 1 : 0,
                    ],
                ];
                break;

            case 'frankenphp':
                $options = [
                    'listen' => ':' . $input->getOption('port'),
                    'worker_num' => (int) $input->getOption('workers'),
                    'debug' => $input->getOption('daemon') ? false : true,
                ];
                break;

            case 'reactphp':
                $options = [
                    'host' => $input->getOption('host'),
                    'port' => (int) $input->getOption('port'),
                    'max_connections' => 1000,
                    'debug' => $input->getOption('daemon') ? false : true,
                ];
                break;

            case 'ripple':
                $options = [
                    'host' => $input->getOption('host'),
                    'port' => (int) $input->getOption('port'),
                    'worker_num' => (int) $input->getOption('workers'),
                    'debug' => $input->getOption('daemon') ? false : true,
                    'enable_fiber' => true,
                ];
                break;

            case 'roadrunner':
                // RoadRunner配置通过.rr.yaml文件管理
                break;

            case 'fpm':
                $options = [
                    'auto_start' => true,
                ];
                break;
        }

        return $options;
    }

    /**
     * 显示启动信息
     *
     * @param Output $output 输出对象
     * @param string $runtimeName 运行时名称
     * @param array $options 启动选项
     * @return void
     */
    protected function displayStartupInfo(Output $output, string $runtimeName, array $options): void
    {
        $output->writeln('');
        $output->writeln('<comment>Runtime Information:</comment>');
        $output->writeln("  Runtime: {$runtimeName}");

        switch ($runtimeName) {
            case 'swoole':
                $host = $options['host'] ?? '0.0.0.0';
                $port = $options['port'] ?? 9501;
                $workers = $options['settings']['worker_num'] ?? 4;
                $daemon = $options['settings']['daemonize'] ?? 0;

                $output->writeln("  Host: {$host}");
                $output->writeln("  Port: {$port}");
                $output->writeln("  Workers: {$workers}");
                $output->writeln("  Daemon: " . ($daemon ? 'Yes' : 'No'));
                $output->writeln("  URL: http://{$host}:{$port}");
                break;

            case 'frankenphp':
                $listen = $options['listen'] ?? ':8080';
                $workers = $options['worker_num'] ?? 4;
                $debug = $options['debug'] ?? false;

                $output->writeln("  Listen: {$listen}");
                $output->writeln("  Workers: {$workers}");
                $output->writeln("  Debug: " . ($debug ? 'Yes' : 'No'));
                $output->writeln("  Features: HTTP/2, Auto HTTPS");
                break;

            case 'reactphp':
                $host = $options['host'] ?? '0.0.0.0';
                $port = $options['port'] ?? 8080;
                $maxConnections = $options['max_connections'] ?? 1000;
                $debug = $options['debug'] ?? false;

                $output->writeln("  Host: {$host}");
                $output->writeln("  Port: {$port}");
                $output->writeln("  Max Connections: {$maxConnections}");
                $output->writeln("  Debug: " . ($debug ? 'Yes' : 'No'));
                $output->writeln("  Features: Event-driven, Async I/O");
                break;

            case 'ripple':
                $host = $options['host'] ?? '0.0.0.0';
                $port = $options['port'] ?? 8080;
                $workers = $options['worker_num'] ?? 4;
                $fiber = $options['enable_fiber'] ?? true;
                $debug = $options['debug'] ?? false;

                $output->writeln("  Host: {$host}");
                $output->writeln("  Port: {$port}");
                $output->writeln("  Workers: {$workers}");
                $output->writeln("  Fiber Support: " . ($fiber ? 'Yes' : 'No'));
                $output->writeln("  Debug: " . ($debug ? 'Yes' : 'No'));
                $output->writeln("  Features: Coroutines, High Performance");
                break;

            case 'roadrunner':
                $output->writeln("  Mode: RoadRunner Worker");
                $output->writeln("  Config: .rr.yaml");
                break;

            case 'fpm':
                $output->writeln("  Mode: PHP-FPM");
                $output->writeln("  Note: Make sure your web server is configured properly");
                break;
        }

        $output->writeln('');
        $output->writeln('<comment>Press Ctrl+C to stop the server</comment>');
        $output->writeln('');
    }
}
