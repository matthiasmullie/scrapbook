<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class CouchbaseProvider extends AdapterProvider
{
    public function __construct()
    {
        if (class_exists('CouchbaseCluster')) {
            $cluster = new \CouchbaseCluster('couchbase://couchbase:11210?detailed_errcodes=1');
        } elseif (class_exists('\Couchbase\Cluster')) {
            $cluster = new \Couchbase\Cluster('couchbase://couchbase:11210?detailed_errcodes=1');
        } else {
            throw new Exception('ext-couchbase is not installed.');
        }

        $authenticator = new \Couchbase\PasswordAuthenticator();
        $authenticator->username('Administrator')->password('password');

        $cluster->authenticate($authenticator);
        $bucket = $cluster->openBucket('default');

        $healthy = $this->waitForHealthyServer($bucket);

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\Couchbase($bucket, !$healthy));
    }

    /**
     * Wait 10 seconds should nodes not be healthy; they may be warming up.
     *
     * @return bool
     */
    protected function waitForHealthyServer(\CouchbaseBucket $bucket)
    {
        for ($i = 0; $i < 10; ++$i) {
            $healthy = true;
            $info = $bucket->manager()->info();
            foreach ($info['nodes'] as $node) {
                $healthy = $healthy && 'healthy' === $node['status'];
            }

            if ($healthy) {
                return true;
            }

            sleep(1);
        }

        return false;
    }
}
