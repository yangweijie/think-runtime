<?php

/**
 * Run core functionality tests without complex dependencies
 */

require_once __DIR__ . '/vendor/autoload.php';

use Think\Runtime\Runtime\ThinkPHPRuntime;
use Think\Runtime\Runtime\WorkermanRuntime;
use Think\Runtime\Runtime\ReactPHPRuntime;
use Think\Runtime\Runtime\FrankenPHPRuntime;
use Think\Runtime\Runtime\SwooleRuntime;
use Think\Runtime\Runtime\VercelRuntime;
use Think\Runtime\Resolver\GenericResolver;

echo "🧪 Running Core ThinkPHP Runtime Tests...\n\n";

$passed = 0;
$failed = 0;

function test(string $name, callable $test): void {
    global $passed, $failed;
    
    try {
        $result = $test();
        if ($result === true || $result === null) {
            echo "✅ $name\n";
            $passed++;
        } else {
            echo "❌ $name - Unexpected result\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "❌ $name - Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

// Test 1: Runtime Creation
test("ThinkPHP Runtime Creation", function() {
    $runtime = new ThinkPHPRuntime();
    return $runtime instanceof \Think\Runtime\Contract\RuntimeInterface;
});

test("Workerman Runtime Creation", function() {
    $runtime = new WorkermanRuntime();
    return $runtime instanceof \Think\Runtime\Contract\RuntimeInterface;
});

test("ReactPHP Runtime Creation", function() {
    $runtime = new ReactPHPRuntime();
    return $runtime instanceof \Think\Runtime\Contract\RuntimeInterface;
});

test("FrankenPHP Runtime Creation", function() {
    $runtime = new FrankenPHPRuntime();
    return $runtime instanceof \Think\Runtime\Contract\RuntimeInterface;
});

test("Swoole Runtime Creation", function() {
    $runtime = new SwooleRuntime();
    return $runtime instanceof \Think\Runtime\Contract\RuntimeInterface;
});

test("Vercel Runtime Creation", function() {
    $runtime = new VercelRuntime();
    return $runtime instanceof \Think\Runtime\Contract\RuntimeInterface;
});

// Test 2: Options Configuration
test("Runtime Options Configuration", function() {
    $runtime = new ThinkPHPRuntime(['debug' => true, 'env' => 'testing']);
    $options = $runtime->getOptions();
    return $options['debug'] === true && $options['env'] === 'testing';
});

test("Runtime Options Merging", function() {
    $runtime = new ThinkPHPRuntime(['custom' => 'value']);
    $newRuntime = $runtime->withOptions(['another' => 'option']);
    $options = $newRuntime->getOptions();
    return isset($options['custom']) && isset($options['another']);
});

// Test 3: Resolver Functionality
test("Resolver with No Parameters", function() {
    $callable = function() { return 'test'; };
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    return $resolvedCallable === $callable && count($arguments) === 0;
});

test("Resolver with Context Parameter", function() {
    $_SERVER['TEST_VAR'] = 'test_value';
    $callable = function(array $context) { return $context; };
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    return count($arguments) === 1 && isset($arguments[0]['TEST_VAR']);
});

test("Resolver with Default Values", function() {
    $callable = function(string $optional = 'default') { return $optional; };
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    return count($arguments) === 1 && $arguments[0] === 'default';
});

test("Resolver with Built-in Types", function() {
    $callable = function(string $str, int $num, bool $flag, array $arr) { 
        return [$str, $num, $flag, $arr]; 
    };
    $resolver = new GenericResolver($callable);
    [$resolvedCallable, $arguments] = $resolver->resolve();
    return count($arguments) === 4 && 
           $arguments[0] === '' && 
           $arguments[1] === 0 && 
           $arguments[2] === false && 
           $arguments[3] === [];
});

// Test 4: Runner Functionality
test("Void Runner", function() {
    $runtime = new ThinkPHPRuntime();
    $runner = $runtime->getRunner(null);
    return $runner->run() === 0;
});

test("Callable Runner", function() {
    $runtime = new ThinkPHPRuntime();
    $callable = function() { return 42; };
    $runner = $runtime->getRunner($callable);
    return $runner->run() === 42;
});

test("Generic Object Runner", function() {
    $runtime = new ThinkPHPRuntime();
    $app = new class {
        public function handle($request) {
            return 'Hello World';
        }
    };
    $runner = $runtime->getRunner($app);
    
    ob_start();
    $exitCode = $runner->run();
    $output = ob_get_clean();
    
    return $exitCode === 0 && $output === 'Hello World';
});

// Test 5: Integration Test
test("Complete Runtime Workflow", function() {
    $runtime = new ThinkPHPRuntime();
    
    $appCallable = function(array $context) {
        return new class {
            public function handle($request) {
                return 'Integration Test Success';
            }
        };
    };
    
    $resolver = $runtime->getResolver($appCallable);
    [$callable, $arguments] = $resolver->resolve();
    $application = $callable(...$arguments);
    $runner = $runtime->getRunner($application);
    
    ob_start();
    $exitCode = $runner->run();
    $output = ob_get_clean();
    
    return $exitCode === 0 && $output === 'Integration Test Success';
});

// Test 6: Runtime Specific Options
test("Workerman Runtime Options", function() {
    $runtime = new WorkermanRuntime(['host' => '127.0.0.1', 'port' => 9000]);
    $options = $runtime->getOptions();
    return $options['host'] === '127.0.0.1' && $options['port'] === 9000;
});

test("ReactPHP Runtime Options", function() {
    $runtime = new ReactPHPRuntime(['host' => '0.0.0.0', 'max_concurrent_requests' => 200]);
    $options = $runtime->getOptions();
    return $options['host'] === '0.0.0.0' && $options['max_concurrent_requests'] === 200;
});

test("FrankenPHP Runtime Options", function() {
    $runtime = new FrankenPHPRuntime(['frankenphp_loop_max' => 1000, 'frankenphp_worker' => true]);
    $options = $runtime->getOptions();
    return $options['frankenphp_loop_max'] === 1000 && $options['frankenphp_worker'] === true;
});

test("Swoole Runtime Options", function() {
    $runtime = new SwooleRuntime(['host' => '127.0.0.1', 'port' => 8080, 'worker_num' => 8]);
    $options = $runtime->getOptions();
    return $options['host'] === '127.0.0.1' && $options['port'] === 8080 && $options['worker_num'] === 8;
});

test("Vercel Runtime Options", function() {
    $runtime = new VercelRuntime(['vercel_env' => 'production', 'enable_cors' => false, 'max_execution_time' => 30]);
    $options = $runtime->getOptions();
    return $options['vercel_env'] === 'production' && $options['enable_cors'] === false && $options['max_execution_time'] === 30;
});

// Summary
echo "\n📊 Test Results:\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";
echo "📈 Success Rate: " . round(($passed / ($passed + $failed)) * 100, 1) . "%\n";

if ($failed === 0) {
    echo "\n🎉 All core tests passed! The ThinkPHP Runtime is working correctly.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Please check the implementation.\n";
    exit(1);
}
