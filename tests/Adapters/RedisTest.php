<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class RedisTest implements AdapterInterface
{
    public function get()
    {
        $client = new \Redis();
        $client->connect('127.0.0.1');

        return new \MatthiasMullie\Scrapbook\Adapters\Redis($client);
    }
}
