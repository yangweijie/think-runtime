#!/bin/bash

# FrankenPHP 清洁启动脚本
# 自动抑制 PHP 弃用警告

echo "🧹 清理旧的临时文件..."
rm -f Caddyfile.runtime frankenphp-worker.php frankenphp-php.ini

echo "🔧 创建临时 PHP 配置..."
cat > frankenphp-clean.ini << 'EOF'
; 临时 PHP 配置 - 抑制弃用警告
error_reporting = E_ERROR & E_WARNING & E_PARSE
display_errors = Off
display_startup_errors = Off
html_errors = Off
log_errors = On

; 性能优化
memory_limit = 512M
max_execution_time = 0

; 禁用可能导致弃用警告的扩展
session.auto_start = 0
EOF

echo "🚀 启动 FrankenPHP（无弃用警告）..."

# 方法1: 使用临时 PHP 配置
if command -v /usr/local/bin/frankenphp >/dev/null 2>&1; then
    echo "使用直接 FrankenPHP 启动..."
    
    # 创建简洁的 Caddyfile
    cat > Caddyfile.clean << 'EOF'
localhost:8080 {
    root * public
    php_server
    tls off
    log {
        level WARN
    }
}
EOF
    
    # 使用自定义 PHP 配置启动
    php -c frankenphp-clean.ini -d error_reporting=E_ERROR /usr/local/bin/frankenphp run --config Caddyfile.clean
    
    # 清理
    rm -f Caddyfile.clean frankenphp-clean.ini
    
else
    echo "使用 think-runtime 启动..."
    
    # 使用环境变量和 PHP 参数
    PHP_INI_SCAN_DIR=/dev/null php -c frankenphp-clean.ini -d error_reporting=E_ERROR think runtime:start /usr/local/bin/frankenphp --host=localhost --port=8080 --workers=0
    
    # 清理
    rm -f frankenphp-clean.ini
fi

echo "✅ FrankenPHP 已停止"
