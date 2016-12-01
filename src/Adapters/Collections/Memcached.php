<?php

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use MatthiasMullie\Scrapbook\Adapters\Memcached as Adapter;
use MatthiasMullie\Scrapbook\Adapters\Collections\Utils\PrefixReset;

/**
 * Memcached adapter for a subset of data, accomplished by prefixing keys.
 *
 * I would like to OPT_PREFIX_KEY on the client, but since $client is a
 * reference to the one in parent, that prefix would also be applied
 * there. Instead, I'll manually apply the prefix to all keys prior to
 * them going out to the server - this will have the exact same result!
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Memcached extends PrefixReset
{
    /**
     * @param Adapter $cache
     * @param string  $name
     */
    public function __construct(Adapter $cache, $name)
    {
        parent::__construct($cache, $name);
    }
}
