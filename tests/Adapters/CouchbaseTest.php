<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * @group default
 * @group Couchbase
 */
class CouchbaseTest implements AdapterInterface
{
    public function get()
    {
        if (!class_exists('CouchbaseCluster')) {
            throw new Exception('ext-couchbase is not installed.');
        }

        $cluster = new \CouchbaseCluster('couchbase://localhost?detailed_errcodes=1');
        $bucket = $cluster->openBucket('default');

        return new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket);
    }
}
