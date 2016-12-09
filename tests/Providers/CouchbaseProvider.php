<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;

class CouchbaseProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists('CouchbaseCluster')) {
            throw new Exception('ext-couchbase is not installed.');
        }

        $cluster = new \CouchbaseCluster('couchbase://localhost?detailed_errcodes=1');
        $bucket = $cluster->openBucket('default');

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket));
    }
}
