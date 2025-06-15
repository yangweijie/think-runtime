<?php

namespace yangweijie\thinkRuntime\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

class RuntimeStartCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('runtime:start')
             ->setDescription('Start the runtime server')
             ->addArgument('runtime', Argument::OPTIONAL, 'The runtime to start (swoole, frankenphp, workerman, reactphp, ripple, roadrunner, bref, vercel)')
             ->addOption('host', null, Option::VALUE_OPTIONAL, 'The host to listen on')
             ->addOption('port', null, Option::VALUE_OPTIONAL, 'The port to listen on')
             ->addOption('workers', null, Option::VALUE_OPTIONAL, 'The number of worker processes')
             ->addOption('debug', null, Option::VALUE_NONE, 'Enable debug mode')
             ->addOption('daemon', null, Option::VALUE_NONE, 'Run the server in daemon mode');
    }

    protected function execute(Input $input, Output $output): int
    {
        $runtime = $input->getArgument('runtime');
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $workers = $input->getOption('workers');
        $debug = $input->getOption('debug');
        $daemon = $input->getOption('daemon');

        $options = [];

        if ($host) {
            $options['host'] = $host;
        }

        if ($port) {
            $options['port'] = $port;
        }

        if ($workers !== null) {
            $options['worker_num'] = $workers;
        }

        if ($debug) {
            $options['debug'] = true;
        }

        if ($daemon) {
            $options['daemon'] = true;
        }

        // 获取应用实例和配置
        $app = $this->app;
        $config = $app->make('runtime.config');
        $runtimeManager = new RuntimeManager($app, $config);

        $options = $this->buildStartOptions($runtime, $options);

        $runtimeManager->start($runtime, $options);

        $this->displayStartupInfo($output, $runtimeManager, $options);

        return 0;
    }

    protected function buildStartOptions(?string $runtime, array $options): array
    {
        switch ($runtime) {
            case 'frankenphp':
                if (isset($options['host']) && isset($options['port'])) {
                    $options['listen'] = $options['host'] . ':' . $options['port'];
                    unset($options['host'], $options['port']);
                }

                if (isset($options['worker_num'])) {
                    $options['worker_num'] = (int) $options['worker_num'];
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'reactphp':
                if (isset($options['worker_num'])) {
                    unset($options['worker_num']);
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'ripple':
                if (isset($options['worker_num'])) {
                    $options['worker_num'] = (int) $options['worker_num'];
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'roadrunner':
                if (isset($options['host']) || isset($options['port'])) {
                    unset($options['host'], $options['port']);
                }

                if (isset($options['worker_num'])) {
                    unset($options['worker_num']);
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'workerman':
                if (isset($options['worker_num'])) {
                    $options['count'] = (int) $options['worker_num'];
                    unset($options['worker_num']);
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'bref':
                // Bref运行在AWS Lambda环境中，移除不适用的选项
                if (isset($options['host']) || isset($options['port'])) {
                    unset($options['host'], $options['port']);
                }

                if (isset($options['worker_num'])) {
                    unset($options['worker_num']);
                }

                if (isset($options['daemon'])) {
                    unset($options['daemon']);
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'vercel':
                // Vercel运行在serverless环境中，移除不适用的选项
                if (isset($options['host']) || isset($options['port'])) {
                    unset($options['host'], $options['port']);
                }

                if (isset($options['worker_num'])) {
                    unset($options['worker_num']);
                }

                if (isset($options['daemon'])) {
                    unset($options['daemon']);
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;

            case 'swoole':
            default:
                // 对于swoole和其他runtime，保持默认行为
                if (isset($options['worker_num'])) {
                    $options['worker_num'] = (int) $options['worker_num'];
                }

                if (isset($options['debug'])) {
                    $options['debug'] = true;
                }
                break;
        }

        return $options;
    }

    protected function displayStartupInfo(Output $output, RuntimeManager $runtimeManager, array $options): void
    {
        $runtime = $runtimeManager->getRuntimeInfo();
        $runtimeName = $runtime['name'];

        $output->writeln('<info>ThinkPHP Runtime Server started!</info>');
        $output->writeln('');

        switch ($runtimeName) {
            case 'swoole':
                $output->writeln('<comment>Mode: Swoole</comment>');
                $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
                $output->writeln('<comment>Port: ' . ($options['port'] ?? '9501') . '</comment>');
                $output->writeln('<comment>Workers: ' . ($options['worker_num'] ?? '4') . '</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>Daemon: ' . (($options['daemon'] ?? false) ? 'true' : 'false') . '</comment>');
                break;

            case 'frankenphp':
                $output->writeln('<comment>Mode: FrankenPHP</comment>');
                $output->writeln('<comment>Listen: ' . ($options['listen'] ?? ':8080') . '</comment>');
                $output->writeln('<comment>Workers: ' . ($options['worker_num'] ?? '4') . '</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                break;

            case 'workerman':
                $output->writeln('<comment>Mode: Workerman</comment>');
                $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
                $output->writeln('<comment>Port: ' . ($options['port'] ?? '8080') . '</comment>');
                $output->writeln('<comment>Workers: ' . ($options['count'] ?? '4') . '</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>Daemon: ' . (($options['daemon'] ?? false) ? 'true' : 'false') . '</comment>');
                break;

            case 'reactphp':
                $output->writeln('<comment>Mode: ReactPHP</comment>');
                $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
                $output->writeln('<comment>Port: ' . ($options['port'] ?? '8080') . '</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                break;

            case 'ripple':
                $output->writeln('<comment>Mode: Ripple</comment>');
                $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
                $output->writeln('<comment>Port: ' . ($options['port'] ?? '8080') . '</comment>');
                $output->writeln('<comment>Workers: ' . ($options['worker_num'] ?? '4') . '</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                break;

            case 'roadrunner':
                $output->writeln('<comment>Mode: RoadRunner</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>Note: RoadRunner server must be started separately</comment>');
                break;

            case 'bref':
                $output->writeln('<comment>Mode: Bref (AWS Lambda)</comment>');
                $output->writeln('<comment>Environment: Serverless</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>Note: Running in AWS Lambda environment</comment>');
                break;

            case 'vercel':
                $output->writeln('<comment>Mode: Vercel (Serverless Functions)</comment>');
                $output->writeln('<comment>Environment: Serverless</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>Note: Running in Vercel serverless environment</comment>');
                break;

            default:
                $output->writeln('<comment>Mode: ' . ucfirst($runtimeName) . '</comment>');
                $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
                break;
        }

        $output->writeln('');
        $output->writeln('<info>Use Ctrl+C to stop the server</info>');
    }
}
