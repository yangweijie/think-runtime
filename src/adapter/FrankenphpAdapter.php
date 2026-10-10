<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\adapter;

use Exception;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use yangweijie\thinkRuntime\contract\AdapterInterface;
use yangweijie\thinkRuntime\runtime\AbstractRuntime;
use yangweijie\thinkRuntime\config\CaddyConfigBuilder;

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
        'auto_https' => false,  // 默认关闭HTTPS，适合开发环境
        'http2' => true,
        'http3' => false,
        'debug' => null,  // 将从app_debug环境变量自动检测
        'access_log' => true,
        'error_log' => true,
        'log_level' => 'INFO',
        'root' => 'public',
        'index' => 'index.php',
        'log_dir' => null,  // 将从ThinkPHP配置自动检测
        'enable_rewrite' => true,  // 启用URL重写
        'hide_index' => true,  // 隐藏入口文件
        'env' => [],
        // 新增配置选项
        'use_json_config' => false,  // 是否使用JSON配置格式
        'use_fastcgi' => false,  // 是否使用FastCGI（FrankenPHP通常不需要）
        'fastcgi_address' => '127.0.0.1:9000',  // FastCGI地址
        'hosts' => ['localhost'],  // 主机名列表
        'enable_gzip' => true,  // 启用Gzip压缩
        'enable_file_server' => true,  // 启用文件服务器
        'static_extensions' => ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'pdf', 'txt', 'xml'],
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

        // 自动检测配置
        $this->autoDetectConfig();

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

        echo "🚀 FrankenPHP Server for ThinkPHP starting...\n";
        echo "📡 Listening on: {$config['listen']}\n";
        echo "📁 Document root: {$config['root']}\n";
        echo "👥 Workers: {$config['worker_num']}\n";
        echo "🔄 Max requests per worker: " . ($config['max_requests'] > 0 ? $config['max_requests'] : 'Unlimited') . "\n";

        // 配置 PHP 错误处理设置
        $this->configureErrorHandling($config);

        // 详细显示 Debug 模式和错误报告设置
        $this->displayDebugInfo($config);

        echo "📝 Log directory: {$config['log_dir']}\n";
        echo "🔗 URL rewrite: " . ($config['enable_rewrite'] ? 'ON' : 'OFF') . "\n";
        echo "🔒 Hide index: " . ($config['hide_index'] ? 'ON' : 'OFF') . "\n";
        echo "📄 Config format: " . ($config['use_json_config'] ? 'JSON' : 'Caddyfile') . "\n";
        echo "🗜️  Gzip compression: " . ($config['enable_gzip'] ? 'ON' : 'OFF') . "\n";
        echo "📂 File server: " . ($config['enable_file_server'] ? 'ON' : 'OFF') . "\n";
        echo "🌐 Hosts: " . implode(', ', $config['hosts']) . "\n";
        echo "⏱️  Execution time: Unlimited\n";
        echo "💾 Memory limit: " . ini_get('memory_limit') . "\n";

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
     * 配置 PHP 错误处理设置
     *
     * @param array $config
     * @return void
     */
    protected function configureErrorHandling(array $config): void
    {
        if ($config['debug']) {
            // Debug 模式：启用详细的错误报告
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            ini_set('log_errors', '1');

            // 设置详细的错误日志
            if (!empty($config['log_dir'])) {
                $errorLogPath = rtrim($config['log_dir'], '/') . '/php_errors.log';
                ini_set('error_log', $errorLogPath);
            }

            echo "⚙️  PHP 错误处理已配置为开发模式\n";
        } else {
            // 生产模式：隐藏错误显示，但保持错误记录
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            ini_set('log_errors', '1');

            // 设置生产环境错误日志
            if (!empty($config['log_dir'])) {
                $errorLogPath = rtrim($config['log_dir'], '/') . '/php_errors.log';
                ini_set('error_log', $errorLogPath);
            }

            echo "⚙️  PHP 错误处理已配置为生产模式\n";
        }
    }

    /**
     * 显示详细的调试信息
     *
     * @param array $config
     * @return void
     */
    protected function displayDebugInfo(array $config): void
    {
        // 获取当前 error_reporting 设置
        $errorReporting = error_reporting();
        $errorReportingText = $this->getErrorReportingText($errorReporting);

        // 获取其他 PHP 设置
        $displayErrors = ini_get('display_errors') ? 'ON' : 'OFF';
        $logErrors = ini_get('log_errors') ? 'ON' : 'OFF';

        if ($config['debug']) {
            echo "🐛 Debug mode: \033[1;33mON\033[0m (Development)\n";
            echo "   ├─ Error reporting: \033[1;33m{$errorReportingText}\033[0m\n";
            echo "   ├─ Display errors: \033[1;33m{$displayErrors}\033[0m\n";
            echo "   ├─ Log errors: \033[1;32m{$logErrors}\033[0m\n";
            echo "   ├─ Log level: \033[1;33mDEBUG\033[0m\n";
            echo "   └─ Error display: \033[1;33mDetailed\033[0m\n";
        } else {
            echo "🐛 Debug mode: \033[1;32mOFF\033[0m (Production)\n";
            echo "   ├─ Error reporting: \033[1;32m{$errorReportingText}\033[0m\n";
            echo "   ├─ Display errors: \033[1;32m{$displayErrors}\033[0m\n";
            echo "   ├─ Log errors: \033[1;32m{$logErrors}\033[0m\n";
            echo "   ├─ Log level: \033[1;32mINFO\033[0m\n";
            echo "   └─ Error display: \033[1;32mSimple\033[0m\n";
        }
    }

    /**
     * 获取 error_reporting 的文本描述
     *
     * @param int $errorReporting
     * @return string
     */
    protected function getErrorReportingText(int $errorReporting): string
    {
        if ($errorReporting === 0) {
            return 'OFF (0)';
        }

        if ($errorReporting === E_ALL) {
            return 'ALL (' . E_ALL . ')';
        }

        $levels = [];

        if ($errorReporting & E_ERROR) $levels[] = 'ERROR';
        if ($errorReporting & E_WARNING) $levels[] = 'WARNING';
        if ($errorReporting & E_PARSE) $levels[] = 'PARSE';
        if ($errorReporting & E_NOTICE) $levels[] = 'NOTICE';
        if ($errorReporting & E_CORE_ERROR) $levels[] = 'CORE_ERROR';
        if ($errorReporting & E_CORE_WARNING) $levels[] = 'CORE_WARNING';
        if ($errorReporting & E_COMPILE_ERROR) $levels[] = 'COMPILE_ERROR';
        if ($errorReporting & E_COMPILE_WARNING) $levels[] = 'COMPILE_WARNING';
        if ($errorReporting & E_USER_ERROR) $levels[] = 'USER_ERROR';
        if ($errorReporting & E_USER_WARNING) $levels[] = 'USER_WARNING';
        if ($errorReporting & E_USER_NOTICE) $levels[] = 'USER_NOTICE';
        if ($errorReporting & E_STRICT) $levels[] = 'STRICT';
        if ($errorReporting & E_RECOVERABLE_ERROR) $levels[] = 'RECOVERABLE_ERROR';
        if ($errorReporting & E_DEPRECATED) $levels[] = 'DEPRECATED';
        if ($errorReporting & E_USER_DEPRECATED) $levels[] = 'USER_DEPRECATED';

        if (empty($levels)) {
            return "CUSTOM ({$errorReporting})";
        }

        return implode('|', $levels) . " ({$errorReporting})";
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
     * 自动检测配置
     *
     * @return void
     */
    protected function autoDetectConfig(): void
    {
        // 检测调试模式
        if ($this->config['debug'] === null) {
            $appDebug = $this->app->env->get('app_debug', false);
            $this->config['debug'] = (bool) $appDebug;
        }

        // 检测日志目录
        if (!isset($this->config['log_dir']) || $this->config['log_dir'] === null) {
            try {
                $logPath = $this->app->getRuntimePath() . 'log';
                if (!is_dir($logPath)) {
                    $logPath = $this->app->getBasePath() . 'runtime/log';
                }
            } catch (Exception $e) {
                // 如果无法获取应用路径，使用默认路径
                $logPath = getcwd() . '/runtime/log';
            }
            $this->config['log_dir'] = $logPath;
        }

        // 确保日志目录存在
        if (!is_dir($this->config['log_dir'])) {
            mkdir($this->config['log_dir'], 0755, true);
        }
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

        // 检查是否使用JSON配置
        $useJsonConfig = $config['use_json_config'] ?? false;

        if ($useJsonConfig) {
            // 创建JSON配置
            $jsonConfig = $this->createCaddyJsonConfig($config);
            $configPath = getcwd() . '/caddy-config.json';
            file_put_contents($configPath, $jsonConfig);
            echo "📄 Created Caddy JSON config: {$configPath}\n";
        } else {
            // 创建Caddyfile
            $caddyfile = $this->createCaddyfile($config);
            $configPath = getcwd() . '/Caddyfile.thinkphp';
            file_put_contents($configPath, $caddyfile);
            echo "📄 Created Caddyfile: {$configPath}\n";
        }

        echo "⚙️  Created PHP config: {$phpIniPath}\n";
        echo "🎯 ThinkPHP URL patterns:\n";
        $port = $config['listen'];
        $protocol = $config['auto_https'] ? 'https' : 'http';
        if ($config['hide_index']) {
            echo "   - {$protocol}://localhost{$port}/\n";
            echo "   - {$protocol}://localhost{$port}/index/hello\n";
            echo "   - {$protocol}://localhost{$port}/api/user/list\n";
        } else {
            echo "   - {$protocol}://localhost{$port}/index.php\n";
            echo "   - {$protocol}://localhost{$port}/index.php/index/hello\n";
        }
        echo "🚀 Starting FrankenPHP process...\n\n";

        // 构建启动命令
        if ($useJsonConfig) {
            $command = "{$frankenphpBinary} run --config {$configPath} --adapter json";
        } else {
            $command = "{$frankenphpBinary} run --config {$configPath}";
        }

        // 注意：FrankenPHP 的调试模式通过配置文件设置，不是命令行参数

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
        $this->cleanupTempFiles($configPath, $phpIniPath);

        if ($exitCode !== 0) {
            throw new RuntimeException("FrankenPHP process exited with code {$exitCode}");
        }
    }

    /**
     * 处理FrankenPHP响应头部
     * 使用头部去重服务处理响应头部，防止重复
     *
     * @param ResponseInterface $response PSR-7响应对象
     * @return ResponseInterface 处理后的PSR-7响应对象
     */
    protected function processFrankenPHPResponseHeaders(ResponseInterface $response): ResponseInterface
    {
        // 构建FrankenPHP运行时头部
        $runtimeHeaders = $this->buildRuntimeHeaders();
        
        // 添加FrankenPHP特定头部
        $runtimeHeaders['X-Powered-By'] = 'FrankenPHP/ThinkPHP-Runtime';
        $runtimeHeaders['X-Runtime'] = 'FrankenPHP';
        
        // 使用头部去重服务处理所有头部
        $finalHeaders = $this->processResponseHeaders($response, $runtimeHeaders);
        
        // 创建新的响应对象，先移除所有现有头部
        $newResponse = $response;
        foreach ($response->getHeaders() as $name => $values) {
            $newResponse = $newResponse->withoutHeader($name);
        }
        
        // 添加去重后的头部
        foreach ($finalHeaders as $name => $value) {
            $newResponse = $newResponse->withHeader($name, $value);
        }
        
        return $newResponse;
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
        // FrankenPHP 使用简化的配置，不需要 Worker 脚本
        // 直接使用 php_server 指令处理 ThinkPHP
        return $this->buildFrankenPHPCaddyfile($config, null);
    }

    /**
     * 构建专门为 FrankenPHP 优化的 Caddyfile
     *
     * @param array $config
     * @param string|null $workerScript
     * @return string
     */
    protected function buildFrankenPHPCaddyfile(array $config, ?string $workerScript = null): string
    {
        $listen = $config['listen'];
        $root = $config['root'];
        $index = $config['index'];

        // 确保 root 路径是绝对路径
        $absoluteRoot = $this->getAbsolutePath($root);

        // 🔥 FrankenPHP ThinkPHP 配置
        // 基于 ThinkPHP 官方推荐的 Nginx 配置转换为 Caddy 配置
        // 验证了 s= 参数路由确实工作，问题在于 try_files 的参数传递
        $caddyfile = "{\n";
        if (!$config['auto_https']) {
            $caddyfile .= "    # 禁用自动 HTTPS（开发环境）\n";
            $caddyfile .= "    auto_https off\n";
        }
        $caddyfile .= "}\n\n";

        $caddyfile .= "{$listen} {\n";
        $caddyfile .= "    # 设置根目录\n";
        $caddyfile .= "    root * {$absoluteRoot}\n";
        $caddyfile .= "    \n";
        $caddyfile .= "    # 🔥 ThinkPHP 专用配置：使用 try_files 指令\n";
        $caddyfile .= "    # 这是 ThinkPHP 官方推荐的 Nginx 配置的 Caddy 等价物\n";
        $caddyfile .= "    # try_files \$uri \$uri/ /{$index}?\$args;\n";
        $caddyfile .= "    try_files {path} {path}/ /{$index}?s={path}&{query}\n";
        $caddyfile .= "    \n";
        $caddyfile .= "    # 处理 PHP 文件\n";
        $caddyfile .= "    php\n";
        $caddyfile .= "    \n";
        $caddyfile .= "    # 处理静态文件\n";
        $caddyfile .= "    file_server\n";
        $caddyfile .= "}\n";

        return $caddyfile;
    }

    /**
     * 获取绝对路径
     *
     * @param string $path
     * @return string
     */
    protected function getAbsolutePath(string $path): string
    {
        // 如果已经是绝对路径，直接返回
        if (str_starts_with($path, '/') || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:/', $path))) {
            return $path;
        }

        // 处理相对路径
        $currentDir = getcwd();

        // 清理路径中的 ./ 和 ../
        $path = ltrim($path, './');

        // 如果路径以 ../ 开头，需要特殊处理
        if (str_starts_with($path, '../')) {
            // 简单处理：移除 ../ 前缀，在当前目录下创建路径
            $path = ltrim($path, '../');
        }

        return $currentDir . '/' . $path;
    }

    /**
     * 创建Caddy JSON配置
     *
     * @param array $config
     * @return string
     */
    protected function createCaddyJsonConfig(array $config): string
    {
        // 准备Worker脚本路径（如果需要）
        $workerScript = null;
        if ($config['worker_num'] > 0) {
            $workerScript = $this->createWorkerScript();
        }

        // 使用优化的CaddyConfigBuilder构建JSON配置
        $builder = CaddyConfigBuilder::fromArray(array_merge($config, [
            'worker_script' => $workerScript,
            'hosts' => ['localhost'],
            'use_fastcgi' => false,
            'enable_gzip' => true,
            'enable_file_server' => true,
        ]));

        return $builder->build();
    }



    /**
     * 创建Worker脚本文件
     *
     * @return string Worker脚本文件路径
     */
    protected function createWorkerScript(): string
    {
        $workerScript = getcwd() . '/frankenphp-worker.php';

        $config = array_merge($this->defaultConfig, $this->config);
        // 优先使用配置中的 debug 设置，如果没有则从环境变量获取
        $debugValue = $config['debug'] ?? $this->app->env->get('app_debug', false);
        $appDebug = $debugValue ? 'true' : 'false';
        $logDir = $config['log_dir'] ?? 'runtime/log';

        $content = '<?php
// FrankenPHP Worker Script for ThinkPHP
// Auto-generated by think-runtime

declare(strict_types=1);

// 错误报告配置
$appDebug = ' . $appDebug . ';

if (!$appDebug) {
    // 生产环境：完全禁用错误输出
    ini_set("display_errors", "0");
    ini_set("display_startup_errors", "0");
    ini_set("html_errors", "0");
    ini_set("log_errors", "1");
    ini_set("error_log", "' . $logDir . '/frankenphp_php_error.log");

    // 只报告致命错误
    error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);

    // 自定义错误处理器抑制非致命错误
    set_error_handler(function($severity, $message, $file, $line) {
        if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
            return false; // 让PHP处理致命错误
        }
        return true; // 抑制其他错误
    });
} else {
    // 开发环境：显示所有错误
    ini_set("display_errors", "1");
    ini_set("display_startup_errors", "1");
    ini_set("log_errors", "1");
    ini_set("error_log", "' . $logDir . '/frankenphp_php_error.log");
    error_reporting(E_ALL);
}

// 自动检测并加载 autoload.php
$autoloadPaths = [
    __DIR__ . "/vendor/autoload.php",
    __DIR__ . "/../vendor/autoload.php",
    __DIR__ . "/../../vendor/autoload.php",
    __DIR__ . "/../../../vendor/autoload.php",
];

$autoloadFound = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    die("Error: Could not find vendor/autoload.php. Please run \'composer install\' first.\\n");
}

use think\\App;

// 设置正确的工作目录 - 切换到 public 目录
$publicDir = __DIR__ . "/public";
if (is_dir($publicDir)) {
    chdir($publicDir);
}

// 初始化ThinkPHP应用 - 按照标准方式
$app = new App();
$http = $app->http;

// Worker模式主循环
for ($nbHandledRequests = 0, $running = true; $running; ++$nbHandledRequests) {
    $running = frankenphp_handle_request(function () use ($http): void {
        try {
            // 处理请求 - 使用标准的 ThinkPHP 方式
            $response = $http->run();

            // 发送响应
            $response->send();

        } catch (\\Throwable $e) {
            // 错误处理
            http_response_code(500);

            if ($appDebug) {
                // 开发环境：显示详细错误信息
                header("Content-Type: text/html; charset=utf-8");
                echo "<h1>FrankenPHP Worker Error</h1>";
                echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
                echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
                echo "<h2>Stack Trace:</h2>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            } else {
                // 生产环境：简单错误信息
                header("Content-Type: application/json");
                echo json_encode([
                    "error" => true,
                    "message" => "Internal Server Error",
                    "code" => 500
                ], JSON_UNESCAPED_UNICODE);
            }

            // 记录错误到日志
            error_log(sprintf(
                "[%s] FrankenPHP Worker Error: %s in %s:%d\nStack trace:\n%s",
                date("Y-m-d H:i:s"),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));
        }
    });

    // 垃圾回收和内存管理
    if ($nbHandledRequests % 100 === 0) {
        gc_collect_cycles();

        // 检查内存使用情况
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get("memory_limit");
        if ($memoryLimit !== "-1") {
            $memoryLimitBytes = parseMemoryLimit($memoryLimit);
            if ($memoryUsage > $memoryLimitBytes * 0.8) {
                // 内存使用超过80%，重启worker
                break;
            }
        }
    }
}

/**
 * 解析内存限制字符串
 */
function parseMemoryLimit($limit) {
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit)-1]);
    $limit = (int) $limit;
    switch($last) {
        case "g": $limit *= 1024;
        case "m": $limit *= 1024;
        case "k": $limit *= 1024;
    }
    return $limit;
}
';

        file_put_contents($workerScript, $content);

        // 语法错误测试
        $this->validateWorkerScriptSyntax($workerScript);

        return $workerScript;
    }

    /**
     * 验证 Worker 脚本语法
     *
     * @param string $workerScriptPath Worker 脚本路径
     * @return void
     * @throws RuntimeException 如果语法检查失败
     */
    protected function validateWorkerScriptSyntax(string $workerScriptPath): void
    {
        // 使用 php -l 检查语法
        $command = "php -l " . escapeshellarg($workerScriptPath) . " 2>&1";
        $output = shell_exec($command);

        if ($output === null) {
            throw new RuntimeException("Failed to execute syntax check command for Worker script");
        }

        // 检查是否有语法错误
        if (!str_contains($output, 'No syntax errors detected')) {
            // 清理生成的文件（如果有语法错误）
            if (file_exists($workerScriptPath)) {
                unlink($workerScriptPath);
            }

            // 解析错误信息
            $errorLines = explode("\n", trim($output));
            $errorMessage = "Worker script syntax validation failed:\n";

            foreach ($errorLines as $line) {
                if (!empty(trim($line))) {
                    $errorMessage .= "  " . trim($line) . "\n";
                }
            }

            throw new RuntimeException($errorMessage);
        }

        // 额外的内容验证
        $this->validateWorkerScriptContent($workerScriptPath);
    }

    /**
     * 验证 Worker 脚本内容
     *
     * @param string $workerScriptPath Worker 脚本路径
     * @return void
     * @throws RuntimeException 如果内容验证失败
     */
    protected function validateWorkerScriptContent(string $workerScriptPath): void
    {
        $content = file_get_contents($workerScriptPath);

        if ($content === false) {
            throw new RuntimeException("Failed to read Worker script content for validation");
        }

        $validationErrors = [];

        // 检查必需的组件
        $requiredComponents = [
            'frankenphp_handle_request' => 'FrankenPHP handle request function call',
            'use think\\App' => 'ThinkPHP App class import',
            'new App()' => 'ThinkPHP App instantiation',
            'parseMemoryLimit' => 'Memory limit parsing function',
            'autoload.php' => 'Composer autoload inclusion',
        ];

        foreach ($requiredComponents as $component => $description) {
            if (!str_contains($content, $component)) {
                $validationErrors[] = "Missing required component: {$description} ({$component})";
            }
        }

        // 检查潜在的问题
        $potentialIssues = [
            '$this->' => 'Potential $this reference outside class context',
        ];

        // 检查动态 require/include（但排除我们的 autoload 检测逻辑）
        if (preg_match('/require_once\s+\$(?!autoloadPath)/', $content)) {
            $potentialIssues['require_once $'] = 'Dynamic require statement that might fail';
        }
        if (preg_match('/include_once\s+\$(?!autoloadPath)/', $content)) {
            $potentialIssues['include_once $'] = 'Dynamic include statement that might fail';
        }

        foreach ($potentialIssues as $pattern => $description) {
            if (is_int($pattern)) {
                // 这是一个已经检查过的动态模式，跳过
                continue;
            }
            if (str_contains($content, $pattern)) {
                $validationErrors[] = "Potential issue detected: {$description} ({$pattern})";
            }
        }

        // 检查语法结构
        $structureChecks = [
            'for (' => 'Main worker loop',
            'try {' => 'Error handling try block',
            'catch (' => 'Error handling catch block',
            'function parseMemoryLimit' => 'Memory limit parsing function definition',
        ];

        foreach ($structureChecks as $structure => $description) {
            if (!str_contains($content, $structure)) {
                $validationErrors[] = "Missing code structure: {$description} ({$structure})";
            }
        }

        // 如果有验证错误，抛出异常
        if (!empty($validationErrors)) {
            // 清理生成的文件
            if (file_exists($workerScriptPath)) {
                unlink($workerScriptPath);
            }

            $errorMessage = "Worker script content validation failed:\n";
            foreach ($validationErrors as $error) {
                $errorMessage .= "  - " . $error . "\n";
            }

            throw new RuntimeException($errorMessage);
        }
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

        // 构建运行时头部
        $runtimeHeaders = $this->buildRuntimeHeaders();

        // 使用头部去重服务处理所有头部
        $finalHeaders = $this->processResponseHeaders($response, $runtimeHeaders);

        // 发送去重后的响应头（多值头部如 Set-Cookie 需逐行追加）
        [$singleHeaders, $multiHeaders] = $this->splitMultiValueHeaders($finalHeaders);

        foreach ($singleHeaders as $name => $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }

        foreach ($multiHeaders as $name => $values) {
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
        $config = array_merge($this->defaultConfig, $this->config);

        http_response_code(500);

        if ($config['debug']) {
            // 开发模式：返回详细错误信息
            header('Content-Type: text/html; charset=utf-8');
            echo $this->renderDebugErrorPage($e);
        } else {
            // 生产模式：返回简单错误信息
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Internal Server Error',
                'code' => 500,
                'timestamp' => date('c'),
            ], JSON_UNESCAPED_UNICODE);
        }

        // 记录错误到日志
        $this->logError($e);
    }

    /**
     * 渲染调试错误页面
     *
     * @param Throwable $e 异常
     * @return string
     */
    protected function renderDebugErrorPage(Throwable $e): string
    {
        $trace = $e->getTraceAsString();
        $file = $e->getFile();
        $line = $e->getLine();
        $message = htmlspecialchars($e->getMessage());
        $class = get_class($e);

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FrankenPHP Runtime Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #dc3545; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { padding: 20px; }
        .error-info { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
        .trace { background: #f1f3f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        pre { margin: 0; white-space: pre-wrap; }
        .label { font-weight: bold; color: #495057; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 FrankenPHP Runtime Error</h1>
            <p>An error occurred while processing your request</p>
        </div>
        <div class="content">
            <div class="error-info">
                <p><span class="label">Exception:</span> {$class}</p>
                <p><span class="label">Message:</span> {$message}</p>
                <p><span class="label">File:</span> {$file}</p>
                <p><span class="label">Line:</span> {$line}</p>
                <p><span class="label">Time:</span> " . date('Y-m-d H:i:s') . "</p>
            </div>
            <h3>Stack Trace:</h3>
            <div class="trace">
                <pre>{$trace}</pre>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * 记录错误到日志
     *
     * @param Throwable $e 异常
     * @return void
     */
    protected function logError(Throwable $e): void
    {
        $config = array_merge($this->defaultConfig, $this->config);
        $logDir = $config['log_dir'] ?? 'runtime/log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/frankenphp_error.log';
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n\n",
            $timestamp,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * 获取运行时状态信息
     *
     * @return array
     */
    public function getStatus(): array
    {
        $config = array_merge($this->defaultConfig, $this->config);

        return [
            'name' => $this->getName(),
            'version' => $_SERVER['FRANKENPHP_VERSION'] ?? 'unknown',
            'status' => 'running',
            'config' => [
                'listen' => $config['listen'],
                'worker_num' => $config['worker_num'],
                'max_requests' => $config['max_requests'],
                'debug' => $config['debug'],
                'auto_https' => $config['auto_https'],
            ],
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => php_sapi_name(),
                'memory_limit' => ini_get('memory_limit'),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ],
            'system' => [
                'os' => PHP_OS_FAMILY,
                'timestamp' => time(),
                'uptime' => $this->getUptime(),
            ],
        ];
    }

    /**
     * 获取运行时间
     *
     * @return int
     */
    protected function getUptime(): int
    {
        static $startTime = null;
        if ($startTime === null) {
            $startTime = time();
        }
        return time() - $startTime;
    }

    /**
     * 健康检查
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        try {
            // 检查基本功能
            if (!$this->isSupported()) {
                return false;
            }

            // 检查内存使用情况
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');

            if ($memoryLimit !== '-1') {
                $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
                if ($memoryUsage > $memoryLimitBytes * 0.9) {
                    return false; // 内存使用超过90%
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->logError($e);
            return false;
        }
    }

    /**
     * 解析内存限制字符串
     *
     * @param string $limit
     * @return int
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }

        return $limit;
    }
}
