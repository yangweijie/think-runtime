<?php

declare(strict_types=1);

/**
 * RoadRunner Worker 入口文件
 * 
 * 此文件用于RoadRunner环境下的应用启动
 */

use think\App;
use yangweijie\thinkRuntime\runtime\RuntimeManager;

// 引入自动加载
require_once __DIR__ . '/vendor/autoload.php';

// 创建应用实例
$app = new App();

// 初始化应用
$app->initialize();

// 获取运行时管理器
$manager = $app->make('runtime.manager');

// 启动RoadRunner运行时
$manager->start('roadrunner');
