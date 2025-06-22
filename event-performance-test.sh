#!/bin/bash

# Event 扩展高性能测试脚本
# 专门测试使用 Event 扩展的 Workerman 性能

echo "=== Event 扩展高性能测试 ==="
echo "测试目标: 验证 Event 扩展对 Workerman 性能的提升"
echo "开始时间: $(date)"
echo ""

# 检查 Event 扩展
echo "检查 Event 扩展..."
if ! php -r "exit(extension_loaded('event') ? 0 : 1);" 2>/dev/null; then
    echo "❌ Event 扩展未安装"
    echo "请安装 Event 扩展: pecl install event"
    exit 1
fi

echo "✅ Event 扩展已安装"

# 检查 wrk
if ! command -v wrk &> /dev/null; then
    echo "❌ wrk 未安装"
    exit 1
fi

echo "✅ wrk 已安装"

# 进入项目目录
cd /Volumes/data/git/php/tp

# 创建结果目录
mkdir -p event_test_results
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

echo ""
echo "=== 启动 Event 优化的 Workerman 服务 ==="

# 启动服务（后台运行）
nohup php think runtime:start workerman > event_test_results/workerman_${TIMESTAMP}.log 2>&1 &
WORKERMAN_PID=$!
echo "Workerman PID: $WORKERMAN_PID"

# 等待服务启动
echo "等待服务启动..."
sleep 5

# 检查服务是否启动成功
if ! curl -s http://127.0.0.1:8080/ > /dev/null; then
    echo "❌ Workerman 服务启动失败"
    kill $WORKERMAN_PID 2>/dev/null || true
    exit 1
fi

echo "✅ Workerman 服务启动成功"

# Event 扩展优化测试配置
TESTS=(
    "Event基准测试:4:100:30s"
    "Event中等负载:8:200:60s"
    "Event高负载:12:500:90s"
    "Event极限测试:16:1000:120s"
)

echo ""
echo "=== 开始 Event 扩展性能测试 ==="

for test in "${TESTS[@]}"; do
    IFS=':' read -r name threads connections duration <<< "$test"
    
    echo ""
    echo "--- $name ---"
    echo "线程数: $threads, 连接数: $connections, 持续时间: $duration"
    
    # 记录测试前状态
    echo "测试前内存状态:" > "event_test_results/${name}_${TIMESTAMP}.log"
    ps aux | grep -E "(workerman|think-runtime)" | grep -v grep >> "event_test_results/${name}_${TIMESTAMP}.log"
    echo "" >> "event_test_results/${name}_${TIMESTAMP}.log"
    
    # 执行压测
    echo "开始压测..."
    wrk -t$threads -c$connections -d$duration \
        --latency \
        --timeout 10s \
        -H "Connection: keep-alive" \
        -H "User-Agent: Event-Performance-Test" \
        http://127.0.0.1:8080/ >> "event_test_results/${name}_${TIMESTAMP}.log" 2>&1
    
    # 记录测试后状态
    echo "" >> "event_test_results/${name}_${TIMESTAMP}.log"
    echo "测试后内存状态:" >> "event_test_results/${name}_${TIMESTAMP}.log"
    ps aux | grep -E "(workerman|think-runtime)" | grep -v grep >> "event_test_results/${name}_${TIMESTAMP}.log"
    
    # 等待系统恢复
    echo "等待系统恢复..."
    sleep 10
done

# 停止服务
echo ""
echo "=== 停止服务 ==="
kill $WORKERMAN_PID 2>/dev/null || true
sleep 3

# 生成 Event 扩展性能报告
REPORT_FILE="event_test_results/event_performance_report_${TIMESTAMP}.txt"

cat > $REPORT_FILE << EOF
=== Event 扩展 Workerman 性能测试报告 ===

测试时间: $(date)
测试环境: Event 扩展 + Workerman
PHP版本: $(php -v | head -n 1)
Event扩展版本: $(php -r "echo phpversion('event');")

=== Event 扩展优势 ===
1. 基于 epoll/kqueue 的高效事件循环
2. 支持数万并发连接
3. 低内存占用和 CPU 使用率
4. 非阻塞 I/O 操作

=== 测试结果摘要 ===

EOF

# 提取测试结果
for test in "${TESTS[@]}"; do
    IFS=':' read -r name threads connections duration <<< "$test"
    log_file="event_test_results/${name}_${TIMESTAMP}.log"
    
    if [ -f "$log_file" ]; then
        echo "--- $name ---" >> $REPORT_FILE
        
        # 提取关键指标
        if grep -q "Requests/sec:" "$log_file"; then
            qps=$(grep "Requests/sec:" "$log_file" | awk '{print $2}')
            echo "QPS: $qps" >> $REPORT_FILE
        fi
        
        if grep -q "Latency" "$log_file"; then
            latency=$(grep "Latency" "$log_file" | head -n 1 | awk '{print $2}')
            echo "平均延迟: $latency" >> $REPORT_FILE
        fi
        
        if grep -q "99%" "$log_file"; then
            p99=$(grep "99%" "$log_file" | awk '{print $2}')
            echo "99%延迟: $p99" >> $REPORT_FILE
        fi
        
        # 检查错误
        if grep -q "Socket errors:" "$log_file"; then
            errors=$(grep "Socket errors:" "$log_file")
            echo "错误情况: $errors" >> $REPORT_FILE
        fi
        
        echo "" >> $REPORT_FILE
    fi
done

# 添加性能分析
cat >> $REPORT_FILE << EOF
=== Event 扩展性能分析 ===

Event 扩展的优势：
1. 使用 epoll (Linux) / kqueue (macOS) 系统调用
2. O(1) 时间复杂度的事件通知
3. 支持边缘触发模式
4. 内存使用效率高

与其他事件循环对比：
- Event (libevent): ⭐⭐⭐⭐⭐ (最高性能)
- Ev: ⭐⭐⭐⭐⭐ (最高性能)
- Select: ⭐⭐ (基础性能，有连接数限制)

=== 优化建议 ===

1. 系统级优化：
   - 增加文件描述符限制: ulimit -n 65535
   - 调整内核参数: net.core.somaxconn = 65535
   - 启用 TCP 快速打开: net.ipv4.tcp_fastopen = 3

2. PHP 配置优化：
   - memory_limit = 512M
   - opcache.enable = 1
   - opcache.memory_consumption = 256

3. Workerman 配置优化：
   - 根据 CPU 核心数设置进程数
   - 启用 reusePort (Linux 3.9+)
   - 合理设置连接超时时间

=== 预期性能提升 ===

使用 Event 扩展相比 Select：
- QPS 提升: 300-500%
- 延迟降低: 50-70%
- 内存使用: 降低 20-30%
- 并发连接数: 从 1024 提升到 65535+

EOF

echo "✅ Event 扩展性能测试完成!"
echo "📊 详细报告: $REPORT_FILE"
echo "📁 测试日志: event_test_results/"

# 显示报告摘要
echo ""
echo "=== 测试结果摘要 ==="
cat $REPORT_FILE

# 性能建议
echo ""
echo "=== 下一步优化建议 ==="
echo "1. 调整系统参数以支持更高并发"
echo "2. 优化 PHP 和 Workerman 配置"
echo "3. 考虑使用连接池和缓存"
echo "4. 监控系统资源使用情况"

echo ""
echo "测试完成时间: $(date)"
