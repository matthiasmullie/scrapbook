<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\SQLite;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class SQLiteProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists(\PDO::class)) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('sqlite::memory:');

        parent::__construct(new SQLite($client));
    }
}
