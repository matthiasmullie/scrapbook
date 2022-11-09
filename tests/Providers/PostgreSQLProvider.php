<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\PostgreSQL;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class PostgreSQLProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists(\PDO::class)) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('pgsql:host=postgresql;port=5432;dbname=cache', 'user', 'pass');

        parent::__construct(new PostgreSQL($client));
    }
}
