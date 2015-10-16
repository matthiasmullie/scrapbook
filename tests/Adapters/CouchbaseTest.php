<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class CouchbaseTest implements AdapterInterface
{
    public function get()
    {
        static $bucket = null;
        if ($bucket === null) {
            $cluster = new \CouchbaseCluster('couchbase://localhost');
            $bucket = $cluster->openBucket('default');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket);
    }
}
