<?php

/**
 * Console Application Example
 * 
 * This example shows how to create a console application with ThinkPHP Runtime.
 * 
 * Usage:
 * php console.php hello
 */

use think\Console;
use think\console\Command;
use think\console\Input;
use think\console\Output;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context): Console {
    // Create console application
    $console = new Console();
    
    // Add a simple command
    $console->add(new class extends Command {
        protected function configure()
        {
            $this->setName('hello')
                 ->setDescription('Say hello')
                 ->addArgument('name', null, 'Your name', 'World');
        }
        
        protected function execute(Input $input, Output $output)
        {
            $name = $input->getArgument('name');
            $output->writeln("Hello, {$name}!");
            return 0;
        }
    });
    
    return $console;
};
