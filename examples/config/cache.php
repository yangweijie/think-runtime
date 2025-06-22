<?php

declare(strict_types=1);

/**
 * ThinkPHP 缓存配置
 */

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores' => [
        // 文件缓存
        'file' => [
            // 驱动方式
            'type' => 'File',
            // 缓存保存目录
            'path' => runtime_path() . 'cache/',
            // 缓存前缀
            'prefix' => '',
            // 缓存有效期 0表示永久缓存
            'expire' => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制
            'serialize' => [],
        ],
    ],
];