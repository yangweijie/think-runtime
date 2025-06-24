#!/bin/bash

# Workerman 端口占用问题解决脚本

echo "=== Workerman 端口占用问题解决 ==="

# 检查端口占用
check_port() {
    local port=$1
    echo "检查端口 $port 占用情况..."
    
    # 使用 lsof 检查端口
    local pid=$(lsof -ti:$port 2>/dev/null)
    if [ -n "$pid" ]; then
        echo "端口 $port 被进程 $pid 占用"
        ps -p $pid -o pid,ppid,command
        return 1
    else
        echo "端口 $port 可用"
        return 0
    fi
}

# 强制杀死端口占用进程
kill_port() {
    local port=$1
    echo "强制释放端口 $port..."
    
    local pids=$(lsof -ti:$port 2>/dev/null)
    if [ -n "$pids" ]; then
        echo "发现占用进程: $pids"
        for pid in $pids; do
            echo "杀死进程 $pid"
            kill -9 $pid 2>/dev/null
        done
        sleep 2
        
        # 再次检查
        local remaining=$(lsof -ti:$port 2>/dev/null)
        if [ -n "$remaining" ]; then
            echo "❌ 仍有进程占用端口: $remaining"
            return 1
        else
            echo "✅ 端口 $port 已释放"
            return 0
        fi
    else
        echo "端口 $port 没有被占用"
        return 0
    fi
}

# 停止 Workerman 进程
stop_workerman() {
    echo "停止 Workerman 进程..."
    
    # 方法1: 使用 think 命令停止
    if [ -f "think" ]; then
        echo "尝试使用 think 命令停止..."
        php think runtime:start workerman stop 2>/dev/null || true
        sleep 2
    fi
    
    # 方法2: 查找并杀死 workerman 进程
    echo "查找 workerman 进程..."
    local workerman_pids=$(ps aux | grep -i workerman | grep -v grep | awk '{print $2}')
    if [ -n "$workerman_pids" ]; then
        echo "发现 workerman 进程: $workerman_pids"
        for pid in $workerman_pids; do
            echo "杀死 workerman 进程 $pid"
            kill -9 $pid 2>/dev/null
        done
        sleep 2
    fi
    
    # 方法3: 查找并杀死 PHP 进程中包含 workerman 的
    echo "查找相关 PHP 进程..."
    local php_pids=$(ps aux | grep php | grep -i workerman | grep -v grep | awk '{print $2}')
    if [ -n "$php_pids" ]; then
        echo "发现相关 PHP 进程: $php_pids"
        for pid in $php_pids; do
            echo "杀死 PHP 进程 $pid"
            kill -9 $pid 2>/dev/null
        done
        sleep 2
    fi
}

# 查找可用端口
find_available_port() {
    local start_port=8080
    local max_port=8090
    
    for port in $(seq $start_port $max_port); do
        if check_port $port; then
            echo "找到可用端口: $port"
            return $port
        fi
    done
    
    echo "❌ 在 $start_port-$max_port 范围内没有找到可用端口"
    return 1
}

# 主函数
main() {
    echo "当前目录: $(pwd)"
    echo ""
    
    # 1. 停止现有的 Workerman 进程
    stop_workerman
    
    # 2. 检查默认端口 8080
    echo ""
    if check_port 8080; then
        echo "✅ 端口 8080 可用，可以启动 Workerman"
        echo ""
        echo "启动命令:"
        echo "php think runtime:start workerman --host=127.0.0.1 --port=8080 --workers=2"
    else
        echo "❌ 端口 8080 被占用"
        
        # 3. 尝试强制释放端口
        echo ""
        read -p "是否强制释放端口 8080? (y/n): " choice
        if [ "$choice" = "y" ] || [ "$choice" = "Y" ]; then
            kill_port 8080
            if check_port 8080; then
                echo "✅ 端口 8080 已释放，可以启动 Workerman"
                echo ""
                echo "启动命令:"
                echo "php think runtime:start workerman --host=127.0.0.1 --port=8080 --workers=2"
            else
                echo "❌ 无法释放端口 8080"
            fi
        else
            # 4. 查找其他可用端口
            echo ""
            echo "查找其他可用端口..."
            if find_available_port; then
                local available_port=$?
                echo ""
                echo "建议使用端口 $available_port:"
                echo "php think runtime:start workerman --host=127.0.0.1 --port=$available_port --workers=2"
            fi
        fi
    fi
    
    echo ""
    echo "=== 其他解决方案 ==="
    echo "1. 重启系统 (最彻底的解决方案)"
    echo "2. 使用其他端口:"
    echo "   php think runtime:start workerman --port=8081"
    echo "   php think runtime:start workerman --port=8082"
    echo "3. 检查是否有其他服务占用端口:"
    echo "   lsof -i :8080"
    echo "   netstat -tulpn | grep 8080"
}

# 运行主函数
main "$@"
