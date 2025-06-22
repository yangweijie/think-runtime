#!/bin/bash

# ThinkPHP Workerman 优化应用脚本
# 基于测试结果的实用优化方案

echo "=== ThinkPHP Workerman 优化应用脚本 ==="
echo "基于真实测试结果的优化方案"
echo ""

# 检查是否在正确的目录
if [ ! -f "think" ]; then
    echo "❌ 请在 ThinkPHP 项目根目录运行此脚本"
    exit 1
fi

echo "✅ 检测到 ThinkPHP 项目"
echo ""

# 1. PHP 配置检查和建议
echo "=== 1. PHP 配置检查 ==="

# 检查 OPcache
if php -m | grep -q "Zend OPcache"; then
    echo "✅ OPcache 扩展已安装"
    
    # 检查是否启用
    if php -r "echo opcache_get_status() ? 'enabled' : 'disabled';" 2>/dev/null | grep -q "enabled"; then
        echo "✅ OPcache 已启用"
    else
        echo "⚠️  OPcache 已安装但未启用"
        echo "建议在 php.ini 中添加:"
        echo "opcache.enable=1"
        echo "opcache.memory_consumption=128"
        echo "opcache.max_accelerated_files=4000"
    fi
else
    echo "❌ OPcache 扩展未安装"
    echo "建议安装 OPcache 以获得 15-25% 性能提升"
fi

# 检查内存限制
MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
echo "当前内存限制: $MEMORY_LIMIT"
if [[ "$MEMORY_LIMIT" == "128M" ]]; then
    echo "⚠️  建议增加到 256M"
elif [[ "$MEMORY_LIMIT" =~ ^[0-9]+M$ ]] && [[ ${MEMORY_LIMIT%M} -lt 256 ]]; then
    echo "⚠️  建议增加到 256M"
else
    echo "✅ 内存限制合适"
fi

echo ""

# 2. ThinkPHP 配置优化
echo "=== 2. ThinkPHP 配置优化 ==="

# 检查调试模式
if [ -f "config/app.php" ]; then
    if grep -q "'debug'\s*=>\s*true" config/app.php; then
        echo "⚠️  检测到调试模式已启用"
        echo "是否禁用调试模式? (y/n)"
        read -r response
        if [[ "$response" =~ ^[Yy]$ ]]; then
            # 备份原文件
            cp config/app.php config/app.php.backup
            # 禁用调试模式
            sed -i.bak "s/'debug'\s*=>\s*true/'debug' => false/g" config/app.php
            echo "✅ 调试模式已禁用 (原文件备份为 config/app.php.backup)"
        fi
    else
        echo "✅ 调试模式已禁用"
    fi
else
    echo "⚠️  未找到 config/app.php"
fi

# 检查 trace 配置
if [ -f "config/trace.php" ]; then
    if grep -q "'enable'\s*=>\s*true" config/trace.php; then
        echo "⚠️  检测到 think-trace 已启用"
        echo "是否禁用 think-trace? (y/n)"
        read -r response
        if [[ "$response" =~ ^[Yy]$ ]]; then
            # 备份原文件
            cp config/trace.php config/trace.php.backup
            # 禁用 trace
            sed -i.bak "s/'enable'\s*=>\s*true/'enable' => false/g" config/trace.php
            echo "✅ think-trace 已禁用 (原文件备份为 config/trace.php.backup)"
        fi
    else
        echo "✅ think-trace 已禁用"
    fi
else
    echo "ℹ️  未找到 config/trace.php"
fi

echo ""

# 3. 缓存优化
echo "=== 3. 缓存优化 ==="

echo "是否启用 ThinkPHP 缓存优化? (y/n)"
read -r response
if [[ "$response" =~ ^[Yy]$ ]]; then
    echo "启用路由缓存..."
    php think optimize:route
    
    echo "启用配置缓存..."
    php think optimize:config
    
    echo "✅ 缓存优化完成"
else
    echo "跳过缓存优化"
fi

echo ""

# 4. 系统参数检查
echo "=== 4. 系统参数检查 ==="

# 检查文件描述符限制
ULIMIT=$(ulimit -n)
echo "当前文件描述符限制: $ULIMIT"
if [ "$ULIMIT" -lt 10000 ]; then
    echo "⚠️  文件描述符限制较低，建议增加到 65535"
    echo "临时设置: ulimit -n 65535"
    echo "永久设置: 编辑 /etc/security/limits.conf"
else
    echo "✅ 文件描述符限制合适"
fi

# 检查系统负载
LOAD=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
echo "当前系统负载: $LOAD"

echo ""

# 5. Workerman 配置建议
echo "=== 5. Workerman 配置建议 ==="

# 检查 CPU 核心数
CPU_CORES=$(nproc 2>/dev/null || sysctl -n hw.ncpu 2>/dev/null || echo "4")
echo "检测到 CPU 核心数: $CPU_CORES"
echo "建议 Workerman 进程数: $CPU_CORES (当前可能不是最优)"

# 检查 runtime 配置
if [ -f "config/runtime.php" ]; then
    echo "✅ 找到 runtime 配置文件"
    if grep -q "workerman" config/runtime.php; then
        echo "✅ 找到 workerman 配置"
    else
        echo "⚠️  未找到 workerman 配置，建议添加优化配置"
    fi
else
    echo "⚠️  未找到 config/runtime.php"
fi

echo ""

# 6. 性能测试
echo "=== 6. 性能测试 ==="

echo "是否进行性能测试? (y/n)"
read -r response
if [[ "$response" =~ ^[Yy]$ ]]; then
    echo "启动 Workerman 服务进行测试..."
    
    # 启动服务
    nohup php think runtime:start workerman > /tmp/workerman_test.log 2>&1 &
    WORKERMAN_PID=$!
    
    # 等待服务启动
    sleep 5
    
    # 检查服务是否启动
    if curl -s http://127.0.0.1:8080/ > /dev/null; then
        echo "✅ 服务启动成功"
        
        # 检查是否安装了 wrk
        if command -v wrk &> /dev/null; then
            echo "进行 30 秒性能测试..."
            wrk -t4 -c100 -d30s --latency http://127.0.0.1:8080/
        else
            echo "⚠️  未安装 wrk，跳过性能测试"
            echo "安装 wrk: brew install wrk (macOS) 或 apt-get install wrk (Ubuntu)"
        fi
        
        # 停止服务
        kill $WORKERMAN_PID 2>/dev/null
        echo "服务已停止"
    else
        echo "❌ 服务启动失败，请检查配置"
        kill $WORKERMAN_PID 2>/dev/null
    fi
else
    echo "跳过性能测试"
fi

echo ""

# 7. 总结和建议
echo "=== 7. 优化总结 ==="

echo "✅ 优化检查完成！"
echo ""
echo "📋 **立即可行的优化**:"
echo "1. 启用 OPcache (预期提升 15-25%)"
echo "2. 调整内存限制到 256M"
echo "3. 禁用调试模式和 think-trace"
echo "4. 启用 ThinkPHP 缓存"
echo ""
echo "📋 **系统级优化**:"
echo "1. 增加文件描述符限制: ulimit -n 65535"
echo "2. 调整 Workerman 进程数为 CPU 核心数"
echo "3. 监控系统负载和内存使用"
echo ""
echo "📋 **预期性能提升**:"
echo "- 当前基准: ~880 QPS"
echo "- 优化后目标: 1000-1200 QPS (15-35% 提升)"
echo "- 进一步优化: 1500+ QPS (需要应用层优化)"
echo ""
echo "📋 **下一步建议**:"
echo "1. 应用上述优化后重新测试"
echo "2. 监控生产环境性能表现"
echo "3. 根据实际情况进行微调"
echo "4. 考虑数据库和缓存优化"
echo ""
echo "🎯 **重要提醒**:"
echo "- 专注于稳定性而不是极致性能"
echo "- 逐步优化，避免一次性大改动"
echo "- 在生产环境前充分测试"
echo ""
echo "优化脚本执行完成！"
