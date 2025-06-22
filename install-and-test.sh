#!/bin/bash

# ThinkPHP Workerman 安装和测试脚本
# 
# 自动安装依赖并执行测试

set -e

TARGET_PROJECT="/Volumes/data/git/php/tp"
CURRENT_DIR=$(pwd)

echo "=== ThinkPHP Workerman 安装和测试 ==="
echo "目标项目: $TARGET_PROJECT"
echo ""

# 检查目标项目
if [ ! -d "$TARGET_PROJECT" ]; then
    echo "❌ 目标项目目录不存在: $TARGET_PROJECT"
    exit 1
fi

cd "$TARGET_PROJECT"

# 检查 composer
if ! command -v composer &> /dev/null; then
    echo "❌ Composer 未安装"
    echo "请先安装 Composer: https://getcomposer.org/"
    exit 1
fi

echo "✅ Composer 已安装"

# 安装 Workerman
echo ""
echo "=== 安装 Workerman ==="
if ! php -r "require_once 'vendor/autoload.php'; exit(class_exists('Workerman\Worker') ? 0 : 1);" 2>/dev/null; then
    echo "正在安装 Workerman..."
    composer require workerman/workerman

    # 验证安装
    if ! php -r "require_once 'vendor/autoload.php'; exit(class_exists('Workerman\Worker') ? 0 : 1);" 2>/dev/null; then
        echo "❌ Workerman 安装失败"
        exit 1
    fi
    echo "✅ Workerman 安装成功"
else
    echo "✅ Workerman 已安装"
fi

# 检查 think-runtime 是否已安装
echo ""
echo "=== 检查 think-runtime ==="
if ! php -r "require_once 'vendor/autoload.php'; exit(class_exists('yangweijie\thinkRuntime\RuntimeManager') ? 0 : 1);" 2>/dev/null; then
    echo "正在安装 think-runtime..."
    composer require yangweijie/think-runtime

    if ! php -r "require_once 'vendor/autoload.php'; exit(class_exists('yangweijie\thinkRuntime\RuntimeManager') ? 0 : 1);" 2>/dev/null; then
        echo "❌ think-runtime 安装失败"
        exit 1
    fi
    echo "✅ think-runtime 安装成功"
else
    echo "✅ think-runtime 已安装"
fi

# 返回源目录执行部署脚本
cd "$CURRENT_DIR"

echo ""
echo "=== 执行部署和测试 ==="
./deploy-and-test.sh
