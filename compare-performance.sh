#!/bin/bash

# 性能对比测试脚本
# 对比原始适配器和优化适配器的性能

echo "=== Workerman 适配器性能对比测试 ==="
echo "测试时间: $(date)"
echo ""

# 检查依赖
if ! command -v wrk &> /dev/null; then
    echo "❌ wrk 未安装"
    exit 1
fi

if ! command -v php &> /dev/null; then
    echo "❌ PHP 未安装"
    exit 1
fi

cd /Volumes/data/git/php/tp

# 创建结果目录
mkdir -p performance_comparison
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

echo "=== 测试1: 原始 Workerman 适配器 ==="

# 启动原始服务
echo "启动原始 Workerman 服务..."
nohup php think runtime:start workerman > performance_comparison/original_${TIMESTAMP}.log 2>&1 &
ORIGINAL_PID=$!
echo "原始服务 PID: $ORIGINAL_PID"

# 等待服务启动
sleep 5

# 检查服务是否启动
if ! curl -s http://127.0.0.1:8080/ > /dev/null; then
    echo "❌ 原始服务启动失败"
    kill $ORIGINAL_PID 2>/dev/null || true
    exit 1
fi

echo "✅ 原始服务启动成功"

# 压测原始服务
echo "压测原始服务..."
wrk -t8 -c200 -d60s --latency http://127.0.0.1:8080/ > performance_comparison/original_wrk_${TIMESTAMP}.txt 2>&1

# 停止原始服务
echo "停止原始服务..."
kill $ORIGINAL_PID 2>/dev/null || true
sleep 3

echo ""
echo "=== 测试2: 优化 Workerman 适配器 ==="

# 启动优化服务
echo "启动优化 Workerman 服务..."
nohup php start-optimized-workerman.php > performance_comparison/optimized_${TIMESTAMP}.log 2>&1 &
OPTIMIZED_PID=$!
echo "优化服务 PID: $OPTIMIZED_PID"

# 等待服务启动
sleep 5

# 检查服务是否启动
if ! curl -s http://127.0.0.1:8087/ > /dev/null; then
    echo "❌ 优化服务启动失败"
    kill $OPTIMIZED_PID 2>/dev/null || true
    exit 1
fi

echo "✅ 优化服务启动成功"

# 压测优化服务
echo "压测优化服务..."
wrk -t8 -c200 -d60s --latency http://127.0.0.1:8087/ > performance_comparison/optimized_wrk_${TIMESTAMP}.txt 2>&1

# 停止优化服务
echo "停止优化服务..."
kill $OPTIMIZED_PID 2>/dev/null || true
sleep 3

echo ""
echo "=== 生成对比报告 ==="

# 生成对比报告
REPORT_FILE="performance_comparison/comparison_report_${TIMESTAMP}.txt"

cat > $REPORT_FILE << EOF
=== Workerman 适配器性能对比报告 ===

测试时间: $(date)
测试环境: $(uname -a)
PHP版本: $(php -v | head -n 1)

=== 测试配置 ===
- 线程数: 8
- 连接数: 200
- 持续时间: 60秒
- 原始服务端口: 8080
- 优化服务端口: 8087

=== 原始适配器结果 ===
EOF

# 提取原始适配器结果
if [ -f "performance_comparison/original_wrk_${TIMESTAMP}.txt" ]; then
    echo "" >> $REPORT_FILE
    cat "performance_comparison/original_wrk_${TIMESTAMP}.txt" >> $REPORT_FILE
    
    # 提取关键指标
    ORIGINAL_QPS=$(grep "Requests/sec:" "performance_comparison/original_wrk_${TIMESTAMP}.txt" | awk '{print $2}')
    ORIGINAL_LATENCY=$(grep "Latency" "performance_comparison/original_wrk_${TIMESTAMP}.txt" | head -n 1 | awk '{print $2}')
    ORIGINAL_P99=$(grep "99%" "performance_comparison/original_wrk_${TIMESTAMP}.txt" | awk '{print $2}')
else
    echo "❌ 原始适配器测试结果文件不存在" >> $REPORT_FILE
fi

cat >> $REPORT_FILE << EOF

=== 优化适配器结果 ===
EOF

# 提取优化适配器结果
if [ -f "performance_comparison/optimized_wrk_${TIMESTAMP}.txt" ]; then
    echo "" >> $REPORT_FILE
    cat "performance_comparison/optimized_wrk_${TIMESTAMP}.txt" >> $REPORT_FILE
    
    # 提取关键指标
    OPTIMIZED_QPS=$(grep "Requests/sec:" "performance_comparison/optimized_wrk_${TIMESTAMP}.txt" | awk '{print $2}')
    OPTIMIZED_LATENCY=$(grep "Latency" "performance_comparison/optimized_wrk_${TIMESTAMP}.txt" | head -n 1 | awk '{print $2}')
    OPTIMIZED_P99=$(grep "99%" "performance_comparison/optimized_wrk_${TIMESTAMP}.txt" | awk '{print $2}')
else
    echo "❌ 优化适配器测试结果文件不存在" >> $REPORT_FILE
fi

# 计算改善
cat >> $REPORT_FILE << EOF

=== 性能对比分析 ===

关键指标对比:
EOF

if [ -n "$ORIGINAL_QPS" ] && [ -n "$OPTIMIZED_QPS" ]; then
    echo "- 原始 QPS: $ORIGINAL_QPS" >> $REPORT_FILE
    echo "- 优化 QPS: $OPTIMIZED_QPS" >> $REPORT_FILE
    
    # 计算 QPS 提升百分比
    QPS_IMPROVEMENT=$(echo "scale=2; ($OPTIMIZED_QPS - $ORIGINAL_QPS) / $ORIGINAL_QPS * 100" | bc -l 2>/dev/null || echo "计算失败")
    echo "- QPS 提升: ${QPS_IMPROVEMENT}%" >> $REPORT_FILE
fi

if [ -n "$ORIGINAL_LATENCY" ] && [ -n "$OPTIMIZED_LATENCY" ]; then
    echo "- 原始延迟: $ORIGINAL_LATENCY" >> $REPORT_FILE
    echo "- 优化延迟: $OPTIMIZED_LATENCY" >> $REPORT_FILE
fi

if [ -n "$ORIGINAL_P99" ] && [ -n "$OPTIMIZED_P99" ]; then
    echo "- 原始 99% 延迟: $ORIGINAL_P99" >> $REPORT_FILE
    echo "- 优化 99% 延迟: $OPTIMIZED_P99" >> $REPORT_FILE
fi

cat >> $REPORT_FILE << EOF

=== 优化效果评估 ===

基于 think-worker 的优化策略:
1. ✅ Sandbox 沙盒机制 - 应用实例隔离和复用
2. ✅ Clone 而非 New - 使用克隆而不是重建应用
3. ✅ 精确重置 - 只重置必要的实例
4. ✅ 内存管理优化 - 更好的垃圾回收策略

预期改善:
- QPS 提升: 20-40%
- 内存稳定性: 显著改善
- 延迟降低: 10-30%

=== 结论 ===

EOF

# 根据结果给出结论
if [ -n "$QPS_IMPROVEMENT" ] && [ "$QPS_IMPROVEMENT" != "计算失败" ]; then
    if (( $(echo "$QPS_IMPROVEMENT > 20" | bc -l) )); then
        echo "🎉 优化效果显著！QPS 提升超过 20%" >> $REPORT_FILE
    elif (( $(echo "$QPS_IMPROVEMENT > 10" | bc -l) )); then
        echo "✅ 优化效果明显，QPS 提升超过 10%" >> $REPORT_FILE
    elif (( $(echo "$QPS_IMPROVEMENT > 0" | bc -l) )); then
        echo "✅ 优化有效果，QPS 有所提升" >> $REPORT_FILE
    else
        echo "❌ 优化效果不明显，需要进一步调整" >> $REPORT_FILE
    fi
else
    echo "⚠️  无法计算性能提升，请检查测试结果" >> $REPORT_FILE
fi

echo "📊 详细报告: $REPORT_FILE"
echo "📁 测试日志: performance_comparison/"

# 显示报告摘要
echo ""
echo "=== 测试结果摘要 ==="
cat $REPORT_FILE

echo ""
echo "✅ 性能对比测试完成！"
echo "测试完成时间: $(date)"
