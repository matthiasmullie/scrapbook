<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

class MemoryStoreProvider extends AdapterProvider
{
    public function __construct()
    {
        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\MemoryStore());
    }
}
