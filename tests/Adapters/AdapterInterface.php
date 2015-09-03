<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\KeyValueStore;

interface AdapterInterface {
    /**
     * @return KeyValueStore
     */
    public function get();
}
