<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;

/**
 * FrankenPHP适配器
 * 提供FrankenPHP应用服务器支持
 */
class FrankenphpAdapter extends AbstractRuntime implements AdapterInterface
{
    /**
     * 默认配置
     *
     * @var array
     */
    protected array $defaultConfig = [
        'listen' => ':8080',
        'worker_num' => 4,
        'max_requests' => 1000,
        'auto_https' => true,
        'http2' => true,
        'http3' => false,
        'debug' => false,
        'access_log' => true,
        'error_log' => true,
        'log_level' => 'INFO',
        'root' => 'public',
        'index' => 'index.php',
        'env' => [],
    ];

    /**
     * 启动适配器
     *
     * @return void
     */
    public function boot(): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('FrankenPHP is not available');
        }

        // 初始化应用
        $this->app->initialize();

        // 设置环境变量
        $this->setupEnvironment();
    }

    /**
     * 运行适配器
     *
     * @return void
     */
    public function run(): void
    {
        $this->boot();

        // 设置无限执行时间
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $config = array_merge($this->defaultConfig, $this->config);

        echo "FrankenPHP Server starting...\n";
        echo "Listening on: {$config['listen']}\n";
        echo "Document root: {$config['root']}\n";
        echo "Workers: {$config['worker_num']}\n";
        echo "Execution time: Unlimited\n";
        echo "Memory limit: " . ini_get('memory_limit') . "\n";

        // 检查是否在FrankenPHP环境中
        if (function_exists('frankenphp_handle_request')) {
            echo "Mode: FrankenPHP Worker\n";
            echo "Press Ctrl+C to stop the server\n\n";
            // 在FrankenPHP环境中，启动Worker模式
            $this->startWorkerMode();
        } else {
            echo "Mode: External FrankenPHP Process\n";
            echo "Press Ctrl+C to stop the server\n\n";
            // 不在FrankenPHP环境中，启动FrankenPHP进程
            $this->startFrankenphpProcess();
        }
    }

    /**
     * 启动运行时
     *
     * @param array $options 启动选项
     * @return void
     */
    public function start(array $options = []): void
    {
        $this->setConfig($options);
        $this->run();
    }

    /**
     * 停止运行时
     *
     * @return void
     */
    public function stop(): void
    {
        // FrankenPHP的停止通常由外部信号处理
        if (function_exists('frankenphp_stop')) {
            frankenphp_stop();
        }
    }

    /**
     * 停止适配器
     *
     * @return void
     */
    public function terminate(): void
    {
        $this->stop();
    }

    /**
     * 获取适配器名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'frankenphp';
    }

    /**
     * 检查运行时是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isSupported();
    }

    /**
     * 检查适配器是否支持当前环境
     *
     * @return bool
     */
    public function isSupported(): bool
    {
        // 检查是否在FrankenPHP环境中运行
        $inFrankenphp = isset($_SERVER['FRANKENPHP_VERSION']) ||
                       function_exists('frankenphp_handle_request') ||
                       getenv('FRANKENPHP_CONFIG') !== false;

        if ($inFrankenphp) {
            return true;
        }

        // 如果不在FrankenPHP环境中，检查是否可以启动FrankenPHP
        return $this->canStartFrankenphp();
    }

    /**
     * 检查是否可以启动FrankenPHP
     *
     * @return bool
     */
    protected function canStartFrankenphp(): bool
    {
        // 检查FrankenPHP二进制文件是否存在
        $frankenphpPaths = [
            '/usr/local/bin/frankenphp',
            '/usr/bin/frankenphp',
            'frankenphp', // 在PATH中
        ];

        foreach ($frankenphpPaths as $path) {
            if ($this->commandExists($path)) {
                return true;
            }
        }

        // 检查是否通过Composer安装
        if (file_exists('vendor/bin/frankenphp')) {
            return true;
        }

        return false;
    }

    /**
     * 检查命令是否存在
     *
     * @param string $command
     * @return bool
     */
    protected function commandExists(string $command): bool
    {
        $result = shell_exec("which $command 2>/dev/null");
        return !empty($result);
    }

    /**
     * 获取适配器优先级
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 95; // 高优先级，仅次于Swoole
    }

    /**
     * 获取运行时配置（合并默认配置）
     *
     * @return array
     */
    public function getConfig(): array
    {
        return array_merge($this->defaultConfig, $this->config);
    }

    /**
     * 设置环境变量
     *
     * @return void
     */
    protected function setupEnvironment(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 设置FrankenPHP相关环境变量
        if (!empty($config['env'])) {
            foreach ($config['env'] as $key => $value) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        // 设置基本配置
        putenv("FRANKENPHP_CONFIG=" . $this->buildFrankenphpConfig());

        // 设置Worker数量
        if (isset($config['worker_num'])) {
            putenv("FRANKENPHP_WORKER_NUM={$config['worker_num']}");
        }
    }

    /**
     * 构建FrankenPHP配置
     *
     * @return string
     */
    protected function buildFrankenphpConfig(): string
    {
        $config = array_merge($this->defaultConfig, $this->config);

        $frankenphpConfig = [
            'listen' => $config['listen'],
            'root' => $config['root'],
            'index' => $config['index'],
            'worker_num' => $config['worker_num'],
            'max_requests' => $config['max_requests'],
        ];

        if ($config['auto_https']) {
            $frankenphpConfig['auto_https'] = 'on';
        }

        if ($config['http2']) {
            $frankenphpConfig['http2'] = 'on';
        }

        if ($config['http3']) {
            $frankenphpConfig['http3'] = 'on';
        }

        return json_encode($frankenphpConfig);
    }

    /**
     * 启动FrankenPHP进程
     *
     * @return void
     */
    protected function startFrankenphpProcess(): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // 查找FrankenPHP二进制文件
        $frankenphpBinary = $this->findFrankenphpBinary();
        if (!$frankenphpBinary) {
            throw new RuntimeException('FrankenPHP binary not found. Please install FrankenPHP first.');
        }

        // 创建PHP配置文件来抑制弃用警告
        $phpIniPath = $this->createPhpIniFile();

        // 创建Caddyfile
        $caddyfile = $this->createCaddyfile($config);
        $caddyfilePath = getcwd() . '/Caddyfile.runtime';
        file_put_contents($caddyfilePath, $caddyfile);

        echo "Created Caddyfile: {$caddyfilePath}\n";
        echo "Created PHP config: {$phpIniPath}\n";
        echo "Starting FrankenPHP process...\n\n";

        // 构建启动命令
        $command = "{$frankenphpBinary} run --config {$caddyfilePath}";

        if ($config['debug']) {
            $command .= ' --debug';
        }

        // 启动FrankenPHP进程
        $process = proc_open($command, [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ], $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start FrankenPHP process');
        }

        // 等待进程结束
        $exitCode = proc_close($process);

        // 清理临时文件
        $this->cleanupTempFiles($caddyfilePath, $phpIniPath);

        if ($exitCode !== 0) {
            throw new RuntimeException("FrankenPHP process exited with code {$exitCode}");
        }
    }

    /**
     * 创建PHP配置文件
     *
     * @return string PHP配置文件路径
     */
    protected function createPhpIniFile(): string
    {
        $phpIniPath = getcwd() . '/frankenphp-php.ini';

        $content = '; FrankenPHP PHP Configuration
; Auto-generated by think-runtime

; 错误报告设置
error_reporting = E_ERROR & E_WARNING & E_PARSE
display_errors = Off
display_startup_errors = Off
log_errors = On
html_errors = Off

; 禁用弃用警告的输出
; 注意：我们不修改session.sid_length和session.sid_bits_per_character
; 让PHP使用默认值，避免触发新的警告

; 性能优化
memory_limit = 512M
max_execution_time = 0
max_input_time = -1

; Session配置（使用默认值，不修改弃用的设置）
session.auto_start = 0
session.use_cookies = 1
session.use_only_cookies = 1

; OPcache配置
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
';

        file_put_contents($phpIniPath, $content);
        return $phpIniPath;
    }

    /**
     * 清理临时文件
     *
     * @param string $caddyfilePath
     * @param string $phpIniPath
     * @return void
     */
    protected function cleanupTempFiles(string $caddyfilePath, string $phpIniPath): void
    {
        // 清理Caddyfile
        if (file_exists($caddyfilePath)) {
            unlink($caddyfilePath);
        }

        // 清理PHP配置文件
        if (file_exists($phpIniPath)) {
            unlink($phpIniPath);
        }

        // 清理Worker脚本文件
        $workerScript = getcwd() . '/frankenphp-worker.php';
        if (file_exists($workerScript)) {
            unlink($workerScript);
        }
    }

    /**
     * 查找FrankenPHP二进制文件
     *
     * @return string|null
     */
    protected function findFrankenphpBinary(): ?string
    {
        $paths = [
            '/usr/local/bin/frankenphp',
            '/usr/bin/frankenphp',
            'vendor/bin/frankenphp',
        ];

        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // 检查PATH中的frankenphp
        $result = shell_exec('which frankenphp 2>/dev/null');
        if (!empty($result)) {
            return trim($result);
        }

        return null;
    }

    /**
     * 创建Caddyfile配置
     *
     * @param array $config
     * @return string
     */
    protected function createCaddyfile(array $config): string
    {
        $listen = $config['listen'];
        $root = $config['root'];

        $caddyfile = "{$listen} {\n";
        $caddyfile .= "    root * {$root}\n";

        // 根据是否启用Worker模式选择不同的配置
        if ($config['worker_num'] > 0) {
            // Worker模式需要指定worker脚本文件
            $workerScript = $this->createWorkerScript();
            $caddyfile .= "    php_server {\n";
            $caddyfile .= "        worker {$workerScript}\n";
            $caddyfile .= "        env PHP_INI_SCAN_DIR /dev/null\n";  // 禁用额外的ini文件扫描
            $caddyfile .= "        env FRANKENPHP_NO_DEPRECATION_WARNINGS 1\n";  // 自定义环境变量
            $caddyfile .= "    }\n";
        } else {
            // 标准模式配置
            $caddyfile .= "    php_server {\n";
            $caddyfile .= "        env PHP_INI_SCAN_DIR /dev/null\n";  // 禁用额外的ini文件扫描
            $caddyfile .= "    }\n";
        }

        // TLS配置
        if ($config['auto_https']) {
            $caddyfile .= "    tls internal\n";
        } else {
            $caddyfile .= "    tls off\n";
        }

        // 日志配置
        if ($config['debug']) {
            $caddyfile .= "    log {\n";
            $caddyfile .= "        level DEBUG\n";
            $caddyfile .= "    }\n";
        }

        $caddyfile .= "}\n";

        return $caddyfile;
    }

    /**
     * 创建Worker脚本文件
     *
     * @return string Worker脚本文件路径
     */
    protected function createWorkerScript(): string
    {
        $workerScript = getcwd() . '/frankenphp-worker.php';

        $content = '<?php
// FrankenPHP Worker Script for ThinkPHP
// Auto-generated by think-runtime

// 完全禁用所有错误输出到浏览器/控制台
ini_set("display_errors", "0");
ini_set("display_startup_errors", "0");
ini_set("html_errors", "0");
ini_set("log_errors", "1");

// 只报告致命错误，忽略弃用警告
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);

// 设置自定义错误处理器来完全抑制弃用警告
set_error_handler(function($severity, $message, $file, $line) {
    // 只处理致命错误，忽略其他所有错误
    if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        return false; // 让PHP处理致命错误
    }
    return true; // 抑制其他所有错误
});

require_once __DIR__ . "/vendor/autoload.php";

use think\\App;
use Nyholm\\Psr7\\Factory\\Psr17Factory;
use Nyholm\\Psr7Server\\ServerRequestCreator;

// 初始化ThinkPHP应用
$app = new App();
$app->initialize();

// Worker模式主循环
for ($nbHandledRequests = 0, $running = true; $running; ++$nbHandledRequests) {
    $running = frankenphp_handle_request(function () use ($app): void {
        try {
            // 创建PSR-7请求
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory,
                $psr17Factory,
                $psr17Factory,
                $psr17Factory
            );
            $request = $creator->fromGlobals();

            // 创建运行时实例来处理请求
            $runtime = new static($app);

            // 使用正确的请求处理流程
            $psr7Response = $runtime->handleRequest($request);

            // 发送响应
            http_response_code($psr7Response->getStatusCode());

            foreach ($psr7Response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf("%s: %s", $name, $value), false);
                }
            }

            echo (string) $psr7Response->getBody();

        } catch (\\Throwable $e) {
            // 错误处理
            http_response_code(500);
            header("Content-Type: application/json");
            echo json_encode([
                "error" => true,
                "message" => $e->getMessage(),
                "file" => $e->getFile(),
                "line" => $e->getLine(),
            ], JSON_UNESCAPED_UNICODE);
        }
    });

    // 垃圾回收
    if ($nbHandledRequests % 100 === 0) {
        gc_collect_cycles();
    }
}
';

        file_put_contents($workerScript, $content);
        return $workerScript;
    }

    /**
     * 启动Worker模式
     *
     * @return void
     */
    protected function startWorkerMode(): void
    {
        // 检查是否支持Worker模式
        if (!function_exists('frankenphp_handle_request')) {
            // 如果不在FrankenPHP环境中，使用传统方式处理请求
            $this->handleTraditionalRequest();
            return;
        }

        // FrankenPHP Worker模式
        for ($nbHandledRequests = 0, $running = true; $running; ++$nbHandledRequests) {
            $running = frankenphp_handle_request(function (): void {
                try {
                    // 创建PSR-7请求
                    $psr7Request = $this->createPsr7RequestFromGlobals();

                    // 处理请求
                    $psr7Response = $this->handleRequest($psr7Request);

                    // 发送响应
                    $this->sendResponse($psr7Response);

                } catch (Throwable $e) {
                    $this->handleFrankenphpError($e);
                }
            });

            // 检查是否达到最大请求数
            $config = array_merge($this->defaultConfig, $this->config);
            if ($config['max_requests'] > 0 && $nbHandledRequests >= $config['max_requests']) {
                break;
            }

            // 垃圾回收
            if ($nbHandledRequests % 100 === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * 处理传统请求（非Worker模式）
     *
     * @return void
     */
    protected function handleTraditionalRequest(): void
    {
        try {
            // 创建PSR-7请求
            $psr7Request = $this->createPsr7RequestFromGlobals();

            // 处理请求
            $psr7Response = $this->handleRequest($psr7Request);

            // 发送响应
            $this->sendResponse($psr7Response);

        } catch (Throwable $e) {
            $this->handleFrankenphpError($e);
        }
    }

    /**
     * 从全局变量创建PSR-7请求
     *
     * @return ServerRequestInterface
     */
    protected function createPsr7RequestFromGlobals(): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        return $creator->fromGlobals();
    }

    /**
     * 发送响应
     *
     * @param ResponseInterface $response PSR-7响应
     * @return void
     */
    protected function sendResponse(ResponseInterface $response): void
    {
        // 发送状态码
        http_response_code($response->getStatusCode());

        // 发送响应头
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        // 发送响应体
        echo $response->getBody();
    }

    /**
     * 处理FrankenPHP错误
     *
     * @param Throwable $e 异常
     * @return void
     */
    protected function handleFrankenphpError(Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json');

        echo json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
