<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class CouchbaseProvider extends AdapterProvider
{
    public function __construct()
    {
        if (class_exists('CouchbaseCluster')) {
            // Couchbase SDK <=2.2
            $cluster = new \CouchbaseCluster('couchbase://couchbase:11210?detailed_errcodes=1');
            $authenticator = new \Couchbase\PasswordAuthenticator();
            $authenticator->username('Administrator')->password('password');
            $cluster->authenticate($authenticator);
            $bucket = $cluster->openBucket('default');
            $collection = $bucket;
            $bucketManager = $bucket->manager();
        } elseif (class_exists('Couchbase\ClusterOptions')) {
            // Couchbase SDK >=3.0
            $options = new \Couchbase\ClusterOptions();
            $options->credentials('Administrator', 'password');
            $cluster = new \Couchbase\Cluster('couchbase://couchbase:11210?detailed_errcodes=1', $options);
            $bucket = $cluster->bucket('default');
            $collection = $bucket->defaultCollection();
            $bucketManager = $cluster->buckets();
        } elseif (class_exists('\Couchbase\Cluster')) {
            // Couchbase SDK >=2.3 & <3.0
            $cluster = new \Couchbase\Cluster('couchbase://couchbase:11210?detailed_errcodes=1');
            $authenticator = new \Couchbase\PasswordAuthenticator();
            $authenticator->username('Administrator')->password('password');
            $cluster->authenticate($authenticator);
            $bucket = $cluster->openBucket('default');
            $collection = $bucket;
            $bucketManager = $bucket->manager();
        } else {
            throw new Exception('ext-couchbase is not installed.');
        }

        if (!$this->waitForHealthyServer($bucket)) {
            throw new Exception('Couchbase server is not healthy.');
        }

        parent::__construct(
            new \MatthiasMullie\Scrapbook\Adapters\Couchbase(
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
    protected function waitForHealthyServer(/* \CouchbaseBucket|\Couchbase\Bucket */ $bucket)
    {
        for ($i = 0; $i < 30; ++$i) {
            $healthy = true;

            if ($bucket instanceof \CouchbaseBucket) {
                $info = $bucket->manager()->info();
                foreach ($info['nodes'] as $node) {
                    $healthy = $healthy && 'healthy' === $node['status'];
                }
            } elseif ($bucket instanceof \Couchbase\Bucket) {
                $info = $bucket->ping();
                foreach ($info['services']['kv'] as $kv) {
                    $healthy = $healthy && 'ok' === $kv['state'];
                }
            }

            if ($healthy) {
                return true;
            }

            sleep(1);
        }

        return false;
    }
}
