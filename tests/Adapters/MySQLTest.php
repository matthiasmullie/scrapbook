<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class MySQLTest implements AdapterInterface
{
    public function get()
    {
        static $client = null;
        if ($client === null) {
            $client = new \PDO('mysql:host=127.0.0.1;dbname=cache', 'travis', '');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\MySQL($client);
    }
}
