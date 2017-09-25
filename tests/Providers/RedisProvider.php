<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class RedisProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists('Redis')) {
            throw new Exception('ext-redis is not installed.');
        }

        $client = new \Redis();
        $client->connect(getenv('redis-host'), getenv('redis-port'));

        // Redis databases are numeric
        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\Redis($client), 1);
    }
}
