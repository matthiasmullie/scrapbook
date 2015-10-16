<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class CouchbaseTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('CouchbaseCluster')) {
            throw new Exception('ext-couchbase is not installed.');
        }

        static $bucket = null;
        if ($bucket === null) {
            $cluster = new \CouchbaseCluster('couchbase://localhost');
            $bucket = $cluster->openBucket('default');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket);
    }
}
