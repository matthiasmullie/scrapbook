<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use MatthiasMullie\Scrapbook\Adapters\Collections\Utils\PrefixKeys;
use MatthiasMullie\Scrapbook\Adapters\SQL as Adapter;

/**
 * SQL adapter for a subset of data, accomplished by prefixing keys.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class SQL extends PrefixKeys
{
    protected \PDO $client;

    protected string $table;

    public function __construct(Adapter $cache, \PDO $client, string $table, string $name)
    {
        parent::__construct($cache, 'collection:' . $name . ':');
        $this->client = $client;
        $this->table = $table;
    }

    public function flush(): bool
    {
        // deleting key with a prefixed LIKE should be fast, they're indexed
        $statement = $this->client->prepare(
            "DELETE FROM $this->table
            WHERE k LIKE :key"
        );

        return $statement->execute([
            ':key' => $this->prefix . '%',
        ]);
    }
}
