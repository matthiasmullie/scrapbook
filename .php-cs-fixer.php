<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();
return $config->setRules(array(
        '@Symfony' => true,
        'array_syntax' => array('syntax' => 'long'),
    ))
    ->setFinder($finder)
    ->setUsingCache(false);
