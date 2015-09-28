<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class SQLiteTest implements AdapterInterface
{
    public function get()
    {
        $client = new \PDO('sqlite::memory:');

        return new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);
    }
}
