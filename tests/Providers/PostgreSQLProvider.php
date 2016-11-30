<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;

class PostgreSQLProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('pgsql:host=127.0.0.1;dbname=cache', 'postgres', '');

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client));
    }
}
