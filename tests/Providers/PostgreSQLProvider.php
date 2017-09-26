<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class PostgreSQLProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('pgsql:host=postgresql;port=5432;dbname=cache', 'postgres', '');

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client));
    }
}
