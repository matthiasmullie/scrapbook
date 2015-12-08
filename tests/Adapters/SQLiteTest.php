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

        try {
            $client = new \PDO('sqlite::memory:');
        } catch (\Exception $e) {
            throw new Exception('Failed to connect to SQLite client.');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);
    }
}
