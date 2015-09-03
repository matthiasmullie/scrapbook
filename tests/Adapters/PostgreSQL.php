<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class PostgreSQL implements AdapterInterface
{
    public function get()
    {
        $client = new \PDO('pgsql:user=postgres dbname=cache password=');

        return new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);
    }
}
