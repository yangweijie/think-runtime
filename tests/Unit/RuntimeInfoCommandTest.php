<?php

declare(strict_types=1);

use yangweijie\thinkRuntime\command\RuntimeInfoCommand;

test('can create runtime info command', function () {
    $command = new RuntimeInfoCommand();
    expect($command)->toBeInstanceOf(RuntimeInfoCommand::class);
});

test('has correct command name and description', function () {
    $command = new RuntimeInfoCommand();

    // 使用反射访问受保护的方法
    $reflection = new \ReflectionClass($command);
    $configureMethod = $reflection->getMethod('configure');
    $configureMethod->setAccessible(true);
    $configureMethod->invoke($command);

    expect($command->getName())->toBe('runtime:info');
    expect($command->getDescription())->toBe('Show runtime information');
});

test('command has required methods', function () {
    $command = new RuntimeInfoCommand();

    expect(method_exists($command, 'configure'))->toBe(true);
    expect(method_exists($command, 'execute'))->toBe(true);
    expect(method_exists($command, 'displaySystemInfo'))->toBe(true);
    expect(method_exists($command, 'displayRuntimeInfo'))->toBe(true);
    expect(method_exists($command, 'displayAvailableRuntimes'))->toBe(true);
    expect(method_exists($command, 'displayExtensionInfo'))->toBe(true);
});

/*
test('can execute command successfully', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();

    // 注册运行时管理器到应用
    $this->app->instance('runtime.manager', $this->runtimeManager);

    $command = new RuntimeInfoCommand();
    $command->setApp($this->app);

    // 创建模拟输入输出
    $input = new class extends Input {
        public function __construct() {}
        public function getArgument(string $name) { return null; }
        public function getOption(string $name) { return null; }
        public function hasOption(string $name): bool { return false; }
        public function bind($definition): void {}
        public function validate(): void {}
        public function getArguments(): array { return []; }
        public function getOptions(): array { return []; }
    };

    $output = new class extends Output {
        private array $messages = [];

        public function __construct() {}
        public function write($messages, bool $newline = false, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function writeln($messages, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function getMessages(): array { return $this->messages; }
        public function setVerbosity(int $level): void {}
        public function getVerbosity(): int { return self::VERBOSITY_NORMAL; }
        public function isQuiet(): bool { return false; }
        public function isVerbose(): bool { return false; }
        public function isVeryVerbose(): bool { return false; }
        public function isDebug(): bool { return false; }
    };

    // 使用反射执行命令
    $reflection = new \ReflectionClass($command);
    $executeMethod = $reflection->getMethod('execute');
    $executeMethod->setAccessible(true);

    $result = $executeMethod->invoke($command, $input, $output);

    expect($result)->toBe(0); // 成功返回码
    expect($output->getMessages())->not->toBeEmpty();
});

test('displays system information', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();

    $this->app->instance('runtime.manager', $this->runtimeManager);

    $command = new RuntimeInfoCommand();
    $command->setApp($this->app);

    $output = new class extends Output {
        private array $messages = [];

        public function __construct() {}
        public function write($messages, bool $newline = false, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function writeln($messages, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function getMessages(): array { return $this->messages; }
        public function setVerbosity(int $level): void {}
        public function getVerbosity(): int { return self::VERBOSITY_NORMAL; }
        public function isQuiet(): bool { return false; }
        public function isVerbose(): bool { return false; }
        public function isVeryVerbose(): bool { return false; }
        public function isDebug(): bool { return false; }
    };

    // 使用反射调用displaySystemInfo方法
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('displaySystemInfo');
    $method->setAccessible(true);
    $method->invoke($command, $output);

    $messages = $output->getMessages();
    $allMessages = implode(' ', $messages);

    expect($allMessages)->toContain('System Information');
    expect($allMessages)->toContain('PHP Version');
    expect($allMessages)->toContain('PHP SAPI');
    expect($allMessages)->toContain('Operating System');
});

test('displays runtime information', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();

    $this->app->instance('runtime.manager', $this->runtimeManager);

    $command = new RuntimeInfoCommand();
    $command->setApp($this->app);

    $output = new class extends Output {
        private array $messages = [];

        public function __construct() {}
        public function write($messages, bool $newline = false, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function writeln($messages, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function getMessages(): array { return $this->messages; }
        public function setVerbosity(int $level): void {}
        public function getVerbosity(): int { return self::VERBOSITY_NORMAL; }
        public function isQuiet(): bool { return false; }
        public function isVerbose(): bool { return false; }
        public function isVeryVerbose(): bool { return false; }
        public function isDebug(): bool { return false; }
    };

    // 检查方法是否存在，如果不存在则跳过
    $reflection = new \ReflectionClass($command);
    if ($reflection->hasMethod('displayRuntimeInfo')) {
        $method = $reflection->getMethod('displayRuntimeInfo');
        $method->setAccessible(true);
        $method->invoke($command, $output, $this->runtimeManager);

        $messages = $output->getMessages();
        $allMessages = implode(' ', $messages);

        expect($allMessages)->toContain('Current Runtime');
    } else {
        // 如果方法不存在，测试通过
        expect(true)->toBe(true);
    }
});

test('displays available runtimes', function () {
    $this->createApplication();
    $this->createRuntimeConfig();
    $this->createRuntimeManager();

    $this->app->instance('runtime.manager', $this->runtimeManager);

    $command = new RuntimeInfoCommand();
    $command->setApp($this->app);

    $output = new class extends Output {
        private array $messages = [];

        public function __construct() {}
        public function write($messages, bool $newline = false, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function writeln($messages, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function getMessages(): array { return $this->messages; }
        public function setVerbosity(int $level): void {}
        public function getVerbosity(): int { return self::VERBOSITY_NORMAL; }
        public function isQuiet(): bool { return false; }
        public function isVerbose(): bool { return false; }
        public function isVeryVerbose(): bool { return false; }
        public function isDebug(): bool { return false; }
    };

    // 使用反射调用displayAvailableRuntimes方法
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('displayAvailableRuntimes');
    $method->setAccessible(true);
    $method->invoke($command, $output, $this->runtimeManager);

    $messages = $output->getMessages();
    $allMessages = implode(' ', $messages);

    expect($allMessages)->toContain('Available Runtimes');
});

test('displays extension information', function () {
    $command = new RuntimeInfoCommand();

    $output = new class extends Output {
        private array $messages = [];

        public function __construct() {}
        public function write($messages, bool $newline = false, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function writeln($messages, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function getMessages(): array { return $this->messages; }
        public function setVerbosity(int $level): void {}
        public function getVerbosity(): int { return self::VERBOSITY_NORMAL; }
        public function isQuiet(): bool { return false; }
        public function isVerbose(): bool { return false; }
        public function isVeryVerbose(): bool { return false; }
        public function isDebug(): bool { return false; }
    };

    // 使用反射调用displayExtensionInfo方法
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('displayExtensionInfo');
    $method->setAccessible(true);
    $method->invoke($command, $output);

    $messages = $output->getMessages();
    $allMessages = implode(' ', $messages);

    expect($allMessages)->toContain('PHP Extensions');
    expect($allMessages)->toContain('swoole');
    expect($allMessages)->toContain('curl');
    expect($allMessages)->toContain('json');
});

test('handles runtime manager errors', function () {
    $this->createApplication();

    // 不注册运行时管理器，模拟错误情况
    $command = new RuntimeInfoCommand();
    $command->setApp($this->app);

    $input = new class extends Input {
        public function __construct() {}
        public function getArgument(string $name) { return null; }
        public function getOption(string $name) { return null; }
        public function hasOption(string $name): bool { return false; }
        public function bind($definition): void {}
        public function validate(): void {}
        public function getArguments(): array { return []; }
        public function getOptions(): array { return []; }
    };

    $output = new class extends Output {
        private array $messages = [];

        public function __construct() {}
        public function write($messages, bool $newline = false, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function writeln($messages, int $options = 0): void {
            $this->messages[] = $messages;
        }
        public function getMessages(): array { return $this->messages; }
        public function setVerbosity(int $level): void {}
        public function getVerbosity(): int { return self::VERBOSITY_NORMAL; }
        public function isQuiet(): bool { return false; }
        public function isVerbose(): bool { return false; }
        public function isVeryVerbose(): bool { return false; }
        public function isDebug(): bool { return false; }
    };

    // 使用反射执行命令
    $reflection = new \ReflectionClass($command);
    $executeMethod = $reflection->getMethod('execute');
    $executeMethod->setAccessible(true);

    $result = $executeMethod->invoke($command, $input, $output);

    expect($result)->toBe(1); // 错误返回码

    $messages = $output->getMessages();
    $allMessages = implode(' ', $messages);
    expect($allMessages)->toContain('Failed to get runtime info');
});
*/
