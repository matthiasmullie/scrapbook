<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class MySQL implements AdapterInterface
{
    public function get()
    {
        $client = new \PDO('mysql:host=127.0.0.1;dbname=cache', 'travis', '');

        return new \MatthiasMullie\Scrapbook\Adapters\MySQL($client);
    }
}
