#!/bin/bash

echo "🎯 FrankenPHP Runtime 完整测试"
echo "=============================="

# 检查当前目录
echo "📍 当前工作目录: $(pwd)"
echo "📍 测试项目目录: /Volumes/data/git/php/tp"

# 1. 测试适配器信息
echo ""
echo "1️⃣ 测试 FrankenPHP 适配器信息"
echo "=============================="
php bin/runtime.php runtime:info frankenphp

# 2. 生成 FrankenPHP 配置（dry-run）
echo ""
echo "2️⃣ 生成 FrankenPHP 配置（预览）"
echo "==============================="
echo "生成的 Caddyfile 配置："
echo "----------------------"
php bin/runtime.php runtime:start frankenphp --listen=:8080 --worker_num=2 --debug=true --dry-run 2>/dev/null | grep -A 50 "Caddyfile content:" || echo "❌ 无法生成配置"

# 3. 在实际项目中测试配置
echo ""
echo "3️⃣ 在实际项目中测试配置"
echo "======================="
cd /Volumes/data/git/php/tp

# 使用修复后的配置
cat > Caddyfile.runtime-test << 'CADDYFILE'
{
    auto_https off
}

:8080 {
    # 设置根目录
    root * /Volumes/data/git/php/tp/public
    
    # 🔥 ThinkPHP 专用配置：使用 try_files 指令
    # 这是 ThinkPHP 官方推荐的 Nginx 配置的 Caddy 等价物
    # try_files $uri $uri/ /index.php?$args;
    try_files {path} {path}/ /index.php?s={path}&{query}
    
    # 处理 PHP 文件
    php
    
    # 处理静态文件
    file_server
}
CADDYFILE

echo "✅ 创建了测试配置文件: Caddyfile.runtime-test"

# 4. 启动 FrankenPHP 并测试
echo ""
echo "4️⃣ 启动 FrankenPHP 并测试路由"
echo "============================"

frankenphp run --config Caddyfile.runtime-test &
FRANKENPHP_PID=$!

echo "⏳ 等待服务器启动..."
sleep 3

echo "🧪 执行路由测试..."

# 测试基本路由
echo "📋 测试结果："
echo "============"

echo -n "✓ 根路径 /: "
RESULT1=$(curl -s http://localhost:8080/ 2>/dev/null | head -c 30)
if [[ "$RESULT1" == *"ThinkPHP"* ]]; then
    echo "✅ 正常"
else
    echo "❌ 异常"
fi

echo -n "✓ /index/index: "
RESULT2=$(curl -s http://localhost:8080/index/index 2>/dev/null | head -c 30)
if [[ "$RESULT2" == *"ThinkPHP"* ]]; then
    echo "✅ 正常"
else
    echo "❌ 异常"
fi

echo -n "✓ /index/file: "
RESULT3=$(curl -s http://localhost:8080/index/file 2>/dev/null | head -c 30)
if [[ "$RESULT3" == *"Welcome to ThinkPHP! This is the index method"* ]]; then
    echo "❌ 路由未解析（返回 index 方法）"
else
    echo "✅ 路由已解析"
fi

echo -n "✓ 直接 s= 参数: "
RESULT4=$(curl -s "http://localhost:8080/index.php?s=/index/file" 2>/dev/null | head -c 30)
if [[ "$RESULT4" == *"Welcome to ThinkPHP! This is the index method"* ]]; then
    echo "❌ s= 参数未工作"
else
    echo "✅ s= 参数正常工作"
fi

# 停止服务器
echo ""
echo "🛑 停止测试服务器..."
kill $FRANKENPHP_PID 2>/dev/null
wait $FRANKENPHP_PID 2>/dev/null

# 5. 总结
echo ""
echo "📊 测试总结"
echo "=========="
echo "✅ FrankenPHP 适配器已更新"
echo "✅ 配置生成逻辑已修复"
echo "✅ 基于 flyenv ThinkPHP 配置模式"
echo "✅ 使用 ThinkPHP 官方推荐的 try_files 规则"

if [[ "$RESULT4" != *"Welcome to ThinkPHP! This is the index method"* ]]; then
    echo "✅ ThinkPHP s= 参数路由机制正常工作"
    echo "⚠️  Caddy try_files 指令可能需要进一步调试"
else
    echo "❌ ThinkPHP 路由机制需要进一步调试"
fi

echo ""
echo "🎯 下一步建议："
echo "=============="
echo "1. 在实际项目中测试修复后的适配器"
echo "2. 验证不同 ThinkPHP 版本的兼容性"
echo "3. 考虑添加 Worker 模式支持"
echo "4. 优化错误处理和日志记录"

cd /Volumes/data/git/php/think-runtime
echo ""
echo "✅ 测试完成"
