<?php

declare(strict_types=1);

namespace yangweijie\thinkRuntime\helper;

use think\App;
use think\Console;

/**
 * 命令助手类
 * 用于手动注册Runtime命令
 */
class CommandHelper
{
    /**
     * 注册Runtime命令到ThinkPHP
     *
     * @param App $app ThinkPHP应用实例
     * @return void
     */
    public static function registerCommands(App $app): void
    {
        // 获取Console实例
        $console = $app->console;
        
        if ($console instanceof Console) {
            // 注册命令
            $console->addCommands([
                \yangweijie\thinkRuntime\command\RuntimeStartCommand::class,
                \yangweijie\thinkRuntime\command\RuntimeInfoCommand::class,
            ]);
        }
    }
    
    /**
     * 注册Runtime服务到ThinkPHP
     *
     * @param App $app ThinkPHP应用实例
     * @return void
     */
    public static function registerServices(App $app): void
    {
        // 注册运行时配置
        $app->bind('runtime.config', function (App $app) {
            $config = $app->config->get('runtime', []);
            return new \yangweijie\thinkRuntime\config\RuntimeConfig($config);
        });

        // 注册运行时管理器
        $app->bind('runtime.manager', function (App $app) {
            $config = $app->make('runtime.config');
            return new \yangweijie\thinkRuntime\runtime\RuntimeManager($app, $config);
        });

        // 注册运行时别名
        $app->bind(\yangweijie\thinkRuntime\config\RuntimeConfig::class, 'runtime.config');
        $app->bind(\yangweijie\thinkRuntime\runtime\RuntimeManager::class, 'runtime.manager');
    }
    
    /**
     * 完整初始化Runtime扩展
     *
     * @param App $app ThinkPHP应用实例
     * @return void
     */
    public static function initialize(App $app): void
    {
        // 注册服务
        self::registerServices($app);
        
        // 注册命令
        self::registerCommands($app);
        
        // 应用全局配置
        self::applyGlobalConfig($app);
    }
    
    /**
     * 应用全局配置
     *
     * @param App $app ThinkPHP应用实例
     * @return void
     */
    protected static function applyGlobalConfig(App $app): void
    {
        try {
            /** @var \yangweijie\thinkRuntime\config\RuntimeConfig $config */
            $config = $app->make('runtime.config');
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
        } catch (\Exception $e) {
            // 忽略配置错误
        }
    }
    
    /**
     * 检查Runtime扩展是否已正确安装
     *
     * @param App $app ThinkPHP应用实例
     * @return array 检查结果
     */
    public static function checkInstallation(App $app): array
    {
        $result = [
            'services' => [],
            'commands' => [],
            'config' => false,
            'errors' => [],
        ];
        
        try {
            // 检查服务
            $result['services']['runtime.config'] = $app->has('runtime.config');
            $result['services']['runtime.manager'] = $app->has('runtime.manager');
            
            // 检查配置
            $result['config'] = $app->config->has('runtime');
            
            // 检查命令类
            $commandClasses = [
                'yangweijie\\thinkRuntime\\command\\RuntimeStartCommand',
                'yangweijie\\thinkRuntime\\command\\RuntimeInfoCommand',
            ];
            
            foreach ($commandClasses as $class) {
                $result['commands'][basename($class)] = class_exists($class);
            }
            
        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
}
