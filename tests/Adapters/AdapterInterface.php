<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Exception\Exception;

interface AdapterInterface
{
    /**
     * @return KeyValueStore
     *
     * @throws Exception
     */
    public function get();
}
