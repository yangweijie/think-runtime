<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\config;

use mattvb91\CaddyPhp\Caddy;
use mattvb91\CaddyPhp\Config\Apps\Http;
use mattvb91\CaddyPhp\Config\Apps\Http\Server;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Route;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Handle\ReverseProxy;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Handle\ReverseProxy\Transport\FastCGI;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Handle\ReverseProxy\Upstream;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Handle\StaticResponse;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Handle\FileServer;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Handle\Subroute;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Match\Host;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Match\Path;
use mattvb91\CaddyPhp\Config\Apps\Http\Server\Routes\Match\File;

/**
 * Caddy 配置构建器
 *
 * 使用 mattvb91/caddy-php 包为 ThinkPHP + FrankenPHP 生成优化的 Caddy 配置
 * 支持高级功能如 FastCGI、反向代理、静态文件服务等
 */
class CaddyConfigBuilder
{
    protected string $listen = ':8080';
    protected string $root = 'public';
    protected string $index = 'index.php';
    protected bool $autoHttps = false;
    protected bool $debug = false;
    protected string $logDir = 'runtime/log';
    protected bool $enableRewrite = true;
    protected bool $hideIndex = true;
    protected array $env = [];
    protected ?string $workerScript = null;
    protected array $staticExtensions = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg',
        'woff', 'woff2', 'ttf', 'eot', 'pdf', 'txt', 'xml'
    ];
    protected bool $useFastCGI = false;
    protected string $fastCGIAddress = '127.0.0.1:9000';
    protected array $hosts = ['localhost'];
    protected bool $enableGzip = true;
    protected bool $enableFileServer = true;

    /**
     * 设置监听地址
     */
    public function setListen(string $listen): self
    {
        $this->listen = $listen;
        return $this;
    }

    /**
     * 设置文档根目录
     */
    public function setRoot(string $root): self
    {
        $this->root = $root;
        return $this;
    }

    /**
     * 设置入口文件
     */
    public function setIndex(string $index): self
    {
        $this->index = $index;
        return $this;
    }

    /**
     * 设置自动HTTPS
     */
    public function setAutoHttps(bool $autoHttps): self
    {
        $this->autoHttps = $autoHttps;
        return $this;
    }

    /**
     * 设置调试模式
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * 设置日志目录
     */
    public function setLogDir(string $logDir): self
    {
        $this->logDir = $logDir;
        return $this;
    }

    /**
     * 设置是否启用URL重写
     */
    public function setEnableRewrite(bool $enableRewrite): self
    {
        $this->enableRewrite = $enableRewrite;
        return $this;
    }

    /**
     * 设置是否隐藏入口文件
     */
    public function setHideIndex(bool $hideIndex): self
    {
        $this->hideIndex = $hideIndex;
        return $this;
    }

    /**
     * 设置环境变量
     */
    public function setEnv(array $env): self
    {
        $this->env = $env;
        return $this;
    }

    /**
     * 设置Worker脚本
     */
    public function setWorkerScript(?string $workerScript): self
    {
        $this->workerScript = $workerScript;
        return $this;
    }

    /**
     * 设置是否使用FastCGI
     */
    public function setUseFastCGI(bool $useFastCGI): self
    {
        $this->useFastCGI = $useFastCGI;
        return $this;
    }

    /**
     * 设置FastCGI地址
     */
    public function setFastCGIAddress(string $address): self
    {
        $this->fastCGIAddress = $address;
        return $this;
    }

    /**
     * 设置主机名
     */
    public function setHosts(array $hosts): self
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * 添加主机名
     */
    public function addHost(string $host): self
    {
        if (!in_array($host, $this->hosts)) {
            $this->hosts[] = $host;
        }
        return $this;
    }

    /**
     * 设置是否启用Gzip压缩
     */
    public function setEnableGzip(bool $enableGzip): self
    {
        $this->enableGzip = $enableGzip;
        return $this;
    }

    /**
     * 设置是否启用文件服务器
     */
    public function setEnableFileServer(bool $enableFileServer): self
    {
        $this->enableFileServer = $enableFileServer;
        return $this;
    }

    /**
     * 添加静态文件扩展名
     */
    public function addStaticExtension(string $extension): self
    {
        if (!in_array($extension, $this->staticExtensions)) {
            $this->staticExtensions[] = $extension;
        }
        return $this;
    }

    /**
     * 生成 Caddy 配置（JSON格式）
     */
    public function build(): string
    {
        $caddy = new Caddy();

        // 配置管理端口
        $caddy->getAdmin()->setListen(':2019');

        // 创建HTTP应用
        $http = new Http();

        // 创建服务器
        $server = new Server();
        $server->setListen([$this->listen]);

        // 添加路由
        $this->addRoutes($server);

        // 添加服务器到HTTP应用
        $http->addServer('thinkphp', $server);

        // 添加HTTP应用到Caddy
        $caddy->addApp($http);

        return json_encode($caddy->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * 生成 Caddyfile 配置（传统格式）
     */
    public function buildCaddyfile(): string
    {
        $config = [];

        // 站点块开始
        $config[] = "{$this->listen} {";

        // 基本配置
        $config[] = "    root * {$this->root}";

        // 自动HTTPS配置
        if (!$this->autoHttps) {
            $config[] = "    auto_https off";
        }

        // 日志配置
        $config = array_merge($config, $this->buildLogConfig());

        // 错误处理
        $config[] = "    handle_errors {";
        $config[] = "        respond \"Error {http.error.status_code}: {http.error.status_text}\"";
        $config[] = "    }";
        $config[] = "";

        // 编码压缩
        if ($this->enableGzip) {
            $config[] = "    encode gzip zstd";
            $config[] = "";
        }

        // URL重写和PHP处理
        if ($this->enableRewrite) {
            $config = array_merge($config, $this->buildRewriteRules());
        }

        // 静态文件处理
        if ($this->enableFileServer) {
            $config[] = "    file_server";
        }

        // 站点块结束
        $config[] = "}";

        return implode("\n", $config);
    }

    /**
     * 添加路由到服务器
     */
    protected function addRoutes(Server $server): void
    {
        // 主机匹配路由
        $mainRoute = new Route();

        // 添加主机匹配
        if (!empty($this->hosts)) {
            $hostMatch = new Host('thinkphp_hosts');
            $hostMatch->setHosts($this->hosts);
            $mainRoute->addMatch($hostMatch);
        }

        // 创建子路由
        $subroute = new Subroute();

        // 添加静态文件路由
        if ($this->enableFileServer) {
            $this->addStaticFileRoute($subroute);
        }

        // 添加ThinkPHP特定路由
        $this->addThinkPHPRoutes($subroute);

        // 添加PHP路由
        $this->addPhpRoute($subroute);

        // 添加默认路由（404处理）
        $this->addDefaultRoute($subroute);

        $mainRoute->addHandle($subroute);
        $server->addRoute($mainRoute);
    }

    /**
     * 添加静态文件路由
     */
    protected function addStaticFileRoute(Subroute $subroute): void
    {
        $staticRoute = new Route();

        // 文件存在匹配
        $fileMatch = new File();
        $fileMatch->setTryFiles(['{path}', '{path}/']);
        $staticRoute->addMatch($fileMatch);

        // 文件服务器处理
        $fileServer = new FileServer();
        $staticRoute->addHandle($fileServer);

        $subroute->addRoute($staticRoute);
    }

    /**
     * 添加PHP路由
     */
    protected function addPhpRoute(Subroute $subroute): void
    {
        $phpRoute = new Route();

        if ($this->useFastCGI) {
            // FastCGI模式
            $this->addFastCGIHandler($phpRoute);
        } else {
            // FrankenPHP Worker模式
            $this->addFrankenPHPHandler($phpRoute);
        }

        $subroute->addRoute($phpRoute);
    }

    /**
     * 添加FastCGI处理器
     */
    protected function addFastCGIHandler(Route $route): void
    {
        $reverseProxy = new ReverseProxy();

        // 添加上游服务器
        $upstream = new Upstream();
        $upstream->setDial($this->fastCGIAddress);
        $reverseProxy->addUpstream($upstream);

        // 配置FastCGI传输
        $fastCGI = new FastCGI();
        $fastCGI->setRoot($this->root . '/' . $this->index);
        $fastCGI->setSplitPath(['']);

        // 添加环境变量
        foreach ($this->env as $key => $value) {
            $fastCGI->addEnv($key, $value);
        }

        $reverseProxy->addTransport($fastCGI);
        $route->addHandle($reverseProxy);
    }

    /**
     * 添加FrankenPHP处理器
     */
    protected function addFrankenPHPHandler(Route $route): void
    {
        // FrankenPHP使用内置的PHP处理器
        // 这里我们使用反向代理来模拟FrankenPHP的行为
        $reverseProxy = new ReverseProxy();

        // 添加本地PHP处理器
        $upstream = new Upstream();
        $upstream->setDial('127.0.0.1:80'); // 本地处理
        $reverseProxy->addUpstream($upstream);

        $route->addHandle($reverseProxy);
    }

    /**
     * 添加ThinkPHP特定的路由处理
     */
    protected function addThinkPHPRoutes(Subroute $subroute): void
    {
        // API路由处理
        $apiRoute = new Route();
        $apiPath = new Path();
        $apiPath->setPaths(['/api/*']);
        $apiRoute->addMatch($apiPath);

        if ($this->useFastCGI) {
            $this->addFastCGIHandler($apiRoute);
        } else {
            $this->addFrankenPHPHandler($apiRoute);
        }

        $subroute->addRoute($apiRoute);

        // 管理后台路由
        $adminRoute = new Route();
        $adminPath = new Path();
        $adminPath->setPaths(['/admin/*']);
        $adminRoute->addMatch($adminPath);

        if ($this->useFastCGI) {
            $this->addFastCGIHandler($adminRoute);
        } else {
            $this->addFrankenPHPHandler($adminRoute);
        }

        $subroute->addRoute($adminRoute);
    }

    /**
     * 添加安全头处理
     */
    protected function addSecurityHeaders(Route $route): void
    {
        // 这里可以添加安全头的处理逻辑
        // mattvb91/caddy-php 可能需要扩展来支持headers指令
    }

    /**
     * 添加CORS支持
     */
    protected function addCORSSupport(Route $route): void
    {
        // 这里可以添加CORS的处理逻辑
        // mattvb91/caddy-php 可能需要扩展来支持CORS
    }

    /**
     * 添加默认路由（404处理）
     */
    protected function addDefaultRoute(Subroute $subroute): void
    {
        $defaultRoute = new Route();
        $response = new StaticResponse('Not Found', 404);
        $defaultRoute->addHandle($response);
        $subroute->addRoute($defaultRoute);
    }

    /**
     * 构建日志配置
     */
    protected function buildLogConfig(): array
    {
        $config = [];

        $config[] = "    log {";
        $config[] = "        level " . ($this->debug ? 'DEBUG' : 'INFO');
        $config[] = "        output file {$this->logDir}/frankenphp_access.log {";
        $config[] = "            roll_size 100mb";
        $config[] = "            roll_keep 10";
        $config[] = "        }";
        $config[] = "        format console";
        $config[] = "    }";
        $config[] = "";

        return $config;
    }

    /**
     * 构建URL重写规则
     */
    protected function buildRewriteRules(): array
    {
        $config = [];
        
        // 静态文件匹配器
        $staticExtensions = implode(' *.', $this->staticExtensions);
        $config[] = "    @static {";
        $config[] = "        file {";
        $config[] = "            try_files {path} {path}/";
        $config[] = "        }";
        $config[] = "    }";
        $config[] = "    handle @static {";
        $config[] = "        file_server";
        $config[] = "    }";
        $config[] = "";
        
        // ThinkPHP 路由匹配器
        $config[] = "    @thinkphp {";
        $config[] = "        not file";
        $config[] = "        not path *.{$staticExtensions}";
        $config[] = "    }";
        
        // ThinkPHP 路由处理
        $config[] = "    handle @thinkphp {";
        
        if ($this->hideIndex) {
            // 隐藏入口文件模式
            $config[] = "        rewrite * /{$this->index}";
        } else {
            // 显示入口文件模式
            $config[] = "        try_files {path} {path}/ /{$this->index}?{query}";
        }
        
        // PHP 处理器
        $config = array_merge($config, $this->buildPhpHandler());
        
        $config[] = "    }";
        $config[] = "";
        
        // 直接 PHP 文件处理
        $config[] = "    @php {";
        $config[] = "        path *.php";
        $config[] = "    }";
        $config[] = "    handle @php {";
        $config = array_merge($config, $this->buildPhpHandler());
        $config[] = "    }";
        $config[] = "";
        
        return $config;
    }

    /**
     * 构建PHP处理器配置
     */
    protected function buildPhpHandler(): array
    {
        $config = [];
        
        if ($this->workerScript) {
            // Worker 模式
            $config[] = "        php_server {";
            $config[] = "            worker {$this->workerScript}";
        } else {
            // 标准模式
            $config[] = "        php {";
        }
        
        // 环境变量
        $config[] = "            env PHP_INI_SCAN_DIR /dev/null";
        $config[] = "            env FRANKENPHP_NO_DEPRECATION_WARNINGS 1";
        
        // 自定义环境变量
        foreach ($this->env as $key => $value) {
            $config[] = "            env {$key} {$value}";
        }
        
        $config[] = "        }";
        
        return $config;
    }

    /**
     * 从配置数组创建构建器
     */
    public static function fromArray(array $config): self
    {
        $builder = new self();

        if (isset($config['listen'])) {
            $builder->setListen($config['listen']);
        }

        if (isset($config['root'])) {
            $builder->setRoot($config['root']);
        }

        if (isset($config['index'])) {
            $builder->setIndex($config['index']);
        }

        if (isset($config['auto_https'])) {
            $builder->setAutoHttps($config['auto_https']);
        }

        if (isset($config['debug'])) {
            $builder->setDebug($config['debug']);
        }

        if (isset($config['log_dir'])) {
            $builder->setLogDir($config['log_dir']);
        }

        if (isset($config['enable_rewrite'])) {
            $builder->setEnableRewrite($config['enable_rewrite']);
        }

        if (isset($config['hide_index'])) {
            $builder->setHideIndex($config['hide_index']);
        }

        if (isset($config['env'])) {
            $builder->setEnv($config['env']);
        }

        if (isset($config['worker_script'])) {
            $builder->setWorkerScript($config['worker_script']);
        }

        if (isset($config['use_fastcgi'])) {
            $builder->setUseFastCGI($config['use_fastcgi']);
        }

        if (isset($config['fastcgi_address'])) {
            $builder->setFastCGIAddress($config['fastcgi_address']);
        }

        if (isset($config['hosts'])) {
            $builder->setHosts($config['hosts']);
        }

        if (isset($config['enable_gzip'])) {
            $builder->setEnableGzip($config['enable_gzip']);
        }

        if (isset($config['enable_file_server'])) {
            $builder->setEnableFileServer($config['enable_file_server']);
        }

        return $builder;
    }

    /**
     * 获取配置摘要
     */
    public function getConfigSummary(): array
    {
        return [
            'listen' => $this->listen,
            'root' => $this->root,
            'index' => $this->index,
            'auto_https' => $this->autoHttps,
            'debug' => $this->debug,
            'log_dir' => $this->logDir,
            'enable_rewrite' => $this->enableRewrite,
            'hide_index' => $this->hideIndex,
            'use_fastcgi' => $this->useFastCGI,
            'fastcgi_address' => $this->fastCGIAddress,
            'hosts' => $this->hosts,
            'enable_gzip' => $this->enableGzip,
            'enable_file_server' => $this->enableFileServer,
            'worker_script' => $this->workerScript,
            'env_count' => count($this->env),
            'static_extensions_count' => count($this->staticExtensions),
        ];
    }
}
