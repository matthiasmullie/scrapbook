<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class StampedeProtectorAdapterTest extends AdapterTest
{
    /**
     * Time (in milliseconds) to protect against stampede.
     *
     * @var int
     */
    protected const SLA = 100;

    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = new StampedeProtectorStub($adapter, static::SLA);
    }
}
