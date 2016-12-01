<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Exception\Exception;

class AdapterProvider
{
    /**
     * @var KeyValueStore
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $collectionName;

    /**
     * @param KeyValueStore $adapter
     * @param string        $collectionName
     *
     * @throws Exception
     */
    public function __construct(KeyValueStore $adapter, $collectionName = 'collection')
    {
        $this->adapter = $adapter;
        $this->collectionName = $collectionName;
    }

    /**
     * @return KeyValueStore
     *
     * @throws Exception
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }
}
