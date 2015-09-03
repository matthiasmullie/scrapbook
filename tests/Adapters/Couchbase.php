<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class Couchbase implements AdapterInterface
{
    public function get()
    {
        $cluster = new \CouchbaseCluster('couchbase://localhost');
        $bucket = $cluster->openBucket('default');

        return new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket);
    }
}
