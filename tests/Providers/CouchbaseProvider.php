<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class CouchbaseProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!class_exists(ClusterOptions::class)) {
            throw new Exception('ext-couchbase is not installed.');
        }

        $options = new ClusterOptions();
        $options->credentials('Administrator', 'password');
        $cluster = new Cluster('couchbase://couchbase:11210?detailed_errcodes=1', $options);
        $bucket = $cluster->bucket('default');
        $collection = $bucket->defaultCollection();
        $bucketManager = $cluster->buckets();

        if (!$this->waitForHealthyServer($bucket)) {
            throw new Exception('Couchbase server is not healthy.');
        }

        parent::__construct(
            new Couchbase(
                $collection,
                $bucketManager,
                $bucket,
                30000
            )
        );
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
