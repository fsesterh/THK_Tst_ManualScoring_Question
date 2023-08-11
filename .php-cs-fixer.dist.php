<?php

require_once __DIR__ . '/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
    ->exclude(array(
        __DIR__ . "/vendor",
    ))
    ->in([
        __DIR__ . "/src",
        __DIR__ . "/classes",

    ]);

return (new PhpCsFixer\Config())
    ->setUsingCache(false)
    ->setFinder($finder)
    ->setRules([
        '@PSR12' => true,
        'cast_spaces' => true,
        'strict_param' => false,
        'concat_space' => ['spacing' => 'one'],
        'function_typehint_space' => true
    ]);
