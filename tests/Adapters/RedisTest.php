<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * @group default
 * @group Redis
 */
class RedisTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('Redis')) {
            throw new Exception('ext-redis is not installed.');
        }

        $client = new \Redis();
        $client->connect('127.0.0.1');

        return new \MatthiasMullie\Scrapbook\Adapters\Redis($client);
    }
}
