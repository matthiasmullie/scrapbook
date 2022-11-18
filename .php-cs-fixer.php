<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'single_line_throw' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        '@PSR12' => true,
        'class_definition' => false, // @see https://github.com/FriendsOfPHP/PHP-CS-Fixer/issues/5463
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
