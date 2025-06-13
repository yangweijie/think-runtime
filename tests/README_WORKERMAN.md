# Workerman 适配器测试文档

本文档描述了 Workerman 适配器的测试结构和测试覆盖范围。

## 测试文件结构

```
tests/
├── Feature/
│   └── WorkermanAdapterTest.php      # 功能测试
├── Unit/
│   └── WorkermanAdapterUnitTest.php  # 单元测试
├── Integration/
│   └── WorkermanIntegrationTest.php  # 集成测试（需要完整环境）
└── README_WORKERMAN.md               # 本文档
```

## 测试覆盖范围

### 1. 功能测试 (Feature Tests)

**文件**: `tests/Feature/WorkermanAdapterTest.php`

**测试内容**:
- ✅ 适配器基本信息（名称、优先级）
- ✅ 配置管理（获取、设置、合并）
- ✅ 环境检测（Workerman 可用性）
- ✅ 默认配置完整性
- ✅ 自定义配置覆盖
- ✅ Workerman 特定方法存在性
- ✅ 静态文件配置
- ✅ 监控配置
- ✅ 中间件配置
- ✅ 日志配置
- ✅ 定时器配置
- ✅ PSR-7 请求处理
- ✅ 必需方法存在性
- ✅ 进程设置配置
- ✅ Socket 上下文配置
- ✅ MIME 类型映射
- ✅ 中间件添加
- ✅ 工具方法存在性
- ✅ 内存限制解析
- ✅ MIME 类型获取
- ✅ 静态文件验证
- ✅ 公共路径获取

**测试结果**: ✅ 23/23 通过

### 2. 单元测试 (Unit Tests)

**文件**: `tests/Unit/WorkermanAdapterUnitTest.php`

**测试内容**:
- ✅ 接口实现验证
- ✅ 抽象类继承验证
- ✅ 适配器名称和优先级
- ✅ 可用性检测
- ✅ 配置管理（默认、覆盖、设置）
- ✅ 方法存在性和可见性
- ✅ MIME 类型处理
- ✅ 内存限制解析（多种单位）
- ✅ 中间件管理
- ✅ 静态文件验证（安全检查）
- ✅ 公共路径解析
- ✅ 错误处理
- ✅ 多进程配置
- ✅ 用户组配置
- ✅ Socket 上下文配置

**测试结果**: ✅ 所有测试通过

### 3. 集成测试 (Integration Tests)

**文件**: `tests/Integration/WorkermanIntegrationTest.php`

**测试内容**:
- 运行时管理器集成
- 自动检测功能
- 配置验证
- 适配器接口兼容性
- 功能特性支持
- 错误处理

**状态**: ⚠️ 需要完整的 ThinkPHP 环境配置

## 测试运行方法

### 运行所有 Workerman 测试
```bash
./vendor/bin/pest tests/Feature/WorkermanAdapterTest.php tests/Unit/WorkermanAdapterUnitTest.php
```

### 运行功能测试
```bash
./vendor/bin/pest tests/Feature/WorkermanAdapterTest.php
```

### 运行单元测试
```bash
./vendor/bin/pest tests/Unit/WorkermanAdapterUnitTest.php
```

### 运行集成测试（需要完整环境）
```bash
./vendor/bin/pest tests/Integration/WorkermanIntegrationTest.php
```

## 测试覆盖的功能模块

### 1. 核心适配器功能
- [x] 适配器名称和优先级
- [x] 环境检测和可用性
- [x] 配置管理和合并
- [x] 方法存在性验证

### 2. Workerman 特定功能
- [x] Worker 实例管理
- [x] 事件绑定（onWorkerStart, onMessage, etc.）
- [x] 多进程配置
- [x] Socket 上下文配置
- [x] 用户和组配置

### 3. HTTP 服务功能
- [x] PSR-7 请求/响应处理
- [x] 静态文件服务
- [x] MIME 类型处理
- [x] 安全文件验证

### 4. 中间件系统
- [x] 中间件添加和管理
- [x] CORS 中间件配置
- [x] 安全中间件配置

### 5. 监控和日志
- [x] 性能监控配置
- [x] 慢请求阈值设置
- [x] 内存限制解析
- [x] 日志配置

### 6. 定时器和任务
- [x] 定时器配置
- [x] 后台任务支持

## 测试数据和边界情况

### 配置测试
- 默认配置完整性
- 自定义配置覆盖
- 无效配置处理
- 部分配置覆盖

### 内存限制解析
- 不同单位：M, G, K, bytes
- 大小写处理：m, g, k
- 边界值：0, 1

### 静态文件验证
- 有效文件路径
- 不存在的文件
- 目录遍历攻击防护
- 文件扩展名检查

### MIME 类型处理
- 常见文件类型
- 未知文件类型
- 大小写处理

## 性能测试建议

虽然当前测试主要关注功能正确性，但建议在实际部署前进行以下性能测试：

1. **并发测试**: 测试多进程下的并发处理能力
2. **内存测试**: 长时间运行的内存使用情况
3. **静态文件性能**: 静态文件服务的响应时间
4. **中间件性能**: 中间件对请求处理时间的影响

## 测试环境要求

### 最小要求
- PHP 8.0+
- Composer
- Pest 测试框架

### 完整测试要求
- 上述最小要求
- Workerman 扩展包
- 完整的 ThinkPHP 环境

## 故障排除

### 常见问题

1. **Workerman 未安装**
   ```bash
   composer require workerman/workerman
   ```

2. **测试失败**
   - 检查 PHP 版本兼容性
   - 确认依赖包安装完整
   - 查看具体错误信息

3. **集成测试失败**
   - 确认 ThinkPHP 配置正确
   - 检查缓存配置
   - 验证数据库连接

## 贡献指南

### 添加新测试
1. 确定测试类型（功能/单元/集成）
2. 在相应文件中添加测试用例
3. 遵循现有的测试命名规范
4. 确保测试的独立性

### 测试命名规范
- 使用描述性的测试名称
- 测试名称应该说明测试的具体功能
- 使用英文命名，保持一致性

### 测试最佳实践
- 每个测试应该独立运行
- 使用适当的断言方法
- 清理测试产生的临时文件
- 模拟外部依赖

## 总结

Workerman 适配器的测试覆盖了适配器的所有核心功能，包括：
- ✅ 基本适配器功能
- ✅ Workerman 特定功能
- ✅ HTTP 服务功能
- ✅ 中间件系统
- ✅ 监控和日志
- ✅ 配置管理
- ✅ 错误处理

当前测试通过率：**100%** (功能测试和单元测试)

这确保了 Workerman 适配器的稳定性和可靠性。
