<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class MemoryStoreProvider extends AdapterProvider
{
    public function __construct()
    {
        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\MemoryStore());
    }
}
