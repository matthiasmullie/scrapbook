<?php

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore as Adapter;
use MatthiasMullie\Scrapbook\Adapters\Collections\Utils\PrefixReset;

/**
 * MemoryStore adapter for a subset of data.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class MemoryStore extends PrefixReset
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
