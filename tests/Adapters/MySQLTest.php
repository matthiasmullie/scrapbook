<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class MySQLTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        static $client = null;
        if ($client === null) {
            try {
                $client = new \PDO('mysql:host=127.0.0.1;dbname=cache', 'travis', '');
            } catch (\Exception $e) {
                throw new Exception('Failed to connect to MySQL client.');
            }
        }

        return new \MatthiasMullie\Scrapbook\Adapters\MySQL($client);
    }
}
