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

        static $client = null;
        if ($client === null) {
            try {
                $client = new \Memcached();
                $client->addServer('localhost', 11211);
            } catch (\Exception $e) {
                throw new Exception('Failed to connect to Memcached client.');
            }
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);
    }
}
