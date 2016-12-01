<?php

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use MatthiasMullie\Scrapbook\Adapters\Apc as Adapter;
use MatthiasMullie\Scrapbook\Adapters\Collections\Utils\PrefixKeys;

/**
 * APC adapter for a subset of data, accomplished by prefixing keys.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Apc extends PrefixKeys
{
    /**
     * @param Adapter $cache
     * @param string  $name
     */
    public function __construct(Adapter $cache, $name)
    {
        parent::__construct($cache, 'collection:'.$name.':');
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        /*
         * Both of these utility methods are protected in parent, because I just
         * don't want to expose them to users. But I really want to use them
         * here... I'll use reflection to access them - I shouldn't, but I have
         * a decent test suite to protect me, should I forget about this and
         * change the implementation of these methods.
         */
        $reflection = new \ReflectionMethod($this->cache, 'APCuIterator');
        $reflection->setAccessible(true);
        $iterator = $reflection->invoke($this->cache, '/^'.preg_quote($this->prefix, '/').'/', \APC_ITER_KEY);

        $reflection = new \ReflectionMethod($this->cache, 'apcu_delete');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->cache, $iterator);
    }
}
