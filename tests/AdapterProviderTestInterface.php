<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit\Framework\TestSuite;

interface AdapterProviderTestInterface
{
    /**
     * @return TestSuite
     */
    public static function suite();

    /**
     * This is where AdapterProvider will inject the adapter to.
     *
     * @param KeyValueStore $adapter
     */
    public function setAdapter(KeyValueStore $adapter);

    /**
     * This is where AdapterProvider will inject the adapter to.
     *
     * @param string $name
     */
    public function setCollectionName($name);
}
