<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\Psr6\Pool;

class RepositoryTest extends Psr6TestCase
{
    public function testDestructUnresolvedItem()
    {
        // add an item
        $item = $this->pool->getItem('key');

        list($unresolved, $resolved) = $this->getRepositoryData($this->pool);
        $this->assertNotEmpty($unresolved);
        $this->assertEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        list($unresolved, $resolved) = $this->getRepositoryData($this->pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    public function testDestructResolvedItem()
    {
        // add to cache, so there is something to resolve
        $this->cache->set('key', 'value');

        // add an item
        $item = $this->pool->getItem('key');
        $item->get();

        list($unresolved, $resolved) = $this->getRepositoryData($this->pool);
        $this->assertEmpty($unresolved);
        $this->assertNotEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        list($unresolved, $resolved) = $this->getRepositoryData($this->pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    /**
     * @return array
     */
    protected function getRepositoryData(Pool $pool)
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

        return array($unresolved, $resolved);
    }
}
