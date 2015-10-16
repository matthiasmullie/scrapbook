<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class PostgreSQLTest implements AdapterInterface
{
    public function get()
    {
        static $client = null;
        if ($client === null) {
            $client = new \PDO('pgsql:user=postgres dbname=cache password=');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);
    }
}
