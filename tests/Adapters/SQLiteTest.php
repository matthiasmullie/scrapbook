<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class SQLiteTest implements AdapterInterface
{
    public function get()
    {
        static $client = null;
        if ($client === null) {
            $client = new \PDO('sqlite::memory:');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);
    }
}
