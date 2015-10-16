<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class RedisTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('Redis')) {
            throw new Exception('ext-redis is not installed.');
        }

        static $client = null;
        if ($client === null) {
            try {
                $client = new \Redis();
                $client->connect('127.0.0.1');
            } catch (\Exception $e) {
                throw new Exception('Failed to connect to Redis client.');
            }
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Redis($client);
    }
}
