<?php

/**
 * Header Deduplication Configuration Validator
 * 
 * This script validates the header deduplication configuration
 * and provides recommendations for optimization.
 * 
 * Usage: php scripts/validate_header_config.php [--fix] [--environment=production]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use yangweijie\thinkRuntime\service\HeaderDeduplicationService;

class HeaderConfigValidator
{
    private array $config;
    private array $errors = [];
    private array $warnings = [];
    private array $recommendations = [];
    private bool $fixMode = false;
    private string $environment = 'production';

    public function __construct(array $config, bool $fixMode = false, string $environment = 'production')
    {
        $this->config = $config;
        $this->fixMode = $fixMode;
        $this->environment = $environment;
    }

    /**
     * 验证配置
     */
    public function validate(): array
    {
        $this->validateBasicSettings();
        $this->validateLoggingSettings();
        $this->validatePerformanceSettings();
        $this->validateCustomRules();
        $this->validateEnvironmentSpecific();
        $this->generateRecommendations();

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'recommendations' => $this->recommendations,
            'fixed_config' => $this->fixMode ? $this->config : null,
        ];
    }

    /**
     * 验证基本设置
     */
    private function validateBasicSettings(): void
    {
        $headerConfig = $this->config['header_deduplication'] ?? [];

        // 检查是否启用
        if (!isset($headerConfig['enabled'])) {
            $this->warnings[] = "header_deduplication.enabled not set, defaulting to true";
            if ($this->fixMode) {
                $this->config['header_deduplication']['enabled'] = true;
            }
        } elseif (!is_bool($headerConfig['enabled'])) {
            $this->errors[] = "header_deduplication.enabled must be boolean";
            if ($this->fixMode) {
                $this->config['header_deduplication']['enabled'] = (bool)$headerConfig['enabled'];
            }
        }

        // 验证严格模式
        if (isset($headerConfig['strict_mode']) && !is_bool($headerConfig['strict_mode'])) {
            $this->errors[] = "header_deduplication.strict_mode must be boolean";
            if ($this->fixMode) {
                $this->config['header_deduplication']['strict_mode'] = (bool)$headerConfig['strict_mode'];
            }
        }

        // 验证头部值最大长度
        if (isset($headerConfig['max_header_value_length'])) {
            if (!is_int($headerConfig['max_header_value_length']) || $headerConfig['max_header_value_length'] < 1) {
                $this->errors[] = "header_deduplication.max_header_value_length must be positive integer";
                if ($this->fixMode) {
                    $this->config['header_deduplication']['max_header_value_length'] = 8192;
                }
            } elseif ($headerConfig['max_header_value_length'] > 65536) {
                $this->warnings[] = "header_deduplication.max_header_value_length is very large (>64KB), may impact performance";
            }
        }

        // 验证缓存大小
        if (isset($headerConfig['max_cache_size'])) {
            if (!is_int($headerConfig['max_cache_size']) || $headerConfig['max_cache_size'] < 0) {
                $this->errors[] = "header_deduplication.max_cache_size must be non-negative integer";
                if ($this->fixMode) {
                    $this->config['header_deduplication']['max_cache_size'] = 1000;
                }
            }
        }
    }

    /**
     * 验证日志设置
     */
    private function validateLoggingSettings(): void
    {
        $headerConfig = $this->config['header_deduplication'] ?? [];

        // 验证日志级别
        if (isset($headerConfig['log_level'])) {
            $validLevels = ['debug', 'info', 'warning', 'error'];
            if (!in_array($headerConfig['log_level'], $validLevels)) {
                $this->errors[] = "header_deduplication.log_level must be one of: " . implode(', ', $validLevels);
                if ($this->fixMode) {
                    $this->config['header_deduplication']['log_level'] = 'info';
                }
            }
        }

        // 验证日志文件路径
        if (isset($headerConfig['log_file']) && $headerConfig['log_file'] !== null) {
            if (!is_string($headerConfig['log_file'])) {
                $this->errors[] = "header_deduplication.log_file must be string or null";
                if ($this->fixMode) {
                    $this->config['header_deduplication']['log_file'] = null;
                }
            } else {
                $logDir = dirname($headerConfig['log_file']);
                if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
                    $this->warnings[] = "Log directory '{$logDir}' does not exist and cannot be created";
                }
            }
        }

        // 检查调试日志在生产环境中的使用
        if ($this->environment === 'production' && ($headerConfig['debug_logging'] ?? false)) {
            $this->warnings[] = "debug_logging is enabled in production environment, may impact performance";
        }
    }

    /**
     * 验证性能设置
     */
    private function validatePerformanceSettings(): void
    {
        $headerConfig = $this->config['header_deduplication'] ?? [];

        // 检查性能日志在生产环境中的使用
        if ($this->environment === 'production' && ($headerConfig['enable_performance_logging'] ?? false)) {
            $this->warnings[] = "enable_performance_logging is enabled in production, may impact performance";
        }

        // 检查缓存设置
        if (isset($headerConfig['enable_header_name_cache']) && !$headerConfig['enable_header_name_cache']) {
            if ($this->environment === 'production') {
                $this->warnings[] = "Header name cache is disabled in production, may impact performance";
            }
        }

        // 检查批处理设置
        if (isset($headerConfig['enable_batch_processing']) && !$headerConfig['enable_batch_processing']) {
            if ($this->environment === 'production') {
                $this->warnings[] = "Batch processing is disabled in production, may impact performance";
            }
        }
    }

    /**
     * 验证自定义规则
     */
    private function validateCustomRules(): void
    {
        $headerConfig = $this->config['header_deduplication'] ?? [];
        $customRules = $headerConfig['custom_rules'] ?? [];

        if (!is_array($customRules)) {
            $this->errors[] = "header_deduplication.custom_rules must be array";
            if ($this->fixMode) {
                $this->config['header_deduplication']['custom_rules'] = [];
            }
            return;
        }

        foreach ($customRules as $headerName => $rule) {
            if (!is_array($rule)) {
                $this->errors[] = "Custom rule for header '{$headerName}' must be array";
                if ($this->fixMode) {
                    unset($this->config['header_deduplication']['custom_rules'][$headerName]);
                }
                continue;
            }

            // 验证优先级
            if (isset($rule['priority'])) {
                $validPriorities = ['psr7_first', 'runtime_first', 'combine'];
                if (!in_array($rule['priority'], $validPriorities)) {
                    $this->errors[] = "Invalid priority '{$rule['priority']}' for header '{$headerName}'";
                    if ($this->fixMode) {
                        $this->config['header_deduplication']['custom_rules'][$headerName]['priority'] = 'psr7_first';
                    }
                }
            }

            // 验证combinable设置
            if (isset($rule['combinable']) && !is_bool($rule['combinable'])) {
                $this->errors[] = "combinable setting for header '{$headerName}' must be boolean";
                if ($this->fixMode) {
                    $this->config['header_deduplication']['custom_rules'][$headerName]['combinable'] = false;
                }
            }

            // 验证分隔符
            if (isset($rule['separator']) && !is_string($rule['separator'])) {
                $this->errors[] = "separator for header '{$headerName}' must be string";
                if ($this->fixMode) {
                    $this->config['header_deduplication']['custom_rules'][$headerName]['separator'] = ', ';
                }
            }

            // 验证critical设置
            if (isset($rule['critical']) && !is_bool($rule['critical'])) {
                $this->errors[] = "critical setting for header '{$headerName}' must be boolean";
                if ($this->fixMode) {
                    $this->config['header_deduplication']['custom_rules'][$headerName]['critical'] = false;
                }
            }

            // 检查逻辑一致性
            if (($rule['priority'] ?? '') === 'combine' && !($rule['combinable'] ?? false)) {
                $this->warnings[] = "Header '{$headerName}' has priority 'combine' but combinable is false";
            }
        }
    }

    /**
     * 验证环境特定设置
     */
    private function validateEnvironmentSpecific(): void
    {
        $headerConfig = $this->config['header_deduplication'] ?? [];

        switch ($this->environment) {
            case 'production':
                $this->validateProductionSettings($headerConfig);
                break;
            case 'development':
                $this->validateDevelopmentSettings($headerConfig);
                break;
            case 'testing':
                $this->validateTestingSettings($headerConfig);
                break;
        }
    }

    /**
     * 验证生产环境设置
     */
    private function validateProductionSettings(array $config): void
    {
        if ($config['strict_mode'] ?? false) {
            $this->warnings[] = "strict_mode is enabled in production, may cause request failures";
        }

        if ($config['throw_on_merge_failure'] ?? false) {
            $this->warnings[] = "throw_on_merge_failure is enabled in production, may cause request failures";
        }

        if (($config['log_level'] ?? 'info') === 'debug') {
            $this->warnings[] = "log_level is set to 'debug' in production, may impact performance";
        }
    }

    /**
     * 验证开发环境设置
     */
    private function validateDevelopmentSettings(array $config): void
    {
        if (!($config['debug_logging'] ?? false)) {
            $this->recommendations[] = "Consider enabling debug_logging in development for better debugging";
        }

        if (!($config['strict_mode'] ?? false)) {
            $this->recommendations[] = "Consider enabling strict_mode in development to catch issues early";
        }
    }

    /**
     * 验证测试环境设置
     */
    private function validateTestingSettings(array $config): void
    {
        if (!($config['strict_mode'] ?? false)) {
            $this->recommendations[] = "Consider enabling strict_mode in testing to catch issues";
        }

        if ($config['enable_header_name_cache'] ?? true) {
            $this->recommendations[] = "Consider disabling header_name_cache in testing for consistent results";
        }
    }

    /**
     * 生成优化建议
     */
    private function generateRecommendations(): void
    {
        $headerConfig = $this->config['header_deduplication'] ?? [];

        // 性能优化建议
        if ($this->environment === 'production') {
            if (($headerConfig['max_cache_size'] ?? 1000) < 2000) {
                $this->recommendations[] = "Consider increasing max_cache_size to 2000+ for better performance in production";
            }

            if (!($headerConfig['enable_batch_processing'] ?? true)) {
                $this->recommendations[] = "Enable batch_processing for better performance in production";
            }
        }

        // 安全建议
        if (empty($headerConfig['custom_rules'])) {
            $this->recommendations[] = "Consider adding custom rules for security headers like X-Frame-Options, CSP";
        }

        // 监控建议
        if (!($headerConfig['log_critical_conflicts'] ?? true)) {
            $this->recommendations[] = "Consider enabling log_critical_conflicts for monitoring header issues";
        }
    }

    /**
     * 生成配置报告
     */
    public function generateReport(): string
    {
        $result = $this->validate();
        $report = [];

        $report[] = "=== Header Deduplication Configuration Validation Report ===";
        $report[] = "Environment: {$this->environment}";
        $report[] = "Status: " . ($result['valid'] ? 'VALID' : 'INVALID');
        $report[] = "";

        if (!empty($result['errors'])) {
            $report[] = "ERRORS:";
            foreach ($result['errors'] as $error) {
                $report[] = "  ❌ {$error}";
            }
            $report[] = "";
        }

        if (!empty($result['warnings'])) {
            $report[] = "WARNINGS:";
            foreach ($result['warnings'] as $warning) {
                $report[] = "  ⚠️  {$warning}";
            }
            $report[] = "";
        }

        if (!empty($result['recommendations'])) {
            $report[] = "RECOMMENDATIONS:";
            foreach ($result['recommendations'] as $recommendation) {
                $report[] = "  💡 {$recommendation}";
            }
            $report[] = "";
        }

        if (empty($result['errors']) && empty($result['warnings'])) {
            $report[] = "✅ Configuration is valid with no issues detected.";
        }

        return implode("\n", $report);
    }
}

// 命令行处理
function main(): void
{
    $options = getopt('', ['fix', 'environment:']);
    $fixMode = isset($options['fix']);
    $environment = $options['environment'] ?? 'production';

    // 加载配置
    $configFile = __DIR__ . '/../config/runtime.php';
    if (!file_exists($configFile)) {
        echo "Error: Configuration file not found: {$configFile}\n";
        exit(1);
    }

    $config = require $configFile;

    // 验证配置
    $validator = new HeaderConfigValidator($config, $fixMode, $environment);
    $report = $validator->generateReport();

    echo $report . "\n";

    // 如果启用修复模式，保存修复后的配置
    if ($fixMode) {
        $result = $validator->validate();
        if ($result['fixed_config']) {
            $backupFile = $configFile . '.backup.' . date('Y-m-d_H-i-s');
            copy($configFile, $backupFile);
            echo "Original configuration backed up to: {$backupFile}\n";

            $fixedConfig = "<?php\n\nreturn " . var_export($result['fixed_config'], true) . ";\n";
            file_put_contents($configFile, $fixedConfig);
            echo "Configuration has been fixed and saved.\n";
        }
    }

    // 退出码
    $result = $validator->validate();
    exit($result['valid'] ? 0 : 1);
}

// 如果直接运行此脚本
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    main();
}