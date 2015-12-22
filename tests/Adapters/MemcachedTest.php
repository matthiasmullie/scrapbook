<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class MemcachedTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('Memcached')) {
            throw new Exception('ext-memcached is not installed.');
        }

        $client = new \Memcached();
        $client->addServer('localhost', 11211);

        return new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);
    }
}
