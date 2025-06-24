#!/bin/bash

# Workerman 启动助手脚本
# 自动处理端口冲突和进程管理

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="/Volumes/data/git/php/tp"

echo "=== Workerman 启动助手 ==="
echo "项目目录: $PROJECT_DIR"
echo ""

# 检查项目目录
if [ ! -d "$PROJECT_DIR" ]; then
    echo "❌ 项目目录不存在: $PROJECT_DIR"
    exit 1
fi

if [ ! -f "$PROJECT_DIR/think" ]; then
    echo "❌ ThinkPHP 项目文件不存在: $PROJECT_DIR/think"
    exit 1
fi

cd "$PROJECT_DIR"

# 停止现有的 Workerman 进程
stop_workerman() {
    echo "🛑 停止现有的 Workerman 进程..."
    
    # 查找 workerman 相关进程 (排除当前脚本)
    local pids=$(ps aux | grep -E "(workerman|think.*runtime)" | grep -v grep | grep -v "workerman_helper" | awk '{print $2}')
    
    if [ -n "$pids" ]; then
        echo "发现进程: $pids"
        for pid in $pids; do
            echo "停止进程 $pid"
            kill -TERM $pid 2>/dev/null || kill -9 $pid 2>/dev/null || true
        done
        sleep 2
        echo "✅ 进程已停止"
    else
        echo "没有发现运行中的 Workerman 进程"
    fi
}

# 查找可用端口
find_port() {
    local start_port=${1:-8080}
    local max_attempts=10
    
    for i in $(seq 0 $max_attempts); do
        local port=$((start_port + i))
        if ! lsof -i :$port >/dev/null 2>&1; then
            echo $port
            return 0
        fi
    done
    
    echo "❌ 无法找到可用端口 (尝试了 $start_port-$((start_port + max_attempts)))"
    return 1
}

# 启动 Workerman
start_workerman() {
    local port=${1:-8080}
    local workers=${2:-2}
    local host=${3:-127.0.0.1}
    
    echo "🚀 启动 Workerman..."
    echo "配置: $host:$port, $workers 个进程"
    
    # 检查端口是否可用
    if lsof -i :$port >/dev/null 2>&1; then
        echo "⚠️  端口 $port 被占用，查找其他端口..."
        port=$(find_port $((port + 1)))
        if [ $? -ne 0 ]; then
            return 1
        fi
        echo "使用端口: $port"
    fi
    
    # 启动命令
    local cmd="php think runtime:start workerman --host=$host --port=$port --workers=$workers"
    echo "执行命令: $cmd"
    echo ""
    
    # 启动服务器
    exec $cmd
}

# 显示帮助信息
show_help() {
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  start [端口] [进程数] [主机]  启动 Workerman (默认: 8080 2 127.0.0.1)"
    echo "  stop                        停止 Workerman"
    echo "  restart [端口] [进程数]      重启 Workerman"
    echo "  status                      查看状态"
    echo "  help                        显示帮助"
    echo ""
    echo "示例:"
    echo "  $0 start                    # 启动在默认端口 8080"
    echo "  $0 start 8081               # 启动在端口 8081"
    echo "  $0 start 8082 4             # 启动在端口 8082，4个进程"
    echo "  $0 start 8083 4 0.0.0.0     # 启动在所有接口"
    echo "  $0 stop                     # 停止服务"
    echo "  $0 restart                  # 重启服务"
}

# 查看状态
show_status() {
    echo "📊 Workerman 状态:"
    echo ""
    
    # 检查进程
    local pids=$(ps aux | grep -E "(workerman|think.*runtime)" | grep -v grep | grep -v "workerman_helper")
    if [ -n "$pids" ]; then
        echo "运行中的进程:"
        echo "$pids"
        echo ""
        
        # 检查端口
        echo "监听的端口:"
        netstat -an 2>/dev/null | grep LISTEN | grep -E ":(808[0-9]|909[0-9])" || echo "未发现监听端口"
    else
        echo "❌ 没有运行中的 Workerman 进程"
    fi
    
    echo ""
    echo "可用的 runtime:"
    php think runtime:info | grep -E "(workerman|Available)"
}

# 主函数
main() {
    local action=${1:-help}
    
    case $action in
        "start")
            stop_workerman
            start_workerman ${2:-8080} ${3:-2} ${4:-127.0.0.1}
            ;;
        "stop")
            stop_workerman
            ;;
        "restart")
            stop_workerman
            sleep 1
            start_workerman ${2:-8080} ${3:-2} ${4:-127.0.0.1}
            ;;
        "status")
            show_status
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            echo "❌ 未知命令: $action"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# 运行主函数
main "$@"
