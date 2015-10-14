<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use ReflectionObject;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class RepositoryTest extends Psr6TestCase
{
    /**
     * @dataProvider adapterProvider
     */
    public function testDestructUnresolvedItem(KeyValueStore $cache, Pool $pool)
    {
        // add an item
        $item = $pool->getItem('key');

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertNotEmpty($unresolved);
        $this->assertEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDestructResolvedItem(KeyValueStore $cache, Pool $pool)
    {
        // add to cache, so there is something to resolve
        $cache->set('key', 'value');

        // add an item
        $item = $pool->getItem('key');
        $item->get();

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertEmpty($unresolved);
        $this->assertNotEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    /**
     * @param Pool $pool
     *
     * @return array
     */
    protected function getRepositoryData(Pool $pool)
    {
        // grab repository from pool
        $object = new ReflectionObject($pool);
        $property = $object->getProperty('repository');
        $property->setAccessible(true);
        $repository = $property->getValue($pool);

        // grab repository queues
        $object = new ReflectionObject($repository);
        $property = $object->getProperty('unresolved');
        $property->setAccessible(true);
        $unresolved = $property->getValue($repository);
        $property = $object->getProperty('resolved');
        $property->setAccessible(true);
        $resolved = $property->getValue($repository);

        return array($unresolved, $resolved);
    }
}
