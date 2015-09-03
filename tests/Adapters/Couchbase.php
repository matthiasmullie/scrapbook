<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class Couchbase implements AdapterInterface
{
    public function get()
    {
        $cluster = new \CouchbaseCluster('couchbase://localhost');
        $bucket = $cluster->openBucket('default');

        $info = $bucket->manager()->info();
        foreach ($info['nodes'] as $node) {
            if ($node['status'] !== 'healthy') {
                $this->markTestSkipped('Server isn\'t ready yet');
            }
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket);
    }
}
