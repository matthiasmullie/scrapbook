<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Couchbase;

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a bit of a workaround to ensure we only use 1 client
 * across all tests.
 * Traits are essentially copied into the classes they use, so
 * they can't otherwise share a static variable.
 */
function getCouchbaseCluster(): Cluster
{
    static $cluster;
    if (!$cluster) {
        $options = new ClusterOptions();
        $options->credentials('Administrator', 'password');
        $cluster = new Cluster('couchbase://couchbase:11210?detailed_errcodes=1', $options);
    }

    return $cluster;
}

trait AdapterProviderTrait
{
    public function getAdapterKeyValueStore(): KeyValueStore
    {
        if (!class_exists(ClusterOptions::class)) {
            throw new Exception('ext-couchbase is not installed.');
        }

        $cluster = getCouchbaseCluster();
        $bucket = $cluster->bucket('default');
        $collection = $bucket->defaultCollection();
        $bucketManager = $cluster->buckets();

        if (!$this->waitForHealthyServer($bucket)) {
            throw new Exception('Couchbase server is not healthy.');
        }

        return new Couchbase(
            $collection,
            $bucketManager,
            $bucket,
            30000
        );
    }

    public function getCollectionName(): string
    {
        return 'collection';
    }

    /**
     * Wait 30 seconds should nodes not be healthy; they may be warming up.
     */
    protected function waitForHealthyServer(Bucket $bucket): bool
    {
        for ($i = 0; $i < 30; ++$i) {
            $healthy = true;

            $info = $bucket->ping();
            foreach ($info['services']['kv'] as $kv) {
                $healthy = $healthy && $kv['state'] === 'ok';
            }

            if ($healthy) {
                return true;
            }

            sleep(1);
        }

        return false;
    }
}
