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
             ->addArgument('runtime', Argument::OPTIONAL, 'The runtime to start (swoole, frankenphp, workerman, reactphp, roadrunner, bref, vercel)')
             ->addOption('host', null, Option::VALUE_OPTIONAL, 'The host to listen on')
             ->addOption('port', null, Option::VALUE_OPTIONAL, 'The port to listen on')
             ->addOption('workers', null, Option::VALUE_OPTIONAL, 'The number of worker processes')
             ->addOption('debug', null, Option::VALUE_NONE, 'Enable debug mode')
             ->addOption('daemon', null, Option::VALUE_NONE, 'Run the server in daemon mode')
             ->addOption('hide-index', null, Option::VALUE_NONE, 'Hide index.php in URLs (FrankenPHP only)')
             ->addOption('show-index', null, Option::VALUE_NONE, 'Show index.php in URLs (FrankenPHP only)')
             ->addOption('https', null, Option::VALUE_NONE, 'Enable HTTPS (FrankenPHP only)')
             ->addOption('no-rewrite', null, Option::VALUE_NONE, 'Disable URL rewriting (FrankenPHP only)');
    }

    protected function execute(Input $input, Output $output): int
    {
        $runtime = $input->getArgument('runtime');
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $workers = $input->getOption('workers');
        $debug = $input->getOption('debug');
        $daemon = $input->getOption('daemon');
        $hideIndex = $input->getOption('hide-index');
        $showIndex = $input->getOption('show-index');
        $https = $input->getOption('https');
        $noRewrite = $input->getOption('no-rewrite');

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
            $options['daemonize'] = true;
        }

        // FrankenPHP 特有选项
        if ($hideIndex) {
            $options['hide_index'] = true;
        }

        if ($showIndex) {
            $options['hide_index'] = false;
        }

        if ($https) {
            $options['auto_https'] = true;
        }

        if ($noRewrite) {
            $options['enable_rewrite'] = false;
        }

        // 新增：支持 fpm runtime 的内置服务启动
        if ($runtime === 'fpm') {
            $output->writeln('<info>Starting FPM runtime (内置服务)...</info>');
            // 直接用 RuntimeManager 的 start 方法统一启动
            $app = $this->app;
            $config = $app->make('runtime.config');
            $runtimeManager = new RuntimeManager($app, $config);
            $runtimeManager->start('fpm', $options);
            $output->writeln('<info>FPM runtime started (模拟模式)。如需生产环境请用 Nginx/Apache 启动 FPM。</info>');
            return 0;
        }

        // 获取应用实例和配置
        $app = $this->app;
        $config = $app->make('runtime.config');
        $runtimeManager = new RuntimeManager($app, $config);

        $options = $this->buildStartOptions($runtime, $options);

        // 特殊处理 RoadRunner：生成 worker.php 文件而不是直接启动
        if ($runtime === 'roadrunner') {
            $this->generateRoadRunnerWorker($output, $options);
            return 0;
        }

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
                $output->writeln('<comment>Hide Index: ' . (($options['hide_index'] ?? true) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>URL Rewrite: ' . (($options['enable_rewrite'] ?? true) ? 'true' : 'false') . '</comment>');
                $output->writeln('<comment>Auto HTTPS: ' . (($options['auto_https'] ?? false) ? 'true' : 'false') . '</comment>');

                // 显示访问URL示例
                $port = str_replace(':', '', $options['listen'] ?? ':8080');
                $protocol = ($options['auto_https'] ?? false) ? 'https' : 'http';
                $hideIndex = $options['hide_index'] ?? true;

                $output->writeln('');
                $output->writeln('<info>Access URLs:</info>');
                if ($hideIndex) {
                    $output->writeln('<comment>  ' . $protocol . '://localhost' . $port . '/</comment>');
                    $output->writeln('<comment>  ' . $protocol . '://localhost' . $port . '/index/hello</comment>');
                    $output->writeln('<comment>  ' . $protocol . '://localhost' . $port . '/api/user/list</comment>');
                } else {
                    $output->writeln('<comment>  ' . $protocol . '://localhost' . $port . '/index.php</comment>');
                    $output->writeln('<comment>  ' . $protocol . '://localhost' . $port . '/index.php/index/hello</comment>');
                }
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
                $output->writeln('<comment>Port: ' . ($options['port'] ?? '8000') . '</comment>');
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

    /**
     * 生成 RoadRunner worker.php 文件
     *
     * @param Output $output
     * @param array $options
     * @return void
     */
    protected function generateRoadRunnerWorker(Output $output, array $options): void
    {
        $workerContent = $this->getRoadRunnerWorkerTemplate($options);
        
        // 在项目根目录生成 worker.php
        $workerPath = getcwd() . '/worker.php';
        
        if (file_put_contents($workerPath, $workerContent) !== false) {
            $output->writeln('<info>RoadRunner worker.php generated successfully!</info>');
            $output->writeln('');
            $output->writeln('<comment>File: ' . $workerPath . '</comment>');
            $output->writeln('');
            $output->writeln('<info>To start RoadRunner server:</info>');
            $output->writeln('<comment>1. Install RoadRunner binary: https://roadrunner.dev/docs/installation</comment>');
            $output->writeln('<comment>2. Create .rr.yaml configuration file</comment>');
            $output->writeln('<comment>3. Run: rr serve</comment>');
            $output->writeln('');
            $output->writeln('<info>Example .rr.yaml configuration:</info>');
            $output->writeln($this->getRoadRunnerConfigExample());
        } else {
            $output->writeln('<error>Failed to generate worker.php file!</error>');
        }
    }

    /**
     * 获取 RoadRunner worker.php 模板
     *
     * @param array $options
     * @return string
     */
    protected function getRoadRunnerWorkerTemplate(array $options): string
    {
        $debug = $options['debug'] ?? false;
        $debugCode = $debug ? 'error_reporting(E_ALL);' : 'error_reporting(0);';
        $optionsCode = var_export($options, true);
        
        return "<?php

declare(strict_types=1);

{$debugCode}

// 引入 ThinkPHP 框架
require_once __DIR__ . '/vendor/autoload.php';

use yangweijie\\thinkRuntime\\runtime\\RuntimeManager;

// 创建应用实例
\$app = new \\think\\App();
\$app->initialize();

// 获取运行时配置
\$config = \$app->make('runtime.config');

// 创建运行时管理器
\$runtimeManager = new RuntimeManager(\$app, \$config);

// 启动 RoadRunner 适配器
\$runtimeManager->start('roadrunner', {$optionsCode});
";
    }

    /**
     * 获取 RoadRunner 配置示例
     *
     * @return string
     */
    protected function getRoadRunnerConfigExample(): string
    {
        return '<comment>
version: "3"

server:
  command: "php worker.php"
  user: ""
  group: ""
  env:
    - RR_MODE: http

http:
  address: 0.0.0.0:8080
  middleware: []
  uploads:
    forbid: [".php", ".exe", ".bat"]
  trusted_subnets: []

logs:
  mode: development
  level: error
</comment>';
    }
}
