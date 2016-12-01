<?php

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
    /**
     * @param \Redis $client
     * @param int    $database
     */
    public function __construct($client, $database)
    {
        parent::__construct($client);
        $this->client->select($database);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->client->flushDB();
    }
}
