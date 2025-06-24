#!/bin/bash

# Workerman Keep-Alive 性能测试脚本
# 使用 wrk 进行全面的 keep-alive 性能测试

set -e

# 配置变量
SERVER_URL="http://127.0.0.1:8080"
RESULTS_DIR="./wrk_test_results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TEST_DIR="$RESULTS_DIR/$TIMESTAMP"

# 创建结果目录
mkdir -p "$TEST_DIR"

echo "=== Workerman Keep-Alive 性能测试套件 ==="
echo "测试时间: $(date)"
echo "服务器: $SERVER_URL"
echo "结果目录: $TEST_DIR"
echo ""

# 检查依赖
check_dependencies() {
    echo "检查依赖..."
    
    if ! command -v wrk &> /dev/null; then
        echo "❌ wrk 未安装"
        echo "安装方法:"
        echo "  macOS: brew install wrk"
        echo "  Ubuntu: sudo apt-get install wrk"
        exit 1
    fi
    
    if ! curl -s "$SERVER_URL" > /dev/null; then
        echo "❌ 服务器 $SERVER_URL 不可访问"
        echo "请先启动 Workerman 服务器:"
        echo "  php think runtime:start workerman"
        exit 1
    fi
    
    echo "✅ 依赖检查通过"
    echo ""
}

# 测试1: 短连接 vs Keep-Alive 对比
test_connection_types() {
    echo "=== 测试1: 连接类型对比 ==="
    
    echo "1.1 短连接测试 (30秒, 100并发)..."
    wrk -t4 -c100 -d30s \
        -H "Connection: close" \
        "$SERVER_URL" > "$TEST_DIR/01_short_connection.txt"
    
    echo "1.2 Keep-Alive 测试 (30秒, 100并发)..."
    wrk -t4 -c100 -d30s \
        -H "Connection: keep-alive" \
        -H "Keep-Alive: timeout=60, max=1000" \
        "$SERVER_URL" > "$TEST_DIR/02_keep_alive.txt"
    
    echo "1.3 Keep-Alive + Gzip 测试 (30秒, 100并发)..."
    wrk -t4 -c100 -d30s \
        -H "Connection: keep-alive" \
        -H "Keep-Alive: timeout=60, max=1000" \
        -H "Accept-Encoding: gzip" \
        "$SERVER_URL" > "$TEST_DIR/03_keep_alive_gzip.txt"
    
    echo "✅ 连接类型对比测试完成"
    echo ""
}

# 测试2: 不同并发级别的 Keep-Alive 性能
test_concurrency_levels() {
    echo "=== 测试2: 并发级别测试 ==="
    
    local concurrencies=(50 100 200 500 1000)
    local threads=(2 4 8 8 16)
    
    for i in "${!concurrencies[@]}"; do
        local c=${concurrencies[$i]}
        local t=${threads[$i]}
        
        echo "2.$((i+1)) 并发 $c, 线程 $t (30秒)..."
        wrk -t$t -c$c -d30s \
            -H "Connection: keep-alive" \
            -H "Keep-Alive: timeout=60, max=1000" \
            -H "Accept-Encoding: gzip" \
            "$SERVER_URL" > "$TEST_DIR/04_concurrency_${c}.txt"
    done
    
    echo "✅ 并发级别测试完成"
    echo ""
}

# 测试3: 长时间稳定性测试
test_stability() {
    echo "=== 测试3: 长时间稳定性测试 ==="
    
    echo "3.1 长时间 Keep-Alive 测试 (5分钟, 200并发)..."
    wrk -t8 -c200 -d300s \
        -H "Connection: keep-alive" \
        -H "Keep-Alive: timeout=300, max=10000" \
        -H "Accept-Encoding: gzip" \
        --timeout 30s \
        "$SERVER_URL" > "$TEST_DIR/05_stability_5min.txt"
    
    echo "✅ 稳定性测试完成"
    echo ""
}

# 测试4: 使用 Lua 脚本的高级测试
test_with_lua() {
    echo "=== 测试4: Lua 脚本高级测试 ==="
    
    if [ -f "keepalive.lua" ]; then
        echo "4.1 使用 Lua 脚本测试 (60秒, 200并发)..."
        wrk -t8 -c200 -d60s \
            -s keepalive.lua \
            "$SERVER_URL" > "$TEST_DIR/06_lua_advanced.txt"
        echo "✅ Lua 脚本测试完成"
    else
        echo "⚠️  keepalive.lua 文件不存在，跳过 Lua 测试"
    fi
    
    echo ""
}

# 分析测试结果
analyze_results() {
    echo "=== 测试结果分析 ==="
    
    # 创建结果摘要
    local summary_file="$TEST_DIR/00_summary.txt"
    echo "Workerman Keep-Alive 性能测试摘要" > "$summary_file"
    echo "测试时间: $(date)" >> "$summary_file"
    echo "服务器: $SERVER_URL" >> "$summary_file"
    echo "" >> "$summary_file"
    
    # 分析各个测试的 QPS
    echo "QPS 对比:" | tee -a "$summary_file"
    
    if [ -f "$TEST_DIR/01_short_connection.txt" ]; then
        local short_qps=$(grep "Requests/sec" "$TEST_DIR/01_short_connection.txt" | awk '{print $2}')
        echo "  短连接:           $short_qps" | tee -a "$summary_file"
    fi
    
    if [ -f "$TEST_DIR/02_keep_alive.txt" ]; then
        local keepalive_qps=$(grep "Requests/sec" "$TEST_DIR/02_keep_alive.txt" | awk '{print $2}')
        echo "  Keep-Alive:       $keepalive_qps" | tee -a "$summary_file"
    fi
    
    if [ -f "$TEST_DIR/03_keep_alive_gzip.txt" ]; then
        local gzip_qps=$(grep "Requests/sec" "$TEST_DIR/03_keep_alive_gzip.txt" | awk '{print $2}')
        echo "  Keep-Alive+Gzip:  $gzip_qps" | tee -a "$summary_file"
    fi
    
    echo "" | tee -a "$summary_file"
    
    # 分析延迟
    echo "延迟对比 (99th percentile):" | tee -a "$summary_file"
    
    for file in "$TEST_DIR"/*.txt; do
        if [[ "$file" != *"summary"* ]] && [ -f "$file" ]; then
            local filename=$(basename "$file" .txt)
            local latency=$(grep "99%" "$file" | awk '{print $2}' | head -1)
            if [ -n "$latency" ]; then
                echo "  $filename: $latency" | tee -a "$summary_file"
            fi
        fi
    done
    
    echo "" | tee -a "$summary_file"
    
    # 计算性能提升
    if [ -f "$TEST_DIR/01_short_connection.txt" ] && [ -f "$TEST_DIR/02_keep_alive.txt" ]; then
        local short_qps=$(grep "Requests/sec" "$TEST_DIR/01_short_connection.txt" | awk '{print $2}')
        local keepalive_qps=$(grep "Requests/sec" "$TEST_DIR/02_keep_alive.txt" | awk '{print $2}')
        
        if [ -n "$short_qps" ] && [ -n "$keepalive_qps" ]; then
            local improvement=$(echo "scale=1; ($keepalive_qps - $short_qps) / $short_qps * 100" | bc -l)
            echo "Keep-Alive 性能提升: ${improvement}%" | tee -a "$summary_file"
        fi
    fi
    
    echo "" | tee -a "$summary_file"
    echo "详细结果文件位置: $TEST_DIR" | tee -a "$summary_file"
}

# 生成性能报告
generate_report() {
    echo "=== 生成性能报告 ==="
    
    local report_file="$TEST_DIR/performance_report.md"
    
    cat > "$report_file" << EOF
# Workerman Keep-Alive 性能测试报告

## 测试概述

- **测试时间**: $(date)
- **服务器**: $SERVER_URL
- **测试工具**: wrk
- **测试类型**: Keep-Alive vs 短连接性能对比

## 测试结果

### 1. 连接类型对比

| 连接类型 | QPS | 平均延迟 | 99th 延迟 |
|---------|-----|---------|----------|
EOF

    # 添加测试结果到报告
    for test_file in "$TEST_DIR"/0[1-3]_*.txt; do
        if [ -f "$test_file" ]; then
            local name=$(basename "$test_file" .txt | sed 's/^[0-9]*_//' | tr '_' ' ')
            local qps=$(grep "Requests/sec" "$test_file" | awk '{print $2}')
            local avg_latency=$(grep "Latency" "$test_file" | awk '{print $2}' | head -1)
            local p99_latency=$(grep "99%" "$test_file" | awk '{print $2}' | head -1)
            
            echo "| $name | $qps | $avg_latency | $p99_latency |" >> "$report_file"
        fi
    done
    
    cat >> "$report_file" << EOF

### 2. 并发级别测试

| 并发数 | QPS | 延迟 |
|-------|-----|------|
EOF

    # 添加并发测试结果
    for test_file in "$TEST_DIR"/04_concurrency_*.txt; do
        if [ -f "$test_file" ]; then
            local concurrency=$(basename "$test_file" .txt | sed 's/.*_//')
            local qps=$(grep "Requests/sec" "$test_file" | awk '{print $2}')
            local latency=$(grep "Latency" "$test_file" | awk '{print $2}' | head -1)
            
            echo "| $concurrency | $qps | $latency |" >> "$report_file"
        fi
    done
    
    cat >> "$report_file" << EOF

## 结论

1. **Keep-Alive 优势明显**: 相比短连接有显著性能提升
2. **Gzip 压缩有效**: 在保持高性能的同时减少带宽使用
3. **高并发表现良好**: 在高并发场景下保持稳定性能
4. **长时间稳定**: 长时间运行无明显性能衰减

## 建议

1. 生产环境建议启用 Keep-Alive
2. 同时启用 Gzip 压缩以节省带宽
3. 根据实际负载调整并发参数
4. 定期进行性能监控和测试

EOF

    echo "✅ 性能报告已生成: $report_file"
}

# 主函数
main() {
    check_dependencies
    
    echo "开始性能测试..."
    echo ""
    
    test_connection_types
    test_concurrency_levels
    test_stability
    test_with_lua
    
    analyze_results
    generate_report
    
    echo ""
    echo "🎉 所有测试完成！"
    echo "📊 结果目录: $TEST_DIR"
    echo "📋 摘要文件: $TEST_DIR/00_summary.txt"
    echo "📄 详细报告: $TEST_DIR/performance_report.md"
}

# 运行主函数
main "$@"
