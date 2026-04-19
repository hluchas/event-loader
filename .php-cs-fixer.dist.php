<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__.'/src', __DIR__.'/tests'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized']],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'phpdoc_to_comment' => false,
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache');
