<?php

declare(strict_types=1);

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
    public function __construct(Adapter $cache, string $name)
    {
        parent::__construct($cache, 'collection:' . $name . ':');
    }

    public function flush(): bool
    {
        $iterator = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/', \APC_ITER_KEY);
        apcu_delete($iterator);

        return true;
    }
}
