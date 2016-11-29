<?php

namespace MatthiasMullie\Scrapbook\Collections;

use MatthiasMullie\Scrapbook\Adapters\Memcached as Adapter;
use MatthiasMullie\Scrapbook\Collections\Utils\PrefixKeys;

/**
 * APC adapter for a subset of data, accomplished by prefixing keys.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Memcached extends PrefixKeys
{
    /**
     * @var string
     */
    protected $collection;

    /**
     * @param Adapter $cache
     * @param string $name
     */
    public function __construct(Adapter $cache, $name)
    {
        $this->cache = $cache;
        $this->collection = 'collection:'.$name;
        parent::__construct($cache, $this->getPrefix());
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $index = $this->cache->increment($this->collection);
        $this->setPrefix($this->collection.':'.$index.':');
        return $index !== false;
    }

    /**
     * @return string
     */
    protected function getPrefix()
    {
        /*
         * It's easy enough to just set a prefix to be used, but we can not
         * flush only a prefix!
         * Instead, we'll generate a unique prefix key, based on some name.
         * If we want to flush, we just create a new prefix and use that one.
         */
        $index = $this->cache->get($this->collection);

        if ($index === false) {
            $index = $this->cache->set($this->collection, 1);
        }

        /*
         * I would like to OPT_PREFIX_KEY on the client, but since $client is a
         * reference to the one in parent, that prefix would also be applied
         * there. Instead, I'll manually apply the prefix to all keys prior to
         * them going out to the server - this will have the exact same result!
         */
        return $this->collection.':'.$index.':';
    }
}
