<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\Psr6\Pool;
use MatthiasMullie\Scrapbook\Tests\AbstractAdapterTestCase;

abstract class AbstractPsr6TestCase extends AbstractAdapterTestCase
{
    protected Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = new Pool($this->adapterKeyValueStore);
        $this->pool->clear();
    }
}
