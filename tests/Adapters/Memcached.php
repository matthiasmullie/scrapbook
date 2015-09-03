<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class Memcached implements AdapterInterface
{
    public function get()
    {
        $client = new \Memcached();
        $client->addServer('localhost', 11211);

        return new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);
    }
}
