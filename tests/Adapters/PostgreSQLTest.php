<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class PostgreSQLTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        try {
            $client = new \PDO('pgsql:user=postgres dbname=cache password=');
        } catch (\Exception $e) {
            throw new Exception('Failed to connect to PostgreSQL client.');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);
    }
}
