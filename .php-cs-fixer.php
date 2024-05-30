<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();

return $config
    ->setRules([
        '@PHP70Migration' => true,
        '@PHP71Migration' => true,
        '@PHP73Migration' => true,
        '@PHP74Migration' => true,
        '@PHP80Migration' => true,
        '@PHP81Migration' => true,
        '@PHP82Migration' => true,
        '@PHP83Migration' => true,
        '@Symfony' => true,
        'concat_space' => ['spacing' => 'one'],
        'single_line_throw' => false,
        'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
        'phpdoc_align' => ['align' => 'left'],
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        '@PER-CS' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
