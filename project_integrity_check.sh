#!/bin/bash

echo "🔍 FrankenPHP Runtime 项目完整性检查"
echo "===================================="

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 检查结果统计
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

# 检查函数
check_file() {
    local file=$1
    local description=$2
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if [ -f "$file" ]; then
        echo -e "✅ ${GREEN}$description${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
        return 0
    else
        echo -e "❌ ${RED}$description${NC}"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
        return 1
    fi
}

check_directory() {
    local dir=$1
    local description=$2
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if [ -d "$dir" ]; then
        echo -e "✅ ${GREEN}$description${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
        return 0
    else
        echo -e "❌ ${RED}$description${NC}"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
        return 1
    fi
}

check_content() {
    local file=$1
    local pattern=$2
    local description=$3
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if [ -f "$file" ] && grep -q "$pattern" "$file"; then
        echo -e "✅ ${GREEN}$description${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
        return 0
    else
        echo -e "❌ ${RED}$description${NC}"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
        return 1
    fi
}

echo ""
echo "📁 1. 核心文件检查"
echo "=================="

# 核心适配器文件
check_file "src/adapter/FrankenphpAdapter.php" "FrankenPHP 适配器主文件"

# 检查适配器关键方法
if [ -f "src/adapter/FrankenphpAdapter.php" ]; then
    check_content "src/adapter/FrankenphpAdapter.php" "buildFrankenPHPCaddyfile" "Caddyfile 生成方法"
    check_content "src/adapter/FrankenphpAdapter.php" "handleFrankenphpError" "错误处理方法"
    check_content "src/adapter/FrankenphpAdapter.php" "getStatus" "状态监控方法"
    check_content "src/adapter/FrankenphpAdapter.php" "healthCheck" "健康检查方法"
    check_content "src/adapter/FrankenphpAdapter.php" "renderDebugErrorPage" "调试错误页面方法"
fi

echo ""
echo "📚 2. 文档文件检查"
echo "=================="

# 文档文件
check_file "README_FRANKENPHP.md" "FrankenPHP 使用文档"
check_file "FRANKENPHP_RUNTIME_SOLUTION.md" "技术解决方案文档"
check_file "DEPLOYMENT_GUIDE.md" "部署指南文档"
check_file "PROJECT_STATUS_REPORT.md" "项目状态报告"
check_file "CHANGELOG.md" "变更日志"

# 检查文档内容完整性
if [ -f "README_FRANKENPHP.md" ]; then
    check_content "README_FRANKENPHP.md" "性能指标" "README 包含性能指标"
    check_content "README_FRANKENPHP.md" "使用方法" "README 包含使用方法"
    check_content "README_FRANKENPHP.md" "配置选项" "README 包含配置选项"
fi

echo ""
echo "🧪 3. 测试文件检查"
echo "=================="

# 测试脚本
check_file "test_frankenphp_enhanced.sh" "增强功能测试脚本"
check_file "demo_frankenphp_complete.sh" "完整功能演示脚本"
check_file "quick_performance_test.sh" "快速性能测试脚本"
check_file "benchmark_frankenphp.sh" "性能基准测试脚本"

# 检查测试脚本可执行性
for script in test_frankenphp_enhanced.sh demo_frankenphp_complete.sh quick_performance_test.sh benchmark_frankenphp.sh; do
    if [ -f "$script" ]; then
        TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
        if [ -x "$script" ]; then
            echo -e "✅ ${GREEN}$script 可执行权限${NC}"
            PASSED_CHECKS=$((PASSED_CHECKS + 1))
        else
            echo -e "⚠️  ${YELLOW}$script 缺少可执行权限${NC}"
            chmod +x "$script" 2>/dev/null && echo -e "   ${GREEN}已自动修复权限${NC}"
            PASSED_CHECKS=$((PASSED_CHECKS + 1))
        fi
    fi
done

echo ""
echo "⚙️  4. 配置文件检查"
echo "=================="

# 配置相关文件
check_file "composer.json" "Composer 配置文件"
check_directory "config" "配置目录"

# 检查 composer.json 内容
if [ -f "composer.json" ]; then
    check_content "composer.json" "yangweijie/think-runtime" "包名配置正确"
    check_content "composer.json" "autoload" "自动加载配置"
fi

echo ""
echo "🔧 5. 源码结构检查"
echo "=================="

# 源码目录结构
check_directory "src" "源码目录"
check_directory "src/adapter" "适配器目录"

# 检查其他适配器文件（确保项目完整性）
if [ -d "src/adapter" ]; then
    adapter_count=$(find src/adapter -name "*Adapter.php" | wc -l)
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    if [ "$adapter_count" -gt 0 ]; then
        echo -e "✅ ${GREEN}发现 $adapter_count 个适配器文件${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
    else
        echo -e "❌ ${RED}未找到适配器文件${NC}"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
    fi
fi

echo ""
echo "📊 6. 代码质量检查"
echo "=================="

# PHP 语法检查
if [ -f "src/adapter/FrankenphpAdapter.php" ]; then
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    if php -l "src/adapter/FrankenphpAdapter.php" > /dev/null 2>&1; then
        echo -e "✅ ${GREEN}FrankenphpAdapter.php 语法正确${NC}"
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
    else
        echo -e "❌ ${RED}FrankenphpAdapter.php 语法错误${NC}"
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
    fi
fi

# 检查关键配置内容
if [ -f "src/adapter/FrankenphpAdapter.php" ]; then
    check_content "src/adapter/FrankenphpAdapter.php" "try_files" "包含 ThinkPHP 路由配置"
    check_content "src/adapter/FrankenphpAdapter.php" "s={path}" "包含 s= 参数配置"
    check_content "src/adapter/FrankenphpAdapter.php" "auto_https" "包含 HTTPS 配置"
fi

echo ""
echo "🎯 7. 功能完整性检查"
echo "==================="

# 检查关键功能实现
if [ -f "src/adapter/FrankenphpAdapter.php" ]; then
    # 错误处理功能
    check_content "src/adapter/FrankenphpAdapter.php" "renderDebugErrorPage" "调试错误页面功能"
    check_content "src/adapter/FrankenphpAdapter.php" "logError" "错误日志记录功能"
    
    # 监控功能
    check_content "src/adapter/FrankenphpAdapter.php" "memory_get_usage" "内存监控功能"
    check_content "src/adapter/FrankenphpAdapter.php" "parseMemoryLimit" "内存限制解析功能"
    
    # 配置验证功能
    check_content "src/adapter/FrankenphpAdapter.php" "getAbsolutePath" "路径处理功能"
fi

echo ""
echo "📈 8. 性能优化检查"
echo "=================="

# 检查性能相关实现
if [ -f "src/adapter/FrankenphpAdapter.php" ]; then
    check_content "src/adapter/FrankenphpAdapter.php" "static.*startTime" "运行时间跟踪"
    check_content "src/adapter/FrankenphpAdapter.php" "memory_get_peak_usage" "峰值内存监控"
fi

# 检查测试脚本中的性能验证
if [ -f "quick_performance_test.sh" ]; then
    check_content "quick_performance_test.sh" "性能指标测试" "性能测试功能"
    check_content "quick_performance_test.sh" "批量操作性能测试" "批量操作测试"
fi

echo ""
echo "🔒 9. 安全性检查"
echo "================"

# 检查安全相关实现
if [ -f "src/adapter/FrankenphpAdapter.php" ]; then
    check_content "src/adapter/FrankenphpAdapter.php" "htmlspecialchars" "XSS 防护"
    check_content "src/adapter/FrankenphpAdapter.php" "config\['debug'\]" "调试模式安全控制"
fi

echo ""
echo "📋 10. 项目完整性总结"
echo "===================="

# 计算完整性百分比
if [ $TOTAL_CHECKS -gt 0 ]; then
    COMPLETION_RATE=$((PASSED_CHECKS * 100 / TOTAL_CHECKS))
else
    COMPLETION_RATE=0
fi

echo -e "${BLUE}检查项目总数:${NC} $TOTAL_CHECKS"
echo -e "${GREEN}通过检查项目:${NC} $PASSED_CHECKS"
echo -e "${RED}失败检查项目:${NC} $FAILED_CHECKS"
echo -e "${YELLOW}项目完整性:${NC} $COMPLETION_RATE%"

echo ""
if [ $COMPLETION_RATE -ge 95 ]; then
    echo -e "🎉 ${GREEN}项目完整性检查通过！项目已准备就绪。${NC}"
    exit 0
elif [ $COMPLETION_RATE -ge 80 ]; then
    echo -e "⚠️  ${YELLOW}项目基本完整，但有一些问题需要修复。${NC}"
    exit 1
else
    echo -e "❌ ${RED}项目存在重大问题，需要进一步完善。${NC}"
    exit 2
fi
