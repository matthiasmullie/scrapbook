<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\MySQL;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class MySQLProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists(\PDO::class)) {
            throw new Exception('ext-pdo is not installed.');
        }

        $client = new \PDO('mysql:host=mysql;port=3306;dbname=cache', 'root', '');

        parent::__construct(new MySQL($client));
    }
}
