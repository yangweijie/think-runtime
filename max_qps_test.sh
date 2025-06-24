#!/bin/bash

# Workerman Runtime 最大 QPS 承受能力测试
# 测试不同并发参数下的性能表现

set -e

SERVER_URL="http://127.0.0.1:8080"
RESULTS_DIR="./max_qps_results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEST_DIR="$RESULTS_DIR/$TIMESTAMP"

# 创建结果目录
mkdir -p "$TEST_DIR"

echo "=== Workerman Runtime 最大 QPS 测试 ==="
echo "测试时间: $(date)"
echo "服务器: $SERVER_URL"
echo "结果目录: $TEST_DIR"
echo ""

# 检查服务器是否可用
check_server() {
    if ! curl -s "$SERVER_URL" > /dev/null; then
        echo "❌ 服务器 $SERVER_URL 不可访问"
        echo "请先启动服务器: php test_workerman_keepalive.php start"
        exit 1
    fi
    echo "✅ 服务器连接正常"
}

# 测试函数
run_test() {
    local concurrency=$1
    local threads=$2
    local duration=$3
    local test_name=$4
    
    echo "测试: $test_name (并发: $concurrency, 线程: $threads, 时长: ${duration}s)"
    
    local output_file="$TEST_DIR/${test_name}_c${concurrency}_t${threads}.txt"
    
    wrk -t$threads -c$concurrency -d${duration}s \
        -H "Connection: keep-alive" \
        -H "Keep-Alive: timeout=60, max=1000" \
        "$SERVER_URL" > "$output_file" 2>&1
    
    # 提取关键指标
    local qps=$(grep "Requests/sec" "$output_file" | awk '{print $2}' | head -1)
    local latency=$(grep "Latency" "$output_file" | awk '{print $2}' | head -1)
    local errors=$(grep "Socket errors" "$output_file" | awk -F'timeout ' '{print $2}' | head -1)
    
    echo "  QPS: $qps, 延迟: $latency, 超时: $errors"
    
    # 记录到摘要文件
    echo "$concurrency,$threads,$qps,$latency,$errors,$test_name" >> "$TEST_DIR/summary.csv"
}

# 创建摘要文件头
echo "Concurrency,Threads,QPS,Latency,Timeouts,TestName" > "$TEST_DIR/summary.csv"

# 主测试函数
main() {
    check_server
    
    echo ""
    echo "=== 开始多并发参数测试 ==="
    echo ""
    
    # 测试1: 低并发基准测试
    echo "1. 低并发基准测试"
    run_test 50 2 15 "baseline_low"
    run_test 100 4 15 "baseline_medium"
    run_test 200 4 15 "baseline_high"
    
    echo ""
    
    # 测试2: 中等并发测试
    echo "2. 中等并发测试"
    run_test 300 6 20 "medium_300"
    run_test 400 8 20 "medium_400"
    run_test 500 8 20 "medium_500"
    
    echo ""
    
    # 测试3: 高并发测试
    echo "3. 高并发测试"
    run_test 600 8 20 "high_600"
    run_test 800 8 20 "high_800"
    run_test 1000 8 20 "high_1000"
    
    echo ""
    
    # 测试4: 极高并发测试
    echo "4. 极高并发测试"
    run_test 1200 12 20 "extreme_1200"
    run_test 1500 12 20 "extreme_1500"
    run_test 2000 16 20 "extreme_2000"
    
    echo ""
    
    # 测试5: 超高并发测试
    echo "5. 超高并发测试"
    run_test 2500 16 20 "ultra_2500"
    run_test 3000 16 20 "ultra_3000"
    run_test 4000 16 20 "ultra_4000"
    
    echo ""
    echo "=== 所有测试完成 ==="
    
    # 分析结果
    analyze_results
}

# 分析测试结果
analyze_results() {
    echo ""
    echo "=== 测试结果分析 ==="
    
    local summary_file="$TEST_DIR/analysis.txt"
    echo "Workerman Runtime 最大 QPS 测试分析" > "$summary_file"
    echo "测试时间: $(date)" >> "$summary_file"
    echo "" >> "$summary_file"
    
    echo "QPS 性能表现:" | tee -a "$summary_file"
    echo "并发数 | 线程数 | QPS | 延迟 | 超时" | tee -a "$summary_file"
    echo "-------|--------|-----|------|------" | tee -a "$summary_file"
    
    # 读取并排序结果
    tail -n +2 "$TEST_DIR/summary.csv" | sort -t',' -k1 -n | while IFS=',' read -r concurrency threads qps latency timeouts testname; do
        printf "%-7s | %-6s | %-8s | %-8s | %-8s\n" "$concurrency" "$threads" "$qps" "$latency" "$timeouts" | tee -a "$summary_file"
    done
    
    echo "" | tee -a "$summary_file"
    
    # 找出最高 QPS
    local max_qps_line=$(tail -n +2 "$TEST_DIR/summary.csv" | sort -t',' -k3 -nr | head -1)
    local max_qps=$(echo "$max_qps_line" | cut -d',' -f3)
    local max_qps_concurrency=$(echo "$max_qps_line" | cut -d',' -f1)
    
    echo "🏆 最高 QPS: $max_qps (并发: $max_qps_concurrency)" | tee -a "$summary_file"
    
    # 找出性能拐点
    echo "" | tee -a "$summary_file"
    echo "性能分析:" | tee -a "$summary_file"
    
    # 计算不同并发级别的平均 QPS
    local low_avg=$(tail -n +2 "$TEST_DIR/summary.csv" | awk -F',' '$1 <= 200 {sum+=$3; count++} END {if(count>0) print sum/count; else print 0}')
    local medium_avg=$(tail -n +2 "$TEST_DIR/summary.csv" | awk -F',' '$1 > 200 && $1 <= 1000 {sum+=$3; count++} END {if(count>0) print sum/count; else print 0}')
    local high_avg=$(tail -n +2 "$TEST_DIR/summary.csv" | awk -F',' '$1 > 1000 {sum+=$3; count++} END {if(count>0) print sum/count; else print 0}')
    
    echo "- 低并发 (≤200): 平均 QPS $low_avg" | tee -a "$summary_file"
    echo "- 中并发 (201-1000): 平均 QPS $medium_avg" | tee -a "$summary_file"
    echo "- 高并发 (>1000): 平均 QPS $high_avg" | tee -a "$summary_file"
    
    echo "" | tee -a "$summary_file"
    echo "详细结果文件: $TEST_DIR" | tee -a "$summary_file"
}

# 生成图表数据
generate_chart_data() {
    echo ""
    echo "=== 生成图表数据 ==="
    
    local chart_file="$TEST_DIR/chart_data.json"
    echo "{" > "$chart_file"
    echo '  "title": "Workerman Runtime QPS vs Concurrency",' >> "$chart_file"
    echo '  "data": [' >> "$chart_file"
    
    local first=true
    tail -n +2 "$TEST_DIR/summary.csv" | sort -t',' -k1 -n | while IFS=',' read -r concurrency threads qps latency timeouts testname; do
        if [ "$first" = true ]; then
            first=false
        else
            echo "," >> "$chart_file"
        fi
        echo "    {\"concurrency\": $concurrency, \"qps\": $qps, \"latency\": \"$latency\", \"timeouts\": $timeouts}" >> "$chart_file"
    done
    
    echo "" >> "$chart_file"
    echo "  ]" >> "$chart_file"
    echo "}" >> "$chart_file"
    
    echo "图表数据已生成: $chart_file"
}

# 运行主函数
main "$@"
generate_chart_data

echo ""
echo "🎉 最大 QPS 测试完成！"
echo "📊 结果目录: $TEST_DIR"
echo "📋 摘要文件: $TEST_DIR/summary.csv"
echo "📄 分析报告: $TEST_DIR/analysis.txt"
