<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\Redis;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class RedisProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists(\Redis::class)) {
            throw new Exception('ext-redis is not installed.');
        }

        $client = new \Redis();
        $client->connect('redis', 6379);

        // Redis databases are numeric
        parent::__construct(new Redis($client), '1');
    }
}
