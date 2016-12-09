<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * @group default
 * @group MySQL
 */
class MySQLTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('PDO')) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('mysql:host=127.0.0.1;port=3307;dbname=cache', 'root', '');

        return new \MatthiasMullie\Scrapbook\Adapters\MySQL($client);
    }
}
