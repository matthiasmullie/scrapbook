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
            // container (docker) used in Travis
            $client = new \PDO('pgsql:host=127.0.0.1;port=5431;dbname=cache', 'postgres', '');
        } catch (\Exception $e) {
            // default
            $client = new \PDO('pgsql:host=127.0.0.1;dbname=cache', 'postgres', '');
        } catch (\Exception $e) {
            throw new Exception('Failed to connect to PostgreSQL client.');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);
    }
}
