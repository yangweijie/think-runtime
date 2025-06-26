<?php

declare(strict_types=1);

use Think\Runtime\Composer\RuntimePlugin;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Config;

beforeEach(function () {
    $this->plugin = new RuntimePlugin();
    $this->composer = $this->createMock(Composer::class);
    $this->io = $this->createMock(IOInterface::class);
});

it('implements required interfaces', function () {
    expect($this->plugin)->toBeInstanceOf(\Composer\Plugin\PluginInterface::class)
        ->and($this->plugin)->toBeInstanceOf(\Composer\EventDispatcher\EventSubscriberInterface::class);
});

it('subscribes to correct events', function () {
    $events = RuntimePlugin::getSubscribedEvents();
    
    expect($events)->toBeArray()
        ->and($events)->toHaveKey('post-autoload-dump');
});

it('can be activated', function () {
    $this->plugin->activate($this->composer, $this->io);

    // If we get here without exception, the test passes
    expect(true)->toBeTrue();
});

it('can be deactivated', function () {
    $this->plugin->activate($this->composer, $this->io);
    $this->plugin->deactivate($this->composer, $this->io);

    // If we get here without exception, the test passes
    expect(true)->toBeTrue();
});

it('can generate autoload runtime content', function () {
    // Create a temporary directory for testing
    $tempDir = sys_get_temp_dir() . '/think-runtime-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    
    // Mock composer configuration
    $config = $this->createMock(Config::class);
    $config->method('get')->with('vendor-dir')->willReturn($tempDir);
    
    $package = $this->createMock(RootPackageInterface::class);
    $package->method('getExtra')->willReturn([
        'runtime' => [
            'class' => 'Think\\Runtime\\Runtime\\ThinkPHPRuntime',
        ],
    ]);
    
    $this->composer->method('getConfig')->willReturn($config);
    $this->composer->method('getPackage')->willReturn($package);
    
    $this->plugin->activate($this->composer, $this->io);
    
    // Create a mock event
    $event = $this->createMock(\Composer\Script\Event::class);
    
    // Generate the autoload runtime file
    $this->plugin->generateAutoloadRuntime($event);
    
    // Check if the file was created
    $autoloadFile = $tempDir . '/autoload_runtime.php';
    expect(file_exists($autoloadFile))->toBeTrue();
    
    // Check file content
    $content = file_get_contents($autoloadFile);
    expect($content)->toContain('Think\\Runtime\\Runtime\\ThinkPHPRuntime')
        ->and($content)->toContain('BasicErrorHandler')
        ->and($content)->toContain('require_once __DIR__ . \'/autoload.php\'');
    
    // Clean up
    unlink($autoloadFile);
    rmdir($tempDir);
});

it('can handle custom template', function () {
    // Create a temporary directory and template file
    $tempDir = sys_get_temp_dir() . '/think-runtime-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    
    $templateFile = $tempDir . '/custom.template';
    file_put_contents($templateFile, '<?php echo "Custom template with {{RUNTIME_CLASS}}";');
    
    // Mock composer configuration
    $config = $this->createMock(Config::class);
    $config->method('get')->with('vendor-dir')->willReturn($tempDir);
    
    $package = $this->createMock(RootPackageInterface::class);
    $package->method('getExtra')->willReturn([
        'runtime' => [
            'class' => 'Custom\\Runtime\\Class',
            'autoload_template' => $templateFile,
        ],
    ]);
    
    $this->composer->method('getConfig')->willReturn($config);
    $this->composer->method('getPackage')->willReturn($package);
    
    $this->plugin->activate($this->composer, $this->io);
    
    // Create a mock event
    $event = $this->createMock(\Composer\Script\Event::class);
    
    // Generate the autoload runtime file
    $this->plugin->generateAutoloadRuntime($event);
    
    // Check if the file was created with custom template
    $autoloadFile = $tempDir . '/autoload_runtime.php';
    expect(file_exists($autoloadFile))->toBeTrue();
    
    $content = file_get_contents($autoloadFile);
    expect($content)->toContain('Custom\\Runtime\\Class')
        ->and($content)->toContain('Custom template with');
    
    // Clean up
    unlink($autoloadFile);
    unlink($templateFile);
    rmdir($tempDir);
});

it('can uninstall and remove autoload file', function () {
    // Create a temporary directory and autoload file
    $tempDir = sys_get_temp_dir() . '/think-runtime-test-' . uniqid();
    mkdir($tempDir, 0755, true);
    
    $autoloadFile = $tempDir . '/autoload_runtime.php';
    file_put_contents($autoloadFile, '<?php // test file');
    
    // Mock composer configuration
    $config = $this->createMock(Config::class);
    $config->method('get')->with('vendor-dir')->willReturn($tempDir);
    
    $this->composer->method('getConfig')->willReturn($config);
    
    $this->plugin->activate($this->composer, $this->io);
    
    // Verify file exists before uninstall
    expect(file_exists($autoloadFile))->toBeTrue();
    
    // Uninstall the plugin
    $this->plugin->uninstall($this->composer, $this->io);
    
    // Verify file was removed
    expect(file_exists($autoloadFile))->toBeFalse();
    
    // Clean up
    rmdir($tempDir);
});
