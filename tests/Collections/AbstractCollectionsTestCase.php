<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Collections;

use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;

abstract class AbstractCollectionsTestCase extends AbstractKeyValueStoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // this *could* be done within `getTestKeyValueStore`, except that that
        // is executed when the test suite is being assembled, before tests are
        // actually run
        // inbetween tests, caches are usually flushed to guarantee that they're
        // starting with a known clean slate
        // this, however, may cause collections that would've been created earlier
        // to lose some context (e.g. for PrefixReset-based collections, who use
        // a key in the main cache to keep track of collections) that is required
        // in order to run reliably
        // hence, I will not be initializing the collection during test suite
        // assembly time, but right before the test is executed
        $this->testKeyValueStore = $this->adapterKeyValueStore->getCollection($this->collectionName);
    }

    public function testCollectionGetParentKey(): void
    {
        $this->markTestSkipped(
            'This test is invalid for collections derived from collections. ' .
            "They don't keep nesting, there's only server/collection."
        );
    }

    public function testCollectionGetCollectionKey(): void
    {
        $this->markTestSkipped(
            'This test is invalid for collections derived from collections. ' .
            "They don't keep nesting, there's only server/collection."
        );
    }

    public function testCollectionSetSameKey(): void
    {
        $this->markTestSkipped(
            'This test is invalid for collections derived from collections. ' .
            "They don't keep nesting, there's only server/collection."
        );
    }

    public function testCollectionFlushParent(): void
    {
        $this->markTestSkipped(
            'This test is invalid for collections derived from collections. ' .
            "They don't keep nesting, there's only server/collection."
        );
    }

    public function testCollectionFlushCollection(): void
    {
        $this->markTestSkipped(
            'This test is invalid for collections derived from collections. ' .
            "They don't keep nesting, there's only server/collection."
        );
    }
}
