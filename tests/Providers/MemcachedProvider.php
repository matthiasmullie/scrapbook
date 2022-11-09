<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\Memcached;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class MemcachedProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists(\Memcached::class)) {
            throw new Exception('ext-memcached is not installed.');
        }

        $client = new \Memcached();
        $client->addServer('memcached', 11211);

        parent::__construct(new Memcached($client));
    }
}
