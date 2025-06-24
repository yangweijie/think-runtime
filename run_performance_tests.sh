#!/bin/bash

# Workerman 性能测试脚本
# 包含内存泄漏测试和 QPS 测试

echo "=== Workerman 性能测试套件 ==="
echo ""

# 检查依赖
check_dependencies() {
    echo "检查依赖..."
    
    # 检查 PHP
    if ! command -v php &> /dev/null; then
        echo "❌ PHP 未安装"
        exit 1
    fi
    
    # 检查 wrk
    if ! command -v wrk &> /dev/null; then
        echo "⚠️  wrk 未安装，将使用 curl 进行简单测试"
        echo "   安装 wrk: brew install wrk (macOS) 或 apt-get install wrk (Ubuntu)"
        USE_WRK=false
    else
        USE_WRK=true
    fi
    
    # 检查 ab
    if ! command -v ab &> /dev/null; then
        echo "⚠️  Apache Bench (ab) 未安装"
        USE_AB=false
    else
        USE_AB=true
    fi
    
    echo "✅ 依赖检查完成"
    echo ""
}

# 启动内存泄漏测试服务器
start_memory_test() {
    echo "=== 启动内存泄漏测试服务器 ==="
    echo "端口: 8081"
    echo "进程数: 2"
    echo "监控间隔: 10秒"
    echo ""
    
    php test_workerman_memory_leak.php &
    MEMORY_PID=$!
    
    echo "内存测试服务器已启动 (PID: $MEMORY_PID)"
    echo "等待服务器启动..."
    sleep 3
    
    # 简单测试连接
    if curl -s http://127.0.0.1:8081/ > /dev/null; then
        echo "✅ 内存测试服务器运行正常"
    else
        echo "❌ 内存测试服务器启动失败"
        kill $MEMORY_PID 2>/dev/null
        return 1
    fi
    
    echo ""
    echo "内存测试服务器信息:"
    echo "- URL: http://127.0.0.1:8081/"
    echo "- 监控: 每10秒输出内存统计"
    echo "- 特性: 内存泄漏检测、GC监控、上下文管理"
    echo ""
    
    return 0
}

# 启动 QPS 测试服务器
start_qps_test() {
    echo "=== 启动 QPS 测试服务器 ==="
    echo "端口: 8082"
    echo "进程数: 4"
    echo "优化: 高性能模式"
    echo ""
    
    php test_workerman_qps.php &
    QPS_PID=$!
    
    echo "QPS 测试服务器已启动 (PID: $QPS_PID)"
    echo "等待服务器启动..."
    sleep 3
    
    # 简单测试连接
    if curl -s http://127.0.0.1:8082/ > /dev/null; then
        echo "✅ QPS 测试服务器运行正常"
    else
        echo "❌ QPS 测试服务器启动失败"
        kill $QPS_PID 2>/dev/null
        return 1
    fi
    
    echo ""
    echo "QPS 测试服务器信息:"
    echo "- URL: http://127.0.0.1:8082/"
    echo "- 监控: 每5秒输出QPS统计"
    echo "- 特性: 端口复用、内存优化、高并发"
    echo ""
    
    return 0
}

# 运行内存泄漏测试
run_memory_leak_test() {
    echo "=== 内存泄漏测试 ==="
    echo ""
    
    if ! start_memory_test; then
        return 1
    fi
    
    echo "开始内存泄漏测试..."
    echo "测试时间: 60秒"
    echo "并发数: 50"
    echo ""
    
    if [ "$USE_WRK" = true ]; then
        echo "使用 wrk 进行压力测试..."
        wrk -t4 -c50 -d60s http://127.0.0.1:8081/
    elif [ "$USE_AB" = true ]; then
        echo "使用 ab 进行压力测试..."
        ab -n 3000 -c 50 http://127.0.0.1:8081/
    else
        echo "使用 curl 进行简单测试..."
        for i in {1..100}; do
            curl -s http://127.0.0.1:8081/ > /dev/null
            if [ $((i % 10)) -eq 0 ]; then
                echo "已完成 $i/100 请求"
            fi
        done
    fi
    
    echo ""
    echo "内存泄漏测试完成，观察服务器输出的内存统计信息"
    echo "正常情况下内存应该保持稳定，不会持续增长"
    echo ""
    
    # 停止服务器
    kill $MEMORY_PID 2>/dev/null
    wait $MEMORY_PID 2>/dev/null
    echo "内存测试服务器已停止"
    echo ""
}

# 运行 QPS 测试
run_qps_test() {
    echo "=== QPS 性能测试 ==="
    echo ""
    
    if ! start_qps_test; then
        return 1
    fi
    
    echo "开始 QPS 性能测试..."
    echo ""
    
    if [ "$USE_WRK" = true ]; then
        echo "1. 轻量级测试 (30秒, 100并发):"
        wrk -t4 -c100 -d30s --latency http://127.0.0.1:8082/
        echo ""
        
        echo "2. 中等强度测试 (30秒, 200并发):"
        wrk -t8 -c200 -d30s --latency http://127.0.0.1:8082/
        echo ""
        
        echo "3. 高强度测试 (60秒, 500并发):"
        wrk -t8 -c500 -d60s --latency http://127.0.0.1:8082/
        
    elif [ "$USE_AB" = true ]; then
        echo "1. 轻量级测试:"
        ab -n 10000 -c 100 -k http://127.0.0.1:8082/
        echo ""
        
        echo "2. 中等强度测试:"
        ab -n 20000 -c 200 -k http://127.0.0.1:8082/
        
    else
        echo "使用 curl 进行简单 QPS 测试..."
        start_time=$(date +%s)
        for i in {1..1000}; do
            curl -s http://127.0.0.1:8082/ > /dev/null
        done
        end_time=$(date +%s)
        duration=$((end_time - start_time))
        qps=$((1000 / duration))
        echo "简单测试结果: 1000 请求用时 ${duration}秒, QPS: ${qps}"
    fi
    
    echo ""
    echo "QPS 测试完成，查看服务器输出的性能统计信息"
    echo ""
    
    # 停止服务器
    kill $QPS_PID 2>/dev/null
    wait $QPS_PID 2>/dev/null
    echo "QPS 测试服务器已停止"
    echo ""
}

# 清理函数
cleanup() {
    echo ""
    echo "清理测试环境..."
    
    if [ ! -z "$MEMORY_PID" ]; then
        kill $MEMORY_PID 2>/dev/null
        wait $MEMORY_PID 2>/dev/null
    fi
    
    if [ ! -z "$QPS_PID" ]; then
        kill $QPS_PID 2>/dev/null
        wait $QPS_PID 2>/dev/null
    fi
    
    echo "清理完成"
}

# 设置信号处理
trap cleanup EXIT INT TERM

# 主函数
main() {
    check_dependencies
    
    echo "选择测试类型:"
    echo "1. 内存泄漏测试"
    echo "2. QPS 性能测试"
    echo "3. 全部测试"
    echo ""
    read -p "请输入选择 (1-3): " choice
    
    case $choice in
        1)
            run_memory_leak_test
            ;;
        2)
            run_qps_test
            ;;
        3)
            run_memory_leak_test
            echo "等待 5 秒后开始 QPS 测试..."
            sleep 5
            run_qps_test
            ;;
        *)
            echo "无效选择"
            exit 1
            ;;
    esac
    
    echo ""
    echo "=== 测试完成 ==="
    echo "如需查看详细的服务器日志，请单独运行测试服务器文件"
}

# 运行主函数
main
