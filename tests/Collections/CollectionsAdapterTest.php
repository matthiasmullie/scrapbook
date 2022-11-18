<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Collections;

use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class CollectionsAdapterTest extends AdapterTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // I'll do this here instead of in setAdapter, because that runs before
        // the test suite, but I want a new collection for every single test
        $this->cache = $this->cache->getCollection($this->collectionName);
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
