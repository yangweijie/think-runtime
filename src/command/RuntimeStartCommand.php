<?php

namespace think\runtime\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use think\runtime\RuntimeManager;

class RuntimeStartCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('runtime:start')
            ->setDescription('Start the runtime server')
            ->addArgument('runtime', InputArgument::OPTIONAL, 'The runtime to start (swoole, frankenphp, reactphp, ripple, roadrunner)')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host to listen on')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'The port to listen on')
            ->addOption('workers', null, InputOption::VALUE_OPTIONAL, 'The number of worker processes')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug mode')
            ->addOption('daemon', null, InputOption::VALUE_NONE, 'Run the server in daemon mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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

        if ($workers) {
            $options['worker_num'] = $workers;
        }

        if ($debug) {
            $options['debug'] = true;
        }

        if ($daemon) {
            $options['daemon'] = true;
        }

        $runtimeManager = new RuntimeManager();

        if ($runtime) {
            $runtimeManager->setRuntime($runtime);
        }

        $options = $this->buildStartOptions($runtime, $options);

        $runtimeManager->start($options);

        $this->displayStartupInfo($output, $runtimeManager, $options);

        return Command::SUCCESS;
    }

    protected function buildStartOptions(?string $runtime, array $options): array
    {
        if ($runtime === 'frankenphp') {
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
        } elseif ($runtime === 'reactphp') {
            if (isset($options['worker_num'])) {
                unset($options['worker_num']);
            }

            if (isset($options['debug'])) {
                $options['debug'] = true;
            }
        } elseif ($runtime === 'ripple') {
            if (isset($options['worker_num'])) {
                $options['worker_num'] = (int) $options['worker_num'];
            }

            if (isset($options['debug'])) {
                $options['debug'] = true;
            }
        } elseif ($runtime === 'roadrunner') {
            if (isset($options['host']) || isset($options['port'])) {
                unset($options['host'], $options['port']);
            }

            if (isset($options['worker_num'])) {
                unset($options['worker_num']);
            }

            if (isset($options['debug'])) {
                $options['debug'] = true;
            }
        }

        return $options;
    }

    protected function displayStartupInfo(OutputInterface $output, RuntimeManager $runtimeManager, array $options): void
    {
        $runtime = $runtimeManager->getRuntimeInfo();
        $runtimeName = $runtime['name'];

        $output->writeln('<info>ThinkPHP Runtime Server started!</info>');
        $output->writeln('');

        if ($runtimeName === 'swoole') {
            $output->writeln('<comment>Mode: Swoole</comment>');
            $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
            $output->writeln('<comment>Port: ' . ($options['port'] ?? '9501') . '</comment>');
            $output->writeln('<comment>Workers: ' . ($options['worker_num'] ?? '4') . '</comment>');
            $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
            $output->writeln('<comment>Daemon: ' . (($options['daemon'] ?? false) ? 'true' : 'false') . '</comment>');
        } elseif ($runtimeName === 'frankenphp') {
            $output->writeln('<comment>Mode: FrankenPHP</comment>');
            $output->writeln('<comment>Listen: ' . ($options['listen'] ?? ':8080') . '</comment>');
            $output->writeln('<comment>Workers: ' . ($options['worker_num'] ?? '4') . '</comment>');
            $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
        } elseif ($runtimeName === 'reactphp') {
            $output->writeln('<comment>Mode: ReactPHP</comment>');
            $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
            $output->writeln('<comment>Port: ' . ($options['port'] ?? '8080') . '</comment>');
            $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
        } elseif ($runtimeName === 'ripple') {
            $output->writeln('<comment>Mode: Ripple</comment>');
            $output->writeln('<comment>Host: ' . ($options['host'] ?? '0.0.0.0') . '</comment>');
            $output->writeln('<comment>Port: ' . ($options['port'] ?? '8080') . '</comment>');
            $output->writeln('<comment>Workers: ' . ($options['worker_num'] ?? '4') . '</comment>');
            $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
        } elseif ($runtimeName === 'roadrunner') {
            $output->writeln('<comment>Mode: RoadRunner</comment>');
            $output->writeln('<comment>Debug: ' . (($options['debug'] ?? false) ? 'true' : 'false') . '</comment>');
            $output->writeln('<comment>Note: RoadRunner server must be started separately</comment>');
        }

        $output->writeln('');
        $output->writeln('<info>Use Ctrl+C to stop the server</info>');
    }
}
