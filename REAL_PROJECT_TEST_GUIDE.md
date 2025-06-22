# ThinkPHP Workerman 真实项目内存测试指南

## 🎯 **测试目标**

在您的真实 ThinkPHP 项目 `/Volumes/data/git/php/tp` 中进行 Workerman 内存泄漏测试和性能优化。

## 📋 **准备工作**

### 1. 复制测试文件到项目目录

将以下文件复制到 `/Volumes/data/git/php/tp/` 目录：

```bash
# 进入您的项目目录
cd /Volumes/data/git/php/tp

# 复制测试文件（从 think-runtime 项目目录）
cp /path/to/think-runtime/real-project-memory-monitor.php ./
cp /path/to/think-runtime/real-project-stress-test.sh ./
cp /path/to/think-runtime/OptimizedWorkermanAdapter.php ./

# 给脚本执行权限
chmod +x real-project-stress-test.sh
```

### 2. 安装压测工具

```bash
# macOS
brew install wrk

# Ubuntu/Debian
sudo apt-get install wrk

# CentOS/RHEL
sudo yum install wrk
```

### 3. 检查 PHP 配置

```bash
# 检查当前内存限制
php -r "echo 'Memory Limit: ' . ini_get('memory_limit') . PHP_EOL;"

# 检查是否安装了 workerman
php -r "echo class_exists('Workerman\Worker') ? 'Workerman: OK' : 'Workerman: NOT INSTALLED';"
```

## 🚀 **执行测试**

### 步骤 1: 启动内存监控

在第一个终端窗口：

```bash
cd /Volumes/data/git/php/tp
php real-project-memory-monitor.php
```

### 步骤 2: 启动 Workerman 服务

在第二个终端窗口：

```bash
cd /Volumes/data/git/php/tp

# 使用标准适配器启动
php think-runtime workerman

# 或者如果要测试优化版本，需要先替换适配器
# 然后启动
```

### 步骤 3: 执行压力测试

在第三个终端窗口：

```bash
cd /Volumes/data/git/php/tp
./real-project-stress-test.sh
```

## 📊 **测试结果分析**

### 1. 内存监控结果

监控脚本会显示：
- 实时内存使用情况
- 进程状态
- 连接数统计
- 内存增长趋势

### 2. 压力测试结果

压测脚本会生成：
- `stress_test_results/` 目录包含详细日志
- `comprehensive_report_*.txt` 综合报告
- 各阶段的 QPS、延迟、错误率数据

### 3. 关键指标

**内存相关**：
- 初始内存 vs 最终内存
- 内存增长率
- 是否有内存泄漏

**性能相关**：
- QPS (每秒请求数)
- 平均延迟
- 99% 延迟
- 错误率

## 🔧 **优化建议**

### 如果内存使用过高

1. **调整 PHP 配置**：
```bash
# 启动时设置内存限制
php -d memory_limit=256M think-runtime workerman

# 或修改 php.ini
memory_limit = 256M
```

2. **使用优化的适配器**：
```php
// 在 config/runtime.php 中
return [
    'workerman' => [
        'memory' => [
            'enable_gc' => true,
            'gc_interval' => 20,
            'memory_limit_mb' => 128,
        ],
    ],
];
```

3. **禁用调试工具**：
```php
// 在生产环境配置中
'debug' => false,
'trace' => [
    'enable' => false,
],
```

### 如果 QPS 过低

1. **增加进程数**：
```php
'workerman' => [
    'count' => 8, // 根据 CPU 核心数调整
],
```

2. **优化数据库连接**：
```php
'database' => [
    'connections' => 10,
    'pool' => true,
],
```

3. **启用缓存**：
```php
'cache' => [
    'default' => 'redis',
],
```

## 🐛 **常见问题**

### 1. 内存不足错误

```bash
# 临时增加内存限制
php -d memory_limit=512M think-runtime workerman

# 或者减少进程数
php think-runtime workerman --count=1
```

### 2. 端口被占用

```bash
# 检查端口占用
lsof -i :8080

# 杀死占用进程
kill -9 <PID>
```

### 3. 权限问题

```bash
# 给予执行权限
chmod +x real-project-stress-test.sh

# 检查文件权限
ls -la real-project-*
```

## 📈 **基准测试数据**

### 预期性能指标

**良好性能**：
- QPS: > 1000
- 平均延迟: < 50ms
- 内存增长: < 1MB/1000请求
- 错误率: < 0.1%

**需要优化**：
- QPS: < 500
- 平均延迟: > 100ms
- 内存增长: > 5MB/1000请求
- 错误率: > 1%

## 🔍 **深度分析**

### 1. 查看详细内存使用

```bash
# 实时监控进程内存
watch -n 1 'ps aux | grep workerman | grep -v grep'

# 查看内存映射
cat /proc/<PID>/smaps
```

### 2. 分析慢查询

```bash
# 查看应用日志
tail -f runtime/log/*.log

# 查看数据库慢查询日志
```

### 3. 性能分析

```bash
# 使用 strace 分析系统调用
strace -p <PID> -f -e trace=memory

# 使用 xdebug 分析 PHP 性能
```

## 📝 **测试报告模板**

```
=== ThinkPHP Workerman 测试报告 ===

测试环境：
- PHP版本：
- 内存限制：
- 进程数：
- 测试时间：

测试结果：
- 最大QPS：
- 平均延迟：
- 内存使用：
- 错误率：

问题发现：
- [ ] 内存泄漏
- [ ] 性能瓶颈
- [ ] 错误频发

优化建议：
1. 
2. 
3. 

结论：
```

## 🎯 **下一步行动**

1. **执行基础测试** - 使用当前配置测试
2. **分析结果** - 找出性能瓶颈
3. **应用优化** - 根据建议进行优化
4. **重新测试** - 验证优化效果
5. **生产部署** - 应用最佳配置

---

**注意**：测试过程中请确保：
- 有足够的系统资源
- 关闭不必要的服务
- 备份重要数据
- 在非生产环境进行测试
