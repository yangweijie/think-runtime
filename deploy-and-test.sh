#!/bin/bash

# ThinkPHP Workerman 一键部署和测试脚本
# 
# 使用方法：
# 1. 在 think-runtime 项目目录运行此脚本
# 2. 脚本会自动复制文件到目标项目并启动测试

set -e  # 遇到错误立即退出

# 配置
TARGET_PROJECT="/Volumes/data/git/php/tp"
CURRENT_DIR=$(pwd)

echo "=== ThinkPHP Workerman 一键部署和测试 ==="
echo "源目录: $CURRENT_DIR"
echo "目标项目: $TARGET_PROJECT"
echo ""

# 检查目标项目是否存在
if [ ! -d "$TARGET_PROJECT" ]; then
    echo "❌ 目标项目目录不存在: $TARGET_PROJECT"
    echo "请确认项目路径是否正确"
    exit 1
fi

echo "✅ 目标项目目录存在"

# 检查必要文件是否存在
FILES_TO_COPY=(
    "real-project-memory-monitor.php"
    "real-project-stress-test.sh"
    "OptimizedWorkermanAdapter.php"
    "REAL_PROJECT_TEST_GUIDE.md"
)

echo "检查必要文件..."
for file in "${FILES_TO_COPY[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ 文件不存在: $file"
        exit 1
    fi
    echo "✅ $file"
done

# 复制文件到目标项目
echo ""
echo "=== 复制文件到目标项目 ==="
for file in "${FILES_TO_COPY[@]}"; do
    echo "复制 $file..."
    cp "$file" "$TARGET_PROJECT/"
done

# 设置执行权限
echo "设置执行权限..."
chmod +x "$TARGET_PROJECT/real-project-stress-test.sh"

echo "✅ 文件复制完成"

# 检查依赖
echo ""
echo "=== 检查依赖 ==="

# 检查 wrk
if ! command -v wrk &> /dev/null; then
    echo "⚠️  wrk 未安装，正在尝试安装..."
    if command -v brew &> /dev/null; then
        echo "使用 Homebrew 安装 wrk..."
        brew install wrk
    else
        echo "❌ 请手动安装 wrk:"
        echo "  macOS: brew install wrk"
        echo "  Ubuntu: sudo apt-get install wrk"
        echo "  CentOS: sudo yum install wrk"
        exit 1
    fi
else
    echo "✅ wrk 已安装"
fi

# 检查 PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP 未安装"
    exit 1
else
    echo "✅ PHP 已安装: $(php -v | head -n 1)"
fi

# 检查 Workerman
cd "$TARGET_PROJECT"
if ! php -r "require_once 'vendor/autoload.php'; exit(class_exists('Workerman\Worker') ? 0 : 1);" 2>/dev/null; then
    echo "❌ Workerman 未安装"
    echo "请在项目目录运行: composer require workerman/workerman"
    exit 1
else
    echo "✅ Workerman 已安装"
fi

# 检查 think-runtime 命令
if [ ! -f "think" ]; then
    echo "❌ ThinkPHP think 命令不存在"
    exit 1
else
    echo "✅ ThinkPHP think 命令存在"
fi

# 检查端口是否被占用
if lsof -i :8080 &> /dev/null; then
    echo "⚠️  端口 8080 被占用，正在尝试释放..."
    lsof -ti :8080 | xargs kill -9 2>/dev/null || true
    sleep 2
fi

echo ""
echo "=== 启动测试 ==="

# 创建日志目录
mkdir -p runtime/logs

# 启动内存监控（后台运行）
echo "启动内存监控..."
nohup php real-project-memory-monitor.php > runtime/logs/memory-monitor.log 2>&1 &
MONITOR_PID=$!
echo "内存监控 PID: $MONITOR_PID"

# 等待一下
sleep 2

# 启动 Workerman 服务（后台运行）
echo "启动 Workerman 服务..."
nohup php think-runtime workerman > runtime/logs/workerman.log 2>&1 &
WORKERMAN_PID=$!
echo "Workerman PID: $WORKERMAN_PID"

# 等待服务启动
echo "等待服务启动..."
sleep 5

# 检查服务是否启动成功
if ! curl -s http://127.0.0.1:8080/ > /dev/null; then
    echo "❌ Workerman 服务启动失败"
    echo "查看日志: tail -f runtime/logs/workerman.log"
    
    # 清理进程
    kill $MONITOR_PID $WORKERMAN_PID 2>/dev/null || true
    exit 1
fi

echo "✅ Workerman 服务启动成功"

# 执行压力测试
echo ""
echo "=== 开始压力测试 ==="
echo "这可能需要几分钟时间..."

# 运行压测脚本
if ./real-project-stress-test.sh; then
    echo "✅ 压力测试完成"
else
    echo "❌ 压力测试失败"
fi

# 等待一下让监控收集数据
sleep 5

# 停止服务
echo ""
echo "=== 停止服务 ==="
echo "停止 Workerman 服务..."
kill $WORKERMAN_PID 2>/dev/null || true

echo "停止内存监控..."
kill $MONITOR_PID 2>/dev/null || true

# 等待进程完全停止
sleep 3

# 显示测试结果
echo ""
echo "=== 测试结果 ==="

# 查找最新的测试报告
LATEST_REPORT=$(ls -t stress_test_results/comprehensive_report_*.txt 2>/dev/null | head -n 1)
if [ -n "$LATEST_REPORT" ]; then
    echo "📊 压力测试报告: $LATEST_REPORT"
    echo ""
    cat "$LATEST_REPORT"
else
    echo "⚠️  未找到压力测试报告"
fi

# 显示内存监控结果
if [ -f "runtime/memory_monitor.log.report" ]; then
    echo ""
    echo "📈 内存监控报告:"
    cat "runtime/memory_monitor.log.report"
else
    echo "⚠️  未找到内存监控报告"
fi

# 显示日志文件位置
echo ""
echo "=== 日志文件位置 ==="
echo "Workerman 日志: runtime/logs/workerman.log"
echo "内存监控日志: runtime/logs/memory-monitor.log"
echo "内存监控报告: runtime/memory_monitor.log.report"
echo "压力测试结果: stress_test_results/"

# 检查是否有明显问题
echo ""
echo "=== 问题检查 ==="

# 检查内存使用
if [ -f "runtime/memory_monitor.log" ]; then
    MAX_MEMORY=$(grep -o '"memory":[0-9]*' runtime/memory_monitor.log | cut -d: -f2 | sort -n | tail -n 1)
    if [ -n "$MAX_MEMORY" ] && [ "$MAX_MEMORY" -gt 104857600 ]; then  # 100MB
        echo "⚠️  检测到高内存使用: $(($MAX_MEMORY / 1024 / 1024))MB"
    else
        echo "✅ 内存使用正常"
    fi
fi

# 检查错误日志
if grep -q "Fatal error\|PHP Fatal error" runtime/logs/workerman.log 2>/dev/null; then
    echo "❌ 检测到 PHP 致命错误，请查看日志"
else
    echo "✅ 未检测到致命错误"
fi

echo ""
echo "=== 测试完成 ==="
echo "如需重新测试，请再次运行此脚本"
echo "如需查看详细日志，请检查 runtime/logs/ 目录"

# 提供下一步建议
echo ""
echo "=== 下一步建议 ==="
echo "1. 查看压力测试报告分析性能"
echo "2. 检查内存监控报告确认无泄漏"
echo "3. 根据结果调整配置参数"
echo "4. 如有问题，查看详细日志文件"
