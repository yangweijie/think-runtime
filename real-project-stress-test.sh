#!/bin/bash

# ThinkPHP Workerman 压力测试脚本
# 
# 使用方法：
# 1. 将此文件复制到您的项目根目录 /Volumes/data/git/php/tp/
# 2. 给予执行权限: chmod +x real-project-stress-test.sh
# 3. 启动 workerman: php think-runtime workerman
# 4. 运行压测: ./real-project-stress-test.sh

echo "=== ThinkPHP Workerman 压力测试 ==="
echo "测试目标: http://127.0.0.1:8080/"
echo "开始时间: $(date)"
echo ""

# 检查 wrk 是否安装
if ! command -v wrk &> /dev/null; then
    echo "❌ wrk 未安装，请先安装 wrk"
    echo "macOS: brew install wrk"
    echo "Ubuntu: sudo apt-get install wrk"
    echo "CentOS: sudo yum install wrk"
    exit 1
fi

# 检查服务是否运行
echo "检查 Workerman 服务状态..."
if ! curl -s http://127.0.0.1:8080/ > /dev/null; then
    echo "❌ Workerman 服务未运行或无法访问"
    echo "请先启动服务: php think-runtime workerman"
    exit 1
fi

echo "✅ Workerman 服务正在运行"
echo ""

# 创建结果目录
RESULT_DIR="stress_test_results"
mkdir -p $RESULT_DIR

# 获取当前时间戳
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# 测试配置
TESTS=(
    "轻量测试:2:10:10s"
    "中等测试:4:50:30s" 
    "重度测试:8:100:60s"
    "极限测试:12:200:120s"
)

echo "=== 开始分阶段压力测试 ==="
echo ""

for test in "${TESTS[@]}"; do
    IFS=':' read -r name threads connections duration <<< "$test"
    
    echo "--- $name ---"
    echo "线程数: $threads, 连接数: $connections, 持续时间: $duration"
    
    # 记录测试前的内存状态
    echo "测试前内存状态:" > "$RESULT_DIR/${name}_${TIMESTAMP}.log"
    ps aux | grep -E "(workerman|think-runtime)" | grep -v grep >> "$RESULT_DIR/${name}_${TIMESTAMP}.log"
    echo "" >> "$RESULT_DIR/${name}_${TIMESTAMP}.log"
    
    # 执行压测
    echo "开始压测..."
    wrk -t$threads -c$connections -d$duration \
        --latency \
        -s - http://127.0.0.1:8080/ <<'EOF' >> "$RESULT_DIR/${name}_${TIMESTAMP}.log" 2>&1

-- 自定义 Lua 脚本，记录更多信息
wrk.method = "GET"
wrk.headers["User-Agent"] = "wrk-stress-test"

local counter = 0
local errors = 0

function request()
    counter = counter + 1
    return wrk.format(nil, "/")
end

function response(status, headers, body)
    if status ~= 200 then
        errors = errors + 1
    end
end

function done(summary, latency, requests)
    print("\n=== 详细测试结果 ===")
    print("总请求数: " .. summary.requests)
    print("总错误数: " .. errors)
    print("错误率: " .. string.format("%.2f%%", (errors / summary.requests) * 100))
    print("平均QPS: " .. string.format("%.2f", summary.requests / (summary.duration / 1000000)))
    print("平均延迟: " .. string.format("%.2fms", latency.mean / 1000))
    print("99%延迟: " .. string.format("%.2fms", latency.p99 / 1000))
    print("最大延迟: " .. string.format("%.2fms", latency.max / 1000))
    
    -- 输出延迟分布
    print("\n延迟分布:")
    print("50%: " .. string.format("%.2fms", latency.p50 / 1000))
    print("75%: " .. string.format("%.2fms", latency.p75 / 1000))
    print("90%: " .. string.format("%.2fms", latency.p90 / 1000))
    print("95%: " .. string.format("%.2fms", latency.p95 / 1000))
    print("99%: " .. string.format("%.2fms", latency.p99 / 1000))
    print("99.9%: " .. string.format("%.2fms", latency.p999 / 1000))
end

EOF
    
    # 记录测试后的内存状态
    echo "" >> "$RESULT_DIR/${name}_${TIMESTAMP}.log"
    echo "测试后内存状态:" >> "$RESULT_DIR/${name}_${TIMESTAMP}.log"
    ps aux | grep -E "(workerman|think-runtime)" | grep -v grep >> "$RESULT_DIR/${name}_${TIMESTAMP}.log"
    
    # 等待一段时间让系统恢复
    echo "等待系统恢复..."
    sleep 5
    echo ""
done

echo "=== 生成综合报告 ==="

# 生成综合报告
REPORT_FILE="$RESULT_DIR/comprehensive_report_${TIMESTAMP}.txt"

cat > $REPORT_FILE << EOF
=== ThinkPHP Workerman 压力测试综合报告 ===

测试时间: $(date)
测试目标: http://127.0.0.1:8080/
测试工具: wrk

=== 系统信息 ===
操作系统: $(uname -a)
PHP版本: $(php -v | head -n 1)
内存限制: $(php -r "echo ini_get('memory_limit');")

=== 测试结果摘要 ===

EOF

# 提取每个测试的关键指标
for test in "${TESTS[@]}"; do
    IFS=':' read -r name threads connections duration <<< "$test"
    log_file="$RESULT_DIR/${name}_${TIMESTAMP}.log"
    
    if [ -f "$log_file" ]; then
        echo "--- $name ---" >> $REPORT_FILE
        
        # 提取QPS
        qps=$(grep "平均QPS:" "$log_file" | awk '{print $2}')
        if [ -n "$qps" ]; then
            echo "QPS: $qps" >> $REPORT_FILE
        fi
        
        # 提取延迟
        latency=$(grep "平均延迟:" "$log_file" | awk '{print $2}')
        if [ -n "$latency" ]; then
            echo "平均延迟: $latency" >> $REPORT_FILE
        fi
        
        # 提取错误率
        error_rate=$(grep "错误率:" "$log_file" | awk '{print $2}')
        if [ -n "$error_rate" ]; then
            echo "错误率: $error_rate" >> $REPORT_FILE
        fi
        
        echo "" >> $REPORT_FILE
    fi
done

# 添加内存分析
echo "=== 内存使用分析 ===" >> $REPORT_FILE
echo "详细内存数据请查看各个测试的日志文件" >> $REPORT_FILE
echo "" >> $REPORT_FILE

# 添加建议
cat >> $REPORT_FILE << EOF
=== 性能建议 ===

1. 如果QPS低于预期:
   - 检查 PHP 配置 (memory_limit, max_execution_time)
   - 调整 Workerman 进程数 (count 参数)
   - 优化应用代码和数据库查询

2. 如果内存使用过高:
   - 启用 opcache
   - 检查内存泄漏
   - 调整垃圾回收设置

3. 如果延迟过高:
   - 检查网络配置
   - 优化数据库连接
   - 使用缓存减少计算

4. 如果错误率过高:
   - 检查错误日志
   - 增加内存限制
   - 减少并发连接数

=== 文件说明 ===
- comprehensive_report_${TIMESTAMP}.txt: 综合报告
- *_${TIMESTAMP}.log: 各阶段详细测试日志

EOF

echo "✅ 压力测试完成!"
echo "📊 综合报告: $REPORT_FILE"
echo "📁 详细日志: $RESULT_DIR/"
echo ""
echo "=== 快速查看结果 ==="
cat $REPORT_FILE

# 检查是否有明显的问题
echo ""
echo "=== 问题检查 ==="

# 检查是否有进程崩溃
if ! curl -s http://127.0.0.1:8080/ > /dev/null; then
    echo "⚠️  警告: 测试后服务无法访问，可能已崩溃"
else
    echo "✅ 服务仍在正常运行"
fi

# 检查内存使用
memory_usage=$(ps aux | grep -E "(workerman|think-runtime)" | grep -v grep | awk '{sum+=$6} END {print sum}')
if [ -n "$memory_usage" ] && [ "$memory_usage" -gt 500000 ]; then
    echo "⚠️  警告: 内存使用较高 (${memory_usage}KB)"
else
    echo "✅ 内存使用正常"
fi

echo ""
echo "测试完成时间: $(date)"
