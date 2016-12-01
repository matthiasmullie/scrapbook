<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class MySQLProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('mysql:host=127.0.0.1;port=3307;dbname=cache', 'root', '');

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\MySQL($client));
    }
}
