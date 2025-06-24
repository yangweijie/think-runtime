# wrk + Keep-Alive 性能测试指南

## wrk 基础用法

### 安装 wrk

```bash
# macOS
brew install wrk

# Ubuntu/Debian
sudo apt-get install wrk

# CentOS/RHEL
sudo yum install wrk

# 从源码编译
git clone https://github.com/wg/wrk.git
cd wrk
make
sudo cp wrk /usr/local/bin/
```

### 基础命令格式

```bash
wrk [选项] <URL>
```

## Keep-Alive 配置

### 1. 基础 Keep-Alive 测试

```bash
# 基础 keep-alive 测试
wrk -t4 -c100 -d30s --timeout 10s http://127.0.0.1:8080/

# 参数说明:
# -t4: 4个线程
# -c100: 100个并发连接
# -d30s: 持续30秒
# --timeout 10s: 超时时间10秒
```

### 2. 自定义 Keep-Alive 头

```bash
# 明确指定 keep-alive
wrk -t4 -c100 -d30s \
    -H "Connection: keep-alive" \
    -H "Keep-Alive: timeout=60, max=1000" \
    http://127.0.0.1:8080/
```

### 3. 高级 Keep-Alive 配置

```bash
# 使用 Lua 脚本自定义请求
wrk -t8 -c200 -d60s \
    -s keepalive.lua \
    http://127.0.0.1:8080/
```

## Lua 脚本示例

### keepalive.lua

```lua
-- keepalive.lua
-- 自定义 keep-alive 请求脚本

wrk.method = "GET"
wrk.headers["Connection"] = "keep-alive"
wrk.headers["Keep-Alive"] = "timeout=60, max=1000"
wrk.headers["Accept-Encoding"] = "gzip, deflate"
wrk.headers["User-Agent"] = "wrk-keepalive-test/1.0"

-- 请求初始化
function init(args)
    print("Initializing keep-alive test...")
    print("Threads: " .. wrk.thread)
    print("Connections: " .. wrk.connections)
end

-- 每个请求前调用
function request()
    return wrk.format(wrk.method, wrk.path, wrk.headers, wrk.body)
end

-- 每个响应后调用
function response(status, headers, body)
    if status ~= 200 then
        print("Error: HTTP " .. status)
    end
end

-- 测试完成后调用
function done(summary, latency, requests)
    print("\n=== Keep-Alive Test Results ===")
    print("Total Requests: " .. summary.requests)
    print("Total Errors: " .. summary.errors.connect + summary.errors.read + summary.errors.write + summary.errors.timeout)
    print("Average Latency: " .. latency.mean / 1000 .. "ms")
    print("99th Percentile: " .. latency["99"] / 1000 .. "ms")
    print("Requests/sec: " .. summary.requests / summary.duration * 1000000)
end
```

## Workerman Keep-Alive 优化

### 1. 更新 WorkermanAdapter 支持 Keep-Alive

```php
// 在 WorkermanAdapter.php 中添加 keep-alive 配置
'keep_alive' => [
    'enable' => true,
    'timeout' => 60,        // keep-alive 超时时间 (秒)
    'max_requests' => 1000, // 每个连接最大请求数
    'close_on_idle' => 300, // 空闲连接关闭时间 (秒)
],
```

### 2. 响应头优化

```php
// 在响应中添加 keep-alive 头
$headers = [
    'Connection' => 'keep-alive',
    'Keep-Alive' => 'timeout=60, max=1000',
    'Content-Type' => 'application/json; charset=utf-8',
    // ... 其他头
];
```

## 性能测试场景

### 1. 短连接 vs Keep-Alive 对比

```bash
# 短连接测试 (强制关闭连接)
wrk -t4 -c100 -d30s \
    -H "Connection: close" \
    http://127.0.0.1:8080/

# Keep-Alive 测试
wrk -t4 -c100 -d30s \
    -H "Connection: keep-alive" \
    -H "Keep-Alive: timeout=60, max=1000" \
    http://127.0.0.1:8080/
```

### 2. 不同并发级别测试

```bash
# 低并发 keep-alive
wrk -t2 -c50 -d30s -H "Connection: keep-alive" http://127.0.0.1:8080/

# 中等并发 keep-alive  
wrk -t4 -c200 -d30s -H "Connection: keep-alive" http://127.0.0.1:8080/

# 高并发 keep-alive
wrk -t8 -c500 -d30s -H "Connection: keep-alive" http://127.0.0.1:8080/
```

### 3. 长时间稳定性测试

```bash
# 长时间 keep-alive 稳定性测试
wrk -t4 -c100 -d300s \
    -H "Connection: keep-alive" \
    -H "Keep-Alive: timeout=300, max=10000" \
    --timeout 30s \
    http://127.0.0.1:8080/
```

## 监控和分析

### 1. 连接复用率监控

```bash
# 使用 netstat 监控连接状态
watch -n 1 'netstat -an | grep :8080 | grep ESTABLISHED | wc -l'

# 监控 TIME_WAIT 连接
watch -n 1 'netstat -an | grep :8080 | grep TIME_WAIT | wc -l'
```

### 2. 系统资源监控

```bash
# 监控 CPU 和内存
top -p $(pgrep -f workerman)

# 监控文件描述符
lsof -p $(pgrep -f workerman) | wc -l
```

## 优化建议

### 1. 系统级优化

```bash
# 增加文件描述符限制
ulimit -n 65535

# 优化 TCP 参数
echo 1 > /proc/sys/net/ipv4/tcp_tw_reuse
echo 1 > /proc/sys/net/ipv4/tcp_tw_recycle
echo 30 > /proc/sys/net/ipv4/tcp_fin_timeout
```

### 2. Workerman 配置优化

```php
// 优化 Workerman 配置
$config = [
    'count' => 4,  // 根据 CPU 核心数调整
    'keep_alive' => [
        'enable' => true,
        'timeout' => 60,
        'max_requests' => 1000,
    ],
    'socket' => [
        'so_reuseport' => true,  // 启用端口复用
        'tcp_nodelay' => true,   // 禁用 Nagle 算法
        'so_keepalive' => true,  // 启用 TCP keep-alive
    ],
];
```

## 测试脚本示例

### wrk_keepalive_test.sh

```bash
#!/bin/bash

echo "=== Workerman Keep-Alive 性能测试 ==="

SERVER_URL="http://127.0.0.1:8080"
RESULTS_DIR="./test_results"
mkdir -p $RESULTS_DIR

# 测试1: 短连接 vs Keep-Alive
echo "1. 短连接测试..."
wrk -t4 -c100 -d30s -H "Connection: close" $SERVER_URL > $RESULTS_DIR/short_connection.txt

echo "2. Keep-Alive 测试..."
wrk -t4 -c100 -d30s -H "Connection: keep-alive" $SERVER_URL > $RESULTS_DIR/keep_alive.txt

# 测试2: 不同并发级别
echo "3. 低并发 Keep-Alive..."
wrk -t2 -c50 -d30s -H "Connection: keep-alive" $SERVER_URL > $RESULTS_DIR/low_concurrency.txt

echo "4. 高并发 Keep-Alive..."
wrk -t8 -c500 -d30s -H "Connection: keep-alive" $SERVER_URL > $RESULTS_DIR/high_concurrency.txt

# 测试3: 带 gzip 的 Keep-Alive
echo "5. Gzip + Keep-Alive..."
wrk -t4 -c200 -d30s \
    -H "Connection: keep-alive" \
    -H "Accept-Encoding: gzip" \
    $SERVER_URL > $RESULTS_DIR/gzip_keepalive.txt

echo "测试完成！结果保存在 $RESULTS_DIR 目录"

# 分析结果
echo -e "\n=== 测试结果对比 ==="
echo "短连接 QPS:"
grep "Requests/sec" $RESULTS_DIR/short_connection.txt

echo "Keep-Alive QPS:"
grep "Requests/sec" $RESULTS_DIR/keep_alive.txt

echo "高并发 Keep-Alive QPS:"
grep "Requests/sec" $RESULTS_DIR/high_concurrency.txt
```

## 预期性能提升

### Keep-Alive 优势

1. **连接复用**: 减少 TCP 握手开销
2. **更高 QPS**: 通常提升 20-50%
3. **更低延迟**: 减少连接建立时间
4. **资源节省**: 减少系统连接数

### 典型性能对比

```
短连接:     5,000 QPS
Keep-Alive: 8,000 QPS (+60%)

延迟对比:
短连接:     平均 20ms, 99th 50ms
Keep-Alive: 平均 12ms, 99th 30ms
```

这样配置后，Workerman runtime 就能充分利用 keep-alive 连接，显著提升性能表现！
