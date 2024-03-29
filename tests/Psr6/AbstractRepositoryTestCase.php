<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\Psr6\Pool;

abstract class AbstractRepositoryTestCase extends AbstractPsr6TestCase
{
    public function testDestructUnresolvedItem(): void
    {
        // add an item
        $item = $this->pool->getItem('key');

        [$unresolved, $resolved] = $this->getRepositoryData($this->pool);
        $this->assertNotEmpty($unresolved);
        $this->assertEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        [$unresolved, $resolved] = $this->getRepositoryData($this->pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    public function testDestructResolvedItem(): void
    {
        // add to cache, so there is something to resolve
        $this->adapterKeyValueStore->set('key', 'value');

        // add an item
        $item = $this->pool->getItem('key');
        $item->get();

        [$unresolved, $resolved] = $this->getRepositoryData($this->pool);
        $this->assertEmpty($unresolved);
        $this->assertNotEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        [$unresolved, $resolved] = $this->getRepositoryData($this->pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    protected function getRepositoryData(Pool $pool): array
    {
        // grab repository from pool
        $object = new \ReflectionObject($pool);
        $property = $object->getProperty('repository');
        $property->setAccessible(true);
        $repository = $property->getValue($pool);

        // grab repository queues
        $object = new \ReflectionObject($repository);
        $property = $object->getProperty('unresolved');
        $property->setAccessible(true);
        $unresolved = $property->getValue($repository);
        $property = $object->getProperty('resolved');
        $property->setAccessible(true);
        $resolved = $property->getValue($repository);

        return [$unresolved, $resolved];
    }
}
