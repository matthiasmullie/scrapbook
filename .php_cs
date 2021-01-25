<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'long'],
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
