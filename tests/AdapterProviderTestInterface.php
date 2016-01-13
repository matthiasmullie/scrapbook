<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit_Framework_TestSuite;

interface AdapterProviderTestInterface
{
    /**
     * @return PHPUnit_Framework_TestSuite
     */
    public static function suite();

    /**
     * This is where AdapterProvider will inject the adapter to.
     *
     * @param KeyValueStore $adapter
     */
    public function setAdapter(KeyValueStore $adapter);
}
