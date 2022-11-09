<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use MatthiasMullie\Scrapbook\Adapters\Redis as Adapter;

/**
 * Redis adapter for a subset of data, in a different database.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Redis extends Adapter
{
    public function __construct(\Redis $client, int $database)
    {
        parent::__construct($client);
        $this->client->select($database);
    }

    public function flush(): bool
    {
        return $this->client->flushDB();
    }
}
