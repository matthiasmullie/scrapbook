<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class MemoryStoreProvider extends AdapterProvider
{
    public function __construct()
    {
        parent::__construct(new MemoryStore());
    }
}
