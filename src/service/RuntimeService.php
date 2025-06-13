<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\service;

use think\Service;
use think\App;
use yangweijie\thinkRuntime\command\RuntimeInfoCommand;
use yangweijie\thinkRuntime\command\RuntimeStartCommand;
use yangweijie\thinkRuntime\config\RuntimeConfig;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

/**
 * 运行时服务提供者
 * 负责注册运行时相关服务到ThinkPHP容器
 */
class RuntimeService extends Service
{
    /**
     * 注册服务
     *
     * @return void
     */
    public function register(): void
    {
        // 注册运行时配置
        $this->app->bind('runtime.config', function (App $app) {
            $config = $app->config->get('runtime', []);
            return new RuntimeConfig($config);
        });

        // 注册运行时管理器
        $this->app->bind('runtime.manager', function (App $app) {
            $config = $app->make('runtime.config');
            return new RuntimeManager($app, $config);
        });

        // 注册运行时别名
        $this->app->bind(RuntimeConfig::class, 'runtime.config');
        $this->app->bind(RuntimeManager::class, 'runtime.manager');
    }

    /**
     * 启动服务
     *
     * @return void
     */
    public function boot(): void
    {
        // 注册运行时命令
        $this->registerCommands();


        // 应用全局配置
        $this->applyGlobalConfig();
    }

    /**
     * 注册运行时命令
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        $this->commands([
            RuntimeStartCommand::class,
            RuntimeInfoCommand::class,
        ]);
    }

    /**
     * 应用全局配置
     *
     * @return void
     */
    protected function applyGlobalConfig(): void
    {
        /** @var RuntimeConfig $config */
        $config = $this->app->make('runtime.config');
        $globalConfig = $config->getGlobalConfig();

        // 应用PHP配置
        foreach ($globalConfig as $key => $value) {
            switch ($key) {
                case 'error_reporting':
                    error_reporting($value);
                    break;
                case 'display_errors':
                    ini_set('display_errors', $value ? '1' : '0');
                    break;
                case 'log_errors':
                    ini_set('log_errors', $value ? '1' : '0');
                    break;
                case 'memory_limit':
                    ini_set('memory_limit', $value);
                    break;
                case 'max_execution_time':
                    set_time_limit($value);
                    break;
            }
        }
    }
}
