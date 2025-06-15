<?php

namespace yangweijie\thinkRuntime\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

class RuntimeInfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('runtime:info')
             ->setDescription('Show runtime information');
    }

    protected function execute(Input $input, Output $output): int
    {
        // 获取应用实例和配置
        $app = $this->app;
        $config = $app->make('runtime.config');
        $runtimeManager = new RuntimeManager($app, $config);
        $runtimeInfo = $runtimeManager->getRuntimeInfo();

        $this->displaySystemInfo($output);
        $this->displayRuntimeInfo($output, $runtimeInfo);
        $this->displayAvailableRuntimes($output, $runtimeInfo['all_available']);
        $this->displayExtensionInfo($output);

        return 0;
    }

    protected function displaySystemInfo(Output $output): void
    {
        $output->writeln('<info>System Information</info>');
        $output->writeln('PHP Version: ' . PHP_VERSION);
        $output->writeln('OS: ' . PHP_OS);
        $output->writeln('SAPI: ' . PHP_SAPI);
        $output->writeln('');
    }

    protected function displayRuntimeInfo(Output $output, array $runtimeInfo): void
    {
        $output->writeln('<info>Current Runtime</info>');
        $output->writeln('Name: ' . $runtimeInfo['name']);
        $output->writeln('Available: ' . ($runtimeInfo['available'] ? 'Yes' : 'No'));
        $output->writeln('');
    }

    protected function displayAvailableRuntimes(Output $output, array $availableRuntimes): void
    {
        $output->writeln('<info>Available Runtimes</info>');

        $runtimes = [
            'swoole' => [
                'description' => 'High-performance PHP extension for building concurrent services',
            ],
            'frankenphp' => [
                'description' => 'Modern PHP application server',
            ],
            'workerman' => [
                'description' => 'High-performance PHP socket server framework',
            ],
            'reactphp' => [
                'description' => 'Event-driven, non-blocking I/O with PHP',
            ],
            'ripple' => [
                'description' => 'High-performance PHP application server with Fiber support',
            ],
            'roadrunner' => [
                'description' => 'High-performance PHP application server, load balancer, and process manager',
            ],
            'bref' => [
                'description' => 'Serverless PHP runtime for AWS Lambda',
            ],
            'vercel' => [
                'description' => 'Serverless PHP runtime for Vercel platform',
            ],
        ];

        foreach ($runtimes as $name => $info) {
            $available = in_array($name, $availableRuntimes);
            $status = $available ? '<fg=green>Available</>' : '<fg=red>Not Available</>';
            $output->writeln(sprintf('%-12s %s - %s', $name, $status, $info['description']));
        }

        $output->writeln('');
    }

    protected function displayExtensionInfo(Output $output): void
    {
        $output->writeln('<info>PHP Extensions</info>');

        $extensions = [
            'swoole' => [
                'required' => false,
                'loaded' => extension_loaded('swoole'),
                'version' => extension_loaded('swoole') ? phpversion('swoole') : 'N/A',
            ],
            'curl' => [
                'required' => true,
                'loaded' => extension_loaded('curl'),
                'version' => extension_loaded('curl') ? phpversion('curl') : 'N/A',
            ],
            'json' => [
                'required' => true,
                'loaded' => extension_loaded('json'),
                'version' => extension_loaded('json') ? phpversion('json') : 'N/A',
            ],
            'mbstring' => [
                'required' => true,
                'loaded' => extension_loaded('mbstring'),
                'version' => extension_loaded('mbstring') ? phpversion('mbstring') : 'N/A',
            ],
            'openssl' => [
                'required' => true,
                'loaded' => extension_loaded('openssl'),
                'version' => extension_loaded('openssl') ? phpversion('openssl') : 'N/A',
            ],
        ];

        foreach ($extensions as $name => $info) {
            $status = $info['loaded'] ? '<fg=green>Loaded</>' : '<fg=red>Not Loaded</>';
            $required = $info['required'] ? '<fg=yellow>(Required)</>' : '';
            $output->writeln(sprintf('%-12s %s %s - Version: %s', $name, $status, $required, $info['version']));
        }

        $output->writeln('');
    }
}
