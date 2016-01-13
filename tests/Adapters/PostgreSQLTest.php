<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * @group default
 * @group PostgreSQL
 */
class PostgreSQLTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('pgsql:host=127.0.0.1;dbname=cache', 'postgres', '');

        return new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);
    }
}
