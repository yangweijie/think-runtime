# 产品概述

ThinkPHP Runtime 扩展包 - 为 ThinkPHP 8.0+ 提供的高性能运行时扩展，支持 Swoole、RoadRunner、FrankenPHP、ReactPHP、Workerman、Bref 和 Vercel 等多种运行时环境。

## 核心特性

- **多运行时支持**: 自动检测并选择最佳运行时环境
- **高性能**: 支持异步/协程运行时，实现最大吞吐量
- **易于配置**: 简单的配置文件管理，提供合理的默认值
- **PSR 标准兼容**: 遵循 PSR-7、PSR-15 HTTP 消息处理标准
- **ThinkPHP 集成**: 与 ThinkPHP 8.0 框架无缝集成
- **生产就绪**: 内置安全防护、监控和错误处理

## 目标使用场景

- 需要比传统 PHP-FPM 更高性能的高流量 Web 应用
- AWS Lambda (Bref) 或 Vercel 上的 Serverless 部署
- 需要 WebSocket 支持的实时应用 (Swoole)
- 需要快速启动和低内存占用的微服务
- 具有热重载功能的开发环境

## 运行时优先级

系统按以下顺序自动检测并选择运行时：
1. Bref (AWS Lambda 环境)
2. Vercel (Vercel serverless 环境)  
3. Swoole (传统服务器的最高性能)
4. FrankenPHP (现代 HTTP/2、HTTP/3 支持)
5. ReactPHP (事件驱动异步)
6. Ripple (基于协程)
7. RoadRunner (基于 Go 的应用服务器)
8. Workerman (多进程 socket 服务器)