<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class SQLiteTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('sqlite::memory:');

        return new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);
    }
}
