# 项目结构

## 源代码组织 (`src/`)

### 核心架构
- **`src/adapter/`**: 运行时特定的适配器实现
  - 每个适配器实现 `AdapterInterface` 并继承 `AbstractRuntime`
  - 命名规范: `{Runtime}Adapter.php` (例如: `SwooleAdapter.php`, `WorkermanAdapter.php`)
  - 处理运行时特定的服务器设置、请求/响应处理

- **`src/runtime/`**: 核心运行时管理
  - `RuntimeManager.php`: 中央运行时检测和管理
  - `AbstractRuntime.php`: 所有运行时适配器的基类

- **`src/contract/`**: 接口和契约
  - `AdapterInterface.php`: 运行时适配器契约
  - `RuntimeInterface.php`: 运行时管理契约

- **`src/command/`**: ThinkPHP 控制台命令
  - `RuntimeStartCommand.php`: 启动运行时服务器
  - `RuntimeInfoCommand.php`: 显示运行时信息

- **`src/service/`**: ThinkPHP 服务提供者
  - `RuntimeService.php`: 向 ThinkPHP 容器注册运行时服务

- **`src/config/`**: 配置管理
  - `RuntimeConfig.php`: 配置处理和验证
  - `CaddyConfigBuilder.php`: FrankenPHP/Caddy 配置构建器

- **`src/helper/`**: 工具类
  - `CommandHelper.php`: 命令行工具

## 配置 (`config/`)
- **`config/runtime.php`**: 主运行时配置文件
  - 运行时检测顺序
  - 每个运行时的设置 (host, port, workers 等)
  - 全局配置选项

## 测试 (`tests/`)
- **`tests/Unit/`**: 单个组件的单元测试
- **`tests/Feature/`**: 完整功能的集成测试  
- **`tests/Performance/`**: 性能基准测试
- **`tests/TestCase.php`**: 基础测试类
- **`tests/Pest.php`**: Pest 测试框架配置

## 文档和指南
- **根目录**: 不同运行时的多个 README 文件
- **`docs/`**: 详细文档和指南
- **`examples/`**: 示例配置和服务器实现

## 脚本和工具
- **性能测试**: `*_test.sh`, `benchmark_*.sh`
- **安装助手**: `install-*.sh`, `*_setup_guide.md`
- **调试工具**: `debug_*.php`, `diagnose_*.php`

## 命名规范

### 类
- **适配器**: `{Runtime}Adapter` (帕斯卡命名法)
- **命令**: `Runtime{Action}Command` (帕斯卡命名法)
- **服务**: `RuntimeService` (帕斯卡命名法)
- **接口**: `{Name}Interface` (帕斯卡命名法)

### 文件和目录
- **目录**: 小写加下划线 (`src/adapter/`)
- **PHP 文件**: 帕斯卡命名法匹配类名
- **配置文件**: 小写加下划线 (`runtime.php`)
- **脚本**: 小写加下划线和连字符 (`quick_performance_test.sh`)

### 方法和属性
- **方法**: 驼峰命名法，小写开头 (`isSupported()`, `getName()`)
- **属性**: 驼峰命名法，小写开头 (`$runtimeConfig`)
- **常量**: 大写加下划线 (`SWOOLE_PROCESS`)

## 关键架构模式

### 适配器模式
每个运行时实现相同的接口，但处理服务器生命周期的方式不同：
```php
interface AdapterInterface {
    public function isSupported(): bool;
    public function getName(): string;
    public function getPriority(): int;
    public function start(array $config = []): void;
}
```

### 工厂模式
`RuntimeManager` 作为工厂，基于环境检测创建和管理运行时实例。

### 服务提供者模式
`RuntimeService` 向 ThinkPHP 的依赖注入容器注册所有运行时组件。

## 文件备份策略
- 备份文件使用 `.backup` 后缀，可选时间戳
- 关键文件的多个备份版本 (例如: `WorkermanAdapter.php.backup.20250624_221258`)
- 备份文件应从版本控制中排除